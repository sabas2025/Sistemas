<?php
declare(strict_types=1);

/**
 * Reproduz o caminho modular real do install.php: cada módulo é aplicado e
 * validado em seu próprio banco. Usa apenas bancos CI descartáveis.
 */
if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "[SKIP] Driver pdo_mysql indisponível.\n");
    exit(2);
}
$host=getenv('HUB_TEST_DB_HOST')?:'127.0.0.1';
$port=(int)(getenv('HUB_TEST_DB_PORT')?:3306);
$user=getenv('HUB_TEST_DB_USER')?:'root';
$pass=getenv('HUB_TEST_DB_PASS')?:'';
$base=getenv('HUB_TEST_DB_NAME')?:'hub_ci_modular';
if (!preg_match('/(test|homolog|ci)/i', $base) || !preg_match('/^[A-Za-z0-9_]+$/', $base)) {
    fwrite(STDERR, "[FALHA] Base modular recusada: use nome seguro contendo test/homolog/ci.\n");
    exit(1);
}

$root=dirname(__DIR__,2);
require_once $root.'/app/Services/InstallDatabaseProbe.php';
$modules=['core','pedidos','produtos','estoque','fiscal','fila','observabilidade','backups'];
// Reauditoria 2026-09-14: MYSQL_ATTR_USE_BUFFERED_QUERY=>true evita "SQLSTATE[HY000]:
// General error: 2014 Cannot execute queries while other unbuffered queries are active"
// em builds de pdo_mysql que não bufferizam por padrão - ver nota equivalente em
// mysql-runtime-regression.php.
$admin=new PDO("mysql:host={$host};port={$port};charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::MYSQL_ATTR_USE_BUFFERED_QUERY=>true]);
$databases=[];
try {
    foreach ($modules as $module) {
        $database=$base.'_'.$module;
        $databases[]=$database;
        $quoted=quoteIdentifier($database);
        $admin->exec("DROP DATABASE IF EXISTS {$quoted}");
        $admin->exec("CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $path=$root.'/database/modules/'.$module.'.sql';
        $sql=is_file($path)?(string)file_get_contents($path):'';
        if ($sql==='') throw new RuntimeException("SQL modular ausente ou vazio: {$module}.sql");
        $runtimeSql=$module==='core'?withoutAdminSeed($sql):$sql;
        $runtimeSql=renderModuleSql($runtimeSql);
        $pdo=new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::MYSQL_ATTR_USE_BUFFERED_QUERY=>true]);
        if ($module==='core') {
            $pdo->exec('CREATE TABLE `_hub_install_permission_test` (`id` INT NOT NULL PRIMARY KEY, `marker` VARCHAR(40) NOT NULL) ENGINE=InnoDB');
            $pdo->exec("INSERT INTO `_hub_install_permission_test` (`id`,`marker`) VALUES (1,'persistente-preservada')");
            InstallDatabaseProbe::assertDdlPrivileges($pdo);
            if ((string)$pdo->query('SELECT marker FROM `_hub_install_permission_test` WHERE id=1')->fetchColumn()!=='persistente-preservada') throw new RuntimeException('Sonda DDL alterou ou removeu tabela persistente preexistente.');
            $pdo->exec('DROP TABLE `_hub_install_permission_test`');
        }
        // execDrain() em vez de exec() puro: ver nota em mysql-runtime-regression.php sobre
        // EXECUTE de prepared statement dinâmico deixando result set pendente na conexão.
        foreach (splitSql($runtimeSql) as $statement) execDrain($pdo,$statement);
        if ($module==='core') {
            installAdminUser($pdo,'CI Admin','ci@example.invalid',password_hash('CI-only-password-2026',PASSWORD_DEFAULT));
            $pdo->exec("UPDATE usuarios SET nome='CI Sentinel' WHERE email='ci@example.invalid'");
            $pdo->exec("UPDATE configuracoes_integracao SET vsm_ambiente='producao' WHERE id=1");
        }
        // execDrain() em vez de exec() puro: ver nota em mysql-runtime-regression.php sobre
        // EXECUTE de prepared statement dinâmico deixando result set pendente na conexão.
        foreach (splitSql($runtimeSql) as $statement) execDrain($pdo,$statement);
        if ($module==='core') {
            if ((string)$pdo->query("SELECT nome FROM usuarios WHERE email='ci@example.invalid'")->fetchColumn()!=='CI Sentinel') throw new RuntimeException('Reaplicação modular sobrescreveu o administrador preparado.');
            if ((string)$pdo->query('SELECT vsm_ambiente FROM configuracoes_integracao WHERE id=1')->fetchColumn()!=='producao') throw new RuntimeException('Reaplicação modular sobrescreveu configuração operacional não pertencente ao seed.');
        }
        assertModuleTables($pdo,$sql,$module);
    }

    $core=modulePdo($host,$port,$user,$pass,$base,'core');
    assertColumnContract($core,'schema_migrations','migration','varchar(180)',false,null);
    assertColumnContract($core,'schema_migrations','version','varchar(40)',true,null);
    assertColumnContract($core,'schema_migrations','description','text',true,null);
    assertColumnContract($core,'configuracoes_integracao','vsm_ambiente','varchar(30)',true,'homologacao');
    assertColumnContract($core,'configuracoes_integracao','vsm_producao_liberada','tinyint',true,'0');
    assertColumnContract($core,'configuracoes_integracao','vsm_ultimo_teste_ok','tinyint',true,'0');
    assertColumnContract($core,'configuracoes_integracao','vsm_ultimo_teste_em','datetime',true,null);
    assertColumnContract($core,'configuracoes_integracao','vsm_host_producao_liberado','varchar(255)',true,null);
    assertColumnContract($core,'configuracoes_integracao','vsm_producao_liberada_em','datetime',true,null);
    assertColumnContract($core,'configuracoes_integracao','vsm_producao_liberada_por','int',true,null);
    assertColumnContract($core,'vsm_endpoints','modo_teste_seguro','tinyint',false,'1');
    assertColumnContract($core,'vsm_endpoints','origem','varchar(40)',false,'manual');
    assertColumnContract($core,'vsm_endpoints','contract_verified','tinyint',false,'0');
    assertColumnContract($core,'vsm_endpoints','contract_source','varchar(255)',true,null);
    assertColumnContract($core,'vsm_endpoints','contract_checked_at','datetime',true,null);
    assertColumnContract($core,'vsm_endpoints','is_template','tinyint',false,'0');
    assertColumnContract($core,'vsm_campos_mapeamento','tipo_dado','varchar(50)',true,null);
    assertColumnContract($core,'vsm_campos_mapeamento','valor_padrao','varchar(255)',true,null);
    assertColumnContract($core,'vsm_campos_mapeamento','regra_validacao','varchar(255)',true,null);
    assertColumnContract($core,'vsm_campos_mapeamento','exemplo_payload','text',true,null);
    assertColumnContract($core,'vsm_campos_mapeamento','origem','varchar(40)',false,'manual');
    assertColumnContract($core,'vsm_campos_mapeamento','contract_verified','tinyint',false,'0');
    assertColumnContract($core,'vsm_campos_mapeamento','contract_source','varchar(255)',true,null);
    assertColumnContract($core,'vsm_campos_mapeamento','is_template','tinyint',false,'0');
    assertIndexContract($core,'vsm_endpoints','idx_vsm_endpoints_categoria',['categoria'],false);
    assertIndexContract($core,'vsm_campos_mapeamento','uk_vsm_campo',['categoria','campo_vsm','campo_hub'],true);
    if ((int)$core->query("SELECT COUNT(*) FROM usuarios WHERE email='ci@example.invalid'")->fetchColumn()!==1) throw new RuntimeException('Administrador preparado modular ausente ou duplicado.');
    if ((int)$core->query('SELECT COUNT(*) FROM configuracoes_integracao WHERE id=1')->fetchColumn()!==1) throw new RuntimeException('Seed de configuração modular ausente ou duplicado.');

    $fila=modulePdo($host,$port,$user,$pass,$base,'fila');
    assertColumnContract($fila,'fila_integracao','idempotency_key','varchar(190)',true,null);
    assertColumnContract($fila,'fila_integracao','lease_expires_at','datetime',true,null);
    assertIndexContract($fila,'fila_integracao','idx_fila_status_proxima_prioridade',['status','proxima_tentativa','prioridade','id'],false);
    assertIndexContract($fila,'fila_integracao','idx_fila_locked',['locked_by','locked_at'],false);
    assertIndexContract($fila,'fila_integracao','idx_fila_lease',['status','lease_expires_at'],false);

    $observability=modulePdo($host,$port,$user,$pass,$base,'observabilidade');
    assertColumnContract($observability,'vsm_endpoint_logs','modo_teste_seguro','tinyint',false,'1');
    assertIndexContract($observability,'vsm_endpoint_logs','idx_vsm_logs_criado',['criado_em'],false);

    echo '[OK] Runtime modular: 8 módulos instalados duas vezes em 8 bancos, com inventário, 24 colunas-alvo, índices, seeds preparados e sentinelas preservados.'.PHP_EOL;
} finally {
    foreach (array_reverse($databases) as $database) {
        try { $admin->exec('DROP DATABASE IF EXISTS '.quoteIdentifier($database)); }
        catch (Throwable $ignored) {}
    }
}

