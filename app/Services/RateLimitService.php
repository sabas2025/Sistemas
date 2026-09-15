<?php
/**
 * Melhoria 7 da seção 8 (relatório V104.49.3-R6): fachada única de rate limit.
 *
 * Coexistiam três implementações com três políticas de falha diferentes e nenhuma delas declarada
 * em lugar nenhum: RouteRateLimiterService (banco), AtomicRateCounterService (disco) e
 * LoginRateLimitFallbackService (APCu/disco, com leitura e escrita em locks SEPARADOS - o mesmo
 * defeito de atomicidade do achado A-07, que havia sido corrigido só no contador do PWA). Três
 * caminhos independentes é também a razão de o achado B-01 ter nascido: nada obrigava um novo
 * limitador a declarar o que fazer quando o armazenamento falha.
 *
 * Aqui existe UMA tabela de políticas. Cada superfície declara janela, limite, de onde vem o
 * limite configurável e - obrigatoriamente - o que acontece quando o contador não funciona:
 *
 *   'closed' → nega a requisição (canal público protegido por segredo, telemetria descartável)
 *   'open'   → libera, mas registra a degradação em SecurityHealthService (rotas do painel, onde
 *              barrar tudo por falha de infraestrutura seria auto-DoS)
 *
 * Não existe política implícita: uma superfície sem 'on_storage_failure' é rejeitada em tempo de
 * chamada, para que a próxima pessoa a acrescentar um limitador tenha de tomar a decisão.
 */
class RateLimitService {
  public const FAIL_CLOSED = 'closed';
  public const FAIL_OPEN   = 'open';

  /**
   * @var array<string,array{window:int,default:int,config:?string,on_storage_failure:string,health:string,descricao:string}>
   */
  private const POLICIES = [
    'route' => [
      'window' => 60, 'default' => 90, 'config' => 'route_rate_limit_per_minute',
      'on_storage_failure' => self::FAIL_OPEN, 'health' => 'route_rate_limit_counter',
      'descricao' => 'Rotas do painel (último recurso quando o banco caiu)',
    ],
    'route_sensitive' => [
      'window' => 60, 'default' => 20, 'config' => 'route_rate_limit_sensitive_per_minute',
      'on_storage_failure' => self::FAIL_OPEN, 'health' => 'route_rate_limit_counter',
      'descricao' => 'Rotas sensíveis e api/* do painel (último recurso)',
    ],
    // Auditoria de capacidade 2026-09-14 (achado C-01): webhook de ENTRADA não é rota de painel.
    // Ele é máquina-a-máquina, de alto volume por natureza, e já tem defesa própria: allowlist de
    // IP dedicada, assinatura HMAC, contador de tentativas pré-autenticação e guarda de replay.
    // Tratá-lo com o orçamento de uma tela administrativa (20/min) fazia o Hub recusar a própria
    // integração que existe para servir. O teto é alto de propósito e configurável.
    'webhook_inbound' => [
      'window' => 60, 'default' => 3000, 'config' => 'webhook_route_rate_limit_per_minute',
      'on_storage_failure' => self::FAIL_OPEN, 'health' => 'route_rate_limit_counter',
      'descricao' => 'Webhooks de entrada Tiny/VSM (alto volume; defesa por HMAC, allowlist e replay guard)',
    ],
    'tiny_webhook' => [
      'window' => 60, 'default' => 60, 'config' => null,
      'on_storage_failure' => self::FAIL_CLOSED, 'health' => 'tiny_webhook_rate_limit',
      'descricao' => 'Tentativas de webhook Tiny/Olist, contadas antes da autenticação',
    ],
    'pwa_telemetry' => [
      'window' => 60, 'default' => 20, 'config' => null,
      'on_storage_failure' => self::FAIL_CLOSED, 'health' => 'pwa_telemetry_rate_limit',
      'descricao' => 'Telemetria anônima do PWA (endpoint público)',
    ],
    'login_ip_minute' => [
      'window' => 60, 'default' => 5, 'config' => 'login_ip_rate_limit_per_minute',
      'on_storage_failure' => self::FAIL_CLOSED, 'health' => 'login_rate_limit',
      'descricao' => 'Tentativas de login por IP, por minuto',
    ],
    'login_ip_hour' => [
      'window' => 3600, 'default' => 20, 'config' => 'login_ip_rate_limit_per_hour',
      'on_storage_failure' => self::FAIL_CLOSED, 'health' => 'login_rate_limit',
      'descricao' => 'Tentativas de login por IP, por hora',
    ],
    'login_user_minute' => [
      'window' => 60, 'default' => 3, 'config' => 'login_user_rate_limit_per_minute',
      'on_storage_failure' => self::FAIL_CLOSED, 'health' => 'login_rate_limit',
      'descricao' => 'Tentativas de login por usuário, por minuto',
    ],
    'login_user_hour' => [
      'window' => 3600, 'default' => 10, 'config' => 'login_user_rate_limit_per_hour',
      'on_storage_failure' => self::FAIL_CLOSED, 'health' => 'login_rate_limit',
      'descricao' => 'Tentativas de login por usuário, por hora',
    ],
  ];

