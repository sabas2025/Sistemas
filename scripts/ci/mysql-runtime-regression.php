<?php
declare(strict_types=1);
/**
 * Regressão destrutiva isolada para MySQL 8/MariaDB compatível.
 * Variáveis: HUB_TEST_DB_HOST, HUB_TEST_DB_PORT, HUB_TEST_DB_USER, HUB_TEST_DB_PASS, HUB_TEST_DB_NAME.
 * O nome precisa conter "test" ou "homolog"; exceção somente com HUB_ALLOW_DESTRUCTIVE_TEST=1.
 */
$drivers=PDO::getAvailableDrivers();
if(!in_array('mysql',$drivers,true)){fwrite(STDERR,"[SKIP] Driver pdo_mysql indisponível.\n");exit(2);}
$host=getenv('HUB_TEST_DB_HOST')?:'127.0.0.1';$port=(int)(getenv('HUB_TEST_DB_PORT')?:3306);
$user=getenv('HUB_TEST_DB_USER')?:'root';$pass=getenv('HUB_TEST_DB_PASS')?:'';$db=getenv('HUB_TEST_DB_NAME')?:'hub_v104_48_1_test';
if(!preg_match('/(test|homolog|ci)/i',$db)&&getenv('HUB_ALLOW_DESTRUCTIVE_TEST')!=='1'){fwrite(STDERR,"[FALHA] Banco recusado: use nome contendo test/homolog/ci.\n");exit(1);}
$root=dirname(__DIR__,2);
$source='consolidated';
$schema=(string)file_get_contents($root.'/database/install_final_current.sql');
$schema=renderInstallSql($schema,$db);
$migration1=(string)file_get_contents($root.'/database/migrations/20260712_001_queue_oauth_concurrency.sql');
$migration2=(string)file_get_contents($root.'/database/migrations/20260712_002_schema_runtime_vsm_contract.sql');
$migration3=(string)file_get_contents($root.'/database/migrations/20260712_003_missing_core_tables.sql');
$migration4=(string)file_get_contents($root.'/database/migrations/20260712_004_vsm_security_selftest_recovery.sql');
$migration7=(string)file_get_contents($root.'/database/migrations/20260713_007_enterprise_map_recovery.sql');
// Reauditoria 2026-09-14: migrations aplicam ALTER TABLE condicional via
// "SET @hub_sql:=IF(...,'ALTER TABLE ...','SELECT 1'); PREPARE hub_stmt FROM @hub_sql;
// EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;". MySQL trata EXECUTE de um prepared
// statement como uma chamada de procedure: além do result set do "SELECT 1" (quando a
// coluna já existe), o servidor envia um pacote de status final - um padrão de
// "multi-result" clássico. PDO::exec() não drena esse pacote extra sozinho em builds
// baseadas em libmysqlclient (comum em hospedagem cPanel/aaPanel; reproduzido em
// produção mesmo com MYSQL_ATTR_USE_BUFFERED_QUERY=>true, e com a suíte passando limpa
// no runner do GitHub Actions, que usa mysqlnd). O próximo exec() na mesma conexão
// falha então com "SQLSTATE[HY000]: General error: 2014 Cannot execute queries while
// other unbuffered queries are active". A correção robusta, independente de driver, é
// usar PDO::query() (que devolve um PDOStatement) e chamar closeCursor() explicitamente
// em cada statement para forçar o driver a esgotar qualquer result set pendente antes
// do próximo comando - ver execDrain() abaixo.
$admin=new PDO("mysql:host={$host};port={$port};charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::MYSQL_ATTR_USE_BUFFERED_QUERY=>true]);
$q='`'.str_replace('`','``',$db).'`';
$admin->exec("DROP DATABASE IF EXISTS {$q}");$admin->exec("CREATE DATABASE {$q} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try{
 $pdo=new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::MYSQL_ATTR_USE_BUFFERED_QUERY=>true]);
 foreach([$schema,$migration1,$migration2,$migration3,$migration4,$migration7,$migration1,$migration2,$migration3,$migration4,$migration7] as $batch){foreach(splitSql($batch) as $stmt){execDrain($pdo,$stmt);}}
 foreach([
   ['fila_integracao','locked_by'],['fila_integracao','lease_expires_at'],['fila_integracao','heartbeat_at'],
   ['configuracoes_integracao','queue_lease_minutes'],['configuracoes_integracao','queue_lease_by_type_json'],
   ['schema_migrations','checksum'],
   ['vsm_endpoints','contract_verified'],['vsm_endpoints','is_template'],['vsm_campos_mapeamento','contract_verified'],
   ['vsm_endpoint_logs','modo_teste_seguro'],['security_events','detalhe'],['ips_bloqueados','bloqueado_ate'],['rate_limit_hits','janela_inicio'],['selftest_relatorios','detalhes'],['selftest_relatorios','trace_id'],['configuracoes_integracao','produto_novo_aprovacao_modo'],['configuracoes_integracao','vsm_endpoint_pedido']
 ] as [$table,$column]){
   $s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$s->execute([$table,$column]);
   if((int)$s->fetchColumn()!==1)throw new RuntimeException("Coluna ausente: {$table}.{$column}");
 }
 foreach([
   ['schema_migrations','migration','varchar(180)',false,null],
   ['schema_migrations','version','varchar(40)',true,null],
   ['schema_migrations','description','text',true,null],
   ['schema_migrations','checksum','varchar(128)',true,null],
   ['schema_migrations','status',"enum('aplicada','falha')",true,'aplicada'],
   ['fila_integracao','idempotency_key','varchar(190)',true,null],
   ['fila_integracao','lease_expires_at','datetime',true,null],
   ['vsm_endpoints','modo_teste_seguro','tinyint',false,'1'],
   ['vsm_endpoints','origem','varchar(40)',false,'manual'],
   ['vsm_endpoints','contract_verified','tinyint',false,'0'],
   ['vsm_endpoints','is_template','tinyint',false,'0'],
   ['vsm_campos_mapeamento','origem','varchar(40)',false,'manual'],
   ['vsm_campos_mapeamento','contract_verified','tinyint',false,'0'],
   ['vsm_endpoint_logs','modo_teste_seguro','tinyint',false,'1']
 ] as [$table,$column,$type,$nullable,$default]){assertColumnContract($pdo,$table,$column,$type,$nullable,$default);}
 foreach([
   ['schema_migrations','idx_schema_migrations_status',['status'],false],
   ['schema_migrations','idx_schema_migrations_data',['aplicada_em'],false],
   ['fila_integracao','idx_fila_idempotency_key',['idempotency_key'],false],
   ['fila_integracao','idx_fila_status_proxima_prioridade',['status','proxima_tentativa','prioridade','id'],false],
   ['fila_integracao','idx_fila_locked',['locked_by','locked_at'],false],
   ['fila_integracao','idx_fila_lease',['status','lease_expires_at'],false],
   ['vsm_endpoints','idx_vsm_endpoints_categoria',['categoria'],false],
   ['vsm_campos_mapeamento','uk_vsm_campo',['categoria','campo_vsm','campo_hub'],true],
   ['vsm_endpoint_logs','idx_vsm_logs_criado',['criado_em'],false]
 ] as [$table,$index,$columns,$unique]){assertIndexContract($pdo,$table,$index,$columns,$unique);}
 $count=(int)$pdo->query("SELECT COUNT(*) FROM schema_migrations WHERE migration='20260712_001_queue_oauth_concurrency'")->fetchColumn();
 if($count!==1)throw new RuntimeException('Migration 001 não foi idempotente.');
 $count2=(int)$pdo->query("SELECT COUNT(*) FROM schema_migrations WHERE migration='20260712_002_schema_runtime_vsm_contract'")->fetchColumn();
 if($count2!==1)throw new RuntimeException('Migration 002 não foi idempotente.');
 $count3=(int)$pdo->query("SELECT COUNT(*) FROM schema_migrations WHERE migration='20260712_003_missing_core_tables'")->fetchColumn();
 if($count3!==1)throw new RuntimeException('Migration 003 não foi idempotente.');
 $count4=(int)$pdo->query("SELECT COUNT(*) FROM schema_migrations WHERE migration='20260712_004_vsm_security_selftest_recovery'")->fetchColumn();
 if($count4!==1)throw new RuntimeException('Migration 004 não foi idempotente.');
 $count7=(int)$pdo->query("SELECT COUNT(*) FROM schema_migrations WHERE migration='20260713_007_enterprise_map_recovery'")->fetchColumn();
 if($count7!==1)throw new RuntimeException('Migration 007 não foi idempotente.');
 foreach(['comercial_clientes_licencas','comercial_conectores_catalogo','comercial_cobranca_faturas','comercial_demo_ambientes','comercial_suporte_chamados','comercial_sla_eventos','enterprise_quality_gates','enterprise_regression_runs','enterprise_ui_preferences','integration_events','integration_idempotency','llm_approval_queue','llm_audit_logs','llm_policy_settings','llm_prompts','llm_usage_daily','observability_snapshots','worker_heartbeats','comercial_demo_reset_logs','comercial_license_remote_cache','tenant_scope_audit_snapshots','system_release_checks','comercial_billing_provider_events','comercial_billing_gateway_events','connector_operational_checks','comercial_license_checks','pedidos_hub','pedidos_payloads','pedidos_status_historico','pedidos_nfe_xml','security_events','ips_bloqueados','rate_limit_hits','vsm_endpoints','vsm_campos_mapeamento','vsm_endpoint_logs','selftest_relatorios'] as $requiredTable){
   $s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$s->execute([$requiredTable]);
   if((int)$s->fetchColumn()!==1)throw new RuntimeException("Tabela ausente: {$requiredTable}");
 }
 $pdo->exec("INSERT INTO selftest_relatorios(status,resumo,detalhes,trace_id) VALUES('ok','runtime','{}','TRC-RUNTIME')");
 $self=$pdo->query("SELECT id,status,resumo,detalhes,trace_id,criado_em FROM selftest_relatorios ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
 if(!$self||($self['trace_id']??'')!=='TRC-RUNTIME')throw new RuntimeException('Self-Test não está compatível com o schema atual.');
 $ownerA='worker-a';$ownerB='worker-b';
 $pdo->exec("INSERT INTO fila_integracao(tipo,payload,status,tentativas,prioridade,locked_by,locked_at,lease_expires_at,heartbeat_at,criado_em) VALUES('teste','{}','processando',0,'critica','{$ownerA}',NOW(),DATE_ADD(NOW(),INTERVAL 5 MINUTE),NOW(),NOW())");
 $id=(int)$pdo->lastInsertId();
 $st=$pdo->prepare("UPDATE fila_integracao SET status='sucesso' WHERE id=? AND status='processando' AND locked_by=?");$st->execute([$id,$ownerB]);if($st->rowCount()!==0)throw new RuntimeException('Worker sem posse conseguiu concluir item.');
 $st->execute([$id,$ownerA]);if($st->rowCount()!==1)throw new RuntimeException('Worker proprietário não conseguiu concluir item.');
 echo "[OK] Runtime {$source}: instalação, contratos, migrations 001/002/003/004/007 repetidas e posse de fila validados em {$db}.\n";
}finally{$admin->exec("DROP DATABASE IF EXISTS {$q}");}

