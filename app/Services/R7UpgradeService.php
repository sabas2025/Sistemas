<?php
/** Executor explícito de migrations 008–017; não é um reparador genérico de bancos antigos. */
class R7UpgradeService {
  public const CATALOG = [
    '008'=>['20260914_008_rbac_backup_actions.sql',['core']],
    '009'=>['20260914_009_backup_proveniencia.sql',['backups']],
    '010'=>['20260914_010_tenant_isolation.sql',['core','pedidos','produtos','estoque','fiscal','fila','observabilidade']],
    '011'=>['20260914_011_indices_capacidade.sql',['pedidos']],
    '012'=>['20260914_012_pk_bigint_capacidade.sql',['pedidos','fila','observabilidade']],
    '013'=>['20260914_013_capacidade_logs_sessoes.sql',['observabilidade','core']],
    '014'=>['20260915_014_usuarios_empresa.sql',['core']],
    '015'=>['20260915_015_indice_fila_duplicado.sql',['fila']],
    '016'=>['20260916_016_indice_integration_events_fila.sql',['fila']],
    '017'=>['20260917_017_myouro_consulta.sql',['core']],
  ];
  public static function plan(string $phase='standard', ?int $backfill=null): array {
    if (!in_array($phase,['standard','bigint','all'],true)) throw new InvalidArgumentException('Fase inválida.');
    $cfg=App::config(); $plan=[];
    foreach (self::CATALOG as $id=>[$file,$modules]) {
      if (($phase==='standard' && $id==='012') || ($phase==='bigint' && $id!=='012')) continue;
      $raw=(string)file_get_contents(dirname(__DIR__,2).'/database/migrations/'.$file);
      $sql=UpgradeSqlService::split($raw); $targets=[];
      foreach ($modules as $module) {
        $db=Database::moduleConfig($cfg,$module);
        $key=$db['host'].'|'.$db['name'];
        // Conexões do mesmo banco são agrupadas para evitar aplicar duas vezes.
        if (isset($targets[$key]) && $id!=='013') continue;
        if (!isset($targets[$key])) $targets[$key]=['module'=>$module,'database'=>$db['name'],'statements'=>[]];
        foreach ($sql as $statement) {
          // Registros sempre no CORE, após verificação. SQL histórico é preservado no disco.
          if (preg_match('/^INSERT\s+(?:IGNORE\s+)?INTO\s+schema_migrations\b/i',$statement)) continue;
          if ($id==='013') {
            $session=(bool)preg_match('/^CREATE TABLE IF NOT EXISTS sessoes\b/i',$statement);
            if (($module==='core') !== $session) continue;
          }
          // Nunca inferir dono de registros antigos. Backfill requer confirmação explícita.
          if (preg_match('/^SET\s+@empresas\s*:=/i',$statement)) $statement='SET @empresas := '.($backfill===null?'0':'1');
          if (preg_match('/^SET\s+@empresa_unica\s*:=/i',$statement)) $statement='SET @empresa_unica := '.($backfill===null?'NULL':(string)$backfill);
          // PREPARE/EXECUTE repetidos são necessários: nunca deduplicar comandos SQL.
          $targets[$key]['statements'][]=$statement;
        }
      }
      foreach ($targets as $target) $plan[]=array_merge($target,['id'=>$id,'file'=>$file,'sha256'=>hash('sha256',$raw)]);
    }
    return $plan;
  }

