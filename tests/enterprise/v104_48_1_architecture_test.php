<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks=[];
$dash=hub_read('app/Controllers/DashboardController.php');
$legacy=hub_read('app/Controllers/LegacyDatabaseUpgradeController.php');
$config=hub_read('config/config.php');
$installer=hub_read('public/install.php');
$version=hub_read('app/Services/SystemVersionService.php');
$sw=hub_read('public/sw.js');
$pkg=hub_read('package.json');
$pkgLock=hub_read('package-lock.json');

hub_check($checks,'Dashboard delega atualizações legadas ao controller dedicado',str_contains($dash,'LegacyDatabaseUpgradeController')&&!preg_match('/private\s+function\s+atualizarV\d+/',$dash));
hub_check($checks,'Controller legado contém rotas de atualização e CSRF',preg_match('/function\s+atualizarV15HomologacaoFinal\s*\(/',$legacy)===1&&str_contains($legacy,'Csrf::validate'));
hub_check($checks,'Dashboard foi reduzido abaixo de 160 KB',strlen($dash)<160*1024,'bytes='.strlen($dash));
hub_check($checks,'Orquestração foi extraída sem perder fallback compatível',str_contains($dash,"(new OrquestracaoController())->dispatch(\$page)")&&!preg_match('/private\s+function\s+(orquestracaoIntegracoes|salvarOrquestracaoIntegracoes|testarOrquestracaoFluxo)\s*\(/',$dash));
hub_check($checks,'Reparo de schema em runtime fica desativado por padrão',preg_match("/'schema_runtime_repair_enabled'\s*=>\s*false/",$config)===1&&preg_match("/'schema_runtime_repair_enabled'\s*=>\s*false/",$installer)===1);
hub_check($checks,'Instalador publica configuração, FIM e lock de forma atômica, privada e reversível',str_contains($installer,'function atomic_private_write')&&str_contains($installer,'@chmod($path,0600)')&&str_contains($installer,'function publish_install_artifacts')&&str_contains($installer,'publish_install_artifacts($configPath,$root,$cfg)'));

hub_check($checks,'Instalador detecta base_url em vez de gravar caminho fixo',str_contains($installer,'detect')||str_contains($installer,'base_url'));

$declaredVersion=preg_match("/VERSION_NUMBER\s*=\s*'([^']+)'/",$version,$versionMatch)?(string)$versionMatch[1]:'';
$versionOk=$declaredVersion==='104.49.3'
    && str_contains($version,"RELEASE = 'R7'")
    && str_contains($sw,"HUB_VERSION = '".$declaredVersion."'")
    && str_contains($pkg,'"version": "'.$declaredVersion.'"')
    && str_contains($pkgLock,'"version": "'.$declaredVersion.'"');
hub_check($checks,'Versão do sistema, PWA e pacote está sincronizada',$versionOk);

$operational=[
 'app/Services/VsmEndpointService.php','app/Services/IntegrationReplayGuard.php','app/Services/CommercialProductService.php',
 'app/Services/TokenVaultService.php','app/Services/TinyValidationService.php','app/Services/TinyV2HomologationService.php',
 'app/Services/TinyV3HomologationService.php','app/Services/TesteRealTinyService.php','app/Services/RouteRateLimiter.php',
 'app/Services/ProductionReadinessService.php','app/Services/ProductionGoLiveService.php','app/Services/LlmPolicyService.php',
 'app/Services/LlmCostGuardService.php','app/Services/LlmApprovalService.php','app/Services/AuditDailySignatureService.php',
 'app/Services/AuditIntegrityService.php','app/Services/EnterpriseAuditHashChainService.php','app/Services/AutoHomologationService.php',
 'app/Services/PedidoTinyVsmValidationService.php','app/Services/SecurityEventService.php'
];
$ddl=[];
foreach($operational as $file){$txt=hub_read($file);if(preg_match('/\b(CREATE\s+TABLE|ALTER\s+TABLE|DROP\s+TABLE)\b/i',$txt))$ddl[]=$file;}
hub_check($checks,'Services operacionais não executam DDL em requisições normais',$ddl===[],implode(',',$ddl));

$policy=hub_read('app/Services/SchemaRuntimePolicyService.php');
hub_check($checks,'Política de schema oferece validação sem reparo automático',str_contains($policy,'requireTable')&&str_contains($policy,'requireColumns')&&str_contains($policy,'schema_runtime_repair_enabled'));

hub_finish($checks);