/** Executa e drena qualquer result set pendente (ver nota acima sobre EXECUTE de prepared statement). */
function execDrain(PDO $pdo,string $stmt): void{
 $result=$pdo->query($stmt);
 if($result instanceof PDOStatement)$result->closeCursor();
}

function renderInstallSql(string $sql,string $db): string{
 $values=[
  '{DB_NAME}'=>str_replace('`','``',$db),'{ADMIN_NOME}'=>'CI Admin','{ADMIN_EMAIL}'=>'ci@example.invalid',
  '{ADMIN_HASH}'=>password_hash('CI-only-password-2026',PASSWORD_DEFAULT),'{AMBIENTE}'=>'homologacao',
  '{TINY_VERSION}'=>'v2','{TINY_V2_URL}'=>'https://api.tiny.com.br','{TINY_V2_TOKEN}'=>'',
  '{TINY_V3_URL}'=>'https://api.tiny.com.br/public-api/v3','{TINY_V3_TOKEN}'=>'',
  '{VSM_URL}'=>'https://vsm.example.invalid','{VSM_TOKEN}'=>'','{WEBHOOK_SECRET}'=>'ci-webhook-secret'
 ];
 return strtr($sql,$values);
}

function assertColumnContract(PDO $pdo,string $table,string $column,string $expectedType,bool $nullable,?string $default): void{
 $st=$pdo->prepare('SELECT COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
 $st->execute([$table,$column]);$row=$st->fetch(PDO::FETCH_ASSOC);
 if(!$row)throw new RuntimeException("Metadado ausente: {$table}.{$column}");
 $actualType=strtolower((string)$row['COLUMN_TYPE']);
 $actualType=preg_replace('/^(tinyint|smallint|mediumint|int|bigint)\(\d+\)/','$1',$actualType)??$actualType;
 if($actualType!==strtolower($expectedType))throw new RuntimeException("Tipo incompatível em {$table}.{$column}: {$actualType}");
 if(((string)$row['IS_NULLABLE']==='YES')!==$nullable)throw new RuntimeException("Nulabilidade incompatível em {$table}.{$column}");
 $actualDefault=normalizeColumnDefault($pdo,$row['COLUMN_DEFAULT']);
 if($default===null?$actualDefault!==null:strcasecmp((string)$actualDefault,$default)!==0)throw new RuntimeException("Default incompatível em {$table}.{$column}");
}

/**
 * Reauditoria 2026-09-14 (achado ao testar em MariaDB 10.11 real, aaPanel):
 * MariaDB >= 10.2.7 devolve information_schema.COLUMNS.COLUMN_DEFAULT como EXPRESSÃO SQL,
 * enquanto o MySQL 8 devolve o valor cru. Na prática:
 *   coluna sem DEFAULT  -> MariaDB: texto "NULL"   | MySQL: NULL de verdade
 *   DEFAULT 'aplicada'  -> MariaDB: "'aplicada'"   | MySQL: "aplicada"
 * (confirmado no servidor: "SELECT COLUMN_DEFAULT IS NULL" retornou 0 para coluna sem default).
 * O texto "NULL" só é tratado como ausência de default em MariaDB: no MySQL esse mesmo
 * valor significaria um DEFAULT 'NULL' literal, e convertê-lo mascararia divergência real.
 */
function normalizeColumnDefault(PDO $pdo,$raw): ?string{
 if($raw===null)return null;
 $value=(string)$raw;
 if(strlen($value)>=2&&$value[0]==="'"&&substr($value,-1)==="'"){
   return str_replace(["''","\\'"],["'","'"],substr($value,1,-1));
 }
 $isMariaDb=stripos((string)$pdo->getAttribute(PDO::ATTR_SERVER_VERSION),'mariadb')!==false;
 if($isMariaDb&&strcasecmp($value,'NULL')===0)return null;
 return $value;
}

/** @param list<string> $expectedColumns */
function assertIndexContract(PDO $pdo,string $table,string $index,array $expectedColumns,bool $unique): void{
 $st=$pdo->prepare('SELECT COLUMN_NAME,NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=? ORDER BY SEQ_IN_INDEX');
 $st->execute([$table,$index]);$rows=$st->fetchAll(PDO::FETCH_ASSOC);
 if(!$rows)throw new RuntimeException("Índice ausente: {$table}.{$index}");
 $columns=array_map(static fn(array $row):string=>(string)$row['COLUMN_NAME'],$rows);
 if($columns!==$expectedColumns)throw new RuntimeException("Colunas incompatíveis no índice {$table}.{$index}");
 if((((int)$rows[0]['NON_UNIQUE'])===0)!==$unique)throw new RuntimeException("Unicidade incompatível no índice {$table}.{$index}");
}

function splitSql(string $sql): array{
 $sql=preg_replace('/^\s*(--|#).*$/m','',$sql)??$sql;$out=[];$buf='';$quote='';$len=strlen($sql);
 for($i=0;$i<$len;$i++){$c=$sql[$i];
   if($quote!==''&&$c===$quote&&$i+1<$len&&$sql[$i+1]===$quote){$buf.=$c.$sql[++$i];continue;}
   if(($c==="'"||$c==='"')&&($i===0||$sql[$i-1]!=='\\')){$quote=$quote===''?$c:($quote===$c?'':$quote);} 
   if($c===';'&&$quote===''){if(trim($buf)!=='')$out[]=trim($buf);$buf='';continue;}$buf.=$c;
 }
 if(trim($buf)!=='')$out[]=trim($buf);return $out;
}
