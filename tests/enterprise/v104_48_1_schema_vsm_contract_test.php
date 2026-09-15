<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks=[];

$migration=hub_read('database/migrations/20260712_002_schema_runtime_vsm_contract.sql');
$schema=hub_read('database/install_final_current.sql');
$vsm=hub_read('app/Services/VsmEndpointService.php');
$view=hub_read('views/vsm_endpoints.php');
$runtimeCi=hub_read('scripts/ci/schema-runtime-ddl-check.php');
$mysqlCi=hub_read('scripts/ci/mysql-runtime-regression.php');
$migrationController=hub_read('app/Controllers/MigrationController.php');
$schemaService=hub_read('app/Services/SchemaMigrationService.php');
$dashboard=hub_read('app/Controllers/DashboardController.php');

$tables=[
  'comercial_demo_reset_logs','comercial_license_remote_cache','tenant_scope_audit_snapshots','system_release_checks',
  'comercial_billing_provider_events','comercial_billing_gateway_events','connector_operational_checks','comercial_license_checks',
  'pedidos_hub','pedidos_payloads','pedidos_status_historico','pedidos_nfe_xml'
];
$allTables=true;
foreach($tables as $table){
  if(!preg_match('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?'.preg_quote($table,'/').'`?/i',$schema) ||
     !preg_match('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?'.preg_quote($table,'/').'`?/i',$migration)){$allTables=false;break;}
}
hub_check($checks,'Schema consolidado e migration 002 contêm todas as tabelas antes criadas em runtime',$allTables);

$contractColumns=['contract_verified','contract_source','contract_checked_at','is_template','modo_teste_seguro'];
$allColumns=true;foreach($contractColumns as $column){if(!str_contains($schema,$column)||!str_contains($migration,$column)){$allColumns=false;break;}}
hub_check($checks,'Metadados de contrato VSM existem no schema e na migration',$allColumns);

// Reauditoria 2026-09-14: o seed do catálogo modelo foi movido de VsmEndpointService.php
// para o schema SQL (database/install_final_current.sql / database/modules/core.sql) em
// versão anterior e este teste não foi atualizado - ficava sempre falso (falso negativo),
// e como scripts/ci/enterprise-tests.sh usa "set -e", isso abortava a suíte e pulava em
// silêncio todos os testes seguintes em ordem alfabética. Corrigido para checar o seed
// onde ele de fato está hoje; a validação do guard em runtime e do badge na view continua.
$templateSafe=str_contains($schema,"INSERT IGNORE INTO vsm_endpoints")&&
  str_contains($schema,"'template_local',0,NULL,1,'MODELO NÃO VERIFICADO")&&
  str_contains($vsm,"Endpoint é apenas um modelo local não verificado")&&str_contains($view,'Modelo não verificado');
hub_check($checks,'Catálogo VSM local nasce desativado e não verificado',$templateSafe);

$curlSafe=str_contains($vsm,'CURLOPT_FOLLOWLOCATION=>false')&&str_contains($vsm,'CURLOPT_MAXREDIRS=>0')&&
  str_contains($vsm,'CURLOPT_PROTOCOLS')&&str_contains($vsm,'CURLOPT_RESOLVE')&&str_contains($vsm,'validateBaseUrl');
hub_check($checks,'Teste de endpoint VSM aplica SSRF, sem redirects e com DNS validado',$curlSafe);

hub_check($checks,'CI de DDL detecta SQL literal e helpers indiretos',str_contains($runtimeCi,'ensureTableFromSql')&&str_contains($runtimeCi,'addColumnIfMissing')&&str_contains($runtimeCi,'file_get_contents'));
hub_check($checks,'Regressão MySQL aplica migrations 001, 002, 003, 004 e 007 duas vezes',str_contains($mysqlCi,'$migration1')&&str_contains($mysqlCi,'$migration2')&&str_contains($mysqlCi,'$migration3')&&str_contains($mysqlCi,'$migration4')&&str_contains($mysqlCi,'$migration1,$migration2,$migration3,$migration4,$migration7,$migration1,$migration2,$migration3,$migration4,$migration7'));
hub_check($checks,'Migration controller aceita padrão YYYYMMDD_NNN',str_contains($migrationController,'\\d{8}_\\d{3}'));

$serviceComplete=true;foreach(array_merge($tables,['vsm_endpoints','vsm_campos_mapeamento','vsm_endpoint_logs']) as $table){if(!str_contains($schemaService,"'{$table}'")){$serviceComplete=false;break;}}
hub_check($checks,'Reparo assistido Enterprise Core cobre tabelas da migration 002',$serviceComplete);

$delegated=str_contains($dashboard,"(new OrquestracaoController())->dispatch(\$page)")&&!preg_match('/private\s+function\s+(orquestracaoIntegracoes|salvarOrquestracaoIntegracoes|testarOrquestracaoFluxo)\s*\(/',$dashboard);
hub_check($checks,'Dashboard delega orquestração e não mantém implementação duplicada',$delegated);

hub_finish($checks);
