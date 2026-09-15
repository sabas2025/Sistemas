<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks=[];

$migration=hub_read('database/migrations/20260712_004_vsm_security_selftest_recovery.sql');
$schemaService=hub_read('app/Services/SchemaMigrationService.php');
$policy=hub_read('app/Services/SchemaRuntimePolicyService.php');
$controller=hub_read('app/Controllers/DashboardController.php');
$validation=hub_read('app/Services/DatabaseValidationService.php');
$bestEffort=hub_read('app/Services/BestEffortLogService.php');
$staticCi=hub_read('scripts/ci/mysql-schema-static-check.php');
$runtimeCi=hub_read('scripts/ci/mysql-runtime-regression.php');

$tables=['security_events','ips_bloqueados','rate_limit_hits','vsm_endpoints','vsm_campos_mapeamento','vsm_endpoint_logs','selftest_relatorios'];
$allTables=$migration!=='';
foreach($tables as $table){
  if(!preg_match('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?'.preg_quote($table,'/').'`?/i',$migration))$allTables=false;
}
hub_check($checks,'Migration 004 cria tabelas VSM, segurança, rate limit e Self-Test',$allTables);
hub_check($checks,'Migration 004 é idempotente e não destrutiva',str_contains($migration,'20260712_004_vsm_security_selftest_recovery')&&str_contains($migration,'ON DUPLICATE KEY UPDATE')&&!preg_match('/\b(DROP|TRUNCATE|DELETE\s+FROM)\b/i',$migration));
hub_check($checks,'Migration 004 completa colunas de Self-Test e contrato VSM',str_contains($migration,"TABLE_NAME='selftest_relatorios' AND COLUMN_NAME='detalhes'")&&str_contains($migration,"TABLE_NAME='selftest_relatorios' AND COLUMN_NAME='trace_id'")&&str_contains($migration,"TABLE_NAME='vsm_endpoints' AND COLUMN_NAME='contract_verified'")&&str_contains($migration,"TABLE_NAME='vsm_endpoint_logs' AND COLUMN_NAME='modo_teste_seguro'"));
hub_check($checks,'Self-Test consulta apenas colunas existentes no schema atual',str_contains($controller,'SELECT id, status, resumo, detalhes, trace_id, criado_em FROM selftest_relatorios')&&!str_contains($controller,'SELECT id, status, score, resumo, criado_em FROM selftest_relatorios'));
hub_check($checks,'Enterprise Core cria e verifica VSM, segurança e Self-Test',str_contains($schemaService,"'security_events','ips_bloqueados','rate_limit_hits','selftest_relatorios'")&&str_contains($schemaService,"'vsm_endpoints','vsm_campos_mapeamento','vsm_endpoint_logs'")&&(str_contains($schemaService,'v104_48_1_database_recovery_004')||str_contains($schemaService,'v104_48_1_database_recovery_005')||str_contains($schemaService,'v104_48_1_schema_detection_r3')||str_contains($schemaService,'v104_49_2_enterprise_map_recovery_r4')));
hub_check($checks,'Mensagem operacional orienta a migration 004',str_contains($policy,'20260712_004_vsm_security_selftest_recovery')||str_contains($policy,'20260712_004'));
hub_check($checks,'Validar Banco cobre colunas VSM, segurança e Self-Test',str_contains($validation,"'ips_bloqueados'=>")&&str_contains($validation,"'vsm_endpoints'=>")&&str_contains($validation,"'selftest_relatorios'=>"));
hub_check($checks,'Best-effort limita repetição do mesmo erro entre requisições',str_contains($bestEffort,'shouldLog')&&str_contains($bestEffort,"storage/logs/.best-effort-rate")&&str_contains($bestEffort,'flock($fp, LOCK_EX)'));
hub_check($checks,'CI estático e MySQL real incluem migration 004',str_contains($staticCi,'20260712_004_vsm_security_selftest_recovery.sql')&&str_contains($runtimeCi,'$migration4')&&str_contains($runtimeCi,"migration='20260712_004_vsm_security_selftest_recovery'"));

require_once hub_root().'/app/Services/SensitiveDataService.php';
$masked=SensitiveDataService::maskJson('Aplique 20260712_001 e 20260712_004.');
hub_check($checks,'Redaction não transforma IDs de migration em telefone',$masked==='Aplique 20260712_001 e 20260712_004.',$masked);

hub_finish($checks);
