<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks=[];$queue=hub_read('app/Services/QueueService.php');$token=hub_read('app/Services/TinyV3TokenService.php');$controller=hub_read('app/Controllers/DashboardController.php');$api=hub_read('app/Controllers/ApiController.php');$install=hub_read('public/install.php');$view=hub_read('views/configuracoes.php');$sw=hub_read('public/sw.js');$cfg=require hub_config_file();
hub_check($checks,'Lease padrão configurável',isset($cfg['security']['queue_lease_minutes'])&&(int)$cfg['security']['queue_lease_minutes']===5&&str_contains($queue,'leaseMinutes(?string $type'));
hub_check($checks,'Lease por tipo configurável',isset($cfg['security']['queue_lease_minutes_by_type'])&&str_contains($queue,'queue_lease_by_type_json'));
hub_check($checks,'Instalador grava installation_id e lease',str_contains($install,"'installation_id'")&&str_contains($install,'queue_lease_minutes_by_type'));
hub_check($checks,'Painel permite configurar lease',str_contains($view,'queue_lease_minutes')&&str_contains($controller,'queue_lease_by_type_json'));
hub_check($checks,'Heartbeat exige posse',str_contains($queue,'heartbeat(int $id, ?string $owner')&&str_contains($queue,'locked_by=?'));
hub_check($checks,'Resultado exige posse',str_contains($queue,'marcarResultado(int $id, bool $sucesso, array $retorno')&&str_contains($queue,"status=\'processando\' AND locked_by=?"));
hub_check($checks,'API propaga proprietário',str_contains($api,"\$item['locked_by']")&&str_contains($api,'QueueService::heartbeat'));
hub_check($checks,'Reprocessamento bloqueia worker ativo',str_contains($queue,'worker ativo; reprocessamento bloqueado')&&str_contains($queue,'SELECT * FROM fila_integracao WHERE id=? LIMIT 1 FOR UPDATE'));
hub_check($checks,'Refresh manual realmente forçado',str_contains($controller,'TinyV3TokenService::refresh(true)')&&str_contains($token,'public static function refresh(bool $force=false'));
hub_check($checks,'Lock OAuth isolado por instalação',str_contains($token,'installation_id')&&str_contains($token,"hash('sha256'"));
hub_check($checks,'Lock OAuth possui fallback local',str_contains($token,'flock(')&&str_contains($token,"'driver'=>'file'"));
hub_check($checks,'HTTP 500 não é mascarado como offline',!str_contains($sw,'response.status>=500?caches.match')&&str_contains($sw,"fetch(request,{cache:'no-store'}).catch"));
hub_check($checks,'PWA 104.49.2+',hub_sw_version()!==''&&version_compare(hub_sw_version(),'104.49.2','>='),hub_sw_version());
foreach(glob(hub_root().'/public/worker*.php')?:[] as $worker){$content=(string)file_get_contents($worker);hub_check($checks,'Worker web bloqueado: '.basename($worker),str_contains($content,"PHP_SAPI !== 'cli'"));}
hub_finish($checks);