function modulePdo(string $host,int $port,string $user,string $pass,string $base,string $module): PDO {
    return new PDO("mysql:host={$host};port={$port};dbname={$base}_{$module};charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::MYSQL_ATTR_USE_BUFFERED_QUERY=>true]);
}

/** Executa e drena qualquer result set pendente (ver nota em mysql-runtime-regression.php). */
function execDrain(PDO $pdo,string $stmt): void {
    $result=$pdo->query($stmt);
    if ($result instanceof PDOStatement) $result->closeCursor();
}

function quoteIdentifier(string $identifier): string {
    return '`'.str_replace('`','``',$identifier).'`';
}

function renderModuleSql(string $sql): string {
    return strtr($sql,[
        '{ADMIN_NOME}'=>'CI Admin','{ADMIN_EMAIL}'=>'ci@example.invalid',
        '{ADMIN_HASH}'=>password_hash('CI-only-password-2026',PASSWORD_DEFAULT),'{AMBIENTE}'=>'homologacao',
        '{TINY_VERSION}'=>'v2','{TINY_V2_URL}'=>'https://api.tiny.com.br','{TINY_V2_TOKEN}'=>'',
        '{TINY_V3_URL}'=>'https://api.tiny.com.br/public-api/v3','{TINY_V3_TOKEN}'=>'',
        '{VSM_URL}'=>'https://vsm.example.invalid','{VSM_TOKEN}'=>'','{WEBHOOK_SECRET}'=>'ci-webhook-secret'
    ]);
}

function withoutAdminSeed(string $sql): string {
    $clean=preg_replace('/INSERT\s+IGNORE\s+INTO\s+`?usuarios`?\s*\([^;]+?;/is','',$sql,1,$count);
    if (!is_string($clean)||$count!==1||str_contains($clean,'{ADMIN_')) throw new RuntimeException('Seed administrativo modular não pôde ser isolado.');
    return $clean;
}

function installAdminUser(PDO $pdo,string $name,string $email,string $passwordHash): void {
    $statement=$pdo->prepare("INSERT INTO usuarios(nome,email,senha,perfil,ativo) VALUES(?,?,?,'admin',1)");
    $statement->execute([$name,$email,$passwordHash]);
}

function assertModuleTables(PDO $pdo,string $sql,string $module): void {
    preg_match_all('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?([A-Za-z0-9_]+)`?/i',$sql,$matches);
    $expected=array_values(array_unique(array_map('strtolower',$matches[1]??[])));
    sort($expected);
    $actual=array_map('strtolower',$pdo->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE=\'BASE TABLE\'')->fetchAll(PDO::FETCH_COLUMN));
    sort($actual);
    if ($expected!==$actual) {
        $missing=array_diff($expected,$actual);$extra=array_diff($actual,$expected);
        throw new RuntimeException("Inventário divergente no módulo {$module}; ausentes=".implode(',',$missing).'; extras='.implode(',',$extra));
    }
}

