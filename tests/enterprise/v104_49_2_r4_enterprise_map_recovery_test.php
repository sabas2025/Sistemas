<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks=[];
$migration=hub_read('database/migrations/20260713_007_enterprise_map_recovery.sql');
$schema=hub_read('app/Services/SchemaMigrationService.php');
$map=hub_read('app/Services/DatabaseMapService.php');
$db=hub_read('app/Core/Database.php');
$controller=hub_read('app/Controllers/DatabaseMaintenanceController.php');
$view=hub_read('views/mapa_banco.php');
$policy=hub_read('app/Services/SchemaRuntimePolicyService.php');
$version=hub_read('app/Services/SystemVersionService.php');
$staticCi=hub_read('scripts/ci/mysql-schema-static-check.php');
$runtimeCi=hub_read('scripts/ci/mysql-runtime-regression.php');
$tables=[
  'enterprise_quality_gates','enterprise_regression_runs','enterprise_ui_preferences',
  'integration_events','integration_idempotency','llm_approval_queue','llm_audit_logs',
  'llm_policy_settings','llm_prompts','llm_usage_daily','observability_snapshots','worker_heartbeats'
];
$missing=[];
foreach($tables as $table){ if(!str_contains($migration,'CREATE TABLE IF NOT EXISTS '.$table))$missing[]=$table; }
hub_check($checks,'Migration 007 contém as 12 tabelas Enterprise',count($missing)===0,implode(', ',$missing));
hub_check($checks,'Migration 007 é idempotente e não destrutiva',str_contains($migration,'20260713_007_enterprise_map_recovery')&&str_contains($migration,'ON DUPLICATE KEY UPDATE')&&!preg_match('/\b(DROP|TRUNCATE|DELETE\s+FROM)\b/i',$migration));
hub_check($checks,'Reparo focado existe e verifica na mesma conexão',str_contains($schema,'applyEnterpriseTableRecovery')&&str_contains($schema,'enterpriseRecoveryTables')&&str_contains($schema,'Database::inspectTableOn($pdo, $table)'));
hub_check($checks,'Enterprise Core prioriza as tabelas reportadas',str_contains($schema,"self::enterpriseRecoveryTables()))"));
hub_check($checks,'Mapa diferencia existência de metadados de colunas',str_contains($map,'Database::inspectTableOn($pdo, $table)')&&str_contains($map,'columnsForTable'));
hub_check($checks,'Mapa oferece ação dedicada protegida por POST/CSRF',str_contains($view,'apply_enterprise_tables')&&str_contains($view,'Corrigir tabelas Enterprise')&&str_contains($controller,"['apply_enterprise_core','apply_enterprise_tables']"));
hub_check($checks,'Diagnóstico não confunde permissão com tabela ausente',str_contains($db,'inspectTableOn')&&str_contains($db,'Permissão MySQL insuficiente')&&str_contains($db,"[1044,1045,1142,1143,1227]"));
hub_check($checks,'Índice usa INFORMATION_SCHEMA com fallback seguro',str_contains($db,'INFORMATION_SCHEMA.STATISTICS')&&!str_contains($db,"SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?"));
$moduleSql=hub_read('database/modules/core.sql').hub_read('database/modules/fila.sql').hub_read('database/modules/observabilidade.sql');
$missingModules=[];foreach($tables as $table){if(!str_contains($moduleSql,'CREATE TABLE IF NOT EXISTS '.$table))$missingModules[]=$table;}
hub_check($checks,'SQLs modulares contêm as 12 tabelas',count($missingModules)===0,implode(', ',$missingModules));
hub_check($checks,'Mensagens operacionais orientam a migration 007',str_contains($policy,'20260713_007')&&str_contains($version,'20260713_007_enterprise_map_recovery.sql'));
hub_check($checks,'CI estático e MySQL real incluem a migration 007',str_contains($staticCi,'20260713_007_enterprise_map_recovery.sql')&&str_contains($runtimeCi,'$migration7')&&str_contains($runtimeCi,'Migration 007 não foi idempotente'));
hub_finish($checks);
