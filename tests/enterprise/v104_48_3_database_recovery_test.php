<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks=[];

$migration=hub_read('database/migrations/20260712_003_missing_core_tables.sql');
$schemaService=hub_read('app/Services/SchemaMigrationService.php');
$validation=hub_read('app/Services/DatabaseValidationService.php');
$controller=hub_read('app/Controllers/DatabaseMaintenanceController.php');
$view=hub_read('views/mapa_banco.php');
$license=hub_read('app/Services/LicenseEnforcementService.php');
$policy=hub_read('app/Services/SchemaRuntimePolicyService.php');
$mysqlStatic=hub_read('scripts/ci/mysql-schema-static-check.php');
$mysqlRuntime=hub_read('scripts/ci/mysql-runtime-regression.php');
$installer=hub_read('public/install.php');
$configExample=hub_read('config/config.example.php');
$routeLimiter=hub_read('app/Services/RouteRateLimiterService.php');

$missingTables=[
  'comercial_clientes_licencas','comercial_conectores_catalogo','comercial_cobranca_faturas','comercial_demo_ambientes','comercial_suporte_chamados','comercial_sla_eventos',
  'enterprise_quality_gates','enterprise_regression_runs','enterprise_ui_preferences','integration_events','integration_idempotency','llm_approval_queue','llm_audit_logs','llm_policy_settings','llm_prompts','llm_usage_daily','observability_snapshots','worker_heartbeats'
];
$allInMigration=$migration!=='';
$allInService=true;
foreach($missingTables as $table){
  if(!preg_match('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?'.preg_quote($table,'/').'`?/i',$migration))$allInMigration=false;
  if(!str_contains($schemaService,"'{$table}'"))$allInService=false;
}
hub_check($checks,'Migration 003 cria as 18 tabelas reportadas como ausentes',$allInMigration);
hub_check($checks,'Enterprise Core inclui e verifica as 18 tabelas ausentes',$allInService && str_contains($schemaService,'requiredTables()') && str_contains($schemaService,"'verification'"));
hub_check($checks,'Migration 003 registra execução de forma idempotente',str_contains($migration,'20260712_003_missing_core_tables')&&str_contains($migration,'ON DUPLICATE KEY UPDATE'));
hub_check($checks,'Migration 003 suporta schema_migrations legado',str_contains($migration,"COLUMN_NAME='version'")&&str_contains($migration,"COLUMN_NAME='checksum'")&&str_contains($migration,'PREPARE hub_stmt'));
hub_check($checks,'Reparo manual chama Enterprise Core antes da validação',str_contains($validation,'SchemaMigrationService::applyEnterpriseCore(false)')&&(str_contains($validation,'Enterprise Core / migration 004')||str_contains($validation,'Enterprise Core / migrations 004/007')));
hub_check($checks,'Mapa do Banco possui reparo POST protegido por CSRF',str_contains($controller,"apply_enterprise_core")&&str_contains($controller,'Csrf::validate()')&&str_contains($view,'Aplicar tabelas e migrations pendentes'));
$posBypass=strpos($license,"if (in_array(\$page, self::\$technicalBypass, true)) return;");
$posStatus=strpos($license,'$status = self::status();');
hub_check($checks,'Rotas técnicas ignoram licenciamento antes de consultar schema comercial',$posBypass!==false&&$posStatus!==false&&$posBypass<$posStatus);
hub_check($checks,'Mensagem operacional orienta migrations 001/002/003/004',str_contains($policy,'20260712_003')&&str_contains($policy,'20260712_004'));
hub_check($checks,'CI estático e MySQL real incluem migration 003',str_contains($mysqlStatic,'20260712_003_missing_core_tables.sql')&&str_contains($mysqlRuntime,'$migration3')&&str_contains($mysqlRuntime,"migration='20260712_003_missing_core_tables'"));
hub_check($checks,'Rotas de reparo entram no WAF e rate limit sensível',str_contains($installer,'enterprise-core-aplicar')&&str_contains($configExample,'migracao-aplicar')&&str_contains($routeLimiter,"'enterprise-core-aplicar'"));

hub_finish($checks);
