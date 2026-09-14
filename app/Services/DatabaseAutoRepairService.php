<?php
/**
 * V104.49.3-R5 - AutoRepair de schema, limitado a DDL seguro.
 *
 * Corrige cenário em que o painel "Validar Banco" mostra praticamente todas
 * as tabelas como ausentes. Nesse caso o SchemaGuard por DDL parseado pode não
 * ser suficiente ou a instalação ficou parcial. Este serviço lê os módulos
 * oficiais, aplica cada um na sua conexão e ignora integralmente seeds/DML.
 * Usuários, senhas, tokens, segredos e configurações nunca são regravados aqui.
 */
class DatabaseAutoRepairService {
  /** @return array{ok:int,erro:int,atencao:int,logs:array<int,array<string,string>>} */
  public static function repair(bool $dryRun = false): array {
    $logs = [];
    $ok = 0; $erro = 0; $atencao = 0;
    try {
      Database::clearTableResolutionCache();
      $singleDatabase = Database::isSingleDatabaseMode();
      $corePdo = Database::connection('core');
      $coreDbName = Database::currentDatabaseName($corePdo);
      $logs[] = self::log('ok', 'config.php/db_storage_mode', 'Conectado ao banco core: '.($coreDbName ?: '(sem database)').' | modo efetivo: '.($singleDatabase ? 'single' : 'modular'));
      $targets = self::sqlTargets();
      if (!$targets) {
        return ['ok'=>0,'erro'=>1,'atencao'=>0,'logs'=>[self::log('erro','sql','Nenhum SQL modular/current encontrado para reparo.')]];
      }
      $configuredModules = array_fill_keys(Database::modules(), true);
      foreach ($targets as $target) {
        $module = $singleDatabase ? 'core' : $target['module'];
        $path = $target['path'];
        if (!$singleDatabase && !isset($configuredModules[$module])) {
          $atencao++;
          $logs[] = self::log('atencao', basename($path), 'Módulo '.$module.' não configurado; nenhum SQL foi redirecionado ao banco core.');
          continue;
        }
        try {
          $pdo = $module === 'core' ? $corePdo : Database::connection($module);
        } catch (Throwable $e) {
          $erro++;
          $logs[] = self::log('erro', basename($path), 'Conexão do módulo '.$module.' falhou: '.$e->getMessage());
          continue;
        }
        $dbName = Database::currentDatabaseName($pdo);
        $logs[] = self::log('ok', basename($path), 'Destino DDL: módulo '.$module.' / banco '.($dbName ?: '(sem database)').'.');
        $sql = (string)file_get_contents($path);
        foreach (self::splitSql($sql) as $statement) {
          $statement = self::sanitizeStatement($statement);
          if ($statement === '') continue;
          $tipo = self::statementType($statement);
          if (!self::allowedStatement($statement)) {
            $atencao++;
            $logs[] = self::log('atencao', basename($path), 'Comando não DDL ignorado no AutoRepair: '.$tipo);
            continue;
          }
          if ($dryRun) {
            $ok++;
            $logs[] = self::log('ok', basename($path), 'Dry-run: '.$tipo.' validado.');
            continue;
          }
          try {
            $pdo->exec($statement);
            $ok++;
          } catch (Throwable $e) {
            if (self::isIgnorableSqlError($e)) {
              $atencao++;
              $logs[] = self::log('atencao', basename($path), $tipo.' ignorado: '.$e->getMessage());
            } else {
              $erro++;
              $logs[] = self::log('erro', basename($path), $tipo.' falhou: '.$e->getMessage().' | Preview: '.self::preview($statement));
            }
          }
        }
      }
      Database::clearSchemaMetadataCache();
      self::repairCriticalColumns($dryRun, $singleDatabase, $configuredModules, $logs, $ok, $erro, $atencao);
      self::repairCriticalIndexes($dryRun, $singleDatabase, $configuredModules, $logs, $ok, $erro, $atencao);
      self::verifyEssentialTables($dryRun, $singleDatabase, $configuredModules, $logs, $ok, $erro, $atencao);
      self::verifyStrictEnterpriseContract($dryRun, $logs, $ok, $erro, $atencao);
      Database::clearTableResolutionCache();
    } catch (Throwable $e) {
      $erro++;
      $logs[] = self::log('erro','AutoRepair','Falha geral no reparo automático: '.$e->getMessage());
    }
    return ['ok'=>$ok,'erro'=>$erro,'atencao'=>$atencao,'logs'=>$logs];
  }

