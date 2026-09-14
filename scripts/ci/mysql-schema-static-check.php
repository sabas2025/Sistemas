<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);
$schema=(string)@file_get_contents($root.'/database/install_final_current.sql');
$migrationFiles=[$root.'/database/migrations/20260712_001_queue_oauth_concurrency.sql',$root.'/database/migrations/20260712_002_schema_runtime_vsm_contract.sql',$root.'/database/migrations/20260712_003_missing_core_tables.sql',$root.'/database/migrations/20260712_004_vsm_security_selftest_recovery.sql',$root.'/database/migrations/20260713_007_enterprise_map_recovery.sql']; $migration=''; foreach($migrationFiles as $file){ $migration.=(string)@file_get_contents($file)."\n"; }
$errors=[];
if($schema==='')$errors[]='Schema oficial ausente/vazio.';
if($migration==='')$errors[]='Migrations históricas obrigatórias da V104.49.3-R5 ausentes/vazias.';

preg_match_all('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?([A-Za-z0-9_]+)`?\s*\((.*?)\)\s*ENGINE=/is',$schema,$matches,PREG_SET_ORDER);
$tables=[];
foreach($matches as $m){$name=strtolower($m[1]);if(isset($tables[$name]))$errors[]="Tabela duplicada no schema: {$name}";$tables[$name]=$m[2];}
$required=[
 'schema_migrations'=>['migration','version','description','checksum','status','mensagem','aplicada_em'],
 'fila_integracao'=>['status','tentativas','idempotency_key','locked_by','locked_at','lease_expires_at','heartbeat_at'],
 'fila_reprocessamento_historico'=>['fila_id','status_anterior','tentativas_anteriores','locked_by_anterior','solicitado_por','trace_id'],
 'configuracoes_integracao'=>['queue_processing_timeout_minutes','queue_lease_minutes','queue_lease_by_type_json','vsm_ambiente','vsm_producao_liberada','produto_novo_aprovacao_modo','pedido_tiny_vsm_validacao_obrigatoria'],
 'vsm_endpoints'=>['modo_teste_seguro','origem','contract_verified','contract_source','contract_checked_at','is_template'],
 'vsm_campos_mapeamento'=>['tipo_dado','valor_padrao','regra_validacao','exemplo_payload','origem','contract_verified','contract_source','is_template'],
 'vsm_endpoint_logs'=>['modo_teste_seguro'],
 'security_events'=>['trace_id','tipo','severidade','ip','usuario_id','rota','metodo','user_agent','detalhe','contexto','created_at'],
 'ips_bloqueados'=>['ip','motivo','severidade','bloqueado_ate','ativo','created_at','updated_at'],
 'rate_limit_hits'=>['ip','usuario_id','rota','metodo','janela_inicio','hits','updated_at'],
 'selftest_relatorios'=>['status','resumo','detalhes','trace_id','criado_em'],
];
foreach($required as $table=>$columns){
 if(!isset($tables[$table])){$errors[]="Tabela obrigatória ausente: {$table}";continue;}
 foreach($columns as $column){if(!preg_match('/`?'.preg_quote($column,'/').'`?\s+/i',$tables[$table]))$errors[]="Coluna ausente: {$table}.{$column}";}
}
foreach(['idx_fila_idempotency_key','idx_fila_status_proxima_prioridade','idx_fila_locked','idx_fila_lease'] as $idx){if(!str_contains($schema,$idx)&&!str_contains($migration,$idx))$errors[]="Índice obrigatório ausente: {$idx}";}
if(preg_match('/\bADD\s+COLUMN\s+IF\s+NOT\s+EXISTS\b/i',$migration))$errors[]='Migration usa ADD COLUMN IF NOT EXISTS, incompatível com parte dos ambientes suportados.';
if(preg_match('/\bCREATE\s+(?:UNIQUE\s+)?INDEX\s+IF\s+NOT\s+EXISTS\b/i',$migration))$errors[]='Migration usa CREATE INDEX IF NOT EXISTS.';
$prepare=preg_match_all('/\bPREPARE\s+hub_stmt\s+FROM\b/i',$migration);
$execute=preg_match_all('/\bEXECUTE\s+hub_stmt\b/i',$migration);
$deallocate=preg_match_all('/\bDEALLOCATE\s+PREPARE\s+hub_stmt\b/i',$migration);
if($prepare!==$execute||$prepare!==$deallocate)$errors[]="Blocos PREPARE inconsistentes: prepare={$prepare}, execute={$execute}, deallocate={$deallocate}.";
foreach(['version','description','checksum','status','mensagem','aplicada_em'] as $column){if(!str_contains($migration,"COLUMN_NAME='{$column}'"))$errors[]="Compatibilidade schema_migrations não verifica {$column}.";}
if(substr_count($migration,"ON DUPLICATE KEY UPDATE")<4)$errors[]='Registros das migrations obrigatórias da R5 não são idempotentes.';
foreach(['20260712_001_queue_oauth_concurrency','20260712_002_schema_runtime_vsm_contract','20260712_003_missing_core_tables','20260712_004_vsm_security_selftest_recovery','20260713_007_enterprise_map_recovery'] as $id){if(!str_contains($migration,$id))$errors[]="Migration obrigatória ausente: {$id}.";}
foreach(['comercial_demo_reset_logs','comercial_license_remote_cache','tenant_scope_audit_snapshots','system_release_checks','comercial_billing_provider_events','comercial_billing_gateway_events','connector_operational_checks','comercial_license_checks','pedidos_hub','pedidos_payloads','pedidos_status_historico','pedidos_nfe_xml'] as $table){if(!str_contains($migration,'CREATE TABLE IF NOT EXISTS '.$table))$errors[]="Migration 002 não garante tabela operacional: {$table}.";}