  /** @return array<string,array<string,mixed>> catálogo para documentação/telas/testes */
  public static function policies(): array { return self::POLICIES; }

  public static function isKnownSurface(string $surface): bool { return isset(self::POLICIES[$surface]); }

  /**
   * Registra uma tentativa e devolve a decisão já aplicada à política da superfície.
   *
   * @return array{count:int,limited:bool,degraded:bool,allowed:bool,limit:int,surface:string,policy:string}
   */
  public static function hit(string $surface, string $identity, ?int $limitOverride = null): array {
    if (!isset(self::POLICIES[$surface])) {
      throw new InvalidArgumentException('Superfície de rate limit não declarada: '.$surface.'. Declare a política (inclusive o comportamento em falha de armazenamento) em RateLimitService::POLICIES.');
    }
    $policy = self::POLICIES[$surface];
    $limit = $limitOverride ?? self::configuredLimit($policy);
    $limit = max(1, min(100000, $limit));

    $state = AtomicRateCounterService::hit($surface.'|'.$identity, (int)$policy['window'], $limit);

    if ($state['degraded']) {
      $failClosed = $policy['on_storage_failure'] === self::FAIL_CLOSED;
      if (class_exists('SecurityHealthService')) {
        SecurityHealthService::degrade(
          (string)$policy['health'],
          'Contador indisponível para "'.$surface.'"; política declarada: '.($failClosed ? 'NEGAR' : 'LIBERAR com registro').'.',
          ['superficie' => $surface]
        );
      }
      return [
        'count' => 0, 'limited' => false, 'degraded' => true,
        'allowed' => !$failClosed, 'limit' => $limit,
        'surface' => $surface, 'policy' => (string)$policy['on_storage_failure'],
      ];
    }

    return [
      'count' => (int)$state['count'], 'limited' => (bool)$state['limited'], 'degraded' => false,
      'allowed' => !$state['limited'], 'limit' => $limit,
      'surface' => $surface, 'policy' => (string)$policy['on_storage_failure'],
    ];
  }

  /** Zera o contador de uma identidade (ex.: login bem-sucedido). */
  public static function reset(string $surface, string $identity): void {
    if (!isset(self::POLICIES[$surface])) return;
    AtomicRateCounterService::reset($surface.'|'.$identity);
  }

  /** Consulta sem registrar tentativa. */
  public static function peek(string $surface, string $identity, ?int $limitOverride = null): array {
    if (!isset(self::POLICIES[$surface])) {
      throw new InvalidArgumentException('Superfície de rate limit não declarada: '.$surface);
    }
    $policy = self::POLICIES[$surface];
    $limit = max(1, min(100000, $limitOverride ?? self::configuredLimit($policy)));
    $state = AtomicRateCounterService::peek($surface.'|'.$identity, (int)$policy['window']);
    $failClosed = $policy['on_storage_failure'] === self::FAIL_CLOSED;
    if ($state['degraded']) {
      return ['count'=>0,'limited'=>false,'degraded'=>true,'allowed'=>!$failClosed,'limit'=>$limit,'surface'=>$surface,'policy'=>(string)$policy['on_storage_failure']];
    }
    return ['count'=>(int)$state['count'],'limited'=>$state['count'] >= $limit,'degraded'=>false,'allowed'=>$state['count'] < $limit,'limit'=>$limit,'surface'=>$surface,'policy'=>(string)$policy['on_storage_failure']];
  }

  /** @param array<string,mixed> $policy */
  private static function configuredLimit(array $policy): int {
    $default = (int)$policy['default'];
    $key = $policy['config'] ?? null;
    if ($key === null || !class_exists('App')) return $default;
    try {
      $sec = App::config()['security'] ?? [];
      $value = $sec[$key] ?? null;
      return is_numeric($value) ? (int)$value : $default;
    } catch (Throwable $e) {
      return $default;
    }
  }
}
