<?php
/**
 * Validação SSRF para todas as saídas Tiny V2/V3/OAuth.
 * Os hosts oficiais permanecem configuráveis por allowlist, sem aceitar destinos internos.
 */
class TinyEndpointSecurityService {
  public static function validateUrl(string $url): string {
    $url = rtrim(trim($url), '/');
    if ($url === '') throw new InvalidArgumentException('URL Tiny não configurada.');
    $parts = parse_url($url);
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    $host = strtolower((string)($parts['host'] ?? ''));
    if ($scheme !== 'https' || $host === '') throw new InvalidArgumentException('URL Tiny inválida. Use HTTPS em host autorizado.');
    if (isset($parts['user']) || isset($parts['pass'])) throw new InvalidArgumentException('URL Tiny não pode conter credenciais embutidas.');
    if (isset($parts['query']) || isset($parts['fragment'])) throw new InvalidArgumentException('URL Tiny base/OAuth não pode conter query ou fragmento.');
    $cfg = class_exists('App') ? App::config() : [];
    $port = (int)($parts['port'] ?? 443);
    $ports = array_values(array_filter(array_map('intval', explode(',', (string)($cfg['security']['tiny_allowed_ports'] ?? '443')))));
    if ($ports && !in_array($port, $ports, true)) throw new InvalidArgumentException('Porta Tiny bloqueada pela allowlist security.tiny_allowed_ports.');
    if (in_array($host, ['localhost','localhost.localdomain','0.0.0.0'], true) || str_ends_with($host, '.local')) throw new InvalidArgumentException('Host Tiny local não permitido.');
    $allowed = array_values(array_filter(array_map(fn($v)=>strtolower(trim($v)), explode(',', (string)($cfg['security']['tiny_allowed_hosts'] ?? 'api.tiny.com.br,accounts.tiny.com.br')))));
    if ($allowed && !in_array($host, $allowed, true)) throw new InvalidArgumentException('Host Tiny não está na allowlist security.tiny_allowed_hosts.');
    self::resolvePublicIps($host);
    return $url;
  }

  public static function sanitizePath(string $path): string {
    $path = trim($path);
    if ($path === '') throw new InvalidArgumentException('Endpoint Tiny vazio.');
    $lower = strtolower($path);
    if (str_contains($lower, '://') || str_contains($lower, 'localhost')) throw new InvalidArgumentException('Endpoint Tiny deve ser caminho relativo.');
    $decoded = $path;
    for ($i=0;$i<3;$i++) { $next=rawurldecode($decoded); if($next===$decoded) break; $decoded=$next; }
    if (str_contains($decoded, '..') || str_contains($decoded, "\\") || str_contains($decoded, "\0") || preg_match('/[\x00-\x1F\x7F]/', $decoded)) throw new InvalidArgumentException('Endpoint Tiny contém trecho inseguro.');
    $path = ltrim($path, '/');
    if (!preg_match('#^[a-zA-Z0-9_./{}\-]+$#', $path)) throw new InvalidArgumentException('Endpoint Tiny contém caracteres inválidos.');
    return $path;
  }

  /** @return string[] */
  public static function resolvePublicIps(string $host): array {
    $host = strtolower(trim($host));
    if ($host === '') throw new InvalidArgumentException('Host Tiny vazio.');
    $ips=[];
    if (filter_var($host, FILTER_VALIDATE_IP)) $ips[]=$host;
    else {
      if (function_exists('dns_get_record')) {
        $records=@dns_get_record($host, DNS_A|DNS_AAAA);
        if (is_array($records)) foreach($records as $record){ if(!empty($record['ip']))$ips[]=(string)$record['ip']; if(!empty($record['ipv6']))$ips[]=(string)$record['ipv6']; }
      }
      if(!$ips){$resolved=@gethostbynamel($host);if(is_array($resolved))$ips=array_merge($ips,$resolved);}
    }
    $ips=array_values(array_unique(array_filter($ips)));
    if(!$ips) throw new InvalidArgumentException('Host Tiny não pôde ser resolvido por DNS.');
    foreach($ips as $ip) if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)) throw new InvalidArgumentException('Host Tiny resolveu IP privado/reservado.');
    return $ips;
  }

  public static function curlResolveEntry(string $url): ?string {
    $parts=parse_url(self::validateUrl($url));
    $host=(string)($parts['host'] ?? '');
    if($host==='' || filter_var($host,FILTER_VALIDATE_IP)) return null;
    $port=(int)($parts['port'] ?? 443);
    $ips=self::resolvePublicIps($host);
    return isset($ips[0]) ? $host.':'.$port.':'.$ips[0] : null;
  }

  /** @return array<int,mixed> */
  public static function curlSecurityOptions(string $url): array {
    $resolve=self::curlResolveEntry($url);
    $options=[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0];
    if(defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) $options[CURLOPT_PROTOCOLS]=CURLPROTO_HTTPS;
    if(defined('CURLOPT_REDIR_PROTOCOLS') && defined('CURLPROTO_HTTPS')) $options[CURLOPT_REDIR_PROTOCOLS]=CURLPROTO_HTTPS;
    if($resolve!==null) $options[CURLOPT_RESOLVE]=[$resolve];
    return $options;
  }
}
