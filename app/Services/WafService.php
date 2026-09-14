<?php
class WafService {
  private const PATTERNS = [
    'sql_union' => '/\bunion\b\s+\bselect\b/i',
    'sql_comment' => '/(--|#|\/\*)/',
    'xss_script' => '/<\s*script\b/i',
    'xss_event' => '/\bon[a-z]{3,}\s*=/i',
    'path_traversal' => '/\.\.\//',
    'php_wrapper' => '/(?:php|data|expect|zip|phar):\/\//i',
    'cmd_injection' => '/(?:;|\|\||&&|`|\$\()\s*(?:cat|curl|wget|bash|sh|nc|python|perl)\b/i'
  ];

  public static function inspect(): void {
    if (PHP_SAPI === 'cli') return;
    $page = (string)($_GET['page'] ?? 'dashboard');
    if (!self::shouldInspect($page)) return;
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $payload = json_encode(['get'=>$_GET, 'post'=>self::limited($_POST), 'uri'=>$_SERVER['REQUEST_URI'] ?? ''], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    foreach (self::PATTERNS as $name=>$rx) {
      if (preg_match($rx, (string)$payload)) {
        SecurityEventService::log('waf.bloqueio', 'alto', 'Padrão bloqueado pelo WAF de painel: '.$name, ['pattern'=>$name, 'method'=>$method, 'page'=>$page]);
        self::autoBlockIfNeeded();
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('Requisição bloqueada por segurança.');
      }
    }
    self::rateGuard();
  }

  public static function shouldInspect(string $page): bool {
    if ($page === '') return false;
    $sec = (class_exists('App') ? (App::config()['security'] ?? []) : []);
    $never = array_filter(array_map('trim', explode(',', (string)($sec['waf_never_inspect_routes'] ?? 'api/tiny/*,api/vsm/*,api/webhook/tiny/*,api/webhook/vsm/*,webhook/tiny/*,webhook/vsm/*'))));
    foreach ($never as $pattern) {
      $pattern = trim($pattern);
      if ($pattern === '') continue;
      $prefix = rtrim($pattern, '*');
      if (($pattern === $page) || ($prefix !== $pattern && str_starts_with($page, $prefix))) return false;
    }
    if (str_starts_with($page, 'api/')) return false;
    // Melhoria 11 da seção 8 (mesma família do achado B-06): aqui havia
    // "if (str_contains($page, 'webhook')) return false;" - casamento por SUBSTRING numa decisão
    // de segurança. Ele desligava a inspeção do WAF para qualquer rota cujo nome contivesse
    // "webhook", inclusive 'tiny-webhooks', que é a página ADMINISTRATIVA de configuração dos
    // webhooks (edita segredo, CNPJs autorizados e limites) e não um webhook. A classificação
    // passa a ser por igualdade exata, contra a lista exaustiva do catálogo: uma rota nova que
    // ninguém classificar continua INSPECIONADA, em vez de ganhar isenção por semelhança de nome.
    if (RouteCatalogService::isInboundWebhook($page)) return false;
    if (empty($sec['waf_panel_only'])) return true;
    $routes = array_filter(array_map('trim', explode(',', (string)($sec['waf_panel_routes'] ?? 'dashboard,configuracoes'))));
    if (in_array($page, $routes, true)) return true;
    foreach (['dashboard','configuracoes','admin','security','seguranca','backup','usuarios','central-tecnica'] as $prefix) {
      if ($page === $prefix || str_starts_with($page, $prefix.'-')) return true;
    }
    return false;
  }

  private static function limited(array $arr): array {
    $out=[]; $i=0;
    foreach ($arr as $k=>$v) { if (++$i>50) break; $out[$k] = is_string($v) ? substr($v,0,1000) : $v; }
    return $out;
  }
  private static function rateGuard(): void {
    try {
      SecurityEventService::ensureSchema();
      $pdo = Database::getConnection(); $ip = SecurityEventService::ip();
      $st = $pdo->prepare("SELECT COUNT(*) FROM security_events WHERE ip=? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
      $st->execute([$ip]);
      $n = (int)$st->fetchColumn();
      if ($n > 120) IpBlockService::block($ip, 'Rate limit global do painel excedido', 30, 'alto');
    } catch (Throwable $e) { if(class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e, ['scope'=>'global_rate_limit']); }
  }
  private static function autoBlockIfNeeded(): void {
    try {
      $pdo = Database::getConnection(); $ip = SecurityEventService::ip();
      $st = $pdo->prepare("SELECT COUNT(*) FROM security_events WHERE ip=? AND tipo='waf.bloqueio' AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
      $st->execute([$ip]);
      if ((int)$st->fetchColumn() >= 5) IpBlockService::block($ip, 'Múltiplos bloqueios WAF no painel', 120, 'critico');
    } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
  }
}
