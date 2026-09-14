<?php
/**
 * V104.16 - Guarda idempotente completo de schema com rescue banco único/modular.
 *
 * Objetivo:
 * - reduzir erro fatal por tabela/coluna/índice ausente;
 * - corrigir anti-replay antigo sem remover dados;
 * - validar o schema oficial completo por módulos;
 * - padronizar registro de migrações em schema_migrations.
 */
class DatabaseSchemaGuardService {
  /** @return array<int, array{tipo:string,item:string,status:string,mensagem:string}> */
  public static function repair(bool $dryRun = false): array {
    $out = [];
    Database::clearTableResolutionCache();
    self::ensureSchemaMigrations($out, $dryRun);
    self::ensureOfficialTables($out, $dryRun);
    Database::clearTableResolutionCache();
    self::ensureCriticalColumns($out, $dryRun);
    self::ensureReplayGuardIndexes($out, $dryRun);
    self::ensureCriticalIndexes($out, $dryRun);
    self::seedSchemaMigration($out, $dryRun);
    return $out;
  }

  private static function ensureSchemaMigrations(array &$out, bool $dryRun): void {
    try {
      if ($dryRun) { $out[] = self::item('tabela','schema_migrations','dry_run','Tabela de migrações padronizada verificada em simulação.'); return; }
      Database::ensureSchemaMigrations();
      $out[] = self::item('tabela','schema_migrations','ok','Tabela de migrações padronizada existe.');
    } catch (Throwable $e) { $out[] = self::item('tabela','schema_migrations','erro',$e->getMessage()); }
  }

  private static function ensureOfficialTables(array &$out, bool $dryRun): void {
    foreach (self::officialCreateStatements() as $table => $ddl) {
      try {
        if (!Database::isKnownTable($table)) {
          $out[] = self::item('tabela', $table, 'atencao', 'Tabela existe no SQL oficial, mas não está no mapa Database::tableModule().');
          continue;
        }
        $pdo = Database::forTable($table);
        $actualModule = Database::resolveTableModule($table);
        $dbName = Database::currentDatabaseName($pdo);
        if ($dryRun) { $out[] = self::item('tabela', $table, 'dry_run', 'Tabela oficial verificada em simulação. Módulo alvo: '.$actualModule.' / banco: '.$dbName); continue; }
        $pdo->exec($ddl);
        $out[] = self::item('tabela', $table, 'ok', 'Tabela oficial existe ou foi criada. Módulo alvo: '.$actualModule.' / banco: '.$dbName);
      } catch (Throwable $e) { $out[] = self::item('tabela', $table, 'erro', $e->getMessage()); }
    }
  }

  /** @return array<string,string> */
  private static function officialCreateStatements(): array {
    $paths = glob(__DIR__ . '/../../database/modules/*.sql') ?: [];
    sort($paths);
    $tables = [];
    foreach ($paths as $path) {
      $sql = (string)file_get_contents($path);
      if (preg_match_all('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?([a-zA-Z0-9_]+)`?\s*\((.*?)\)\s*ENGINE\s*=\s*InnoDB\s+DEFAULT\s+CHARSET\s*=\s*utf8mb4\s*(?:COLLATE\s*=\s*utf8mb4_unicode_ci)?\s*;/is', $sql, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
          $table = $m[1];
          $tables[$table] = trim($m[0]);
        }
      }
    }
    return $tables;
  }

  private static function ensureCriticalColumns(array &$out, bool $dryRun): void {
    $columns = self::criticalColumns();
    foreach ($columns as $table => $cols) {
      foreach ($cols as $column => $definition) {
        try {
          if ($dryRun) { $out[] = self::item('coluna', $table.'.'.$column, 'dry_run', 'Coluna verificada em modo simulação.'); continue; }
          $created = Database::addColumnIfMissing($table, $column, $definition);
          $out[] = self::item('coluna', $table.'.'.$column, $created ? 'criada' : 'ok', $created ? 'Coluna criada.' : 'Coluna já existe.');
        } catch (Throwable $e) { $out[] = self::item('coluna', $table.'.'.$column, 'erro', $e->getMessage()); }
      }
    }
  }

