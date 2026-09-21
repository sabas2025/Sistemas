<?php
/**
 * Observador de limite de requisições do Tiny — auditoria Fase 5 2026-09-21 (achados T-02/T-05).
 *
 * SÓ OBSERVABILIDADE. Lê os cabeçalhos de limite que o Tiny devolve e os normaliza para a trilha e
 * para o retorno das chamadas. NÃO impõe throttle, NÃO bloqueia chamada nenhuma — apenas registra o
 * quão perto do teto a conta está, para que a decisão de pré-limitar (T-01) tenha evidência medida
 * em vez de palpite.
 *
 * V2 (`https://tiny.com.br/api-docs/api2-limites-api`): header `x-limit-api` = chamadas/min da empresa.
 * V3 (`.../comecando/limites-de-consulta`): `X-RateLimit-Limit` / `X-RateLimit-Remaining` /
 * `X-RateLimit-Reset` (segundos até o reset). Limite V3 é por CONTA, compartilhado entre apps.
 */
class TinyRateLimitObserverService {

  /**
   * Closure para CURLOPT_HEADERFUNCTION: preenche $sink com os cabeçalhos (chave minúscula).
   * Não toca no corpo (o corpo continua vindo por CURLOPT_RETURNTRANSFER).
   *
   * @param array<string,string> $sink preenchido por referência
   */
  public static function headerCapture(array &$sink): callable {
    return function ($ch, string $line) use (&$sink): int {
      $pos = strpos($line, ':');
      if ($pos !== false) {
        $nome = strtolower(trim(substr($line, 0, $pos)));
        $sink[$nome] = trim(substr($line, $pos + 1));
      }
      return strlen($line);
    };
  }

  /**
   * Normaliza os cabeçalhos de limite da V2.
   * @param array<string,string> $headers cabeçalhos capturados (chave minúscula)
   * @return array{limite_por_minuto:int}|null null se o header não veio
   */
  public static function v2(array $headers): ?array {
    if (!array_key_exists('x-limit-api', $headers)) return null;
    $valor = trim((string)$headers['x-limit-api']);
    if ($valor === '' || !is_numeric($valor)) return null;
    return ['limite_por_minuto' => (int)$valor];
  }

  /**
   * Normaliza os cabeçalhos de limite da V3.
   * @param array<string,string> $headers cabeçalhos capturados (chave minúscula)
   * @return array{limite?:int,restante?:int,reset_segundos?:int}|null null se nenhum header veio
   */
  public static function v3(array $headers): ?array {
    $mapa = [
      'limite' => 'x-ratelimit-limit',
      'restante' => 'x-ratelimit-remaining',
      'reset_segundos' => 'x-ratelimit-reset',
    ];
    $out = [];
    foreach ($mapa as $chave => $header) {
      if (array_key_exists($header, $headers) && is_numeric(trim((string)$headers[$header]))) {
        $out[$chave] = (int)trim((string)$headers[$header]);
      }
    }
    return $out === [] ? null : $out;
  }
}
