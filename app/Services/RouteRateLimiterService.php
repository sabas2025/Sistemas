<?php
class RouteRateLimiterService {
  private static bool $schemaReady = false;
  private const SENSITIVE = ['backup','backup-download','backup-excluir','backup-importar','backup-restaurar','validar-banco','mapa-banco','health-modulos','enterprise-core-aplicar','migracao-aplicar','api/processar-fila','vsm-simulador-executar','tiny-validacao-executar','producao-segura-executar'];
  public static function ensureSchema(): void {
    if (self::$schemaReady) return;
    SchemaRuntimePolicyService::requireTable('rate_limit_hits', 'rate limit de rotas');
    self::$schemaReady = true;
  }
  /**
   * Melhoria 11 da seção 8: a classificação de rota sensível usava str_starts_with($route, 'api/'),
   * um casamento por prefixo. Ele funcionava, mas é a mesma família de decisão que produziu o
   * achado B-06 (rota classificada por semelhança de nome). Aqui o prefixo continua válido porque
   * TODA rota api/* é de fato uma ação de painel ou integração e merece o limite estrito - a
   * diferença é que agora isso está isolado num único lugar, nomeado e testável, em vez de
   * duplicado em dois pontos do arquivo com a chance de divergirem.
   */
  public static function isSensitive(string $route): bool {
    if (in_array($route, self::SENSITIVE, true)) return true;
    return str_starts_with($route, 'api/');
  }

