<?php
class Database {
  /** @var array<string, PDO> */
  private static array $connections = [];
  private static ?array $configCache = null;
  private static ?array $tableModuleMap = null;
  /** @var array<string,string> */
  private static array $resolvedTableModule = [];
  /** @var array<string,bool> */
  private static array $tableExistsCache = [];
  /** @var array<string,bool> */
  private static array $columnExistsCache = [];

  private static function config(): array {
    if (self::$configCache === null) {
      self::$configCache = class_exists('App') ? App::config() : (require __DIR__ . '/../../config/config.php');
    }
    return self::$configCache;
  }

  public static function getConnection(?string $module = null): PDO {
    return self::connection($module ?: 'core');
  }

  public static function connection(string $module = 'core'): PDO {
    $module = self::normalizeModule($module);
    if (!isset(self::$connections[$module])) {
      $cfg = self::config();
      $db = self::moduleConfig($cfg, $module);
      $dsn = "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}";
      try {
        self::$connections[$module] = new PDO($dsn, $db['user'], $db['pass'], [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
          PDO::ATTR_EMULATE_PREPARES => false,
        ]);
      } catch (PDOException $e) {
        self::handleConnectionError($e, $db, $module);
        throw $e;
      }
    }
    return self::$connections[$module];
  }

  public static function serverConnection(?string $module = null): PDO {
    $cfg = self::config();
    $db = self::moduleConfig($cfg, $module ?: 'core');
    return new PDO("mysql:host={$db['host']};charset={$db['charset']}", $db['user'], $db['pass'], [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
          PDO::ATTR_EMULATE_PREPARES => false,
    ]);
  }

  public static function moduleConfig(array $cfg, string $module = 'core'): array {
    $module = self::normalizeModule($module);
    $base = $cfg['db'] ?? [];
    $modules = $cfg['db_modules'] ?? [];

    /*
     * V104.16 - correção crítica para hospedagem compartilhada/banco único.
     *
     * Nas versões anteriores, mesmo com db_storage_mode='single', se o config.php
     * antigo ainda tivesse db_modules apontando para bancos separados, o sistema
     * podia conectar em bancos vazios como *_pedidos, *_estoque, *_fila etc.
     * Isso gerava erro em massa: "Tabela não existe no módulo resolvido X".
     *
     * Em modo single TODOS os módulos devem usar exatamente o banco principal.
     * O array db_modules fica apenas documentativo/compatibilidade, não manda na conexão.
     */
    if (self::isSingleDatabaseMode($cfg)) {
      // V104.17: banco único absoluto. Em modo single, IGNORA totalmente db_modules,
      // inclusive db_modules['core'], porque configs antigas podem apontar para banco vazio.
      // A conexão de todos os módulos deve usar somente cfg['db'].
      $selected = $base;
    } elseif (self::isStrictModularMode($cfg) && $module !== 'core' && !isset($modules[$module])) {
      // P1-02: em modo modular estrito, um módulo sem entrada em db_modules é erro de
      // configuração - antes isso conectava silenciosamente nas credenciais do core.
      throw new RuntimeException("Módulo '{$module}' não está configurado em db_modules, mas db_modular_strict está ativo. Configure db_modules['{$module}'] ou desative db_modular_strict.");
    } else {
      $selected = $modules[$module] ?? ($module === 'core' ? $base : []);
    }

    return array_merge([
      'host' => $base['host'] ?? '127.0.0.1',
      'name' => $base['name'] ?? 'hub_vsm_tiny',
      'user' => $base['user'] ?? 'root',
      'pass' => $base['pass'] ?? '',
      'charset' => $base['charset'] ?? 'utf8mb4',
    ], $selected);
  }

