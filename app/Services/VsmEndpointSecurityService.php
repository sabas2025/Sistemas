<?php
class VsmEndpointSecurityService {
  public static function sanitizePath(string $path): string {
    $path = trim($path);
    if ($path === '') return '/api/estoque/consulta';
    $lower = strtolower($path);
    foreach (['http://','https://','localhost','127.0.0.1','::1'] as $bad) {
      if (str_contains($lower, $bad)) throw new InvalidArgumentException('Endpoint VSM deve ser caminho relativo seguro, exemplo: /api/estoque/consulta.');
    }
    if ($path[0] !== '/') $path = '/'.$path;
    $decoded=$path;
    for($i=0;$i<3;$i++){ $next=rawurldecode($decoded); if($next===$decoded) break; $decoded=$next; }
    if (str_contains($decoded,'..') || str_contains($decoded,"\\") || str_contains($decoded,"\0") || preg_match('/[\x00-\x1F\x7F]/',$decoded)) throw new InvalidArgumentException('Endpoint VSM contém trecho inseguro.');
    if (!preg_match('#^/[a-zA-Z0-9_./{}\-?=&%]+$#',$path)) throw new InvalidArgumentException('Endpoint VSM contém caracteres inválidos.');
    return $path;
  }

  /** Proteção SSRF e DNS rebinding para URL base da VSM. */
  public static function validateBaseUrl(string $baseUrl): string {
    $baseUrl = rtrim(trim($baseUrl), '/');
    if ($baseUrl === '') throw new InvalidArgumentException('URL base da VSM não configurada.');
    $parts = parse_url($baseUrl);
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    $host = strtolower((string)($parts['host'] ?? ''));
    if (!in_array($scheme, ['https','http'], true) || $host === '') throw new InvalidArgumentException('URL base da VSM inválida. Use https://host autorizado.');
    if (isset($parts['user']) || isset($parts['pass'])) throw new InvalidArgumentException('URL base da VSM não pode conter credenciais embutidas.');
    if (isset($parts['fragment']) || isset($parts['query'])) throw new InvalidArgumentException('URL base da VSM não pode conter query ou fragmento.');
    $cfg = class_exists('App') ? App::config() : [];
    $port=(int)($parts['port']??($scheme==='https'?443:80));
    $allowedPorts=array_values(array_filter(array_map('intval',explode(',',(string)($cfg['security']['vsm_allowed_ports']??'443,80')))));
    if($allowedPorts&&!in_array($port,$allowedPorts,true)) throw new InvalidArgumentException('Porta da VSM bloqueada pela allowlist security.vsm_allowed_ports.');
    $integration = class_exists('IntegrationConfig') ? IntegrationConfig::get() : [];
    $integrationEnvironment=strtolower((string)($integration['ambiente']??''));
    $production = (class_exists('App') && method_exists('App','isProduction') && App::isProduction())
      || in_array($integrationEnvironment,['producao','production','prod'],true)
      || (class_exists('App') && method_exists('App','isPublicHost') && App::isPublicHost());
    if ($production && $scheme !== 'https') throw new InvalidArgumentException('URL VSM deve usar HTTPS em ambiente público/produção.');
    if (in_array($host, ['localhost','localhost.localdomain','0.0.0.0'], true) || str_ends_with($host, '.local')) throw new InvalidArgumentException('URL base da VSM bloqueada: host local não permitido.');
    $allowed = array_values(array_filter(array_map(fn($v)=>strtolower(trim($v)), explode(',', (string)($cfg['security']['vsm_allowed_hosts'] ?? '')))));
    if ($production && $allowed === []) throw new InvalidArgumentException('Allowlist security.vsm_allowed_hosts é obrigatória em produção.');
    if ($allowed && !in_array($host, $allowed, true)) throw new InvalidArgumentException('URL base da VSM bloqueada: host não está na allowlist security.vsm_allowed_hosts.');
    self::resolvePublicIps($host);
    return $baseUrl;
  }

  /** @return string[] */
  public static function resolvePublicIps(string $host): array {
    $host=trim(strtolower($host)); if($host==='') throw new InvalidArgumentException('Host VSM vazio.');
    $ips=[];
    if(filter_var($host,FILTER_VALIDATE_IP)) $ips[]=$host;
    else {
      if(function_exists('dns_get_record')){
        $records=@dns_get_record($host,DNS_A|DNS_AAAA);
        if(is_array($records)) foreach($records as $record){ if(!empty($record['ip']))$ips[]=(string)$record['ip']; if(!empty($record['ipv6']))$ips[]=(string)$record['ipv6']; }
      }
      if(!$ips){$resolved=@gethostbynamel($host);if(is_array($resolved))$ips=array_merge($ips,$resolved);}
    }
    $ips=array_values(array_unique(array_filter($ips)));
    if(!$ips) throw new InvalidArgumentException('Host VSM não pôde ser resolvido por DNS.');
    foreach($ips as $ip){
      if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)) throw new InvalidArgumentException('URL base da VSM bloqueada: DNS resolveu IP privado/reservado.');
    }
    return $ips;
  }

  /** Retorna entrada CURLOPT_RESOLVE para fixar o DNS validado nesta requisição. */
  public static function curlResolveEntry(string $baseUrl): ?string {
    $parts=parse_url(self::validateBaseUrl($baseUrl));
    $host=(string)($parts['host']??''); if($host===''||filter_var($host,FILTER_VALIDATE_IP))return null;
    $port=(int)($parts['port']??(($parts['scheme']??'https')==='https'?443:80));
    $ips=self::resolvePublicIps($host); $ip=(string)($ips[0]??'');
    if(filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV6)) $ip='['.$ip.']';
    return $ip!==''?$host.':'.$port.':'.$ip:null;
  }

  /** Mantém host, rota e nomes dos parâmetros sem persistir seus valores. */
  public static function redactUrlForLog(string $baseUrl,string $endpointTemplate): string {
    $candidate=rtrim($baseUrl,'/').'/'.ltrim($endpointTemplate,'/');
    $parts=parse_url($candidate);
    if(!is_array($parts)||empty($parts['scheme'])||empty($parts['host']))return '[URL VSM protegida]';
    $url=strtolower((string)$parts['scheme']).'://'.strtolower((string)$parts['host']);
    if(isset($parts['port']))$url.=':'.(int)$parts['port'];
    $url.=(string)($parts['path']??'/');
    if(isset($parts['query'])){
      $names=[];
      foreach(explode('&',(string)$parts['query']) as $pair){
        $name=rawurldecode(explode('=',$pair,2)[0]??'');
        if($name!==''&&preg_match('/^[a-zA-Z0-9_.-]{1,80}$/',$name))$names[]=$name.'=***';
      }
      if($names!==[])$url.='?'.implode('&',array_values(array_unique($names)));
    }
    return $url;
  }
}
