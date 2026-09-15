<?php
/**
 * V104.29 - Validação de banco em modo leitura segura.
 *
 * Antes, Central Técnica > Validar Banco executava AutoRepair + SchemaGuard
 * automaticamente no GET. Em bancos grandes/hospedagem compartilhada isso podia
 * deixar a tela carregando indefinidamente. Agora:
 * - GET = validação rápida/read-only, sem CREATE/ALTER automático.
 * - POST action=repair_schema = reparo explícito, com CSRF, permissão e limite.
 */
class DatabaseValidationService {
  private PDO $pdo;
  private string $dbName;
  /** @var array<string,mixed> */
  private array $options;

  /** @param array<string,mixed> $options */
  public function __construct(?PDO $pdo=null, array $options=[]){
    $this->pdo = $pdo ?: Database::getConnection();
    $this->dbName = (string)(cfg('db.name') ?: 'hub_vsm_tiny');
    $this->options = $options;
  }

  /**
   * @return array{status:string,total:int,ok:int,atencao:int,erro:int,checks:array<int,array<string,string>>,schema_guard:array,auto_repair:array,trace_id:string,executado_em:string,modo:string,parcial:bool,duracao_ms:int,acao_recomendada:string}
   */
  public function executar(bool $repair = false): array {
    $checks=[];
    $reparoSchema = [];
    $autoRepair = [];
    $started = microtime(true);
    $maxSeconds = (float)$this->option($repair ? 'repair_max_seconds' : 'validation_max_seconds', $repair ? 90 : 20);
    $maxChecks = (int)$this->option('max_checks', $repair ? 450 : 320);
    $deadline = $started + max(5.0, $maxSeconds);
    $parcial = false;

    @set_time_limit($repair ? 120 : 30);

    try { Database::clearTableResolutionCache(); } catch(Throwable $ignored){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $ignored); }