  public static function isSingleDatabaseMode(?array $cfg = null): bool {
    $cfg = $cfg ?? self::config();
    $mode = strtolower((string)($cfg['db_storage_mode'] ?? ''));
    if ($mode === 'single') return true;

    /*
     * V104.13: modo modular antigo em hospedagem compartilhada gerava todos os
     * erros de tabela ausente. Para evitar dano operacional, só tratamos como
     * modular estrito quando db_modular_strict=true.
     *
     * Quem realmente usa VPS com bancos separados deve configurar:
     *   'db_storage_mode' => 'modular',
     *   'db_modular_strict' => true,
     */
    if ($mode === 'modular') {
      return empty($cfg['db_modular_strict']);
    }

    if (array_key_exists('db_single_database_rescue', $cfg)) return (bool)$cfg['db_single_database_rescue'];
    return true;
  }

  public static function modules(): array {
    $cfg = self::config();
    if (self::isSingleDatabaseMode($cfg)) return ['core'];
    $modules = $cfg['db_modules'] ?? [];
    if (!isset($modules['core'])) $modules = ['core' => $cfg['db'] ?? []] + $modules;
    return array_keys($modules);
  }

  public static function knownTables(): array {
    return array_keys(self::tableModuleMap());
  }

  public static function isKnownTable(string $table): bool {
    return isset(self::tableModuleMap()[$table]);
  }

  public static function tableModule(string $table): string {
    $map = self::tableModuleMap();
    return $map[$table] ?? 'core';
  }

