<?php
class ProductionReadinessV24Service {
  /**
   * Compatibilidade com telas antigas que chamam ::checks().
   * A V24 originalmente expunha checklist(), mas algumas rotas legadas
   * ainda chamavam checks(), causando erro fatal em produção.
   */
  public static function checks(): array {
    return array_map(function($c){
      $nota = (int)($c['nota'] ?? 0);
      return [
        'item' => (string)($c['modulo'] ?? 'Check'),
        'ok' => (($c['status'] ?? '') === 'ok') || $nota >= 98,
        'detalhe' => 'Nota '.$nota.'% - '.(string)($c['acao'] ?? ''),
        'nota' => $nota,
        'status' => (string)($c['status'] ?? 'atenção'),
        'acao' => (string)($c['acao'] ?? '')
      ];
    }, self::checklist());
  }

  public static function scores(): array {
    $pdo = Database::connection('core');
    $cfg = [];
    try { $cfg = IntegrationConfig::get(); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }

    $pre = class_exists('InstallationPrecheckService') ? InstallationPrecheckService::run() : [];
    $preOk = $pre ? count(array_filter($pre)) : 0;
    $preTotal = max(1, count($pre));

    $install = min(100, (int)round(($preOk/$preTotal)*92) + self::tableExists('upgrade_snapshots')*2 + self::tableExists('instalacao_prechecks')*2 + self::tableExists('post_install_tests')*4);
    $tinyV2 = 90 + self::tableExists('tiny_v2_endpoint_logs')*3 + self::tableExists('tiny_error_catalogo')*3 + self::tableExists('tiny_v2_retry_policies')*3 + self::hasConfig($cfg,'tiny_v2_token')*1;
    $vsm = 84 + self::tableExists('vsm_endpoint_catalogo')*4 + self::tableExists('vsm_payload_catalogo')*4 + self::tableExists('vsm_endpoint_metricas')*4 + self::hasConfig($cfg,'vsm_url')*2 + self::hasConfig($cfg,'vsm_endpoint_consulta_estoque')*2;
    $aud = 93 + self::tableExists('auditoria_timeline')*2 + self::tableExists('evento_correlacao')*1 + self::tableExists('auditoria_assinaturas')*2 + self::hasColumn('auditoria_assinaturas','hash_anterior')*2 + self::tableExists('auditoria_detalhes')*1;
    $fila = 93 + self::tableExists('fila_morta')*2 + self::hasColumn('fila_integracao','prioridade')*2 + self::hasColumn('fila_integracao','categoria')*1 + self::tableExists('fila_analytics_snapshots')*2;

    return [
      'instalacao'=>min(100,$install),
      'tiny_v2'=>min(100,$tinyV2),
      'vsm'=>min(100,$vsm),
      'auditoria'=>min(100,$aud),
      'fila_dlq'=>min(100,$fila),
      'producao'=>min(99, (int)round(($install+$tinyV2+$vsm+$aud+$fila)/5))
    ];
  }

  public static function checklist(): array {
    $scores = self::scores();
    return [
      ['modulo'=>'Instalação','nota'=>$scores['instalacao'],'status'=>$scores['instalacao']>=99?'ok':'atenção','acao'=>'Executar pré-check, validar permissões e gerar snapshot antes de upgrades.'],
      ['modulo'=>'Tiny V2','nota'=>$scores['tiny_v2'],'status'=>$scores['tiny_v2']>=99?'ok':'atenção','acao'=>'Validar token real, endpoints Tiny V2, catálogo de erros e snapshots request/response.'],
      ['modulo'=>'VSM','nota'=>$scores['vsm'],'status'=>$scores['vsm']>=98?'ok':'atenção','acao'=>'Cadastrar endpoints reais da VSM, payloads reais e monitorar SLA.'],
      ['modulo'=>'Auditoria','nota'=>$scores['auditoria'],'status'=>$scores['auditoria']>=99?'ok':'atenção','acao'=>'Assinar registros críticos e validar timeline completa por Trace ID.'],
      ['modulo'=>'Fila/DLQ','nota'=>$scores['fila_dlq'],'status'=>$scores['fila_dlq']>=99?'ok':'atenção','acao'=>'Monitorar prioridade, throughput, top erros e replay inteligente.'],
    ];
  }

  public static function gerarSnapshotUpgrade(string $versao='V24'): void {
    $pdo = Database::connection('core');
    if(!self::tableExists('upgrade_snapshots')) return;
    $dados = [
      'versao'=>$versao,
      'scores'=>self::scores(),
      'tabelas'=>self::tableList(),
      'gerado_em'=>date('c')
    ];
    $st=$pdo->prepare("INSERT INTO upgrade_snapshots(versao,tipo,snapshot_json,trace_id) VALUES(?,?,?,?)");
    $st->execute([$versao,'pre_upgrade',json_encode($dados,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),RequestContext::id()]);
  }

  private static function hasConfig(array $cfg, string $key): int { return !empty($cfg[$key]) ? 1 : 0; }
  /* Achado I-10: `SHOW ... LIKE ?` é INVÁLIDO com prepares nativos (o Hub usa EMULATE_PREPARES=false): o servidor recusa com "near '?'". O catch rebaixava isso a "tabela ausente", e a tabela existia. Use sempre o helper central. */
  private static function tableExists(string $table): int {
    return Database::tableExists($table) ? 1 : 0;
  }
  private static function hasColumn(string $table,string $column): int {
    return Database::columnExists($table, $column) ? 1 : 0;
  }
  private static function tableList(): array {
    try { return array_map('current', Database::connection('core')->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM)); } catch(Throwable $e){ return []; }
  }
}