    if ($repair) {
      $this->push($checks, $this->item('atencao','Modo reparo manual','Reparo de schema iniciado por ação explícita protegida por CSRF.','Usar somente após backup e em janela controlada.'), $maxChecks);
      $this->executarReparoSeguro($checks, $autoRepair, $reparoSchema, $deadline, $maxChecks, $parcial);
      try { Database::clearTableResolutionCache(); } catch(Throwable $ignored){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $ignored); }
    } else {
      $this->push($checks, $this->item('ok','Modo validação segura','Consulta em modo leitura: não executa CREATE, ALTER, INSERT, UPDATE ou AutoRepair ao abrir a tela.','Se houver falhas reais, use o botão Reparar Schema Manualmente após backup.'), $maxChecks);
    }

    $this->runStep($checks, 'configuração do banco', fn() => $this->checkConfigDbStorageMode($checks, $maxChecks), $maxChecks);
    $this->guardDeadline($checks, $deadline, $parcial, 'configuração do banco', $maxChecks);

    if (!$parcial) $this->runStep($checks, 'conexão MySQL', fn() => $this->checkConexao($checks, $maxChecks), $maxChecks);
    $this->guardDeadline($checks, $deadline, $parcial, 'conexão MySQL', $maxChecks);

    if (!$parcial) $this->checkTabelas($checks, $deadline, $maxChecks, $parcial);
    $this->guardDeadline($checks, $deadline, $parcial, 'tabelas', $maxChecks);

    if (!$parcial) $this->checkColunasCriticas($checks, $deadline, $maxChecks, $parcial);
    $this->guardDeadline($checks, $deadline, $parcial, 'colunas críticas', $maxChecks);

    if (!$parcial) $this->checkIndicesCriticos($checks, $deadline, $maxChecks, $parcial);
    $this->guardDeadline($checks, $deadline, $parcial, 'índices críticos', $maxChecks);

    if (!$parcial) $this->checkPermissoesCriticas($checks, $deadline, $maxChecks, $parcial);
    $this->guardDeadline($checks, $deadline, $parcial, 'permissões', $maxChecks);

    if (!$parcial) $this->runStep($checks, 'Tiny V3', fn() => $this->checkTinyV3($checks, $maxChecks), $maxChecks);

    if (count($checks) >= $maxChecks) {
      $parcial = true;
      $this->push($checks, $this->item('atencao','Validação parcial','Limite de itens exibidos atingido para evitar travamento da tela.','Use Mapa do Banco para visão resumida ou execute repair_current.sql pelo phpMyAdmin se necessário.'), $maxChecks + 1);
    }

    $statusFinal = $this->statusFinal($checks);
    $duracaoMs = (int)round((microtime(true) - $started) * 1000);
    $acao = $statusFinal==='erro'
      ? 'Executar backup, revisar config.php/permissões MySQL e usar Reparar Schema Manualmente ou database/repair_current.sql em ambiente controlado.'
      : ($parcial ? 'Validação parcial por limite de segurança. Verifique Mapa do Banco e logs por Trace ID.' : 'Manter evidências na homologação.');

    $resumo = [
      'status' => $statusFinal,
      'total' => count($checks),
      'ok' => count(array_filter($checks, fn($c)=>($c['status'] ?? '')==='ok')),
      'atencao' => count(array_filter($checks, fn($c)=>($c['status'] ?? '')==='atencao')),
      'erro' => count(array_filter($checks, fn($c)=>($c['status'] ?? '')==='erro')),
      'checks' => $checks,
      'schema_guard' => $reparoSchema,
      'auto_repair' => $autoRepair,
      'trace_id' => RequestContext::id(),
      'executado_em' => date('Y-m-d H:i:s'),
      'modo' => $repair ? 'reparo_manual' : 'validacao_leitura_segura',
      'parcial' => $parcial,
      'duracao_ms' => $duracaoMs,
      'acao_recomendada' => $acao,
    ];

    try {
      Audit::event('database.validacao.executada', $statusFinal==='erro'?'erro':($statusFinal==='atencao'?'alerta':'sucesso'), [
        'mensagem'=>'Validação estrutural do banco executada pelo painel.',
        'contexto'=>[
          'status'=>$statusFinal,
          'modo'=>$resumo['modo'],
          'parcial'=>$parcial,
          'total'=>$resumo['total'],
          'ok'=>$resumo['ok'],
          'atencao'=>$resumo['atencao'],
          'erro'=>$resumo['erro'],
          'duracao_ms'=>$duracaoMs,
          'trace_id'=>$resumo['trace_id'],
        ],
        'codigo_erro'=>$statusFinal==='erro'?'DATABASE_SCHEMA_INVALID':null,
        'acao_recomendada'=>$acao,
      ]);
    } catch(Throwable $ignored){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $ignored); }

    return $resumo;
  }

  private function executarReparoSeguro(array &$checks, array &$autoRepair, array &$reparoSchema, float $deadline, int $maxChecks, bool &$parcial): void {
    if (microtime(true) > $deadline) { $this->guardDeadline($checks, $deadline, $parcial, 'antes do Enterprise Core', $maxChecks); return; }

    try {
      if (class_exists('SchemaMigrationService')) {
        $enterprise = SchemaMigrationService::applyEnterpriseCore(false);
        $enterpriseErrors = $enterprise['errors'] ?? [];
        $verification = $enterprise['verification'] ?? [];
        $statusEnterprise = empty($enterpriseErrors) ? 'ok' : 'erro';
        $mensagem = 'Aplicados '.count($enterprise['applied'] ?? []).', ignorados '.count($enterprise['skipped'] ?? []).', erros '.count($enterpriseErrors).'.';
        if (isset($verification['ok'], $verification['total'])) $mensagem .= ' Verificação: '.(int)$verification['ok'].'/'.(int)$verification['total'].' tabelas.';
        $this->push($checks, $this->item($statusEnterprise, 'Enterprise Core / migrations 004/007', $mensagem, $statusEnterprise === 'erro' ? 'Verificar permissões CREATE/ALTER e consultar os erros pelo Trace ID.' : 'OK'), $maxChecks);
        foreach (array_slice($enterpriseErrors, 0, 20) as $error) {
          $this->push($checks, $this->item('erro', 'Enterprise Core', $this->safeText((string)$error), 'Aplicar database/migrations/20260712_004_vsm_security_selftest_recovery.sql e database/migrations/20260713_007_enterprise_map_recovery.sql pelo phpMyAdmin se a hospedagem bloquear o painel.'), $maxChecks);
        }
      }
    } catch (Throwable $e) {
      $this->push($checks, $this->item('erro','Enterprise Core / migrations 004/007','Falha no reparo enterprise: '.$this->safeText($e->getMessage()),'Aplicar as migrations 001, 002, 003, 004 e 007 no phpMyAdmin, nessa ordem.'), $maxChecks);
    }

    if (microtime(true) > $deadline) { $this->guardDeadline($checks, $deadline, $parcial, 'antes do AutoRepair', $maxChecks); return; }

    try {
      if (class_exists('DatabaseAutoRepairService')) {
        $autoRepair = DatabaseAutoRepairService::repair(false);
        $statusAuto = ((int)($autoRepair['erro'] ?? 0) > 0) ? 'erro' : (((int)($autoRepair['atencao'] ?? 0) > 0) ? 'atencao' : 'ok');
        $this->push($checks, $this->item($statusAuto, 'AutoRepair SQL oficial', 'Executado: '.(int)($autoRepair['ok'] ?? 0).' OK, '.(int)($autoRepair['atencao'] ?? 0).' atenção, '.(int)($autoRepair['erro'] ?? 0).' erro(s).', $statusAuto === 'erro' ? 'Verificar permissões CREATE/ALTER/INSERT no banco principal.' : 'OK'), $maxChecks);
        foreach (array_slice($autoRepair['logs'] ?? [], 0, 20) as $log) {
          if (microtime(true) > $deadline) { $this->guardDeadline($checks, $deadline, $parcial, 'logs AutoRepair', $maxChecks); break; }
          $this->push($checks, $this->item($this->normalizeStatus($log['status'] ?? 'atencao'), 'AutoRepair '.($log['origem'] ?? 'SQL'), $this->safeText((string)($log['mensagem'] ?? '')), ($log['status'] ?? '') === 'erro' ? 'Corrigir permissão MySQL ou aplicar database/repair_current.sql pelo phpMyAdmin.' : 'OK'), $maxChecks);
        }
      } else {
        $this->push($checks, $this->item('atencao','AutoRepair indisponível','Classe DatabaseAutoRepairService não encontrada.','Usar database/repair_current.sql se necessário.'), $maxChecks);
      }
    } catch (Throwable $e) {
      $this->push($checks, $this->item('erro','AutoRepair SQL oficial','Falha no reparo: '.$this->safeText($e->getMessage()),'Conferir permissões MySQL e aplicar SQL oficial manualmente.'), $maxChecks);
    }

    if (microtime(true) > $deadline) { $this->guardDeadline($checks, $deadline, $parcial, 'antes do SchemaGuard', $maxChecks); return; }

    try {
      if (class_exists('DatabaseSchemaGuardService')) {
        $reparoSchema = DatabaseSchemaGuardService::repair(false);
        foreach (array_slice($reparoSchema, 0, 180) as $r) {
          if (microtime(true) > $deadline) { $this->guardDeadline($checks, $deadline, $parcial, 'SchemaGuard', $maxChecks); break; }
          $status = in_array($r['status'] ?? '', ['erro'], true) ? 'erro' : (in_array($r['status'] ?? '', ['criada','criado','atencao','removido'], true) ? 'atencao' : 'ok');
          $this->push($checks, $this->item($status, 'SchemaGuard '.($r['tipo'] ?? '').' '.($r['item'] ?? ''), $this->safeText((string)($r['mensagem'] ?? '')), $status === 'erro' ? 'Corrigir permissão do banco ou executar SQL oficial manualmente.' : 'OK'), $maxChecks);
        }
        if (count($reparoSchema) > 180) {
          $this->push($checks, $this->item('atencao','SchemaGuard parcial','Foram exibidos os primeiros 180 itens de reparo para evitar sobrecarga visual.','Consultar logs/auditoria para detalhes completos.'), $maxChecks);
        }
      } else {
        $this->push($checks, $this->item('atencao','SchemaGuard indisponível','Classe DatabaseSchemaGuardService não encontrada.','Usar database/repair_current.sql se necessário.'), $maxChecks);
      }
    } catch (Throwable $e) {
      $this->push($checks, $this->item('erro','SchemaGuard','Falha ao reparar/verificar schema: '.$this->safeText($e->getMessage()),'Verificar permissão CREATE/ALTER do usuário MySQL ou corrigir config.php para banco único.'), $maxChecks);
    }
  }

  private function checkConfigDbStorageMode(array &$checks, int $maxChecks): void {
    try {
      $cfg = class_exists('App') ? App::config() : (require __DIR__.'/../../config/config.php');
      $mode = (string)($cfg['db_storage_mode'] ?? 'single');
      $single = Database::isSingleDatabaseMode($cfg);
      $base = (string)($cfg['db']['name'] ?? '');
      $real = Database::currentDatabaseName(Database::connection('core'));
      $count = 0;
      try { $count = (int)Database::connection('core')->query('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchColumn(); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
      $mensagem = 'db_storage_mode='.$mode.' | efetivo='.($single?'single':'modular').' | db config='.$base.' | db conectado='.$real.' | tabelas='.$count;
      if ($single && $base !== '' && $real !== '' && $base !== $real) {
        $this->push($checks, $this->item('erro','config.php/db_storage_mode',$mensagem,'O config.php aponta para banco diferente do banco conectado. Revisar config/config.php e limpar storage/cache/classmap.php.'), $maxChecks);
      } elseif ($count === 0) {
        $this->push($checks, $this->item('erro','config.php/db_storage_mode',$mensagem,'Banco conectado está vazio. Conferir se é o banco correto no cPanel/phpMyAdmin e se o usuário tem CREATE/ALTER/INSERT.'), $maxChecks);
      } else {
        $this->push($checks, $this->item('ok','config.php/db_storage_mode',$mensagem,'OK'), $maxChecks);
      }
    } catch (Throwable $e) {
      $this->push($checks, $this->item('erro','config.php/db_storage_mode','Falha ao diagnosticar configuração: '.$this->safeText($e->getMessage()),'Revisar config/config.php.'), $maxChecks);
    }
  }

  private function checkConexao(array &$checks, int $maxChecks): void {
    try {
      $this->pdo->query('SELECT 1');
      $this->push($checks, $this->item('ok','Banco MySQL','Conexão PDO ativa.','Nenhuma ação necessária.'), $maxChecks);
    } catch(Throwable $e){
      $this->push($checks, $this->item('erro','Banco MySQL','Falha de conexão: '.$this->safeText($e->getMessage()),'Verificar Apache/MySQL no XAMPP e config/config.php.'), $maxChecks);
    }
  }

  private function checkTabelas(array &$checks, float $deadline, int $maxChecks, bool &$parcial): void {
    foreach(Database::knownTables() as $t){
      if (!$this->guardDeadline($checks, $deadline, $parcial, 'tabelas', $maxChecks)) return;
      $official = Database::officialTableModule($t);
      $actual = Database::resolveTableModule($t);
      $exists = $this->tableExists($t);
      $this->push($checks, $exists
        ? $this->item('ok','Tabela '.$t,'Tabela encontrada. Oficial: '.$official.' / Usando: '.$actual.'.','OK')
        : $this->item('erro','Tabela '.$t,'Tabela não existe no módulo resolvido '.$actual.' (oficial: '.$official.').','Rodar Reparar Schema Manualmente ou aplicar database/repair_current.sql após backup.'), $maxChecks);
    }
  }

  private function checkColunasCriticas(array &$checks, float $deadline, int $maxChecks, bool &$parcial): void {
    $cols=[
      'usuarios'=>['two_factor_enabled','two_factor_secret','two_factor_created_at','two_factor_last_verified_at'],
      'produto_pendencias'=>['resolvido_por','resolvido_em','status','resolucao','trace_id'],
      'fila_integracao'=>['processando_desde','codigo_erro','trace_id','proxima_tentativa'],
      'configuracoes_integracao'=>['tiny_v3_operacional','tiny_v3_ambiente','tiny_v3_auth_url','tiny_v3_token_url','tiny_v3_client_id','tiny_v3_client_secret','tiny_v3_redirect_uri','tiny_v3_scopes','tiny_v3_produtos_listar','tiny_v3_estoque_atualizar','tiny_v3_pedidos_lancar_estoque','bloquear_inativo_com_estoque','tiny_webhook_secret','tiny_webhook_exigir_secret','tiny_webhook_cnpj_autorizados'],
      'logs_integracao'=>['codigo_erro','nivel','trace_id'],
      'estoque_divergencias'=>['acao_recomendada','status','trace_id'],
      'tiny_v3_tokens'=>['ambiente','access_token','refresh_token','expires_at','scope','origem'],
      'tiny_v3_endpoint_logs'=>['endpoint','metodo','http_code','sucesso','tempo_ms','trace_id','request_body','response_body','erro'],
      'vsm_endpoint_catalogo'=>['nome','tipo','metodo','endpoint','ambiente','versao','status','ultimo_http_code','tempo_medio_ms'],
      'vsm_payload_catalogo'=>['tipo_evento','versao','origem','payload_exemplo','payload_real','hash_payload'],
      'auditoria_assinaturas'=>['auditoria_evento_id','trace_id','hash_sha256','algoritmo'],
      'fila_analytics_snapshots'=>['snapshot_json','trace_id'],
      'integration_replay_guard'=>['request_hash','payload_hash','time_bucket','hmac_validated_at','request_time','trace_id'],
      'token_vault'=>['provider','ambiente','token_type','token_hash','ciphertext','active'],
      'estoque_consulta_tiny_execucoes'=>['trace_id','modo','status','total_produtos'],
      'estoque_consulta_tiny_resultados'=>['execucao_id','sku','saldo_tiny','trace_id'],
      'security_events'=>['trace_id','tipo','severidade','ip','usuario_id','rota','metodo','user_agent','detalhe','contexto','created_at'],
      'ips_bloqueados'=>['ip','motivo','severidade','bloqueado_ate','ativo','created_at','updated_at'],
      'rate_limit_hits'=>['ip','usuario_id','rota','metodo','janela_inicio','hits','updated_at'],
      'vsm_endpoints'=>['chave','nome','categoria','metodo_http','endpoint','ativo','modo_teste_seguro','origem','contract_verified','is_template'],
      'vsm_campos_mapeamento'=>['categoria','campo_vsm','campo_hub','tipo_dado','ativo','origem','contract_verified','is_template'],
      'vsm_endpoint_logs'=>['metodo_http','url','sucesso','modo_teste_seguro','trace_id','criado_em'],
      'selftest_relatorios'=>['status','resumo','detalhes','trace_id','criado_em'],
      'audit_daily_signatures'=>['audit_date','hash_sha256','hmac_sha256'],
      'orquestracao_fluxos_historico'=>['acao','status','trace_id','criado_em'],
    ];
    foreach($cols as $table=>$columns){
      foreach($columns as $col){
        if (!$this->guardDeadline($checks, $deadline, $parcial, 'colunas críticas', $maxChecks)) return;
        $this->push($checks, $this->columnExists($table,$col)
          ? $this->item('ok',"Coluna {$table}.{$col}",'Coluna encontrada.','OK')
          : $this->item('erro',"Coluna {$table}.{$col}",'Coluna ausente.','Executar Reparar Schema Manualmente ou aplicar database/repair_current.sql após backup.'), $maxChecks);
      }
    }
  }

  private function checkIndicesCriticos(array &$checks, float $deadline, int $maxChecks, bool &$parcial): void {
    $indices=[
      ['logs_integracao','idx_logs_codigo'],
      ['estoque_movimentos','uk_baixa_referencia_sku'],
      ['tiny_webhooks','uk_tiny_webhook_tipo_ref_hash'],
      ['tiny_v3_endpoint_logs','idx_tiny_v3_endpoint_logs_data'],
      ['vsm_endpoint_catalogo','uk_vsm_endpoint'],
      ['auditoria_assinaturas','uk_auditoria_assinatura'],
      ['integration_replay_guard','uk_replay_origem_hash_bucket'],
      ['integration_replay_guard','idx_replay_hash_time'],
    ];
    foreach($indices as [$table,$idx]){
      if (!$this->guardDeadline($checks, $deadline, $parcial, 'índices críticos', $maxChecks)) return;
      $this->push($checks, $this->indexExists($table,$idx)
        ? $this->item('ok',"Índice {$table}.{$idx}",'Índice encontrado.','OK')
        : $this->item('atencao',"Índice {$table}.{$idx}",'Índice ausente ou banco antigo.','Executar atualização de banco para melhorar performance/idempotência.'), $maxChecks);
    }
  }

  private function checkPermissoesCriticas(array &$checks, float $deadline, int $maxChecks, bool &$parcial): void {
    $necessarias=[
      ['admin','database','validar'],['admin','homologacao','relatorio'],['admin','produtos_vsm','visualizar'],['admin','reconciliacao','executar'],['gerente','database','validar']
    ];
    foreach($necessarias as [$perfil,$modulo,$acao]){
      if (!$this->guardDeadline($checks, $deadline, $parcial, 'permissões', $maxChecks)) return;
      try {
        $st=Database::forTable('permissoes_perfil')->prepare('SELECT COUNT(*) c FROM permissoes_perfil WHERE perfil=? AND modulo=? AND acao=? AND permitido=1');
        $st->execute([$perfil,$modulo,$acao]);
        $ok=(int)$st->fetch()['c']>0;
        $this->push($checks, $ok ? $this->item('ok',"Permissão {$perfil}.{$modulo}.{$acao}",'Permissão ativa.','OK') : $this->item('atencao',"Permissão {$perfil}.{$modulo}.{$acao}",'Permissão ausente.','Inserir permissões pelo SQL atual.'), $maxChecks);
      } catch (Throwable $e) {
        $this->push($checks, $this->item('erro',"Permissão {$perfil}.{$modulo}.{$acao}",'Não foi possível consultar permissões: '.$this->safeText($e->getMessage()),'Corrigir config.php/db_storage_mode e permissões MySQL.'), $maxChecks);
      }
    }
  }

  private function checkTinyV3(array &$checks, int $maxChecks): void {
    try { $cfg=IntegrationConfig::get(); } catch(Throwable $e){ $cfg=[]; }
    if(($cfg['tiny_versao'] ?? 'v2') === 'v3' && empty($cfg['tiny_v3_operacional'])){
      $this->push($checks, $this->item('erro','Tiny V3 selecionado','Tiny V3 está selecionado, mas marcado como não operacional.','Voltar para Tiny V2 ou marcar V3 como operacional somente após homologação real.'), $maxChecks);
    } elseif(($cfg['tiny_versao'] ?? 'v2') === 'v3' && ($cfg['ambiente'] ?? 'homologacao') === 'producao' && !TinyV3TokenService::getTokenRow($cfg['tiny_v3_ambiente'] ?? 'producao')) {
      $this->push($checks, $this->item('erro','Tiny V3 OAuth produção','Produção exige token OAuth salvo em tiny_v3_tokens.','Conectar Tiny V3 via OAuth/refresh token antes de produção.'), $maxChecks);
    } else {
      $this->push($checks, $this->item('ok','Tiny versão','Configuração de versão Tiny coerente.','OK'), $maxChecks);
    }
  }

  private function tableExists(string $table): bool {
    try {
      $pdo = Database::forTable($table);
      return Database::tableExistsOn($pdo, $table);
    } catch(Throwable $e) { return false; }
  }
  private function columnExists(string $table,string $column): bool {
    try { return Database::columnExists($table, $column); } catch(Throwable $e) { return false; }
  }
  private function indexExists(string $table,string $index): bool {
    try {
      $pdo = Database::forTable($table);
      return Database::indexExistsOn($pdo, $table, $index);
    } catch(Throwable $e) { return false; }
  }

  private function item(string $status,string $titulo,string $mensagem,string $acao): array {
    return ['status'=>$this->normalizeStatus($status),'titulo'=>$titulo,'mensagem'=>$this->safeText($mensagem),'acao'=>$this->safeText($acao)];
  }
  private function statusFinal(array $checks): string {
    if(count(array_filter($checks, fn($c)=>($c['status'] ?? '')==='erro'))>0) return 'erro';
    if(count(array_filter($checks, fn($c)=>($c['status'] ?? '')==='atencao'))>0) return 'atencao';
    return 'ok';
  }
  private function normalizeStatus(string $status): string {
    return in_array($status, ['ok','erro','atencao'], true) ? $status : (in_array($status, ['criada','criado','removido','dry_run'], true) ? 'atencao' : 'atencao');
  }
  private function option(string $key, mixed $default): mixed { return $this->options[$key] ?? $default; }
  private function safeText(string $text): string {
    $text = preg_replace('/(password|senha|secret|token|client_secret)\s*[=:]\s*[^\s;&]+/i', '$1=***', $text) ?? $text;
    $text = preg_replace('/SQLSTATE\[[^\]]+\]:\s*/i', '', $text) ?? $text;
    return mb_substr($text, 0, 360, 'UTF-8');
  }
  private function push(array &$checks, array $item, int $maxChecks): bool {
    if (count($checks) >= $maxChecks) return false;
    $checks[] = $item;
    return true;
  }
  private function guardDeadline(array &$checks, float $deadline, bool &$parcial, string $fase, int $maxChecks): bool {
    if (microtime(true) <= $deadline) return true;
    if (!$parcial) {
      $parcial = true;
      $this->push($checks, $this->item('atencao','Validação parcial','Interrompido por limite de tempo durante '.$fase.' para evitar loop/travamento da tela.','Use Mapa do Banco para resumo ou execute reparo manual em janela controlada.'), $maxChecks);
    }
    return false;
  }
  private function runStep(array &$checks, string $fase, callable $fn, int $maxChecks): void {
    try { $fn(); }
    catch (Throwable $e) { $this->push($checks, $this->item('erro','Falha em '.$fase,$e->getMessage(),'Corrigir erro informado e consultar auditoria pelo Trace ID.'), $maxChecks); }
  }
}