  private static function tableModuleMap(): array {
    if (self::$tableModuleMap !== null) return self::$tableModuleMap;
    return self::$tableModuleMap = [
      'myouro_conexoes'=>'core','sessoes'=>'core',
      'usuarios'=>'core','empresas'=>'core','filiais'=>'core','configuracoes_integracao'=>'core','configuracoes_historico'=>'core','login_tentativas'=>'core','token_vault'=>'core','security_events'=>'core','ips_bloqueados'=>'core','rate_limit_hits'=>'core','permissoes_perfil'=>'core','schema_migrations'=>'core','tiny_v3_tokens'=>'core','tiny_validacoes_execucoes'=>'core','production_readiness_v50'=>'core','tiny_testes_reais'=>'core','tiny_error_catalogo'=>'core','tiny_v2_retry_policies'=>'core','mapper_versions'=>'core','instalacao_prechecks'=>'core','upgrade_snapshots'=>'core','post_install_tests'=>'core','homologacao_checklist'=>'core','homologacao_automatica_relatorios'=>'core','selftest_relatorios'=>'core','orquestracao_fluxos_historico'=>'core','production_go_live_checks'=>'core','security_hardening_checks'=>'core','system_build_info'=>'core','tiny_v2_homologacao_testes'=>'core','tiny_v3_homologacao_testes'=>'core',
      'pedidos_integracao'=>'pedidos','tiny_webhooks'=>'pedidos','webhook_requisicoes'=>'pedidos','eventos_processados'=>'pedidos','integracao_execucoes'=>'pedidos','evento_correlacao'=>'pedidos','pedidos_validacao'=>'pedidos','pedidos_validacao_historico'=>'pedidos','pedidos_hub'=>'pedidos','pedidos_payloads'=>'pedidos','pedidos_status_historico'=>'pedidos','pedidos_nfe_xml'=>'pedidos',
      'produtos_mapeamento'=>'produtos','produtos_vsm'=>'produtos','produtos_vsm_eventos'=>'produtos','produto_pendencias'=>'produtos','produtos_pendentes_integracao'=>'produtos','categorias_mapeamento'=>'produtos','produtos_aprovacao_historico'=>'produtos','produtos_tiny'=>'produtos','vsm_endpoint_catalogo'=>'produtos','vsm_payload_catalogo'=>'produtos','vsm_endpoint_metricas'=>'produtos','vsm_endpoints'=>'core','vsm_campos_mapeamento'=>'core',
      'estoque_movimentos'=>'estoque','estoque_divergencias'=>'estoque','estoque_reconciliacao'=>'estoque','reconciliacao_execucoes'=>'estoque','reconciliacao_itens'=>'estoque','estoque_configuracoes'=>'estoque','fila_estoque'=>'estoque','estoque_alertas'=>'estoque','estoque_saldos_cache'=>'estoque','estoque_auditoria_sku'=>'estoque','estoque_reconciliacao_agendada'=>'estoque','estoque_eventos_sincronizacao'=>'estoque','estoque_consulta_tiny_execucoes'=>'estoque','estoque_consulta_tiny_resultados'=>'estoque','estoque_consulta_vsm_execucoes'=>'estoque','estoque_consulta_vsm_resultados'=>'estoque',
      'notas_fiscais'=>'fiscal','nfe_integracao'=>'fiscal','notas_fiscais_eventos'=>'fiscal','nfe_xml'=>'fiscal','nfe_status_historico'=>'fiscal','fiscal_reconciliacao_snapshots'=>'fiscal','fiscal_configuracoes_cache'=>'fiscal',
      'fila_integracao'=>'fila','fila_reprocessamento_historico'=>'fila','fila_morta'=>'fila','fila_fiscal'=>'fila','circuit_breakers'=>'fila','integration_replay_guard'=>'fila','payload_snapshots'=>'fila','fila_analytics_snapshots'=>'fila',
      'vsm_endpoint_logs'=>'observabilidade','logs_integracao'=>'observabilidade','auditoria_eventos'=>'observabilidade','auditoria_timeline'=>'observabilidade','auditoria_detalhes'=>'observabilidade','metricas_api'=>'observabilidade','diagnostico_api'=>'observabilidade','security_audit'=>'observabilidade','auditoria_hash_chain'=>'observabilidade','auditoria_assinaturas'=>'observabilidade','audit_exports'=>'observabilidade','tiny_v3_endpoint_logs'=>'observabilidade','tiny_v2_endpoint_logs'=>'observabilidade','hosting_checks'=>'observabilidade','notificacoes'=>'observabilidade','notificacoes_config'=>'observabilidade','dashboard_testes_execucoes'=>'observabilidade','module_health_snapshots'=>'observabilidade','audit_daily_signatures'=>'observabilidade',
      'backups'=>'backups','backups_banco'=>'backups',
      'comercial_clientes_licencas'=>'core','comercial_conectores_catalogo'=>'core','comercial_cobranca_faturas'=>'core','comercial_demo_ambientes'=>'core','comercial_suporte_chamados'=>'core','comercial_sla_eventos'=>'core','comercial_license_checks'=>'core','comercial_billing_gateway_events'=>'core','tenant_scope_audit_snapshots'=>'core','comercial_license_remote_cache'=>'core','comercial_billing_provider_events'=>'core','comercial_demo_reset_logs'=>'core','connector_operational_checks'=>'core','system_release_checks'=>'core','integration_events'=>'fila','integration_idempotency'=>'fila','worker_heartbeats'=>'observabilidade','observability_snapshots'=>'observabilidade','enterprise_quality_gates'=>'observabilidade','llm_prompts'=>'core','llm_policy_settings'=>'core','llm_approval_queue'=>'core','llm_usage_daily'=>'observabilidade','llm_audit_logs'=>'observabilidade','enterprise_regression_runs'=>'observabilidade','enterprise_ui_preferences'=>'core'
    ];
  }