  /** @return array<string,array<string,string>> */
  public static function criticalColumns(): array {
    return [
      'configuracoes_integracao' => [
        'vsm_api_principal' => "VARCHAR(40) DEFAULT 'pedidos-integradora'",
        'vsm_api_loja' => "VARCHAR(40) DEFAULT 'desativado'",
        'vsm_swagger_integradora' => "VARCHAR(255) DEFAULT 'https://conectavenda.homolog.vsm.com.br/swagger-ui/index.html?urls.primaryName=pedidos-integradora'",
        'vsm_swagger_loja' => "VARCHAR(255) DEFAULT 'https://conectavenda.homolog.vsm.com.br/swagger-ui/index.html?urls.primaryName=pedidos-loja'",
        'vsm_waf_agressivo' => 'TINYINT DEFAULT 0',
        'vsm_api_observacao' => 'TEXT NULL',
        'sync_receber_pedidos_vsm' => 'TINYINT DEFAULT 0',
        'sync_enviar_pedido_tiny' => 'TINYINT DEFAULT 0',
        'sync_vsm_enviar_estoque_tiny' => 'TINYINT DEFAULT 1',
        'sync_vsm_status_produto_tiny' => 'TINYINT DEFAULT 1',
        'sync_vsm_enviar_nota_tiny' => 'TINYINT DEFAULT 1',
        'sync_bloquear_produto_novo_vsm' => 'TINYINT DEFAULT 1',
        'sync_permitir_produto_novo_vsm_manual' => 'TINYINT DEFAULT 1',
        'sync_tiny_enviar_nota_vsm' => 'TINYINT DEFAULT 0',
        'sync_tiny_enviar_pedido_vsm' => 'TINYINT DEFAULT 1',
        'sync_tiny_enviar_estoque_vsm' => 'TINYINT DEFAULT 0',
        'sync_tiny_status_produto_vsm' => 'TINYINT DEFAULT 0',
        'sync_tiny_bloquear_produto_novo_vsm' => 'TINYINT DEFAULT 1',
        'sync_exigir_mapeamento_sku' => 'TINYINT DEFAULT 1',
        'sync_exigir_nfe_autorizada' => 'TINYINT DEFAULT 1',
        'sync_ordem_envio' => 'TEXT NULL',
        'sync_aprovacao_manual_produto_novo_vsm' => 'TINYINT DEFAULT 1',
        'sync_exigir_categoria_mapeada_vsm' => 'TINYINT DEFAULT 1',
        'sync_permitir_atualizar_produto_existente_vsm' => 'TINYINT DEFAULT 1',
        'sync_permitir_estoque_vsm_tiny' => 'TINYINT DEFAULT 1',
        'sync_permitir_status_vsm_tiny' => 'TINYINT DEFAULT 1',
        'produto_novo_aprovacao_modo' => "VARCHAR(20) DEFAULT 'manual'",
        'produto_novo_auto_fallback_manual' => 'TINYINT DEFAULT 1',
        'produto_novo_auto_exigir_ean' => 'TINYINT DEFAULT 1',
        'produto_novo_auto_exigir_ncm' => 'TINYINT DEFAULT 1',
        'produto_novo_auto_exigir_categoria' => 'TINYINT DEFAULT 1',
        'produto_novo_auto_bloquear_duplicidade' => 'TINYINT DEFAULT 1',
        'pedido_tiny_vsm_validacao_obrigatoria' => 'TINYINT DEFAULT 1',
        'pedido_tiny_vsm_aprovacao_manual' => 'TINYINT DEFAULT 1',
        'pedido_tiny_vsm_auto_enviar_validos' => 'TINYINT DEFAULT 0',
        'pedido_tiny_vsm_exigir_sku_mapeado' => 'TINYINT DEFAULT 1',
        'pedido_tiny_vsm_exigir_cliente_documento' => 'TINYINT DEFAULT 1',
        'pedido_tiny_vsm_exigir_endereco' => 'TINYINT DEFAULT 1',
        'pedido_tiny_vsm_status_permitidos' => "VARCHAR(255) DEFAULT 'aprovado,pago,faturado,pronto para envio'",
        'vsm_endpoint_pedido' => "VARCHAR(255) DEFAULT '/api/pedidos'",
      ],
      'produtos_pendentes_integracao' => [
        'ncm' => 'VARCHAR(20) NULL',
        'checklist_json' => 'LONGTEXT NULL',
        'duplicidades_json' => 'LONGTEXT NULL',
        'bloqueios_json' => 'LONGTEXT NULL',
        'ultima_analise_em' => 'DATETIME NULL',
      ],
      'produto_pendencias' => ['resolvido_por' => 'INT NULL', 'resolvido_em' => 'DATETIME NULL'],
      'estoque_divergencias' => ['acao_recomendada' => 'TEXT NULL'],
      'usuarios' => ['session_version' => 'INT NOT NULL DEFAULT 0'],
      'comercial_clientes_licencas' => [
        'licenca_origem' => "VARCHAR(40) NOT NULL DEFAULT 'manual'",
        'ultimo_check_em' => 'TIMESTAMP NULL DEFAULT NULL',
        'bloquear_ao_vencer' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'assinatura_hmac' => 'VARCHAR(128) NULL',
      ],
      'comercial_suporte_chamados' => [
        'cliente_licenca_id' => 'BIGINT NULL',
        'titulo' => 'VARCHAR(220) NOT NULL DEFAULT \'Chamado\'',
        'prioridade' => "VARCHAR(30) NOT NULL DEFAULT 'normal'",
        'status' => "VARCHAR(30) NOT NULL DEFAULT 'aberto'",
        'prazo_resposta_em' => 'DATETIME NULL',
        'prazo_resolucao_em' => 'DATETIME NULL',
      ],
      'comercial_sla_eventos' => [
        'chamado_id' => 'BIGINT NULL',
        'tipo' => "VARCHAR(80) NOT NULL DEFAULT 'evento'",
        'status' => "VARCHAR(30) NOT NULL DEFAULT 'registrado'",
      ],
      'integration_replay_guard' => [
        'payload_hash' => 'CHAR(64) NULL',
        'time_bucket' => 'BIGINT UNSIGNED NOT NULL DEFAULT 0',
        'hmac_validated_at' => 'DATETIME NULL',
      ],
    ];
  }

