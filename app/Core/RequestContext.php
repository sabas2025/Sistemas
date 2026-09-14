<?php
class RequestContext {
  private static ?string $traceId = null;

  public static function id(): string {
    if (self::$traceId === null) {
      self::$traceId = 'TRC-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(4)));
      $_SERVER['HUB_INTERNAL_TRACE_ID'] = self::$traceId;
    }
    return self::$traceId;
  }

  public static function clientTraceId(): ?string {
    $raw = (string)($_SERVER['HTTP_X_TRACE_ID'] ?? '');
    if ($raw === '') return null;
    $clean = preg_replace('/[^A-Za-z0-9_.:-]/', '', $raw);
    return $clean !== '' ? substr($clean, 0, 80) : null;
  }

  public static function userId(): ?int { return $_SESSION['user']['id'] ?? null; }

  /**
   * IP real do cliente com Trusted Proxy.
   * Não usar REMOTE_ADDR diretamente em autenticação/fingerprint, pois em produção
   * ele pode ser o proxy/CDN e pode causar bloqueio incorreto ou fingerprint instável.
   */
  public static function ip(): ?string {
    if (class_exists('TrustedProxyService')) return TrustedProxyService::clientIp();
    $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
  }

  public static function ipForAudit(): string { return self::ip() ?: '0.0.0.0'; }

  public static function ipPrefix(?string $ip=null): string {
    $ip = $ip ?: self::ip();
    if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP)) return '0.0.0.0';
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
      $parts = explode('.', $ip);
      return count($parts) === 4 ? $parts[0].'.'.$parts[1].'.'.$parts[2].'.0/24' : $ip;
    }
    $parts = explode(':', $ip);
    return implode(':', array_slice($parts, 0, 4)).'::/64';
  }

  public static function userAgent(): ?string { return $_SERVER['HTTP_USER_AGENT'] ?? null; }
  public static function route(): string { return $_GET['page'] ?? 'dashboard'; }
  public static function method(): string { return $_SERVER['REQUEST_METHOD'] ?? 'GET'; }
}
