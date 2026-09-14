<?php
class RetryPolicyService {
  public static function attempts(): int {
    $sec = (class_exists('App') ? (App::config()['security'] ?? []) : []);
    return max(1, min(5, (int)($sec['integration_retry_attempts'] ?? 3)));
  }
  public static function baseDelayMs(): int {
    $sec = (class_exists('App') ? (App::config()['security'] ?? []) : []);
    return max(50, min(3000, (int)($sec['integration_retry_base_ms'] ?? 350)));
  }
  public static function shouldRetry(?int $http, ?string $curlError, $decoded=null): bool {
    if ($curlError) return true;
    if ($http !== null && in_array($http, [408,409,425,429,500,502,503,504], true)) return true;
    if ($http !== null && $http >= 500) return true;
    return false;
  }
  public static function sleep(int $attempt): void {
    if ($attempt <= 1) return;
    $delay = self::baseDelayMs() * (2 ** ($attempt-2));
    $jitter = random_int(0, min(250, $delay));
    usleep(($delay + $jitter) * 1000);
  }

  /**
   * Auditoria final 2026-09-14 (achado G-03) — jitter do reagendamento de fila.
   *
   * O jitter do sleep() acima cobre o retry DENTRO de uma requisição. O reagendamento das filas
   * (proxima_tentativa) era determinístico em quatro pontos: QueueService::marcarResultado(),
   * EnterpriseIdempotencyGuardService::markGuardFailureQueueItem(),
   * EstoqueEnterpriseService::proximaTentativa() e FiscalEnterpriseService::proximaTentativa().
   * Quando a VSM ou o Tiny falha rápido (503 imediato), muitos itens terminam no mesmo segundo,
   * recebem o mesmo atraso e voltam a ficar elegíveis no MESMO segundo - o provedor recebe uma
   * rajada sincronizada em vez de uma rampa. O caso pior era o guard de idempotência, com 300s
   * fixos para todos.
   *
   * O jitter é ADITIVO de propósito: nunca antecipa a tentativa. Antecipar seria pior que não ter
   * jitter - a política de rate_limit recua 15/30/45 min justamente para parar de bater no
   * provedor, e reduzir esse atraso desfaria a proteção.
   *
   * Teto: 10% do atraso, com piso de 30s e limite de 300s. Para 5 min espalha em 30s; para
   * 60 min, em até 300s. Nunca estoura de forma relevante o teto da política (180 min no
   * rate_limit).
   */
  public static function jitterSegundos(int $baseSegundos): int {
    if ($baseSegundos <= 0) return 0;
    $teto = (int)max(30, min(300, (int)round($baseSegundos * 0.1)));
    return random_int(0, $teto);
  }

  /** Instante da próxima tentativa, no formato de DATETIME do MySQL, já com jitter aditivo. */
  public static function proximaTentativaEm(int $minutos): string {
    $base = max(1, $minutos) * 60;
    return date('Y-m-d H:i:s', time() + $base + self::jitterSegundos($base));
  }
}