  /** @return array<int,array{module:string,path:string}> */
  private static function sqlTargets(): array {
    $base = __DIR__.'/../../database';
    $targets = [];
    foreach (['core','pedidos','produtos','estoque','fiscal','fila','observabilidade','backups'] as $module) {
      $path = $base.'/modules/'.$module.'.sql';
      if (is_file($path)) $targets[] = ['module'=>$module, 'path'=>$path];
    }
    return $targets;
  }

  /** @return array<int,string> */
  private static function splitSql(string $sql): array {
    $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql) ?? $sql;
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql) ?? $sql;
    $lines = preg_split('/\R/', $sql) ?: [];
    $clean = [];
    foreach ($lines as $line) {
      $trim = trim($line);
      if ($trim === '' || str_starts_with($trim, '--') || str_starts_with($trim, '#')) continue;
      $clean[] = $line;
    }
    $sql = implode("\n", $clean);
    $out = [];
    $buf = '';
    $quote = null;
    $len = strlen($sql);
    for ($i=0; $i<$len; $i++) {
      $ch = $sql[$i];
      $prev = $i > 0 ? $sql[$i-1] : '';
      if (($ch === "'" || $ch === '"' || $ch === '`') && $prev !== '\\') {
        if ($quote === null) $quote = $ch;
        elseif ($quote === $ch) $quote = null;
      }
      if ($ch === ';' && $quote === null) {
        $s = trim($buf);
        if ($s !== '') $out[] = $s;
        $buf = '';
      } else {
        $buf .= $ch;
      }
    }
    $s = trim($buf);
    if ($s !== '') $out[] = $s;
    return $out;
  }

  private static function sanitizeStatement(string $statement): string {
    $s = trim($statement);
    if (preg_match('/^CREATE\s+DATABASE\b/i', $s)) return '';
    if (preg_match('/^USE\s+/i', $s)) return '';
    if (preg_match('/^SET\s+NAMES\b/i', $s)) return '';
    return $s;
  }

  private static function allowedStatement(string $statement): bool {
    if (preg_match('/^\s*CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?[a-zA-Z0-9_]+`?\s*\(/i', $statement)) {
      return true;
    }
    // O reparo automático só acrescenta estrutura. MODIFY/CHANGE podem converter
    // ou truncar dados e ficam restritos a manutenção manual após backup.
    if (!preg_match('/^\s*ALTER\s+TABLE\s+`?[a-zA-Z0-9_]+`?\s+ADD\b/i', $statement)) {
      return false;
    }
    return !preg_match('/\b(DROP|RENAME|TRUNCATE)\b/i', $statement);
  }

  private static function statementType(string $statement): string {
    if (preg_match('/^\s*([A-Z]+(?:\s+[A-Z]+){0,3})/i', $statement, $m)) return strtoupper(trim($m[1]));
    return 'SQL';
  }

  private static function isIgnorableSqlError(Throwable $e): bool {
    $msg = strtolower($e->getMessage());
    return str_contains($msg, 'already exists')
      || str_contains($msg, 'duplicate entry')
      || str_contains($msg, 'duplicate column')
      || str_contains($msg, 'duplicate key name');
  }

  /**
   * Contrato mínimo que corrige instalações anteriores sem regravar uma única
   * linha. Database::addColumnIfMissing resolve a conexão oficial da tabela e
   * emite somente ALTER TABLE ... ADD COLUMN.
   */
  private static function repairCriticalColumns(bool $dryRun, bool $singleDatabase, array $configuredModules, array &$logs, int &$ok, int &$erro, int &$atencao): void {
    $columns = [
      ['configuracoes_integracao','vsm_ambiente',"VARCHAR(30) DEFAULT 'homologacao'"],
      ['configuracoes_integracao','vsm_producao_liberada','TINYINT DEFAULT 0'],
      ['configuracoes_integracao','vsm_ultimo_teste_ok','TINYINT DEFAULT 0'],
      ['configuracoes_integracao','vsm_ultimo_teste_em','DATETIME NULL'],
      ['configuracoes_integracao','vsm_host_producao_liberado','VARCHAR(255) NULL'],
      ['configuracoes_integracao','vsm_producao_liberada_em','DATETIME NULL'],
      ['configuracoes_integracao','vsm_producao_liberada_por','INT NULL'],
      ['schema_migrations','version','VARCHAR(40) NULL'],
      ['schema_migrations','description','TEXT NULL'],
      ['vsm_endpoints','modo_teste_seguro','TINYINT NOT NULL DEFAULT 1'],
      ['vsm_endpoints','origem',"VARCHAR(40) NOT NULL DEFAULT 'manual'"],
      ['vsm_endpoints','contract_verified','TINYINT NOT NULL DEFAULT 0'],
      ['vsm_endpoints','contract_source','VARCHAR(255) NULL'],
      ['vsm_endpoints','contract_checked_at','DATETIME NULL'],
      ['vsm_endpoints','is_template','TINYINT NOT NULL DEFAULT 0'],
      ['vsm_campos_mapeamento','tipo_dado','VARCHAR(50) NULL'],
      ['vsm_campos_mapeamento','valor_padrao','VARCHAR(255) NULL'],
      ['vsm_campos_mapeamento','regra_validacao','VARCHAR(255) NULL'],
      ['vsm_campos_mapeamento','exemplo_payload','TEXT NULL'],
      ['vsm_campos_mapeamento','origem',"VARCHAR(40) NOT NULL DEFAULT 'manual'"],
      ['vsm_campos_mapeamento','contract_verified','TINYINT NOT NULL DEFAULT 0'],
      ['vsm_campos_mapeamento','contract_source','VARCHAR(255) NULL'],
      ['vsm_campos_mapeamento','is_template','TINYINT NOT NULL DEFAULT 0'],
      ['vsm_endpoint_logs','modo_teste_seguro','TINYINT NOT NULL DEFAULT 1'],
    ];
    foreach ($columns as [$table, $column, $definition]) {
      $module = Database::officialTableModule($table);
      if (!$singleDatabase && !isset($configuredModules[$module])) {
        $atencao++;
        $logs[] = self::log('atencao', 'contrato crítico', $table.'.'.$column.' ignorada: módulo '.$module.' não configurado.');
        continue;
      }
      try {
        if (Database::columnExists($table, $column)) {
          $ok++;
          continue;
        }
        if ($dryRun) {
          $logs[] = self::log('ok', 'contrato crítico', 'Dry-run: adicionaria '.$table.'.'.$column.'.');
          $ok++;
          continue;
        }
        $added = Database::addColumnIfMissing($table, $column, $definition);
        $logs[] = self::log('ok', 'contrato crítico', $table.'.'.$column.($added ? ' adicionada.' : ' já existia.'));
        $ok++;
      } catch (Throwable $e) {
        $erro++;
        $logs[] = self::log('erro', 'contrato crítico', $table.'.'.$column.' falhou: '.$e->getMessage());
      }
    }
  }

  private static function repairCriticalIndexes(bool $dryRun, bool $singleDatabase, array $configuredModules, array &$logs, int &$ok, int &$erro, int &$atencao): void {
    $indexes = [
      ['schema_migrations','idx_schema_migrations_status','INDEX `idx_schema_migrations_status` (`status`)'],
      ['schema_migrations','idx_schema_migrations_data','INDEX `idx_schema_migrations_data` (`aplicada_em`)'],
      ['fila_integracao','idx_fila_idempotency_key','INDEX `idx_fila_idempotency_key` (`idempotency_key`)'],
      ['fila_integracao','idx_fila_status_proxima_prioridade','INDEX `idx_fila_status_proxima_prioridade` (`status`,`proxima_tentativa`,`prioridade`,`id`)'],
      ['fila_integracao','idx_fila_locked','INDEX `idx_fila_locked` (`locked_by`,`locked_at`)'],
      ['fila_integracao','idx_fila_lease','INDEX `idx_fila_lease` (`status`,`lease_expires_at`)'],
      ['vsm_endpoints','idx_vsm_endpoints_categoria','INDEX `idx_vsm_endpoints_categoria` (`categoria`)'],
      ['vsm_endpoints','idx_vsm_endpoints_ativo','INDEX `idx_vsm_endpoints_ativo` (`ativo`)'],
      ['vsm_endpoints','idx_vsm_endpoints_ordem','INDEX `idx_vsm_endpoints_ordem` (`ordem_execucao`)'],
      ['vsm_campos_mapeamento','uk_vsm_campo','UNIQUE KEY `uk_vsm_campo` (`categoria`,`campo_vsm`,`campo_hub`)'],
      ['vsm_campos_mapeamento','idx_vsm_campos_categoria','INDEX `idx_vsm_campos_categoria` (`categoria`)'],
      ['vsm_campos_mapeamento','idx_vsm_campos_ativo','INDEX `idx_vsm_campos_ativo` (`ativo`)'],
      ['vsm_endpoint_logs','idx_vsm_logs_endpoint','INDEX `idx_vsm_logs_endpoint` (`endpoint_id`)'],
      ['vsm_endpoint_logs','idx_vsm_logs_chave','INDEX `idx_vsm_logs_chave` (`chave`)'],
      ['vsm_endpoint_logs','idx_vsm_logs_sucesso','INDEX `idx_vsm_logs_sucesso` (`sucesso`)'],
      ['vsm_endpoint_logs','idx_vsm_logs_criado','INDEX `idx_vsm_logs_criado` (`criado_em`)'],
    ];
    foreach ($indexes as [$table,$index,$definition]) {
      $module=Database::officialTableModule($table);
      if (!$singleDatabase && !isset($configuredModules[$module])) {
        $atencao++; $logs[]=self::log('atencao','índice crítico',$table.'.'.$index.' ignorado: módulo '.$module.' não configurado.'); continue;
      }
      try {
        $pdo=Database::forTable($table);
        if (Database::indexExistsOn($pdo,$table,$index)) { $ok++; continue; }
        if ($dryRun) { $ok++; $logs[]=self::log('ok','índice crítico','Dry-run: adicionaria '.$table.'.'.$index.'.'); continue; }
        $added=Database::addIndexIfMissing($table,$index,$definition);
        $ok++; $logs[]=self::log('ok','índice crítico',$table.'.'.$index.($added?' adicionado.':' já existia.'));
      } catch (Throwable $e) {
        $erro++; $logs[]=self::log('erro','índice crítico',$table.'.'.$index.' falhou: '.$e->getMessage());
      }
    }
  }

  private static function verifyEssentialTables(bool $dryRun, bool $singleDatabase, array $configuredModules, array &$logs, int &$ok, int &$erro, int &$atencao): void {
    $essenciais = ['usuarios','configuracoes_integracao','schema_migrations','backups_banco'];
    $missing = [];
    foreach ($essenciais as $t) {
      $module = Database::officialTableModule($t);
      if (!$singleDatabase && !isset($configuredModules[$module])) {
        $missing[] = $t.' (módulo '.$module.' não configurado)';
        continue;
      }
      try {
        $pdo = Database::forTable($t);
        if (!Database::tableExistsOn($pdo, $t)) $missing[] = $t;
      } catch (Throwable $e) {
        $missing[] = $t;
      }
    }
    if (!$missing) {
      $ok++;
      $logs[] = self::log('ok','AutoRepair pós-verificação','Tabelas essenciais encontradas no banco conectado.');
      return;
    }

    if ($dryRun) {
      $atencao++;
      $logs[] = self::log('atencao','AutoRepair pós-verificação','Dry-run não altera o schema. Tabelas atualmente ausentes: '.implode(', ', $missing).'.');
      return;
    }
    $erro++;
    $logs[] = self::log('erro','AutoRepair pós-verificação','Tabelas essenciais ainda ausentes após os módulos DDL: '.implode(', ', $missing).'.');
  }

  private static function verifyStrictEnterpriseContract(bool $dryRun, array &$logs, int &$ok, int &$erro, int &$atencao): void {
    if($dryRun){
      $atencao++;
      $logs[]=self::log('atencao','AutoRepair contrato estrito','Dry-run não comprova o contrato final porque nenhuma alteração foi aplicada.');
      return;
    }
    if(!class_exists('SchemaMigrationService')){
      $erro++;
      $logs[]=self::log('erro','AutoRepair contrato estrito','SchemaMigrationService indisponível; o reparo não pode declarar sucesso.');
      return;
    }
    try{
      Database::clearSchemaMetadataCache();
      $status=SchemaMigrationService::status();
      if(($status['status']??'atencao')==='ok'&&(int)($status['ok']??0)===(int)($status['total']??-1)){
        $ok++;
        $logs[]=self::log('ok','AutoRepair contrato estrito','SchemaMigrationService confirmou todos os '.(int)$status['total'].' contratos obrigatórios.');
        return;
      }
      $pending=[];
      foreach(($status['checks']??[]) as $check){if(($check['status']??'pendente')!=='ok')$pending[]=(string)($check['item']??'schema');}
      $erro++;
      $logs[]=self::log('erro','AutoRepair contrato estrito','Reparo incompleto: contratos incompatíveis ou ausentes: '.implode(', ',array_slice($pending,0,30)).(count($pending)>30?' e mais '.(count($pending)-30).' item(ns)':'').'.');
    }catch(Throwable $e){
      $erro++;
      $logs[]=self::log('erro','AutoRepair contrato estrito','Falha ao validar o contrato final: '.$e->getMessage());
    }
  }

  private static function preview(string $statement): string {
    $s = preg_replace('/\s+/', ' ', trim($statement)) ?? trim($statement);
    return mb_substr($s, 0, 180);
  }

  /** @return array<string,string> */
  private static function log(string $status, string $origem, string $mensagem): array {
    return ['status'=>$status,'origem'=>$origem,'mensagem'=>$mensagem];
  }
}