  public static function forTable(string $table): PDO {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
      throw new InvalidArgumentException('Tabela inválida.');
    }
    return self::connection(self::resolveTableModule($table));
  }

  /**
   * Resolve o banco real de uma tabela.
   *
   * Em várias hospedagens compartilhadas o usuário restaura/instala tudo em um banco único,
   * mas o config.php pode continuar com nomes modulares. Antes da V104.16 isso gerava falso
   * erro em massa: "Tabela não existe no módulo X". Agora o sistema procura a tabela nos
   * módulos configurados e, quando ela ainda não existe, pode criar/reparar no banco core
   * em modo rescue de banco único.
   */
  /**
   * P1-02 (reauditoria 2026-08-23): true somente quando o operador declarou
   * explicitamente banco modular real (db_storage_mode=modular + db_modular_strict=true).
   * Usado para desligar a busca cruzada entre módulos - "estrito" deve falhar fechado,
   * não procurar a tabela em outro banco.
   */
  public static function isStrictModularMode(?array $cfg = null): bool {
    $cfg = $cfg ?? self::config();
    return strtolower((string)($cfg['db_storage_mode'] ?? '')) === 'modular' && !empty($cfg['db_modular_strict']);
  }

  public static function resolveTableModule(string $table): string {
    if (isset(self::$resolvedTableModule[$table])) return self::$resolvedTableModule[$table];
    $official = self::tableModule($table);

    // V104.16: em banco único, todas as tabelas resolvem para core.
    // Isso evita consultar bancos modulares vazios quando config.php antigo ainda possui db_modules.
    if (self::isSingleDatabaseMode()) {
      return self::$resolvedTableModule[$table] = 'core';
    }

    // P1-02: em modo modular estrito, NUNCA procura a tabela em outro módulo nem cai
    // para o core - se o módulo oficial não tiver a tabela, isso deve aparecer como
    // erro real (tabela ausente), não ser mascarado silenciosamente por um módulo
    // vizinho que por acaso tenha uma tabela de mesmo nome.
    if (self::isStrictModularMode()) {
      return self::$resolvedTableModule[$table] = $official;
    }

    try {
      $pdo = self::connection($official);
      if (self::tableExistsOn($pdo, $table)) return self::$resolvedTableModule[$table] = $official;
    } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }

    foreach (self::modules() as $module) {
      if ($module === $official) continue;
      try {
        $pdo = self::connection($module);
        if (self::tableExistsOn($pdo, $table)) return self::$resolvedTableModule[$table] = $module;
      } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    }

    // Se a tabela ainda não existe em lugar nenhum, em hospedagem compartilhada é mais seguro
    // reparar no banco core/banco único. Para instalação modular real, defina db_storage_mode='modular'.
    if ($official !== 'core' && self::singleDatabaseRescueEnabled()) {
      return self::$resolvedTableModule[$table] = 'core';
    }
    return self::$resolvedTableModule[$table] = $official;
  }

  public static function officialTableModule(string $table): string {
    return self::tableModule($table);
  }

  public static function clearTableResolutionCache(): void {
    self::$resolvedTableModule = [];
  }

  public static function clearSchemaMetadataCache(): void {
    self::$tableExistsCache = [];
    self::$columnExistsCache = [];
    self::clearTableResolutionCache();
  }

  public static function singleDatabaseRescueEnabled(): bool {
    return self::isSingleDatabaseMode();
  }


  public static function tableExists(string $table): bool {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return false;
    $module = self::resolveTableModule($table);
    $key = $module.'|'.$table;
    if (array_key_exists($key, self::$tableExistsCache)) return self::$tableExistsCache[$key];
    try {
      return self::$tableExistsCache[$key] = self::tableExistsOn(self::connection($module), $table);
    } catch (Throwable $e) {
      if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e, ['table'=>$table,'module'=>$module]);
      return self::$tableExistsCache[$key] = false;
    }
  }

  public static function columnExists(string $table, string $column): bool {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) return false;
    $module = self::resolveTableModule($table);
    $key = $module.'|'.$table.'|'.$column;
    if (array_key_exists($key, self::$columnExistsCache)) return self::$columnExistsCache[$key];
    try {
      return self::$columnExistsCache[$key] = self::columnExistsOn(self::connection($module), $table, $column);
    } catch (Throwable $e) {
      if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e, ['table'=>$table,'column'=>$column,'module'=>$module]);
      return self::$columnExistsCache[$key] = false;
    }
  }




  public static function ensureTableFromSql(string $table): bool {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
      throw new InvalidArgumentException('Tabela inválida para criação pelo SQL oficial.');
    }
    $pdo = self::forTable($table);
    if (self::tableExistsOn($pdo, $table)) return false;
    $paths = [
      __DIR__.'/../../database/install_final_current.sql',
      __DIR__.'/../../database/repair_current.sql',
      __DIR__.'/../../database/modules/core.sql',
      __DIR__.'/../../database/modules/fila.sql',
      __DIR__.'/../../database/modules/observabilidade.sql',
      __DIR__.'/../../database/modules/pedidos.sql',
      __DIR__.'/../../database/modules/produtos.sql',
      __DIR__.'/../../database/modules/estoque.sql',
      __DIR__.'/../../database/modules/fiscal.sql',
      __DIR__.'/../../database/modules/backups.sql',
    ];
    foreach ($paths as $path) {
      if (!is_file($path)) continue;
      $sql = file_get_contents($path) ?: '';
      $pattern = '/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?'.preg_quote($table,'/').'`?\s*\((?:.|\n)*?\)\s*ENGINE\s*=\s*InnoDB(?:\s+DEFAULT)?\s+CHARSET\s*=\s*utf8mb4(?:\s+COLLATE\s*=\s*[a-zA-Z0-9_]+)?(?:\s+[A-Z_]+(?:\s*=\s*[^;\s]+)?)*\s*;?/i';
      if (preg_match($pattern, $sql, $m)) {
        $pdo->exec($m[0]);
        self::clearSchemaMetadataCache();
        if (!self::tableExistsOn($pdo, $table)) {
          $diag = self::connectionDiagnostics($pdo);
          throw new RuntimeException(
            'CREATE TABLE foi executado, mas a tabela não ficou acessível na mesma conexão. '.
            'Banco selecionado: '.($diag['database'] ?: 'nenhum').'; usuário MySQL: '.($diag['current_user'] ?: 'não identificado').
            '. Verifique privilégios CREATE/SELECT e se config.php aponta para o banco correto.'
          );
        }
        return true;
      }
    }
    throw new RuntimeException('CREATE TABLE oficial não encontrado para '.$table);
  }


  public static function currentDatabaseName(PDO $pdo): string {
    try { return trim((string)$pdo->query('SELECT DATABASE()')->fetchColumn()); }
    catch (Throwable $e) { return ''; }
  }

  /**
   * Diagnóstico sem segredos da conexão usada pelo reparo de schema.
   * Útil em cPanel/hospedagem compartilhada, onde INFORMATION_SCHEMA pode ser limitado.
   */
  public static function connectionDiagnostics(PDO $pdo): array {
    $diag = ['database'=>'','current_user'=>'','session_user'=>'','server_version'=>'','driver'=>''];
    try {
      $row = $pdo->query('SELECT DATABASE() AS db, CURRENT_USER() AS current_user, USER() AS session_user, VERSION() AS server_version')->fetch(PDO::FETCH_ASSOC) ?: [];
      $diag['database'] = trim((string)($row['db'] ?? ''));
      $diag['current_user'] = trim((string)($row['current_user'] ?? ''));
      $diag['session_user'] = trim((string)($row['session_user'] ?? ''));
      $diag['server_version'] = trim((string)($row['server_version'] ?? ''));
    } catch (Throwable $e) {
      $diag['database'] = self::currentDatabaseName($pdo);
    }
    try { $diag['driver'] = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME); } catch (Throwable $e) {}
    return $diag;
  }

  /**
   * Verificação robusta: consulta direta primeiro e metadados como fallback.
   * A consulta direta evita falso negativo em provedores que restringem INFORMATION_SCHEMA
   * ou não aceitam placeholders em SHOW TABLES.
   */
  /**
   * Inspeciona uma tabela sem transformar falta de privilégio/conexão em "tabela ausente".
   * @return array{exists:bool,error:?string,sqlstate:?string,driver_code:?int,database:string}
   */
  public static function inspectTableOn(PDO $pdo, string $table): array {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
      return ['exists'=>false,'error'=>'Nome de tabela inválido.','sqlstate'=>null,'driver_code'=>null,'database'=>self::currentDatabaseName($pdo)];
    }
    $database = self::currentDatabaseName($pdo);
    $last = null;
    $directMissing = false;
    try {
      $pdo->query('SELECT 1 FROM `'.$table.'` LIMIT 0');
      return ['exists'=>true,'error'=>null,'sqlstate'=>null,'driver_code'=>null,'database'=>$database];
    } catch (PDOException $e) {
      $last = $e;
      $info = $e->errorInfo ?? [];
      $state = (string)($info[0] ?? $e->getCode());
      $driver = isset($info[1]) ? (int)$info[1] : null;
      $message = strtolower($e->getMessage());
      $missingByMessage = str_contains($message, "doesn't exist") || str_contains($message, 'does not exist') || str_contains($message, 'no such table');
      if (!in_array($state, ['42S02'], true) && !in_array($driver, [1146], true) && !$missingByMessage) {
        return ['exists'=>false,'error'=>self::schemaInspectionError($e, $table, null),'sqlstate'=>$state,'driver_code'=>$driver,'database'=>$database];
      }
      $directMissing = true;
    } catch (Throwable $e) {
      return ['exists'=>false,'error'=>'Falha ao consultar tabela '.$table.': '.$e->getMessage(),'sqlstate'=>null,'driver_code'=>null,'database'=>$database];
    }

    if ($database !== '') {
      try {
        $st = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1');
        $st->execute([$database, $table]);
        if ($st->fetchColumn() !== false) return ['exists'=>true,'error'=>null,'sqlstate'=>null,'driver_code'=>null,'database'=>$database];
      } catch (PDOException $e) {
        $info = $e->errorInfo ?? [];
        $state = (string)($info[0] ?? $e->getCode());
        $driver = isset($info[1]) ? (int)$info[1] : null;
        if (!$directMissing && (!in_array($state, ['42000'], true) || !in_array($driver, [1142,1227], true))) $last = $e;
      }
    }

    try {
      $quoted = $pdo->quote($table);
      if (is_string($quoted) && $quoted !== '') {
        $st = $pdo->query('SHOW TABLES LIKE '.$quoted);
        if ($st && $st->fetchColumn() !== false) return ['exists'=>true,'error'=>null,'sqlstate'=>null,'driver_code'=>null,'database'=>$database];
      }
    } catch (PDOException $e) {
      $last = $e;
    }

    if ($last instanceof PDOException) {
      $info = $last->errorInfo ?? [];
      $state = (string)($info[0] ?? $last->getCode());
      $driver = isset($info[1]) ? (int)$info[1] : null;
      $message = strtolower($last->getMessage());
      $missingByMessage = str_contains($message, "doesn't exist") || str_contains($message, 'does not exist') || str_contains($message, 'no such table');
      if (!in_array($state, ['42S02'], true) && !in_array($driver, [1146], true) && !$missingByMessage) {
        return ['exists'=>false,'error'=>self::schemaInspectionError($last, $table, null),'sqlstate'=>$state,'driver_code'=>$driver,'database'=>$database];
      }
    }
    return ['exists'=>false,'error'=>null,'sqlstate'=>'42S02','driver_code'=>1146,'database'=>$database];
  }

  private static function schemaInspectionError(PDOException $e, string $table, ?string $column): string {
    $info = $e->errorInfo ?? [];
    $state = (string)($info[0] ?? $e->getCode());
    $driver = isset($info[1]) ? (int)$info[1] : 0;
    $target = $column === null ? 'tabela '.$table : 'coluna '.$table.'.'.$column;
    if (in_array($driver, [1044,1045,1142,1143,1227], true) || $state === '42000') {
      return 'Permissão MySQL insuficiente ao consultar '.$target.' (SQLSTATE '.$state.', código '.$driver.').';
    }
    if (in_array($driver, [1049], true)) return 'Banco de dados inexistente ou não selecionado ao consultar '.$target.' (código 1049).';
    if (in_array($driver, [2006,2013], true)) return 'Conexão MySQL perdida ao consultar '.$target.' (código '.$driver.').';
    return 'Falha MySQL ao consultar '.$target.' (SQLSTATE '.$state.', código '.$driver.'): '.$e->getMessage();
  }

  public static function tableExistsOn(PDO $pdo, string $table): bool {
    $inspection = self::inspectTableOn($pdo, $table);
    if (!empty($inspection['error'])) throw new RuntimeException((string)$inspection['error']);
    return !empty($inspection['exists']);
  }

  public static function columnExistsOn(PDO $pdo, string $table, string $column): bool {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) return false;

    try {
      $pdo->query('SELECT `'.$column.'` FROM `'.$table.'` LIMIT 0');
      return true;
    } catch (Throwable $directError) {}

    $database = self::currentDatabaseName($pdo);
    if ($database !== '') {
      try {
        $st = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
        $st->execute([$database, $table, $column]);
        if ($st->fetchColumn() !== false) return true;
      } catch (Throwable $metadataError) {}
    }

    try {
      $quoted = $pdo->quote($column);
      if (is_string($quoted) && $quoted !== '') {
        $st = $pdo->query('SHOW COLUMNS FROM `'.$table.'` LIKE '.$quoted);
        if ($st && $st->fetchColumn() !== false) return true;
      }
    } catch (Throwable $showError) {}

    return false;
  }

  public static function indexExistsOn(PDO $pdo, string $table, string $index): bool {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $index)) return false;
    $database = self::currentDatabaseName($pdo);
    if ($database !== '') {
      try {
        $st = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1');
        $st->execute([$database, $table, $index]);
        if ($st->fetchColumn() !== false) return true;
      } catch (Throwable $e) {
        if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e, ['table'=>$table,'index'=>$index]);
      }
    }
    try {
      $quoted = $pdo->quote($index);
      if (!is_string($quoted) || $quoted === '') return false;
      $st = $pdo->query('SHOW INDEX FROM `'.$table.'` WHERE Key_name = '.$quoted);
      return $st && $st->fetchColumn() !== false;
    } catch (Throwable $e) {
      if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e, ['table'=>$table,'index'=>$index]);
      return false;
    }
  }

  public static function addColumnIfMissing(string $table, string $column, string $definition): bool {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
      throw new InvalidArgumentException('Tabela/coluna inválida para alteração de schema.');
    }
    $pdo = self::forTable($table);
    if (self::columnExistsOn($pdo, $table, $column)) return false;
    try {
      $pdo->exec('ALTER TABLE `'.$table.'` ADD COLUMN `'.$column.'` '.$definition);
      self::clearSchemaMetadataCache();
      return true;
    } catch (PDOException $e) {
      $msg = strtolower($e->getMessage());
      if ((string)$e->getCode() === '42S21' || str_contains($msg, 'duplicate column') || str_contains($msg, '1060')) return false;
      throw $e;
    }
  }

  public static function addIndexIfMissing(string $table, string $index, string $definition): bool {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $index)) {
      throw new InvalidArgumentException('Tabela/índice inválido para alteração de schema.');
    }
    $pdo = self::forTable($table);
    if (self::indexExistsOn($pdo, $table, $index)) return false;
    try {
      $pdo->exec('ALTER TABLE `'.$table.'` ADD '.$definition);
      self::clearSchemaMetadataCache();
      return true;
    } catch (PDOException $e) {
      $msg = strtolower($e->getMessage());
      $driverCode=(int)($e->errorInfo[1]??0);
      if ($driverCode===1061 || str_contains($msg, 'duplicate key name') || str_contains($msg, '1061')) return false;
      throw $e;
    }
  }

  public static function dropIndexIfExists(string $table, string $index): bool {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $index)) {
      throw new InvalidArgumentException('Tabela/índice inválido para alteração de schema.');
    }
    $pdo = self::forTable($table);
    if (!self::indexExistsOn($pdo, $table, $index)) return false;
    try {
      $pdo->exec('ALTER TABLE `'.$table.'` DROP INDEX `'.$index.'`');
      self::clearSchemaMetadataCache();
      return true;
    } catch (PDOException $e) {
      $msg = strtolower($e->getMessage());
      if (str_contains($msg, 'check that column/key exists') || str_contains($msg, '1091') || str_contains($msg, "can't drop")) return false;
      throw $e;
    }
  }

  public static function recordMigration(string $migration, string $checksum = '', string $message = 'Migração registrada.', string $status = 'aplicada'): void {
    $pdo = self::forTable('schema_migrations');
    self::ensureSchemaMigrations($pdo);
    $st = $pdo->prepare('INSERT INTO schema_migrations(migration, checksum, status, mensagem) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE checksum=VALUES(checksum), status=VALUES(status), mensagem=VALUES(mensagem), aplicada_em=CURRENT_TIMESTAMP');
    $st->execute([$migration, $checksum, $status, $message]);
  }

  public static function ensureSchemaMigrations(?PDO $pdo = null): void {
    $pdo = $pdo ?: self::forTable('schema_migrations');
    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
      id BIGINT AUTO_INCREMENT PRIMARY KEY,
      migration VARCHAR(180) NOT NULL UNIQUE,
      version VARCHAR(40) NULL,
      description TEXT NULL,
      checksum VARCHAR(128) NULL,
      status ENUM('aplicada','falha') DEFAULT 'aplicada',
      mensagem TEXT NULL,
      aplicada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_schema_migrations_status(status),
      INDEX idx_schema_migrations_data(aplicada_em)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Compatibilidade com instalações antigas que possuíam apenas applied_at.
    $columns = [
      'version' => 'VARCHAR(40) NULL',
      'description' => 'TEXT NULL',
      'checksum' => 'VARCHAR(128) NULL',
      'status' => "ENUM('aplicada','falha') DEFAULT 'aplicada'",
      'mensagem' => 'TEXT NULL',
      'aplicada_em' => 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP',
    ];
    foreach ($columns as $column => $definition) {
      if (!self::columnExistsOn($pdo, 'schema_migrations', $column)) {
        try { $pdo->exec('ALTER TABLE schema_migrations ADD COLUMN `'.$column.'` '.$definition); }
        catch (PDOException $e) {
          $msg = strtolower($e->getMessage());
          if (!str_contains($msg, 'duplicate column') && !str_contains($msg, '1060')) throw $e;
        }
      }
    }
  }

  public static function execIdempotent(PDO $pdo, string $sql): bool {
    try { $pdo->exec($sql); self::clearSchemaMetadataCache(); return true; }
    catch (PDOException $e) {
      $msg = strtolower($e->getMessage());
      $code = (string)$e->getCode();
      if ($code === '42S21' || str_contains($msg,'duplicate column') || str_contains($msg,'1060')) return false;
      if ($code === '23000' || str_contains($msg,'duplicate entry') || str_contains($msg,'1062')) return false;
      if (str_contains($msg,'duplicate key name') || str_contains($msg,'1061')) return false;
      if (str_contains($msg,'already exists')) return false;
      throw $e;
    }
  }

  public static function tableConnectionForSql(string $sql): PDO {
    if (preg_match('/\b(?:FROM|INTO|UPDATE|JOIN)\s+`?([a-zA-Z0-9_]+)`?/i', $sql, $m)) {
      return self::forTable($m[1]);
    }
    return self::connection('core');
  }

  private static function normalizeModule(string $module): string {
    $module = strtolower(trim($module));
    return preg_replace('/[^a-z0-9_]/', '', $module) ?: 'core';
  }

  private static function handleConnectionError(PDOException $e, array $db, string $module): void {
    $msg = $e->getMessage();
    $isUnknownDatabase = str_contains($msg, 'Unknown database') || (string)$e->getCode() === '1049';
    if (!$isUnknownDatabase || PHP_SAPI === 'cli') return;

    $html = '<div style="font-family:Arial;padding:20px;background:#fff3cd;border:1px solid #ffe69c;border-radius:8px">'
      .'<h3>Banco de dados do módulo não encontrado</h3>'
      .'<p>Módulo: <b>'.htmlspecialchars($module, ENT_QUOTES, 'UTF-8').'</b></p>'
      .'<p>Banco: <b>'.htmlspecialchars($db['name'], ENT_QUOTES, 'UTF-8').'</b></p>'
      .'<p>Acesse <b>public/install.php</b> ou execute <b>'.(class_exists('SystemVersionService')?SystemVersionService::installSql():'database/install_final_current.sql').'</b>.</p>'
      .'</div>';
    if (headers_sent()) { echo $html; exit; }
    header('Location: install.php?erro=banco-modulo-nao-encontrado&modulo=' . urlencode($module) . '&db=' . urlencode((string)$db['name']));
    exit;
  }
}
