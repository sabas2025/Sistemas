<?php
class TrustedProxyService {
  public static function configuredProxies(): array {
    $cfg = App::config()['security'] ?? [];
    $raw = (string)($cfg['trusted_proxies'] ?? '');
    $items = array_filter(array_map('trim', explode(',', $raw)));
    return array_values(array_filter($items, fn($ip)=>filter_var($ip, FILTER_VALIDATE_IP)));
  }
  public static function remoteAddr(): string {
    $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
  }
  public static function isTrustedProxy(?string $ip=null): bool {
    $ip = $ip ?: self::remoteAddr();
    if ($ip === '127.0.0.1' || $ip === '::1') return true;
    return in_array($ip, self::configuredProxies(), true);
  }
  public static function clientIp(): string {
    $remote = self::remoteAddr();
    if (!self::isTrustedProxy($remote)) return $remote;
    $candidates = [];
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) $candidates[] = (string)$_SERVER['HTTP_CF_CONNECTING_IP'];
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) $candidates[] = (string)$_SERVER['HTTP_X_REAL_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
      foreach (explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']) as $part) $candidates[] = trim($part);
    }
    foreach ($candidates as $ip) if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    return $remote;
  }
  public static function isHttps(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    if (!self::isTrustedProxy()) return false;
    return strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
  }

  /**
   * P0-08 (reauditoria 2026-08-23): allowlist opcional de hosts canônicos.
   * Config vazia (padrão) preserva o comportamento atual - só passa a restringir
   * quando o operador configurar security.canonical_host explicitamente.
   * @return string[] hosts em minúsculas, sem porta
   */
  public static function canonicalHosts(): array {
    $cfg = class_exists('App') ? (App::config()['security'] ?? []) : [];
    $raw = (string)($cfg['canonical_host'] ?? '');
    $hosts = array_filter(array_map('trim', explode(',', strtolower($raw))));
    return array_values(array_map(fn($h) => preg_replace('/:\d+$/', '', $h), $hosts));
  }

  /** Host da requisição atual, em minúsculas e sem porta. */
  public static function currentHost(): string {
    $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')));
    return preg_replace('/:\d+$/', '', $host) ?? '';
  }

  /**
   * Se nenhum canonical_host estiver configurado, sempre permite (compatibilidade
   * retroativa). Se configurado, só permite hosts que constem explicitamente na lista -
   * o cabeçalho Host bruto deixa de ser confiável sozinho para decidir se é produção.
   */
  public static function isHostAllowed(): bool {
    $allowed = self::canonicalHosts();
    if ($allowed === []) return true;
    return in_array(self::currentHost(), $allowed, true);
  }
}
