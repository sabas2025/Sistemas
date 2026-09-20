<?php
/**
 * Serviço de migração versionada e idempotente.
 * Camada controlada para bases existentes. Instalações novas usam install.php;
 * somente o repair_current.sql desta release pode ser usado como fallback manual.
 */
class SchemaMigrationService {
  public static function applyEnterpriseCore(bool $dryRun = false): array {
    $trace = class_exists('RequestContext') ? RequestContext::id() : null;
    $result = [
      'version' => class_exists('SystemVersionService') ? SystemVersionService::version() : 'V104.49.3',
      'migration' => 'v104_49_2_enterprise_map_recovery_r4',
      'dry_run' => $dryRun,
      'trace_id' => $trace,
      'applied' => [],
      'skipped' => [],
      'errors' => [],
      'database_diagnostics' => [],
    ];
    $connectionReady = true;

    // Diagnóstico da conexão REAL usada pela recuperação. Não expõe senha/token.
    try {
      $cfg = class_exists('App') ? App::config() : [];
      $corePdo = Database::getConnection('core');
      $diag = Database::connectionDiagnostics($corePdo);
      $diag['configured_database'] = (string)($cfg['db']['name'] ?? '');
      $diag['storage_mode'] = (string)($cfg['db_storage_mode'] ?? 'automatico/single');
      $diag['single_database_mode'] = Database::isSingleDatabaseMode($cfg);
      $result['database_diagnostics'] = $diag;
      if (($diag['database'] ?? '') === '') {
        $result['errors'][] = 'conexão: nenhum banco está selecionado na conexão PDO. Revise db.name em config/config.php.';
        $connectionReady = false;
      } elseif (($diag['configured_database'] ?? '') !== '' && strcasecmp((string)$diag['database'], (string)$diag['configured_database']) !== 0) {
        $result['errors'][] = 'conexão: o banco selecionado pela PDO ('.$diag['database'].') difere do banco configurado ('.$diag['configured_database'].').';
        $connectionReady = false;
      }
    } catch (Throwable $e) {
      $result['errors'][] = 'conexão/diagnóstico: '.$e->getMessage();
      $connectionReady = false;
    }

    if (!$connectionReady) {
      $result['aborted'] = true;
      $result['recommendation'] = 'Corrija o banco selecionado/credenciais em config/config.php antes de executar qualquer migration.';
      return $result;
    }

    // Primeiro garante as tabelas-base exigidas pelo próprio sistema e pelos testes.
    // A criação usa apenas os SQLs oficiais do projeto e nunca apaga dados.
    $baseTables = array_values(array_unique(array_merge([
      'usuarios','configuracoes_integracao','fila_integracao','fila_morta','orquestracao_fluxos_historico','fila_reprocessamento_historico',
      'comercial_clientes_licencas','comercial_conectores_catalogo','comercial_cobranca_faturas','comercial_demo_ambientes','comercial_suporte_chamados','comercial_sla_eventos',
      'comercial_demo_reset_logs','comercial_license_remote_cache','tenant_scope_audit_snapshots','system_release_checks',
      'comercial_billing_provider_events','comercial_billing_gateway_events','connector_operational_checks','comercial_license_checks',
      'security_events','ips_bloqueados','rate_limit_hits','selftest_relatorios',
      'pedidos_hub','pedidos_payloads','pedidos_status_historico','pedidos_nfe_xml','vsm_endpoints','vsm_campos_mapeamento','vsm_endpoint_logs'
    ], self::enterpriseRecoveryTables())));
    foreach ($baseTables as $table) {
      try {
        if (Database::tableExists($table)) { $result['skipped'][] = 'base '.$table.' (já existe)'; continue; }
        if ($dryRun) { $result['skipped'][] = 'base '.$table.' (dry-run)'; continue; }
        Database::ensureTableFromSql($table);
        Database::clearSchemaMetadataCache();
        if (!Database::tableExists($table)) {
          $pdoCheck = Database::forTable($table);
          $diagTable = Database::connectionDiagnostics($pdoCheck);
          throw new RuntimeException('Tabela não foi localizada após criação no banco '.(($diagTable['database'] ?? '') ?: 'não identificado').' com usuário '.(($diagTable['current_user'] ?? '') ?: 'não identificado').'.');
        }
        $result['applied'][] = 'base '.$table;
      } catch (Throwable $e) {
        $result['errors'][] = 'base '.$table.': '.$e->getMessage();
        $result['aborted'] = true;
        $result['recommendation'] = 'O reparo foi interrompido no primeiro erro para evitar mensagens repetidas. Confirme banco selecionado e privilégios CREATE, ALTER, INDEX e SELECT do usuário MySQL.';
        break;
      }
    }

    if (!empty($result['aborted'])) return $result;

    try { if (!$dryRun) Database::ensureSchemaMigrations(); else $result['skipped'][] = 'schema_migrations (dry-run)'; }
    catch (Throwable $e) { $result['errors'][] = 'schema_migrations: '.$e->getMessage(); }

    $statements = self::enterpriseSqlStatements();
    foreach ($statements as $name => $sql) {
      try {
        if ($dryRun) { $result['skipped'][] = $name.' (dry-run)'; continue; }
        $pdo = self::pdoForStatement($sql);
        Database::execIdempotent($pdo, $sql);
        $result['applied'][] = $name;
      } catch (Throwable $e) {
        $result['errors'][] = $name.': '.$e->getMessage();
      }
    }

    if (!$dryRun) {
      Database::clearSchemaMetadataCache();
      $columns = [
        ['fila_integracao','idempotency_key','VARCHAR(190) NULL'],
        ['fila_integracao','locked_by','VARCHAR(120) NULL'],
        ['fila_integracao','locked_at','DATETIME NULL'],
        ['fila_integracao','lease_expires_at','DATETIME NULL'],
        ['fila_integracao','heartbeat_at','DATETIME NULL'],
        ['configuracoes_integracao','queue_processing_timeout_minutes','INT DEFAULT 30'],
        ['configuracoes_integracao','queue_lease_minutes','INT DEFAULT 5'],
        ['configuracoes_integracao','queue_lease_by_type_json','LONGTEXT NULL'],
        ['configuracoes_integracao','vsm_url_consulta',"VARCHAR(255) DEFAULT ''"],
        ['configuracoes_integracao','vsm_api_principal',"VARCHAR(40) DEFAULT 'pedidos-integradora'"],
        ['configuracoes_integracao','vsm_api_loja',"VARCHAR(40) DEFAULT 'desativado'"],
        ['configuracoes_integracao','vsm_swagger_integradora',"VARCHAR(255) NULL"],
        ['configuracoes_integracao','vsm_swagger_loja',"VARCHAR(255) NULL"],
        ['configuracoes_integracao','vsm_waf_agressivo','TINYINT DEFAULT 0'],
        ['configuracoes_integracao','vsm_api_observacao','TEXT NULL'],
        ['configuracoes_integracao','vsm_ambiente',"VARCHAR(30) DEFAULT 'homologacao'"],
        ['configuracoes_integracao','vsm_producao_liberada','TINYINT DEFAULT 0'],
        ['configuracoes_integracao','vsm_ultimo_teste_ok','TINYINT DEFAULT 0'],
        ['configuracoes_integracao','vsm_ultimo_teste_em','DATETIME NULL'],
        ['configuracoes_integracao','vsm_host_producao_liberado','VARCHAR(255) NULL'],
        ['configuracoes_integracao','vsm_producao_liberada_em','DATETIME NULL'],
        ['configuracoes_integracao','vsm_producao_liberada_por','INT NULL'],
        ['configuracoes_integracao','sync_ordem_envio','TEXT NULL'],
        ['configuracoes_integracao','sync_receber_pedidos_vsm','TINYINT DEFAULT 0'],
        ['configuracoes_integracao','sync_enviar_pedido_tiny','TINYINT DEFAULT 0'],
        ['configuracoes_integracao','sync_vsm_enviar_estoque_tiny','TINYINT DEFAULT 1'],
        ['configuracoes_integracao','sync_vsm_status_produto_tiny','TINYINT DEFAULT 1'],
        ['configuracoes_integracao','sync_vsm_enviar_nota_tiny','TINYINT DEFAULT 1'],
        ['configuracoes_integracao','sync_bloquear_produto_novo_vsm','TINYINT DEFAULT 1'],
        ['configuracoes_integracao','sync_permitir_produto_novo_vsm_manual','TINYINT DEFAULT 1'],
        ['configuracoes_integracao','sync_aprovacao_manual_produto_novo_vsm','TINYINT DEFAULT 1'],
        ['configuracoes_integracao','sync_exigir_categoria_mapeada_vsm','TINYINT DEFAULT 1'],
        ['configuracoes_integracao','sync_permitir_atualizar_produto_existente_vsm','TINYINT DEFAULT 1'],
        ['configuracoes_integracao','sync_permitir_estoque_vsm_tiny','TINYINT DEFAULT 1'],
        ['configuracoes_integracao','sync_permitir_status_vsm_tiny','TINYINT DEFAULT 1'],
        ['configuracoes_integracao','sync_tiny_enviar_nota_vsm','TINYINT DEFAULT 0'],
        ['configuracoes_integracao','sync_tiny_enviar_pedido_vsm','TINYINT DEFAULT 1'],
        ['configuracoes_integracao','sync_tiny_enviar_estoque_vsm','TINYINT DEFAULT 0'],
        ['configuracoes_integracao','sync_tiny_status_produto_vsm','TINYINT DEFAULT 0'],
        ['configuracoes_integracao','sync_tiny_bloquear_produto_novo_vsm','TINYINT DEFAULT 1'],
        ['configuracoes_integracao','sync_exigir_mapeamento_sku','TINYINT DEFAULT 1'],
        ['configuracoes_integracao','sync_exigir_nfe_autorizada','TINYINT DEFAULT 1'],
        ['configuracoes_integracao','fluxo_pedido_vsm_receber','TINYINT DEFAULT 0'],
        ['configuracoes_integracao','fluxo_pedido_vsm_enviar_tiny','TINYINT DEFAULT 0'],
        ['configuracoes_integracao','fluxo_pedido_tiny_enviar_vsm','TINYINT DEFAULT 1'],
        ['configuracoes_integracao','fluxo_nfe_vsm_enviar_tiny','TINYINT DEFAULT 1'],
        ['configuracoes_integracao','fluxo_nfe_tiny_enviar_vsm','TINYINT DEFAULT 0'],
        ['configuracoes_integracao','fluxo_estoque_tiny_enviar_vsm','TINYINT DEFAULT 0'],
        ['configuracoes_integracao','fluxo_estoque_vsm_enviar_tiny','TINYINT DEFAULT 1'],
        ['configuracoes_integracao','fluxo_produto_status_tiny_enviar_vsm','TINYINT DEFAULT 0'],
        ['configuracoes_integracao','fluxo_produto_status_vsm_enviar_tiny','TINYINT DEFAULT 1'],
        ['configuracoes_integracao','fluxo_produto_novo_vsm_bloquear','TINYINT DEFAULT 1'],
        ['configuracoes_integracao','fluxo_produto_novo_tiny_bloquear','TINYINT DEFAULT 1'],
        ['fila_morta','categoria_erro',"VARCHAR(80) NULL"],
        ['fila_morta','severidade',"ENUM('baixa','media','alta','critica') DEFAULT 'media'"],
        ['fila_morta','retryable','TINYINT(1) DEFAULT 1'],
        ['fila_morta','owner_area','VARCHAR(80) NULL'],
        ['fila_morta','acao_recomendada_dlq','TEXT NULL'],
        ['fila_morta','classificado_em','DATETIME NULL'],
        ['integration_idempotency','claim_count','INT DEFAULT 0'],
        ['integration_idempotency','last_duplicate_at','DATETIME NULL'],
        ['integration_idempotency','original_fila_id','BIGINT NULL'],
        ['integration_idempotency','owner_token','CHAR(64) NULL'],
        ['integration_idempotency','locked_until','DATETIME NULL'],
        ['llm_audit_logs','user_id','BIGINT NULL'],
        ['llm_audit_logs','environment',"VARCHAR(30) DEFAULT 'homologacao'"],
        ['llm_audit_logs','approval_required','TINYINT(1) DEFAULT 1'],
        ['llm_audit_logs','real_execution_allowed','TINYINT(1) DEFAULT 0'],
        ['llm_audit_logs','redacted_input_sample','LONGTEXT NULL'],
        ['vsm_endpoints','modo_teste_seguro','TINYINT NOT NULL DEFAULT 1'],
        ['vsm_endpoints','origem','VARCHAR(40) NOT NULL DEFAULT \'manual\''],
        ['vsm_endpoints','contract_verified','TINYINT NOT NULL DEFAULT 0'],
        ['vsm_endpoints','contract_source','VARCHAR(255) NULL'],
        ['vsm_endpoints','contract_checked_at','DATETIME NULL'],
        ['vsm_endpoints','is_template','TINYINT NOT NULL DEFAULT 0'],
        ['vsm_campos_mapeamento','tipo_dado','VARCHAR(50) NULL'],
        ['vsm_campos_mapeamento','valor_padrao','VARCHAR(255) NULL'],
        ['vsm_campos_mapeamento','regra_validacao','VARCHAR(255) NULL'],
        ['vsm_campos_mapeamento','exemplo_payload','TEXT NULL'],
        ['vsm_campos_mapeamento','origem','VARCHAR(40) NOT NULL DEFAULT \'manual\''],
        ['vsm_campos_mapeamento','contract_verified','TINYINT NOT NULL DEFAULT 0'],
        ['vsm_campos_mapeamento','contract_source','VARCHAR(255) NULL'],
        ['vsm_campos_mapeamento','is_template','TINYINT NOT NULL DEFAULT 0'],
        ['vsm_endpoint_logs','modo_teste_seguro','TINYINT NOT NULL DEFAULT 1'],
        ['security_events','trace_id','VARCHAR(80) NULL'],
        ['security_events','tipo','VARCHAR(80) NOT NULL'],
        ['security_events','severidade',"ENUM('baixo','medio','alto','critico') NOT NULL DEFAULT 'medio'"],
        ['security_events','ip','VARCHAR(64) NULL'],
        ['security_events','usuario_id','BIGINT NULL'],
        ['security_events','rota','VARCHAR(190) NULL'],
        ['security_events','metodo','VARCHAR(12) NULL'],
        ['security_events','user_agent','VARCHAR(255) NULL'],
        ['security_events','detalhe','TEXT NULL'],
        ['security_events','contexto','LONGTEXT NULL'],
        ['security_events','created_at','DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'],
        ['ips_bloqueados','ip','VARCHAR(64) NOT NULL'],
        ['ips_bloqueados','motivo','VARCHAR(190) NOT NULL'],
        ['ips_bloqueados','severidade',"ENUM('medio','alto','critico') NOT NULL DEFAULT 'alto'"],
        ['ips_bloqueados','bloqueado_ate','DATETIME NULL'],
        ['ips_bloqueados','ativo','TINYINT(1) NOT NULL DEFAULT 1'],
        ['ips_bloqueados','created_at','DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'],
        ['ips_bloqueados','updated_at','DATETIME NULL'],
        ['rate_limit_hits','ip','VARCHAR(64) NOT NULL'],
        ['rate_limit_hits','usuario_id','BIGINT NULL'],
        ['rate_limit_hits','rota','VARCHAR(190) NOT NULL'],
        ['rate_limit_hits','metodo','VARCHAR(12) NOT NULL'],
        ['rate_limit_hits','janela_inicio','DATETIME NOT NULL'],
        ['rate_limit_hits','hits','INT NOT NULL DEFAULT 1'],
        ['rate_limit_hits','updated_at','DATETIME NULL'],
        ['selftest_relatorios','resumo','TEXT NULL'],
        ['selftest_relatorios','detalhes','LONGTEXT NULL'],
        ['selftest_relatorios','trace_id','VARCHAR(80) NULL'],
        ['selftest_relatorios','criado_em','TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP'],
        ['configuracoes_integracao','produto_novo_aprovacao_modo','VARCHAR(20) DEFAULT \'manual\''],
        ['configuracoes_integracao','produto_novo_auto_fallback_manual','TINYINT DEFAULT 1'],
        ['configuracoes_integracao','produto_novo_auto_exigir_ean','TINYINT DEFAULT 1'],
        ['configuracoes_integracao','produto_novo_auto_exigir_ncm','TINYINT DEFAULT 1'],
        ['configuracoes_integracao','produto_novo_auto_exigir_categoria','TINYINT DEFAULT 1'],
        ['configuracoes_integracao','produto_novo_auto_bloquear_duplicidade','TINYINT DEFAULT 1'],
        ['configuracoes_integracao','pedido_tiny_vsm_validacao_obrigatoria','TINYINT DEFAULT 1'],
        ['configuracoes_integracao','pedido_tiny_vsm_aprovacao_manual','TINYINT DEFAULT 1'],
        ['configuracoes_integracao','pedido_tiny_vsm_auto_enviar_validos','TINYINT DEFAULT 0'],
        ['configuracoes_integracao','pedido_tiny_vsm_exigir_sku_mapeado','TINYINT DEFAULT 1'],
        ['configuracoes_integracao','pedido_tiny_vsm_exigir_cliente_documento','TINYINT DEFAULT 1'],
        ['configuracoes_integracao','pedido_tiny_vsm_exigir_endereco','TINYINT DEFAULT 1'],
        ['configuracoes_integracao','pedido_tiny_vsm_status_permitidos','VARCHAR(255) DEFAULT \'aprovado,pago,faturado,pronto para envio\''],
        ['configuracoes_integracao','vsm_endpoint_pedido','VARCHAR(255) DEFAULT \'/api/pedidos\''],
      ];
      foreach ($columns as [$table,$column,$definition]) {
        try {
          $added = Database::addColumnIfMissing($table, $column, $definition);
          $result[$added ? 'applied' : 'skipped'][] = "column {$table}.{$column}";
        } catch (Throwable $e) { $result['errors'][] = "column {$table}.{$column}: ".$e->getMessage(); }
      }
      $indexes = [
        ['schema_migrations','idx_schema_migrations_status','INDEX idx_schema_migrations_status(status)'],
        ['schema_migrations','idx_schema_migrations_data','INDEX idx_schema_migrations_data(aplicada_em)'],
        ['fila_integracao','idx_fila_status_proxima_prioridade','INDEX idx_fila_status_proxima_prioridade(status, proxima_tentativa, prioridade, id)'],
        ['fila_integracao','idx_fila_trace_tipo_ref','INDEX idx_fila_trace_tipo_ref(trace_id, tipo, referencia)'],
        ['fila_integracao','idx_fila_idempotency_key','INDEX idx_fila_idempotency_key(idempotency_key)'],
        ['fila_integracao','idx_fila_locked','INDEX idx_fila_locked(locked_by, locked_at)'],
        ['fila_integracao','idx_fila_lease','INDEX idx_fila_lease(status, lease_expires_at)'],
        ['vsm_endpoints','idx_vsm_endpoints_categoria','INDEX idx_vsm_endpoints_categoria(categoria)'],
        ['vsm_endpoints','idx_vsm_endpoints_ativo','INDEX idx_vsm_endpoints_ativo(ativo)'],
        ['vsm_endpoints','idx_vsm_endpoints_ordem','INDEX idx_vsm_endpoints_ordem(ordem_execucao)'],
        ['vsm_campos_mapeamento','uk_vsm_campo','UNIQUE KEY uk_vsm_campo(categoria, campo_vsm, campo_hub)'],
        ['vsm_campos_mapeamento','idx_vsm_campos_categoria','INDEX idx_vsm_campos_categoria(categoria)'],
        ['vsm_campos_mapeamento','idx_vsm_campos_ativo','INDEX idx_vsm_campos_ativo(ativo)'],
        ['vsm_endpoint_logs','idx_vsm_logs_endpoint','INDEX idx_vsm_logs_endpoint(endpoint_id)'],
        ['vsm_endpoint_logs','idx_vsm_logs_chave','INDEX idx_vsm_logs_chave(chave)'],
        ['vsm_endpoint_logs','idx_vsm_logs_sucesso','INDEX idx_vsm_logs_sucesso(sucesso)'],
        ['vsm_endpoint_logs','idx_vsm_logs_criado','INDEX idx_vsm_logs_criado(criado_em)'],
        ['integration_idempotency','idx_idemp_lock','INDEX idx_idemp_lock(status, locked_until)'],
        ['fila_morta','idx_fila_morta_categoria_severidade','INDEX idx_fila_morta_categoria_severidade(categoria_erro, severidade, status)'],
        ['metricas_api','idx_metricas_sistema_data_sucesso','INDEX idx_metricas_sistema_data_sucesso(sistema, criado_em, sucesso)'],
        ['auditoria_eventos','idx_auditoria_evento_data','INDEX idx_auditoria_evento_data(evento, criado_em)'],
        ['logs_integracao','idx_logs_trace_data','INDEX idx_logs_trace_data(trace_id, criado_em)'],
      ];
      try {
        if (!Database::tableExists('fila_integracao')) throw new RuntimeException('fila_integracao ausente');
        $pdoFila = Database::forTable('fila_integracao');
        $col = $pdoFila->query("SHOW COLUMNS FROM fila_integracao LIKE 'status'")->fetch();
        $type = strtolower((string)($col['Type'] ?? ''));
        if (!str_contains($type, "'ignorado'")) {
          // P1-04 (reauditoria 2026-08-23): antes este ALTER rodava direto, sem checar se
          // alguma linha existente já tinha um valor de status fora do novo ENUM. Em modo
          // não-estrito o MySQL/MariaDB TRUNCA silenciosamente esse valor para '' - dado
          // corrompido sem erro nenhum. Agora consulta os valores distintos primeiro e só
          // aplica o ALTER se todos já couberem no novo conjunto.
          $novoConjunto = ['pendente','processando','sucesso','erro','falha_definitiva','ignorado'];
          $existentes = $pdoFila->query("SELECT DISTINCT status FROM fila_integracao")->fetchAll(PDO::FETCH_COLUMN);
          $foraDoConjunto = array_values(array_diff(array_map('strval', $existentes), $novoConjunto));
          if ($foraDoConjunto !== []) {
            $result['errors'][] = 'status_ignorado_enum_v104_38: valores existentes fora do novo ENUM, ALTER não aplicado para não truncar dado: '.implode(', ', $foraDoConjunto).'. Trate/mapeie essas linhas manualmente antes de reexecutar.';
          } else {
            $pdoFila->exec("ALTER TABLE fila_integracao MODIFY status ENUM('pendente','processando','sucesso','erro','falha_definitiva','ignorado') DEFAULT 'pendente'");
            $result['applied'][] = 'status_ignorado_enum_v104_38';
          }
        } else {
          $result['skipped'][] = 'status_ignorado_enum_v104_38 (já compatível)';
        }
      } catch (Throwable $e) { $result['errors'][] = 'status_ignorado_enum_v104_38: '. $e->getMessage(); }

      foreach ($indexes as [$table,$index,$definition]) {
        try {
          if (!Database::tableExists($table)) { $result['skipped'][] = "index {$index} ({$table} ausente)"; continue; }
          $added = Database::addIndexIfMissing($table, $index, $definition);
          $result[$added ? 'applied' : 'skipped'][] = "index {$index}";
        } catch (Throwable $e) { $result['errors'][] = "index {$index}: ".$e->getMessage(); }
      }
      Database::clearSchemaMetadataCache();
      $contractStatus = self::status();
      $pending = [];
      foreach (($contractStatus['checks'] ?? []) as $check) {
        if (($check['status'] ?? 'pendente') !== 'ok') $pending[] = (string)($check['item'] ?? 'schema');
      }
      $result['verification'] = [
        'total' => (int)($contractStatus['total'] ?? 0),
        'ok' => (int)($contractStatus['ok'] ?? 0),
        'pending' => $pending,
      ];
      if ($pending) $result['errors'][] = 'verificação final: contratos de schema ainda pendentes: '.implode(', ', $pending);

      try {
        $checksum = self::checksum();
        Database::recordMigration($result['migration'], $checksum, empty($result['errors']) ? 'Enterprise Core e recuperação de schema aplicados.' : 'Enterprise Core aplicado com avisos.', empty($result['errors']) ? 'aplicada' : 'falha');
      } catch (Throwable $e) { $result['errors'][] = 'recordMigration: '.$e->getMessage(); }
    }

    try { Audit::event('schema.enterprise_core.aplicar', empty($result['errors']) ? 'sucesso' : 'alerta', ['mensagem'=>'Enterprise Core V104.49.3-R5 executado com validação final dos contratos de schema.', 'contexto'=>$result]); } catch(Throwable $ignored){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $ignored); }
    return $result;
  }

  /** @return list<string> */
  public static function enterpriseRecoveryTables(): array {
    return [
      'enterprise_quality_gates','enterprise_regression_runs','enterprise_ui_preferences',
      'integration_events','integration_idempotency',
      'llm_approval_queue','llm_audit_logs','llm_policy_settings','llm_prompts','llm_usage_daily',
      'observability_snapshots','worker_heartbeats'
    ];
  }

  /**
   * Reparo focado nas tabelas Enterprise exibidas como ERRO no Mapa do Banco.
   * Executa uma tabela por vez, na conexão oficial do módulo, e valida na mesma PDO.
   */
  public static function applyEnterpriseTableRecovery(bool $dryRun = false): array {
    $result = [
      'version' => class_exists('SystemVersionService') ? SystemVersionService::version() : 'V104.49.3',
      'migration' => '20260713_007_enterprise_map_recovery',
      'dry_run' => $dryRun,
      'trace_id' => class_exists('RequestContext') ? RequestContext::id() : null,
      'applied' => [], 'skipped' => [], 'errors' => [], 'verification' => [],
    ];
    $definitions = self::enterpriseSqlStatements();
    foreach (self::enterpriseRecoveryTables() as $table) {
      try {
        $pdo = Database::forTable($table);
        $inspection = method_exists('Database', 'inspectTableOn')
          ? Database::inspectTableOn($pdo, $table)
          : ['exists'=>Database::tableExistsOn($pdo, $table), 'error'=>null];
        if (!empty($inspection['error'])) {
          throw new RuntimeException((string)$inspection['error']);
        }
        if (!empty($inspection['exists'])) {
          $result['skipped'][] = $table.' (já existe)';
          continue;
        }
        if ($dryRun) {
          $result['skipped'][] = $table.' (dry-run)';
          continue;
        }
        $sql = $definitions[$table] ?? null;
        if (!is_string($sql) || trim($sql) === '') {
          throw new RuntimeException('Definição SQL oficial não localizada.');
        }
        Database::execIdempotent($pdo, $sql);
        Database::clearSchemaMetadataCache();
        $verify = method_exists('Database', 'inspectTableOn')
          ? Database::inspectTableOn($pdo, $table)
          : ['exists'=>Database::tableExistsOn($pdo, $table), 'error'=>null];
        if (!empty($verify['error'])) throw new RuntimeException((string)$verify['error']);
        if (empty($verify['exists'])) {
          $diag = Database::connectionDiagnostics($pdo);
          throw new RuntimeException('CREATE executado, porém a tabela não ficou acessível no banco '.(($diag['database'] ?? '') ?: 'não identificado').' com usuário '.(($diag['current_user'] ?? '') ?: 'não identificado').'.');
        }
        $result['applied'][] = $table;
      } catch (Throwable $e) {
        $result['errors'][] = $table.': '.$e->getMessage();
        break;
      }
    }
    Database::clearSchemaMetadataCache();
    $pending = [];
    foreach (self::enterpriseRecoveryTables() as $table) {
      try {
        if (!Database::tableExists($table)) $pending[] = $table;
      } catch (Throwable $e) {
        $pending[] = $table.' ('.$e->getMessage().')';
      }
    }
    $result['verification'] = [
      'total'=>count(self::enterpriseRecoveryTables()),
      'ok'=>count(self::enterpriseRecoveryTables())-count($pending),
      'pending'=>$pending,
    ];
    if ($pending && !$result['errors']) $result['errors'][] = 'Tabelas ainda pendentes: '.implode(', ', $pending);
    if (!$dryRun) {
      try {
        Database::recordMigration(
          '20260713_007_enterprise_map_recovery',
          is_file(__DIR__.'/../../database/migrations/20260713_007_enterprise_map_recovery.sql') ? hash_file('sha256', __DIR__.'/../../database/migrations/20260713_007_enterprise_map_recovery.sql') : '',
          empty($result['errors']) ? 'Recuperação das 12 tabelas Enterprise concluída.' : 'Recuperação Enterprise concluída com pendências.',
          empty($result['errors']) ? 'aplicada' : 'falha'
        );
      } catch (Throwable $e) { $result['errors'][] = 'recordMigration: '.$e->getMessage(); }
    }
    try { Audit::event('schema.enterprise_tables.recovery', empty($result['errors']) ? 'sucesso' : 'alerta', ['mensagem'=>'Reparo focado das 12 tabelas Enterprise executado.', 'contexto'=>$result]); }
    catch (Throwable $ignored) { if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $ignored); }
    return $result;
  }

  /** @return list<string> */
  public static function requiredTables(): array {
    return [
      'schema_migrations','integration_events','integration_idempotency','worker_heartbeats','observability_snapshots','enterprise_quality_gates','enterprise_regression_runs','enterprise_ui_preferences','llm_prompts','llm_policy_settings','llm_approval_queue','llm_usage_daily','llm_audit_logs',
      'fila_reprocessamento_historico','security_events','ips_bloqueados','rate_limit_hits','selftest_relatorios','vsm_endpoints','vsm_campos_mapeamento','vsm_endpoint_logs','pedidos_hub','pedidos_payloads','pedidos_status_historico','pedidos_nfe_xml',
      'comercial_clientes_licencas','comercial_conectores_catalogo','comercial_cobranca_faturas','comercial_demo_ambientes','comercial_suporte_chamados','comercial_sla_eventos',
      'comercial_demo_reset_logs','comercial_license_remote_cache','tenant_scope_audit_snapshots','system_release_checks','comercial_billing_provider_events','comercial_billing_gateway_events','connector_operational_checks','comercial_license_checks'
    ];
  }

  public static function status(): array {
    $rows = [];
    $ok = 0;
    foreach (self::requiredTables() as $table) {
      try {
        $exists = Database::tableExists($table);
        self::appendStatusCheck(
          $rows,
          $ok,
          'table:'.$table,
          $exists,
          $exists ? 'Tabela disponível.' : 'Tabela ausente; aplique Enterprise Core.'
        );
      } catch (Throwable $e) {
        self::appendStatusCheck($rows, $ok, 'table:'.$table, false, 'Não foi possível inspecionar a tabela: '.$e->getMessage());
      }
    }

    foreach (self::requiredColumnContracts() as $table => $columns) {
      try {
        $pdo = Database::forTable($table);
        $metadata = self::columnMetadata($pdo, $table);
        foreach ($columns as $column => $contract) {
          $item = 'column:'.$table.'.'.$column;
          if (!isset($metadata[$column])) {
            self::appendStatusCheck($rows, $ok, $item, false, 'Coluna obrigatória ausente; aplique Enterprise Core.');
            continue;
          }
          $violations = self::columnContractViolations($metadata[$column], $contract);
          self::appendStatusCheck(
            $rows,
            $ok,
            $item,
            $violations === [],
            $violations === [] ? 'Tipo, nulabilidade e valor padrão compatíveis.' : 'Contrato incompatível: '.implode('; ', $violations).'.'
          );
        }
      } catch (Throwable $e) {
        foreach (array_keys($columns) as $column) {
          self::appendStatusCheck($rows, $ok, 'column:'.$table.'.'.$column, false, 'Não foi possível validar o contrato: '.$e->getMessage());
        }
      }
    }

    foreach (self::requiredIndexContracts() as $table => $indexes) {
      try {
        $metadata = self::indexMetadata(Database::forTable($table), $table);
        foreach ($indexes as $index => $contract) {
          $violations = isset($metadata[$index])
            ? self::indexContractViolations($metadata[$index], $contract)
            : ['índice ausente'];
          self::appendStatusCheck(
            $rows,
            $ok,
            'index:'.$table.'.'.$index,
            $violations === [],
            $violations === [] ? 'Índice crítico compatível.' : 'Contrato incompatível: '.implode('; ', $violations).'; aplique Enterprise Core.'
          );
        }
      } catch (Throwable $e) {
        foreach (array_keys($indexes) as $index) {
          self::appendStatusCheck($rows, $ok, 'index:'.$table.'.'.$index, false, 'Não foi possível validar o índice: '.$e->getMessage());
        }
      }
    }
    $connection = [];
    try {
      $cfg = class_exists('App') ? App::config() : [];
      $connection = Database::connectionDiagnostics(Database::getConnection('core'));
      $connection['configured_database'] = (string)($cfg['db']['name'] ?? '');
      $connection['storage_mode'] = (string)($cfg['db_storage_mode'] ?? 'automatico/single');
      $connection['single_database_mode'] = Database::isSingleDatabaseMode($cfg);
      $selectedDatabase = (string)($connection['database'] ?? '');
      $configuredDatabase = (string)$connection['configured_database'];
      $connectionOk = $selectedDatabase !== ''
        && ($configuredDatabase === '' || strcasecmp($selectedDatabase, $configuredDatabase) === 0);
      self::appendStatusCheck(
        $rows,
        $ok,
        'connection:core_database',
        $connectionOk,
        $connectionOk
          ? 'Conexão core aponta para o banco configurado.'
          : 'Banco selecionado ausente ou diferente do configurado; revise config/config.php.'
      );
    } catch (Throwable $e) {
      $connection = ['error'=>$e->getMessage()];
      self::appendStatusCheck($rows, $ok, 'connection:core_database', false, 'Não foi possível validar a conexão core: '.$e->getMessage());
    }
    return [
      'status'=>$ok===count($rows) ? 'ok' : 'atencao',
      'ok'=>$ok,
      'total'=>count($rows),
      'checks'=>$rows,
      'migration'=>'v104_49_2_enterprise_map_recovery_r4',
      'connection'=>$connection,
    ];
  }

  /**
   * Contratos mínimos que protegem os fluxos Enterprise, fila e catálogo VSM.
   * O Quality Gate é conservador: metadados indisponíveis nunca viram sinal verde.
   * @return array<string,array<string,array{type:string,nullable:bool,default:mixed}>>
   */
  public static function requiredColumnContracts(): array {
    return [
      'schema_migrations' => [
        'migration'=>['type'=>'varchar(180)','nullable'=>false,'default'=>null],
        'version'=>['type'=>'varchar(40)','nullable'=>true,'default'=>null],
        'description'=>['type'=>'text','nullable'=>true,'default'=>null],
        'checksum'=>['type'=>'varchar(128)','nullable'=>true,'default'=>null],
        'status'=>['type'=>"enum('aplicada','falha')",'nullable'=>true,'default'=>'aplicada'],
        'mensagem'=>['type'=>'text','nullable'=>true,'default'=>null],
      ],
      'fila_integracao' => [
        'idempotency_key'=>['type'=>'varchar(190)','nullable'=>true,'default'=>null],
        'locked_by'=>['type'=>'varchar(120)','nullable'=>true,'default'=>null],
        'locked_at'=>['type'=>'datetime','nullable'=>true,'default'=>null],
        'lease_expires_at'=>['type'=>'datetime','nullable'=>true,'default'=>null],
        'heartbeat_at'=>['type'=>'datetime','nullable'=>true,'default'=>null],
      ],
      'configuracoes_integracao' => [
        'vsm_ambiente'=>['type'=>'varchar(30)','nullable'=>true,'default'=>'homologacao'],
        'vsm_producao_liberada'=>['type'=>'tinyint','nullable'=>true,'default'=>'0'],
        'vsm_ultimo_teste_ok'=>['type'=>'tinyint','nullable'=>true,'default'=>'0'],
        'vsm_ultimo_teste_em'=>['type'=>'datetime','nullable'=>true,'default'=>null],
        'vsm_host_producao_liberado'=>['type'=>'varchar(255)','nullable'=>true,'default'=>null],
        'vsm_producao_liberada_em'=>['type'=>'datetime','nullable'=>true,'default'=>null],
        'vsm_producao_liberada_por'=>['type'=>'int','nullable'=>true,'default'=>null],
      ],
      'vsm_endpoints' => [
        'modo_teste_seguro'=>['type'=>'tinyint','nullable'=>false,'default'=>'1'],
        'origem'=>['type'=>'varchar(40)','nullable'=>false,'default'=>'manual'],
        'contract_verified'=>['type'=>'tinyint','nullable'=>false,'default'=>'0'],
        'contract_source'=>['type'=>'varchar(255)','nullable'=>true,'default'=>null],
        'contract_checked_at'=>['type'=>'datetime','nullable'=>true,'default'=>null],
        'is_template'=>['type'=>'tinyint','nullable'=>false,'default'=>'0'],
      ],
      'vsm_campos_mapeamento' => [
        'tipo_dado'=>['type'=>'varchar(50)','nullable'=>true,'default'=>null],
        'valor_padrao'=>['type'=>'varchar(255)','nullable'=>true,'default'=>null],
        'regra_validacao'=>['type'=>'varchar(255)','nullable'=>true,'default'=>null],
        'exemplo_payload'=>['type'=>'text','nullable'=>true,'default'=>null],
        'origem'=>['type'=>'varchar(40)','nullable'=>false,'default'=>'manual'],
        'contract_verified'=>['type'=>'tinyint','nullable'=>false,'default'=>'0'],
        'contract_source'=>['type'=>'varchar(255)','nullable'=>true,'default'=>null],
        'is_template'=>['type'=>'tinyint','nullable'=>false,'default'=>'0'],
      ],
      'vsm_endpoint_logs' => [
        'modo_teste_seguro'=>['type'=>'tinyint','nullable'=>false,'default'=>'1'],
      ],
    ];
  }

  /** @return array<string,array<string,array{columns:list<string>,unique:bool}>> */
  public static function requiredIndexContracts(): array {
    return [
      'schema_migrations'=>[
        'idx_schema_migrations_status'=>['columns'=>['status'],'unique'=>false],
        'idx_schema_migrations_data'=>['columns'=>['aplicada_em'],'unique'=>false],
      ],
      'fila_integracao'=>[
        'idx_fila_idempotency_key'=>['columns'=>['idempotency_key'],'unique'=>false],
        'idx_fila_status_proxima_prioridade'=>['columns'=>['status','proxima_tentativa','prioridade','id'],'unique'=>false],
        'idx_fila_locked'=>['columns'=>['locked_by','locked_at'],'unique'=>false],
        'idx_fila_lease'=>['columns'=>['status','lease_expires_at'],'unique'=>false],
      ],
      'vsm_endpoints'=>[
        'idx_vsm_endpoints_categoria'=>['columns'=>['categoria'],'unique'=>false],
        'idx_vsm_endpoints_ativo'=>['columns'=>['ativo'],'unique'=>false],
        'idx_vsm_endpoints_ordem'=>['columns'=>['ordem_execucao'],'unique'=>false],
      ],
      'vsm_campos_mapeamento'=>[
        'uk_vsm_campo'=>['columns'=>['categoria','campo_vsm','campo_hub'],'unique'=>true],
        'idx_vsm_campos_categoria'=>['columns'=>['categoria'],'unique'=>false],
        'idx_vsm_campos_ativo'=>['columns'=>['ativo'],'unique'=>false],
      ],
      'vsm_endpoint_logs'=>[
        'idx_vsm_logs_endpoint'=>['columns'=>['endpoint_id'],'unique'=>false],
        'idx_vsm_logs_chave'=>['columns'=>['chave'],'unique'=>false],
        'idx_vsm_logs_sucesso'=>['columns'=>['sucesso'],'unique'=>false],
        'idx_vsm_logs_criado'=>['columns'=>['criado_em'],'unique'=>false],
      ],
    ];
  }

  private static function appendStatusCheck(array &$rows, int &$ok, string $item, bool $passed, string $message): void {
    if ($passed) $ok++;
    $rows[] = ['item'=>$item, 'status'=>$passed?'ok':'pendente', 'mensagem'=>$message];
  }

  /** @return array<string,array<string,mixed>> */
  private static function columnMetadata(PDO $pdo, string $table): array {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) throw new InvalidArgumentException('Tabela inválida para inspeção.');
    $statement = $pdo->query('SHOW FULL COLUMNS FROM `'.$table.'`');
    if (!$statement) throw new RuntimeException('SHOW FULL COLUMNS não retornou metadados.');
    $rows = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $field = (string)($row['Field'] ?? '');
      if ($field !== '') $rows[$field] = $row;
    }
    return $rows;
  }

  /** @return array<string,array{columns:list<string>,unique:bool}> */
  private static function indexMetadata(PDO $pdo, string $table): array {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) throw new InvalidArgumentException('Tabela inválida para inspeção de índices.');
    $statement = $pdo->query('SHOW INDEX FROM `'.$table.'`');
    if (!$statement) throw new RuntimeException('SHOW INDEX não retornou metadados.');
    $indexes = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $name = (string)($row['Key_name'] ?? '');
      $column = (string)($row['Column_name'] ?? '');
      if ($name === '' || $column === '') continue;
      if (!isset($indexes[$name])) $indexes[$name] = ['columns'=>[], 'unique'=>(int)($row['Non_unique'] ?? 1) === 0];
      $sequence = max(1, (int)($row['Seq_in_index'] ?? 1));
      $indexes[$name]['columns'][$sequence - 1] = $column;
    }
    foreach ($indexes as &$index) {
      ksort($index['columns']);
      $index['columns'] = array_values($index['columns']);
    }
    unset($index);
    return $indexes;
  }

  /** @param array{columns:list<string>,unique:bool} $actual @param array{columns:list<string>,unique:bool} $expected @return list<string> */
  private static function indexContractViolations(array $actual, array $expected): array {
    $violations = [];
    if ($actual['columns'] !== $expected['columns']) {
      $violations[] = 'colunas esperadas '.implode(',', $expected['columns']).', encontradas '.implode(',', $actual['columns']);
    }
    if ($actual['unique'] !== $expected['unique']) {
      $violations[] = $expected['unique'] ? 'deve ser UNIQUE' : 'não deve ser UNIQUE';
    }
    return $violations;
  }

  /** @param array{type:string,nullable:bool,default:mixed} $contract @return list<string> */
  private static function columnContractViolations(array $metadata, array $contract): array {
    $violations = [];
    $actualType = self::normalizeColumnType((string)($metadata['Type'] ?? ''));
    $expectedType = self::normalizeColumnType($contract['type']);
    if ($actualType !== $expectedType) $violations[] = 'tipo esperado '.$expectedType.', encontrado '.$actualType;
    $actualNullable = strtoupper((string)($metadata['Null'] ?? 'YES')) === 'YES';
    if ($actualNullable !== $contract['nullable']) $violations[] = 'nulabilidade esperada '.($contract['nullable']?'NULL':'NOT NULL');
    $actualDefault = $metadata['Default'] ?? null;
    if ($contract['default'] === null) {
      if ($actualDefault !== null) $violations[] = 'padrão esperado NULL, encontrado '.(string)$actualDefault;
    } elseif (strcasecmp((string)$actualDefault, (string)$contract['default']) !== 0) {
      $violations[] = 'padrão esperado '.(string)$contract['default'].', encontrado '.($actualDefault === null ? 'NULL' : (string)$actualDefault);
    }
    return $violations;
  }

  private static function normalizeColumnType(string $type): string {
    $type = strtolower(preg_replace('/\s+/', '', trim($type)) ?? trim($type));
    return preg_replace('/^(tinyint|smallint|mediumint|int|bigint)\(\d+\)/', '$1', $type) ?? $type;
  }

  private static function pdoForStatement(string $sql): PDO {
    if (preg_match('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?([a-zA-Z0-9_]+)`?/i', $sql, $m)) return Database::forTable($m[1]);
    return Database::tableConnectionForSql($sql);
  }

  private static function checksum(): string {
    $files = [
      __DIR__.'/../../database/migrations/20260712_001_queue_oauth_concurrency.sql',
      __DIR__.'/../../database/migrations/20260712_002_schema_runtime_vsm_contract.sql',
      __DIR__.'/../../database/migrations/20260712_003_missing_core_tables.sql',
      __DIR__.'/../../database/migrations/20260712_004_vsm_security_selftest_recovery.sql',
      __DIR__.'/../../database/migrations/20260713_007_enterprise_map_recovery.sql',
    ];
    $hashes=[];
    foreach($files as $file){ if(is_file($file))$hashes[]=hash_file('sha256',$file); }
    $hashes[] = hash_file('sha256', __FILE__);
    $databaseClass = __DIR__.'/../Core/Database.php';
    if (is_file($databaseClass)) $hashes[] = hash_file('sha256', $databaseClass);
    return $hashes ? hash('sha256',implode('|',$hashes)) : hash('sha256',json_encode(self::enterpriseSqlStatements()));
  }

  /** @return array<string,string> */
  public static function enterpriseSqlStatements(): array {
    return [
      'fila_reprocessamento_historico' => "CREATE TABLE IF NOT EXISTS fila_reprocessamento_historico (
        id BIGINT AUTO_INCREMENT PRIMARY KEY, fila_id BIGINT NOT NULL, status_anterior VARCHAR(40) NULL,
        tentativas_anteriores INT DEFAULT 0, codigo_erro_anterior VARCHAR(120) NULL, retorno_anterior LONGTEXT NULL,
        locked_by_anterior VARCHAR(120) NULL, solicitado_por BIGINT NULL, motivo VARCHAR(500) NULL, trace_id VARCHAR(80) NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_fila_reproc_fila(fila_id),
        INDEX idx_fila_reproc_trace(trace_id), INDEX idx_fila_reproc_data(criado_em)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      'integration_events' => "CREATE TABLE IF NOT EXISTS integration_events (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        event_uuid VARCHAR(80) NOT NULL UNIQUE,
        fila_id BIGINT NULL,
        idempotency_key VARCHAR(190) NULL,
        source_system VARCHAR(40) NOT NULL,
        target_system VARCHAR(40) NOT NULL,
        entity_type VARCHAR(60) NOT NULL,
        entity_id VARCHAR(160) NULL,
        operation VARCHAR(80) NOT NULL,
        status ENUM('recebido','processando','sucesso','erro','ignorado','reprocessado') DEFAULT 'recebido',
        payload_hash VARCHAR(128) NULL,
        response_hash VARCHAR(128) NULL,
        error_code VARCHAR(120) NULL,
        error_message TEXT NULL,
        attempts INT DEFAULT 0,
        trace_id VARCHAR(80) NULL,
        metadata_json LONGTEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        finished_at DATETIME NULL,
        INDEX idx_ie_status_created(status, created_at),
        INDEX idx_ie_entity(entity_type, entity_id),
        INDEX idx_ie_trace(trace_id),
        INDEX idx_ie_idempotency(idempotency_key),
        INDEX idx_ie_source_target(source_system, target_system)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
      'integration_idempotency' => "CREATE TABLE IF NOT EXISTS integration_idempotency (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        idempotency_key VARCHAR(190) NOT NULL UNIQUE,
        entity_type VARCHAR(60) NULL,
        entity_id VARCHAR(160) NULL,
        operation VARCHAR(80) NULL,
        request_hash VARCHAR(128) NULL,
        response_hash VARCHAR(128) NULL,
        status ENUM('claimed','completed','failed','expired') DEFAULT 'claimed',
        trace_id VARCHAR(80) NULL,
        expires_at DATETIME NULL,
        metadata_json LONGTEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_idemp_status(status),
        INDEX idx_idemp_entity(entity_type, entity_id),
        INDEX idx_idemp_expires(expires_at)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
      'worker_heartbeats' => "CREATE TABLE IF NOT EXISTS worker_heartbeats (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        worker_name VARCHAR(120) NOT NULL UNIQUE,
        status ENUM('iniciando','rodando','ok','erro','parado') DEFAULT 'rodando',
        pid INT NULL,
        host VARCHAR(160) NULL,
        started_at DATETIME NULL,
        last_seen_at DATETIME NOT NULL,
        last_error TEXT NULL,
        metrics_json LONGTEXT NULL,
        trace_id VARCHAR(80) NULL,
        INDEX idx_worker_status(status),
        INDEX idx_worker_seen(last_seen_at)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
      'observability_snapshots' => "CREATE TABLE IF NOT EXISTS observability_snapshots (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        metric_key VARCHAR(160) NOT NULL,
        metric_value DECIMAL(18,4) NULL,
        status ENUM('ok','atencao','erro','critico') DEFAULT 'ok',
        tags_json LONGTEXT NULL,
        trace_id VARCHAR(80) NULL,
        captured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_obs_key_time(metric_key, captured_at),
        INDEX idx_obs_status(status),
        INDEX idx_obs_trace(trace_id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
      'enterprise_quality_gates' => "CREATE TABLE IF NOT EXISTS enterprise_quality_gates (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        gate_key VARCHAR(120) NOT NULL UNIQUE,
        status ENUM('ok','atencao','bloqueio') DEFAULT 'atencao',
        score INT DEFAULT 0,
        mensagem TEXT NULL,
        detalhes_json LONGTEXT NULL,
        trace_id VARCHAR(80) NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_eqg_status(status)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

      'enterprise_regression_runs' => "CREATE TABLE IF NOT EXISTS enterprise_regression_runs (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        trace_id VARCHAR(80) NULL,
        score INT DEFAULT 0,
        total INT DEFAULT 0,
        ok_count INT DEFAULT 0,
        error_count INT DEFAULT 0,
        results_json LONGTEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_regression_trace(trace_id),
        INDEX idx_regression_created(created_at)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
      'enterprise_ui_preferences' => "CREATE TABLE IF NOT EXISTS enterprise_ui_preferences (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT NULL,
        profile_key VARCHAR(80) NOT NULL DEFAULT 'default',
        density ENUM('compact','comfortable','spacious') DEFAULT 'comfortable',
        theme ENUM('system','light','dark','futurista') DEFAULT 'futurista',
        settings_json LONGTEXT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_ui_user_profile(user_id, profile_key)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
      'llm_prompts' => "CREATE TABLE IF NOT EXISTS llm_prompts (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        prompt_key VARCHAR(120) NOT NULL UNIQUE,
        version VARCHAR(40) NOT NULL DEFAULT '1.0.0',
        status ENUM('rascunho','ativo','arquivado') DEFAULT 'rascunho',
        template LONGTEXT NOT NULL,
        safety_rules LONGTEXT NULL,
        max_tokens INT DEFAULT 2048,
        temperature DECIMAL(4,2) DEFAULT 0.20,
        checksum VARCHAR(128) NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_llm_prompts_status(status)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
      'llm_audit_logs' => "CREATE TABLE IF NOT EXISTS llm_audit_logs (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        trace_id VARCHAR(80) NULL,
        prompt_key VARCHAR(120) NULL,
        provider VARCHAR(60) NULL,
        model VARCHAR(120) NULL,
        environment VARCHAR(30) DEFAULT 'homologacao',
        user_id BIGINT NULL,
        risk_level ENUM('baixo','medio','alto','critico') DEFAULT 'baixo',
        status ENUM('bloqueado','permitido','erro','simulado') DEFAULT 'simulado',
        approval_required TINYINT(1) DEFAULT 1,
        real_execution_allowed TINYINT(1) DEFAULT 0,
        input_hash VARCHAR(128) NULL,
        output_hash VARCHAR(128) NULL,
        redacted_input_sample LONGTEXT NULL,
        tokens_input INT DEFAULT 0,
        tokens_output INT DEFAULT 0,
        cost_estimate DECIMAL(12,6) DEFAULT 0,
        findings_json LONGTEXT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_llm_trace(trace_id),
        INDEX idx_llm_risk(risk_level),
        INDEX idx_llm_prompt(prompt_key),
        INDEX idx_llm_env(environment),
        INDEX idx_llm_user(user_id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
      'llm_policy_settings' => "CREATE TABLE IF NOT EXISTS llm_policy_settings (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(120) NOT NULL UNIQUE,
        setting_value LONGTEXT NULL,
        value_type ENUM('string','int','float','bool','json') DEFAULT 'string',
        updated_by BIGINT NULL,
        trace_id VARCHAR(80) NULL,
        atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_llm_policy_key(setting_key)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
      'llm_approval_queue' => "CREATE TABLE IF NOT EXISTS llm_approval_queue (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        approval_uuid VARCHAR(80) NOT NULL UNIQUE,
        requester_user_id BIGINT NULL,
        approver_user_id BIGINT NULL,
        prompt_key VARCHAR(120) NULL,
        provider VARCHAR(60) NULL,
        model VARCHAR(120) NULL,
        environment VARCHAR(30) DEFAULT 'homologacao',
        risk_level ENUM('baixo','medio','alto','critico') DEFAULT 'baixo',
        status ENUM('pendente','aprovado','rejeitado','expirado','cancelado') DEFAULT 'pendente',
        input_hash VARCHAR(128) NULL,
        redacted_context LONGTEXT NULL,
        reason TEXT NULL,
        decision_reason TEXT NULL,
        trace_id VARCHAR(80) NULL,
        expires_at DATETIME NULL,
        decided_at DATETIME NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_llm_approval_status(status, criado_em),
        INDEX idx_llm_approval_trace(trace_id),
        INDEX idx_llm_approval_risk(risk_level)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
      'llm_usage_daily' => "CREATE TABLE IF NOT EXISTS llm_usage_daily (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        usage_date DATE NOT NULL,
        provider VARCHAR(60) NOT NULL DEFAULT 'none',
        model VARCHAR(120) NULL,
        environment VARCHAR(30) NOT NULL DEFAULT 'homologacao',
        requests INT NOT NULL DEFAULT 0,
        blocked_requests INT NOT NULL DEFAULT 0,
        tokens_input BIGINT NOT NULL DEFAULT 0,
        tokens_output BIGINT NOT NULL DEFAULT 0,
        cost_estimate DECIMAL(12,6) NOT NULL DEFAULT 0,
        trace_id VARCHAR(80) NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_llm_usage_day_provider_model_env(usage_date, provider, model, environment),
        INDEX idx_llm_usage_date(usage_date),
        INDEX idx_llm_usage_env(environment)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
  }
}