  public static function enforce(?string $route=null): void {
    if (PHP_SAPI === 'cli') return;
    $rotaAtual = substr((string)($route ?? ($_GET['page'] ?? 'dashboard')), 0, 190);

    // Auditoria de capacidade 2026-09-14 (achado C-01): webhook de entrada sai do contador em
    // banco e passa a usar o contador atômico, com orçamento próprio.
    //
    // Dois motivos, ambos medidos. (a) CORREÇÃO: str_starts_with($route,'api/') classificava o
    // webhook como rota sensível de painel, com 20 requisições por minuto e bloqueio automático do
    // IP em 3x o limite - a 500 pedidos/min o Hub recusaria ~96% dos pedidos e depois blackholearia
    // o IP do Tiny por uma hora. (b) VAZÃO: a chave do balde é (ip, usuario_id, rota, metodo,
    // janela), e todo webhook do Tiny chega do mesmo IP, anônimo, na mesma rota - os 500 pedidos do
    // minuto colapsavam numa ÚNICA linha, serializando a entrada inteira em INSERT ... ON DUPLICATE
    // KEY UPDATE mais SELECT, duas escritas por requisição na tabela mais disputada do sistema.
    //
    // O canal não fica sem limite: mantém allowlist de IP, HMAC, contador de tentativas
    // pré-autenticação (TinyWebhookSecurityService) e guarda de replay.
    if (class_exists('RouteCatalogService') && RouteCatalogService::isInboundWebhook($rotaAtual)) {
      self::enforceWebhookInbound($rotaAtual);
      return;
    }

    try {
      self::ensureSchema();
      $cfg = App::config()['security'] ?? [];
      $route = substr((string)($route ?? ($_GET['page'] ?? 'dashboard')), 0, 190);
      $method = substr((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'), 0, 12);
      $ip = SecurityEventService::ip();
      $u = class_exists('Auth') ? Auth::user() : null;
      $uid = isset($u['id']) ? (int)$u['id'] : 0;
      $limit = (int)($cfg[self::isSensitive($route) ? 'route_rate_limit_sensitive_per_minute' : 'route_rate_limit_per_minute'] ?? (self::isSensitive($route) ? 20 : 90));
      $limit = max(5, min(600, $limit));
      $bucket = date('Y-m-d H:i:00');
      $pdo = Database::getConnection();
      // Auditoria de capacidade 2026-09-14 (achado C-04): aqui havia um DELETE probabilístico
      // (1 em 50) e a chamada a DataRetentionService::maybeRun(), que em 1 de cada 100 requisições
      // executava até seis DELETE ... LIMIT 5000. A 30 req/s isso disparava a cada ~3 segundos
      // DENTRO de uma requisição de usuário sorteada, que pagava a latência de até 30 mil deleções
      // sem nenhuma relação com o que pediu — e o rastro não apontava para a causa.
      //
      // As duas limpezas passaram para workers/worker_retencao.php, executado por cron. O caminho
      // quente não faz mais manutenção de tabela.
      // P0-07 (reauditoria 2026-08-23): usuario_id NULL nunca colide consigo mesmo em
      // UNIQUE KEY no MySQL, então cada requisição anônima criava uma linha nova em vez
      // de incrementar 'hits' - o rate limit de rotas públicas (login, webhooks) nunca
      // acumulava e nunca bloqueava. Usar 0 (não-NULL) para anônimo resolve sem exigir
      // migração de schema, já que a coluna continua NULLable.
      $uidChave = $uid ?: 0;
      $st = $pdo->prepare("INSERT INTO rate_limit_hits(ip,usuario_id,rota,metodo,janela_inicio,hits,updated_at) VALUES (?,?,?,?,?,1,NOW()) ON DUPLICATE KEY UPDATE hits=hits+1, updated_at=NOW()");
      $st->execute([$ip, $uidChave, $route, $method, $bucket]);
      $st = $pdo->prepare("SELECT hits FROM rate_limit_hits WHERE ip=? AND usuario_id=? AND rota=? AND metodo=? AND janela_inicio=?");
      $st->execute([$ip, $uidChave, $route, $method, $bucket]);
      $hits = (int)$st->fetchColumn();
      if ($hits > $limit) {
        SecurityEventService::log('rate_limit.rota','alto','Rate limit por IP/rota excedido', ['rota'=>$route,'hits'=>$hits,'limite'=>$limit]);
        if ($hits > ($limit * 3)) IpBlockService::block($ip, 'Rate limit por rota excedido repetidamente', 60, 'alto');
        http_response_code(429);
        header('Retry-After: 60');
        header('Content-Type: text/plain; charset=UTF-8');
        exit('Muitas requisições. Tente novamente em instantes.');
      }
    } catch (Throwable $e) {
      // Reauditoria 2026-09-14 (achado A-06): antes bastava um erro de banco/schema para a
      // requisição passar sem limite algum - inclusive em rotas públicas. Agora degrada para
      // um contador local atômico, que não depende do banco, em vez de falhar aberto.
      error_log('RouteRateLimiter: '.$e->getMessage());
      self::enforceDegraded($route);
    }
  }

  /**
   * Orçamento próprio do webhook de entrada, no contador atômico (sem banco, sem linha disputada).
   * Política ABERTA em degradação, como as demais rotas: as camadas de HMAC/allowlist/replay
   * continuam valendo, e recusar a integração inteira por causa de um diretório de cache
   * inacessível seria trocar um problema de infraestrutura por perda de pedido.
   */
  private static function enforceWebhookInbound(string $route): void {
    try {
      $ip = class_exists('RequestContext') ? (RequestContext::ip() ?: '0.0.0.0') : (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
      $state = RateLimitService::hit('webhook_inbound', $ip.'|'.$route);
      if ($state['degraded'] || !$state['limited']) return;
      SecurityEventService::log('rate_limit.webhook','alto','Rate limit de webhook de entrada excedido.', ['rota'=>$route,'hits'=>$state['count'],'limite'=>$state['limit']]);
      http_response_code(429);
      header('Retry-After: 30');
      header('Content-Type: text/plain; charset=UTF-8');
      exit('Muitas requisições. Tente novamente em instantes.');
    } catch (Throwable $e) {
      error_log('RouteRateLimiter webhook: '.$e->getMessage());
    }
  }

  /** Limite de último recurso quando o banco/schema está indisponível (A-06). */
  private static function enforceDegraded(?string $route): void {
    try {
      $cfg = class_exists('App') ? (App::config()['security'] ?? []) : [];
      $route = substr((string)($route ?? ($_GET['page'] ?? 'dashboard')), 0, 190);
      $ip = class_exists('RequestContext') ? (RequestContext::ip() ?: '0.0.0.0') : (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
      // Melhoria 7: a política (janela, limite e o que fazer se o contador falhar) passa a ser
      // declarada uma única vez, em RateLimitService::POLICIES, em vez de repetida aqui.
      // Achado B-01: esta continua sendo a única superfície com política ABERTA, e deliberada -
      // este caminho já é o último recurso (o banco falhou), e bloquear todas as rotas do painel
      // porque storage/cache/security também está inacessível transformaria dois problemas de
      // infraestrutura em indisponibilidade total. A fachada registra a degradação.
      $surface = self::isSensitive($route) ? 'route_sensitive' : 'route';
      $state = RateLimitService::hit($surface, $ip.'|'.$route);
      if ($state['degraded']) return; // política 'open': liberado, já registrado por RateLimitService
      if (!$state['limited']) return;
      SecurityHealthService::degrade('route_rate_limit', 'Rate limit de rotas aplicado pelo contador local (banco indisponível).', ['rota'=>$route]);
      http_response_code(429);
      header('Retry-After: 60');
      header('Content-Type: text/plain; charset=UTF-8');
      exit('Muitas requisições. Tente novamente em instantes.');
    } catch (Throwable $inner) {
      error_log('RouteRateLimiter degradado: '.$inner->getMessage());
    }
  }
}