foreach(['comercial_clientes_licencas','comercial_conectores_catalogo','comercial_cobranca_faturas','comercial_demo_ambientes','comercial_suporte_chamados','comercial_sla_eventos','enterprise_quality_gates','enterprise_regression_runs','enterprise_ui_preferences','integration_events','integration_idempotency','llm_approval_queue','llm_audit_logs','llm_policy_settings','llm_prompts','llm_usage_daily','observability_snapshots','worker_heartbeats'] as $table){
  if(!str_contains($migration,'CREATE TABLE IF NOT EXISTS '.$table))$errors[]="Migration 003 não garante tabela ausente: {$table}.";
}
foreach(['contract_verified','is_template','produto_novo_aprovacao_modo','vsm_producao_liberada'] as $column){if(!str_contains($migration,"COLUMN_NAME='{$column}'"))$errors[]="Migration 002 não verifica coluna {$column}.";}


foreach(['enterprise_quality_gates','enterprise_regression_runs','enterprise_ui_preferences','integration_events','integration_idempotency','llm_approval_queue','llm_audit_logs','llm_policy_settings','llm_prompts','llm_usage_daily','observability_snapshots','worker_heartbeats'] as $table){
  if(!str_contains($migration,'CREATE TABLE IF NOT EXISTS '.$table))$errors[]="Migration 007 não garante tabela Enterprise: {$table}.";
}
foreach(['security_events','ips_bloqueados','rate_limit_hits','vsm_endpoints','vsm_campos_mapeamento','vsm_endpoint_logs','selftest_relatorios'] as $table){
  if(!str_contains($migration,'CREATE TABLE IF NOT EXISTS '.$table))$errors[]="Migration 004 não garante tabela de recuperação: {$table}.";
}
foreach(['selftest_relatorios.resumo','selftest_relatorios.detalhes','selftest_relatorios.trace_id','vsm_endpoints.contract_verified','vsm_endpoint_logs.modo_teste_seguro'] as $qualified){
  [$table,$column]=explode('.',$qualified,2);
  if(!str_contains($migration,"TABLE_NAME='{$table}' AND COLUMN_NAME='{$column}'"))$errors[]="Migration 004 não verifica coluna {$qualified}.";
}

if($errors){foreach($errors as $e)fwrite(STDERR,"[FALHA] {$e}\n");exit(1);} 
echo '[OK] Schema estático: '.count($tables)." tabelas, migration idempotente e colunas/índices críticos presentes.\n";