  public static function verify(string $id): void {
    $columns=[]; $indexes=[];
    if ($id==='008') {
      $st=Database::forTable('permissoes_perfil')->query("SELECT COUNT(*) FROM permissoes_perfil WHERE modulo='backup' AND acao IN ('baixar','excluir','importar','restaurar') AND perfil IN ('admin','gerente','operador')");
      if ((int)$st->fetchColumn()!==12) throw new RuntimeException('RBAC incompleto após 008.');
    }
    if ($id==='009') { $columns[]=['backups_banco','proveniencia']; $indexes[]=['backups_banco','idx_backup_proveniencia','proveniencia']; }
    if ($id==='010') foreach (TenantScopeService::scopedTables() as $table) { if ($table==='logs_integracao') continue; $columns[]=[$table,'empresa_id']; }
    if ($id==='011') $indexes=[['pedidos_integracao','idx_pedidos_integracao_empresa','empresa_id'],['pedidos_integracao','idx_pedidos_empresa_status','empresa_id,status'],['pedidos_integracao','idx_pedidos_empresa_data','empresa_id,criado_em']];
    if ($id==='012') {
      foreach ([['pedidos_validacao','fila_id'],['pedidos_validacao','pedido_hub_id'],['pedidos_payloads','pedido_hub_id'],['pedidos_status_historico','pedido_hub_id'],['pedidos_nfe_xml','pedido_hub_id'],['fila_integracao','id'],['pedidos_integracao','id'],['pedidos_hub','id'],['logs_integracao','id']] as [$table,$column]) {
        $st=Database::forTable($table)->prepare('SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $st->execute([$table,$column]); if ($st->fetchColumn()!=='bigint') throw new RuntimeException('BIGINT não confirmado: '.$table.'.'.$column);
      }
    }
    if ($id==='013') { $columns=[['logs_integracao','empresa_id'],['sessoes','dados']]; $indexes=[['logs_integracao','idx_logs_empresa_data','empresa_id,criado_em']]; }
    if ($id==='014') $columns=[['usuarios','empresa_id']];
    if ($id==='015') $indexes=[['fila_integracao','idx_fila_status_proxima_prioridade','status,proxima_tentativa,prioridade,id']];
    if ($id==='016') $indexes=[['integration_events','idx_ie_fila','fila_id,id']];
    if ($id==='017') { $columns=[['configuracoes_integracao','integracao_empresa_id'],['myouro_conexoes','token_encrypted'],['myouro_conexoes','codigo_loja']]; $indexes=[['myouro_conexoes','uk_myouro_empresa','empresa_id']]; }
    foreach ($columns as [$table,$column]) {
      // Não usa cache de introspecção que antecedeu o ALTER.
      $st=Database::forTable($table)->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
      $st->execute([$table,$column]); if ((int)$st->fetchColumn()!==1) throw new RuntimeException('Coluna não confirmada: '.$table.'.'.$column);
    }
    foreach ($indexes as [$table,$index,$expected]) {
      $st=Database::forTable($table)->prepare('SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=? ORDER BY SEQ_IN_INDEX');
      $st->execute([$table,$index]); if (implode(',',$st->fetchAll(PDO::FETCH_COLUMN))!==$expected) throw new RuntimeException('Índice divergente: '.$table.'.'.$index);
    }
  }

  public static function preflight(?int $backfill=null): array {
    $required=['empresas','usuarios','configuracoes_integracao','schema_migrations','permissoes_perfil','backups_banco','pedidos_integracao','logs_integracao','integration_events'];
    $required=array_unique(array_merge($required,TenantScopeService::scopedTables()));
    foreach ($required as $table) if (!Database::tableExists($table)) throw new RuntimeException('Baseline pendente: '.$table.'. Atualize Enterprise Core antes deste procedimento.');
    if ($backfill!==null && IntegrationTenantService::singleEmpresaId()!==$backfill) throw new RuntimeException('Backfill exige a única empresa cadastrada e confirmação da propriedade do legado.');
    $report=[];
    foreach (TenantScopeService::scopedTables() as $table) {
      if (!Database::columnExists($table,'empresa_id')) { $report[$table]='coluna pendente'; continue; }
      $st=Database::forTable($table)->query('SELECT COUNT(*) FROM `'.$table.'` WHERE empresa_id IS NULL');
      $report[$table]=(int)$st->fetchColumn(); $st->closeCursor();
    }
    return $report;
  }

  public static function apply(array $plan,?int $backfill=null): void {
    if (PHP_SAPI!=='cli') throw new RuntimeException('Upgrade permitido somente por CLI.');
    $core=Database::connection('core');
    $db=Database::moduleConfig(App::config(),'core');
    $lock='hub:r7:'.substr(hash('sha256',$db['host'].'|'.$db['name']),0,48);
    $st=$core->prepare('SELECT GET_LOCK(?,0)'); $st->execute([$lock]);
    if ((int)$st->fetchColumn()!==1) throw new RuntimeException('Outro upgrade está em execução.');
    $st->closeCursor();
    $log=null;
    try {
      self::preflight($backfill);
      $dir=dirname(__DIR__,2).'/storage/logs';
      if (!is_dir($dir) && !mkdir($dir,0700,true)) throw new RuntimeException('Diretório privado de evidências indisponível.');
      $log=fopen($dir.'/upgrade-r7-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.jsonl','x');
      if ($log===false) throw new RuntimeException('Não foi possível registrar o upgrade.');
      $groups=[]; foreach ($plan as $step) $groups[$step['id']][]=$step;
      foreach ($groups as $id=>$steps) {
        $id=(string)$id; $key='r7build20260917_'.$id;
        $checksum=hash('sha256',json_encode($steps,JSON_THROW_ON_ERROR));
        $st=$core->prepare('SELECT checksum FROM schema_migrations WHERE migration=? AND status=?'); $st->execute([$key,'aplicada']);
        $old=$st->fetchColumn(); $st->closeCursor();
        if ($old!==false && !hash_equals((string)$old,$checksum)) throw new RuntimeException('Checksum/plano divergente para '.$key.'. Requer revisão manual.');
        $record=static function(string $state,array $extra=[]) use ($log,$id): void {
          $line=json_encode(array_merge(['at'=>date('c'),'migration'=>$id,'state'=>$state],$extra),JSON_THROW_ON_ERROR)."\n";
          if (fwrite($log,$line)!==strlen($line) || !fflush($log)) throw new RuntimeException('Falha ao persistir evidência do upgrade.');
        };
        $record('inicio',['checksum'=>$checksum]);
        $mark=$core->prepare("INSERT INTO schema_migrations(migration,version,description,checksum,status,mensagem) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE version=VALUES(version),checksum=VALUES(checksum),status=VALUES(status),mensagem=VALUES(mensagem),aplicada_em=CURRENT_TIMESTAMP");
        $mark->execute([$key,SystemVersionService::artifactVersion(),'Upgrade controlado 008–017',$checksum,'falha','INICIADA: ver log; DDL não tem rollback global']);
        try {
          foreach ($steps as $step) {
            $pdo=Database::connection($step['module']);
            foreach ($step['statements'] as $n=>$sql) {
              $record('statement',['module'=>$step['module'],'position'=>$n+1]);
              UpgradeSqlService::execute($pdo,$sql);
            }
          }
          self::verify($id);
          $mark->execute([$key,SystemVersionService::artifactVersion(),'Upgrade controlado 008–017',$checksum,'aplicada','Pós-condições verificadas']);
          $record('concluida');
        } catch (Throwable $e) { $record('erro',['class'=>get_class($e),'code'=>$e->getCode()]); throw $e; }
      }
    } finally {
      if (is_resource($log)) fclose($log);
      $st=$core->prepare('SELECT RELEASE_LOCK(?)'); $st->execute([$lock]); $st->closeCursor();
    }
  }
}