  private static function ensureReplayGuardIndexes(array &$out, bool $dryRun): void {
    try {
      if ($dryRun) { $out[] = self::item('indice','integration_replay_guard.uk_replay_origem_hash','dry_run','Índice legado seria removido se existisse.'); }
      else {
        $dropped = Database::dropIndexIfExists('integration_replay_guard', 'uk_replay_origem_hash');
        $out[] = self::item('indice','integration_replay_guard.uk_replay_origem_hash',$dropped?'removido':'ok',$dropped?'Índice legado removido para evitar bloqueio eterno.':'Índice legado não existe.');
      }
    } catch (Throwable $e) { $out[] = self::item('indice','integration_replay_guard.uk_replay_origem_hash','erro',$e->getMessage()); }
  }

  private static function ensureCriticalIndexes(array &$out, bool $dryRun): void {
    $indexes = [
      ['integration_replay_guard','idx_replay_hash_time','INDEX idx_replay_hash_time (origem, request_hash, request_time)'],
      ['integration_replay_guard','uk_replay_origem_hash_bucket','UNIQUE KEY uk_replay_origem_hash_bucket (origem, request_hash, time_bucket)'],
      ['rate_limit_hits','uk_rate_bucket','UNIQUE KEY uk_rate_bucket (ip, usuario_id, rota, metodo, janela_inicio)'],
      ['auditoria_eventos','idx_auditoria_trace_id','INDEX idx_auditoria_trace_id (trace_id)'],
      ['fila_integracao','idx_fila_status_proxima','INDEX idx_fila_status_proxima (status, proxima_tentativa)'],
      ['logs_integracao','idx_logs_data','INDEX idx_logs_data (criado_em)'],
      ['comercial_clientes_licencas','idx_comercial_licenca_status_expira','INDEX idx_comercial_licenca_status_expira (status,data_expiracao)'],
      ['comercial_suporte_chamados','idx_suporte_status','INDEX idx_suporte_status (status)'],
      ['comercial_sla_eventos','idx_sla_tipo','INDEX idx_sla_tipo (tipo)'],
    ];
    foreach($indexes as [$table,$index,$definition]){
      try {
        if ($dryRun) { $out[] = self::item('indice', $table.'.'.$index, 'dry_run', 'Índice verificado em modo simulação.'); continue; }
        $created = Database::addIndexIfMissing($table, $index, $definition);
        $out[] = self::item('indice', $table.'.'.$index, $created ? 'criado' : 'ok', $created ? 'Índice criado.' : 'Índice já existe.');
      } catch(Throwable $e) { $out[] = self::item('indice', $table.'.'.$index, 'erro', $e->getMessage()); }
    }
  }

  private static function seedSchemaMigration(array &$out, bool $dryRun): void {
    if ($dryRun) return;
    try {
      $migration = class_exists('SystemVersionService') ? SystemVersionService::migration() : 'v104_16_finalizacao_comercial';
      Database::recordMigration($migration,'schema_v104_16','SchemaGuard V104.16 executado: versão centralizada, banco atual, licença comercial, conectores plugáveis e índices críticos.');
      $out[] = self::item('migração',$migration,'ok','Migração registrada no padrão oficial.');
    } catch(Throwable $e) { $out[] = self::item('migração','v104_16_finalizacao_comercial','atencao',$e->getMessage()); }
  }

  private static function item(string $tipo, string $item, string $status, string $mensagem): array {
    return compact('tipo','item','status','mensagem');
  }
}
