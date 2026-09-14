<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks=[];
class App { public static array $cfg=['app_env'=>'homologacao','security'=>['vsm_allowed_hosts'=>'8.8.8.8','vsm_allowed_ports'=>'443,80']]; public static function config():array{return self::$cfg;} public static function isPublicHost():bool{return false;} }
class IntegrationConfig { public static function get():array{return ['ambiente'=>'homologacao'];} }
require_once hub_root().'/app/Services/VsmEndpointSecurityService.php';
hub_check($checks,'Caminho relativo VSM válido é preservado',VsmEndpointSecurityService::sanitizePath('/api/pedidos/{id}?x=1')==='/api/pedidos/{id}?x=1');
$badPaths=['https://evil.test/x','/api/../admin','/api/%2e%2e/admin','/api/%252e%252e/admin','/api/%5cadmin'];
$allBlocked=true;foreach($badPaths as $path){try{VsmEndpointSecurityService::sanitizePath($path);$allBlocked=false;}catch(InvalidArgumentException){}}
hub_check($checks,'SSRF/path traversal bloqueia URL absoluta e traversal codificado',$allBlocked);
$valid=VsmEndpointSecurityService::validateBaseUrl('https://8.8.8.8');
hub_check($checks,'URL HTTPS em host/porta permitidos é aceita',$valid==='https://8.8.8.8');
$badUrls=['http://user:pass@8.8.8.8','https://127.0.0.1','https://8.8.8.8:8443','https://8.8.8.8?redirect=http://127.0.0.1','https://1.1.1.1'];
$urlsBlocked=true;foreach($badUrls as $url){try{VsmEndpointSecurityService::validateBaseUrl($url);$urlsBlocked=false;}catch(InvalidArgumentException){}}
hub_check($checks,'Base URL bloqueia credenciais, IP privado, porta, query e host fora da allowlist',$urlsBlocked);
hub_check($checks,'IP literal validado não gera CURLOPT_RESOLVE',VsmEndpointSecurityService::curlResolveEntry('https://8.8.8.8')===null);
$service=hub_read('app/Services/VsmService.php');
hub_check($checks,'Cliente VSM desativa redirects e fixa protocolos',str_contains($service,'CURLOPT_FOLLOWLOCATION=>false')&&str_contains($service,'CURLOPT_MAXREDIRS=>0')&&str_contains($service,'CURLOPT_RESOLVE'));
hub_finish($checks);
