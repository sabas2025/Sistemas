<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks=[];
class App { public static array $cfg=['security'=>['tiny_allowed_hosts'=>'8.8.8.8','tiny_allowed_ports'=>'443']]; public static function config():array{return self::$cfg;} }
require_once hub_root().'/app/Services/TinyEndpointSecurityService.php';

hub_check($checks,'Caminho relativo Tiny válido é preservado',TinyEndpointSecurityService::sanitizePath('/public-api/v3/produtos/{id}')==='public-api/v3/produtos/{id}');
$badPaths=['https://evil.test/x','../admin','%2e%2e/admin','%252e%252e/admin','api\\admin'];
$blocked=true;foreach($badPaths as $path){try{TinyEndpointSecurityService::sanitizePath($path);$blocked=false;}catch(InvalidArgumentException){}}
hub_check($checks,'Endpoint Tiny bloqueia URL absoluta e traversal codificado',$blocked);

hub_check($checks,'URL Tiny HTTPS em host permitido é aceita',TinyEndpointSecurityService::validateUrl('https://8.8.8.8/api2')==='https://8.8.8.8/api2');
$badUrls=['http://8.8.8.8/api2','https://user:pass@8.8.8.8/api2','https://127.0.0.1/api2','https://8.8.8.8:8443/api2','https://8.8.8.8/api2?redirect=x','https://1.1.1.1/api2'];
$urlsBlocked=true;foreach($badUrls as $url){try{TinyEndpointSecurityService::validateUrl($url);$urlsBlocked=false;}catch(InvalidArgumentException){}}
hub_check($checks,'URL Tiny bloqueia HTTP, credenciais, IP privado, porta, query e host fora da allowlist',$urlsBlocked);

$v2=hub_read('app/Services/TinyV2Service.php');$v3=hub_read('app/Services/TinyV3Service.php');$token=hub_read('app/Services/TinyV3TokenService.php');$dash=hub_read('app/Controllers/DashboardController.php');
$allClients=true;foreach([$v2,$v3,$token,$dash] as $source){if(!str_contains($source,'TinyEndpointSecurityService')){$allClients=false;break;}}
hub_check($checks,'Tiny V2, Tiny V3 e OAuth usam o validador SSRF central',$allClients);
hub_check($checks,'Clientes Tiny desativam redirects e aplicam DNS pinning',str_contains($v2,'$securityOptions+')&&str_contains($v3,'$securityOptions+')&&str_contains($token,'$securityOptions+')&&str_contains($dash,'$securityOptions+'));
$config=hub_read('config/config.php');$installer=hub_read('public/install.php');
hub_check($checks,'Allowlist oficial Tiny é instalada por padrão',str_contains($config,'api.tiny.com.br,accounts.tiny.com.br')&&str_contains($installer,'api.tiny.com.br,accounts.tiny.com.br'));

hub_finish($checks);