function assertColumnContract(PDO $pdo,string $table,string $column,string $expectedType,bool $nullable,?string $default): void {
    $st=$pdo->prepare('SELECT COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $st->execute([$table,$column]);$row=$st->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException("Metadado ausente: {$table}.{$column}");
    $actualType=strtolower((string)$row['COLUMN_TYPE']);
    $actualType=preg_replace('/^(tinyint|smallint|mediumint|int|bigint)\(\d+\)/','$1',$actualType)??$actualType;
    if ($actualType!==strtolower($expectedType)) throw new RuntimeException("Tipo incompatível em {$table}.{$column}: {$actualType}");
    if (((string)$row['IS_NULLABLE']==='YES')!==$nullable) throw new RuntimeException("Nulabilidade incompatível em {$table}.{$column}");
    $actualDefault=normalizeColumnDefault($pdo,$row['COLUMN_DEFAULT']);
    if ($default===null ? $actualDefault!==null : strcasecmp((string)$actualDefault,$default)!==0) throw new RuntimeException("Default incompatível em {$table}.{$column}");
}

/**
 * Reauditoria 2026-09-14: MariaDB devolve COLUMN_DEFAULT como expressão SQL ("NULL" em
 * texto, strings entre aspas) e o MySQL 8 devolve o valor cru - ver nota completa em
 * mysql-runtime-regression.php.
 */
function normalizeColumnDefault(PDO $pdo,$raw): ?string {
    if ($raw===null) return null;
    $value=(string)$raw;
    if (strlen($value)>=2 && $value[0]==="'" && substr($value,-1)==="'") {
        return str_replace(["''","\\'"],["'","'"],substr($value,1,-1));
    }
    $isMariaDb=stripos((string)$pdo->getAttribute(PDO::ATTR_SERVER_VERSION),'mariadb')!==false;
    if ($isMariaDb && strcasecmp($value,'NULL')===0) return null;
    return $value;
}

/** @param list<string> $expectedColumns */
function assertIndexContract(PDO $pdo,string $table,string $index,array $expectedColumns,bool $unique): void {
    $st=$pdo->prepare('SELECT COLUMN_NAME,NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=? ORDER BY SEQ_IN_INDEX');
    $st->execute([$table,$index]);$rows=$st->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) throw new RuntimeException("Índice ausente: {$table}.{$index}");
    $columns=array_map(static fn(array $row):string=>(string)$row['COLUMN_NAME'],$rows);
    if ($columns!==$expectedColumns) throw new RuntimeException("Colunas incompatíveis no índice {$table}.{$index}");
    if ((((int)$rows[0]['NON_UNIQUE'])===0)!==$unique) throw new RuntimeException("Unicidade incompatível no índice {$table}.{$index}");
}

/** @return list<string> */
function splitSql(string $sql): array {
    $out=[];$buffer='';$quote='';$escape=false;$lineComment=false;$length=strlen($sql);
    for ($i=0;$i<$length;$i++) {
        $char=$sql[$i];$next=$i+1<$length?$sql[$i+1]:'';
        if ($lineComment) { if ($char==="\n") {$lineComment=false;$buffer.=$char;} continue; }
        if ($quote===''&&$char==='-'&&$next==='-') {$lineComment=true;$i++;continue;}
        if ($escape) {$buffer.=$char;$escape=false;continue;}
        if ($quote!=='') {$buffer.=$char;if($char==='\\'){$escape=true;continue;}if($char===$quote)$quote='';continue;}
        if ($char==="'"||$char==='"'||$char==='`') {$quote=$char;$buffer.=$char;continue;}
        if ($char===';') {if(trim($buffer)!=='')$out[]=trim($buffer);$buffer='';continue;}
        $buffer.=$char;
    }
    if (trim($buffer)!=='') $out[]=trim($buffer);
    return $out;
}
