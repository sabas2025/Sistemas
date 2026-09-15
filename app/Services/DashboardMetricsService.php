<?php
/**
 * V104.19 - métricas leves do dashboard executivo.
 * Objetivo: reduzir dependência do DashboardController gigante e evitar SELECT * em listagens iniciais.
 */
class DashboardMetricsService {
  private const SAFE_TABLES = [
    'auditoria_eventos','empresas','estoque_divergencias','estoque_movimentos','fila_estoque','fila_fiscal','fila_integracao','logs_integracao','notificacoes','pedidos_hub','pedidos_integracao','pedidos_nfe_xml','produtos_mapeamento'
  ];
  private const SAFE_COLUMNS = [
    'pedidos_integracao' => 'id, origem, pedido_origem_id, pedido_tiny_id, cliente_nome, cliente_documento, valor_total, status, trace_id, criado_em, atualizado_em',
    'logs_integracao' => 'id, trace_id, tipo, nivel, codigo_erro, mensagem, ip, criado_em',
    'fila_integracao' => 'id, trace_id, tipo, prioridade, categoria, referencia, status, tentativas, proxima_tentativa, criado_em',
    'auditoria_eventos' => 'id, trace_id, acao, entidade, status, nivel, mensagem, ip, criado_em',
    'estoque_movimentos' => 'id, origem, referencia, sku, quantidade, tipo_movimento, status, trace_id, criado_em',
    'produtos_mapeamento' => 'id, sku_tiny, sku_vsm, descricao, ativo, estoque_atual, status_tiny, ultima_sincronizacao, criado_em',
    'pedidos_hub' => 'id, trace_id, pedido_tiny_id, pedido_vsm_id, numero_pedido, status_hub, status_tiny, status_vsm, cliente_nome, valor_total, criado_em, atualizado_em',
    'pedidos_nfe_xml' => 'id, pedido_hub_id, chave_nfe, numero_nfe, serie, xml_hash, status_xml, validado, enviado_tiny_em, criado_em',
  ];

  public static function collect(): array {
    try { $cfgInt = IntegrationConfig::get(); } catch(Throwable $e) { $cfgInt = []; }
    $filaPendente = self::count('fila_integracao', "status='pendente'");
    $filaErro = self::count('fila_integracao', "status IN ('erro','falha_definitiva')");
    $filaProcessandoAntiga = self::count('fila_integracao', "status='processando' AND processando_desde IS NOT NULL AND processando_desde < DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
    $falhasRecentes = self::count('logs_integracao', "nivel IN ('erro','critico') AND criado_em >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");

    return [
      'pageTitle' => 'Dashboard',
      'cfgInt' => $cfgInt,
      'k' => [
        'Pedidos integrados' => self::count('pedidos_integracao'),
        'Pedidos com erro' => self::count('pedidos_integracao', "status IN ('erro','falha','falha_definitiva')"),
        'Fila pendente' => $filaPendente,
        'Produtos mapeados' => self::count('produtos_mapeamento'),
        'Empresas' => self::count('empresas'),
        'Eventos de auditoria' => self::count('auditoria_eventos'),
        'Notificações não lidas' => self::count('notificacoes', 'lida=0'),
      ],
      'pedidos' => self::rows('pedidos_integracao', 'id DESC', 8),
      'logs' => self::rows('logs_integracao', 'id DESC', 8),
      'filaResumo' => self::groupStatus('fila_integracao'),
      'syncRules' => class_exists('SyncRulesService') ? SyncRulesService::all() : [],
      'syncResumo' => [
        ['chave'=>'sync_criar_produto_tiny','titulo'=>'Criar produto Tiny'],
        ['chave'=>'sync_atualizar_estoque_tiny','titulo'=>'Atualizar estoque Tiny'],
        ['chave'=>'sync_atualizar_status_tiny','titulo'=>'Ativo/Inativo Tiny'],
        ['chave'=>'sync_bloquear_estoque_negativo','titulo'=>'Bloquear estoque negativo'],
      ],
      'healthCards' => self::healthCards($cfgInt, $filaPendente, $filaErro, $filaProcessandoAntiga, $falhasRecentes),
      'ultimoDiagVsm' => class_exists('DiagnosticoApiService') ? DiagnosticoApiService::ultimoPorSistema('vsm') : null,
      'ultimoDiagTiny' => class_exists('DiagnosticoApiService') ? DiagnosticoApiService::ultimoPorSistema('tiny') : null,
      'ultimoPedidoIntegrado' => self::one('pedidos_integracao'),
      'ultimaFalha' => self::one('logs_integracao', 'id DESC', "nivel IN ('erro','critico')"),
      'ultimaBaixaVsm' => self::one('estoque_movimentos'),
      'ultimoProdutoTiny' => self::one('produtos_mapeamento'),
      'baixasErro' => self::count('estoque_movimentos', "status IN ('erro','falha','falha_definitiva')"),
      'produtosErro' => self::count('fila_integracao', "tipo IN ('produto_vsm_para_tiny','produto_vsm_atualizar_tiny','produto_vsm_estoque_para_tiny','produto_vsm_status_para_tiny') AND status IN ('erro','falha_definitiva')"),
      'filaTinyVsm' => self::count('fila_integracao', "tipo='baixa_estoque_vsm' AND status='pendente'"),
      'filaVsmTiny' => self::count('fila_integracao', "tipo IN ('produto_vsm_para_tiny','produto_vsm_atualizar_tiny','produto_vsm_estoque_para_tiny','produto_vsm_status_para_tiny') AND status='pendente'"),
      'pedidoCicloResumo' => self::pedidoCicloResumo(),
      'tinyV3Aviso' => (($cfgInt['tiny_versao'] ?? 'v2') === 'v3' && empty($cfgInt['tiny_v3_operacional'])),
      'operacao' => self::operationalSummary($cfgInt),
      'workerCards' => self::workerCards(),
      'estoqueResumo' => [
        'sincronizados' => self::count('estoque_movimentos', "status IN ('sucesso','sincronizado','confirmado')"),
        'divergencias' => self::count('estoque_divergencias'),
        'pendentes' => self::count('fila_estoque', "status='pendente'"),
        'falhas' => self::count('fila_estoque', "status IN ('erro','falha_definitiva')"),
      ],
      'fiscalResumo' => [
        'recebidas' => self::count('pedidos_nfe_xml'),
        'xml_processados' => self::count('pedidos_nfe_xml', "status_xml IN ('validado','enviado_tiny','concluido')"),
        'xml_erros' => self::count('pedidos_nfe_xml', "status_xml LIKE '%erro%' OR validado=0"),
        'reenvios' => self::count('fila_fiscal', "status IN ('pendente','processando')"),
      ],
    ];
  }

  public static function count(string $table, string $where='1=1'): int {
    try {
      self::assertTable($table);
      $where = self::safeWhere($table, $where);
      // Melhoria 1 da seção 8: o nome da tabela é interpolado, então este ponto servia várias
      // tabelas com escopo de empresa sem filtrar nenhuma. applyToSelect() só age quando a tabela
      // está no catálogo e há empresa ativa; nas demais é passagem direta.
      [$sql, $params] = TenantScopeService::applyToSelect($table, "SELECT COUNT(*) c FROM `{$table}` WHERE {$where}", []);
      $st = Database::forTable($table)->prepare($sql); $st->execute($params);
      return (int)($st->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
    } catch(Throwable $e) { return 0; }
  }

  public static function rows(string $table, string $orderBy='id DESC', int $limit=10, string $where='1=1'): array {
    try {
      self::assertTable($table);
      $where = self::safeWhere($table, $where);
      $orderBy = self::safeOrder($table, $orderBy);
      $cols = self::SAFE_COLUMNS[$table] ?? (class_exists('HeavyQueryOptimizerService') ? HeavyQueryOptimizerService::columns($table, 'id') : 'id');
      $limit = max(1, min(200, $limit));
      // Melhoria 1 da seção 8: o nome da tabela é interpolado, então este ponto servia várias
      // tabelas com escopo de empresa sem filtrar nenhuma. applyToSelect() só age quando a tabela
      // está no catálogo e há empresa ativa; nas demais é passagem direta.
      [$sql, $params] = TenantScopeService::applyToSelect($table, "SELECT {$cols} FROM `{$table}` WHERE {$where} ORDER BY {$orderBy} LIMIT {$limit}", []);
      $st = Database::forTable($table)->prepare($sql); $st->execute($params);
      return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch(Throwable $e) { return []; }
  }

  public static function one(string $table, string $orderBy='id DESC', string $where='1=1'): ?array {
    $rows = self::rows($table, $orderBy, 1, $where);
    return $rows[0] ?? null;
  }

  private static function groupStatus(string $table): array {
    try { self::assertTable($table); return Database::forTable($table)->query("SELECT status, COUNT(*) total FROM `{$table}` GROUP BY status")->fetchAll(PDO::FETCH_ASSOC) ?: []; } catch(Throwable $e) { return []; }
  }

  private static function pedidoCicloResumo(): array {
    $res = ['recebidos_tiny'=>0,'enviados_vsm'=>0,'aguardando_xml'=>0,'enviados_tiny'=>0,'concluidos'=>0,'erros'=>0];
    try {
      foreach (TenantScopeService::run('pedidos_hub', 'SELECT status_hub, COUNT(*) total FROM pedidos_hub GROUP BY status_hub')->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $s=(string)($r['status_hub']??''); $t=(int)($r['total']??0);
        if($s==='recebido_tiny') $res['recebidos_tiny'] += $t;
        if($s==='enviado_vsm') $res['enviados_vsm'] += $t;
        if(in_array($s,['recebido_vsm','xml_validado'],true)) $res['aguardando_xml'] += $t;
        if($s==='enviado_tiny') $res['enviados_tiny'] += $t;
        if($s==='concluido') $res['concluidos'] += $t;
        if(str_contains($s,'erro')) $res['erros'] += $t;
      }
    } catch(Throwable $e) { $res['erro_consulta'] = $e->getMessage(); }
    return $res;
  }

  private static function healthCards(array $cfgInt, int $filaPendente, int $filaErro, int $filaProcessandoAntiga, int $falhasRecentes): array {
    return [
      ['titulo'=>'Banco MySQL','status'=>'online','icone'=>'bi-database-check','detalhe'=>'Conexão PDO ativa','acao'=>'OK'],
      ['titulo'=>'Tiny V2','status'=>!empty($cfgInt['tiny_v2_token'])?'online':'atencao','icone'=>'bi-cloud-check','detalhe'=>!empty($cfgInt['tiny_v2_token'])?'Token configurado':'Token não configurado','acao'=>!empty($cfgInt['tiny_v2_token'])?'Pronto para teste':'Configure o token Tiny'],
      ['titulo'=>'VSM','status'=>!empty($cfgInt['vsm_url'])?'online':'atencao','icone'=>'bi-diagram-3','detalhe'=>!empty($cfgInt['vsm_url'])?'URL configurada':'URL não configurada','acao'=>!empty($cfgInt['vsm_url'])?'Use Teste VSM para validar HTTPS/API':'Configure a URL da VSM'],
      ['titulo'=>'Webhook VSM','status'=>!empty($cfgInt['webhook_secret'])?'online':'atencao','icone'=>'bi-shield-lock','detalhe'=>!empty($cfgInt['webhook_secret'])?'Secret/HMAC configurado':'Secret não configurado','acao'=>!empty($cfgInt['webhook_secret'])?'Protegido':'Configure o segredo do webhook'],
      ['titulo'=>'Fila','status'=>$filaProcessandoAntiga>0||$filaErro>0?'erro':($filaPendente>0?'atencao':'online'),'icone'=>'bi-arrow-repeat','detalhe'=>$filaPendente.' pendente(s), '.$filaErro.' com erro','acao'=>$filaProcessandoAntiga>0?'Há item travado; rode worker_fila.php':($filaPendente>0?'Processar fila':'Fila normal')],
      ['titulo'=>'Erros 24h','status'=>$falhasRecentes>0?'erro':'online','icone'=>'bi-bug','detalhe'=>$falhasRecentes.' erro(s)/crítico(s)','acao'=>$falhasRecentes>0?'Verifique Logs e Auditoria':'Sem erros recentes'],
    ];
  }

  private static function operationalSummary(array $cfgInt=[]): array {
    $tinyV2Ok = !empty($cfgInt['tiny_v2_token']);
    $tinyV3Operacional = !empty($cfgInt['tiny_v3_operacional']);
    $tinyV3OAuth = !empty($cfgInt['tiny_v3_access_token']) || !empty($cfgInt['tiny_v3_refresh_token']) || !empty($cfgInt['tiny_v3_client_id']);
    $vsmOk = !empty($cfgInt['vsm_url']);
    $filaErro = self::count('fila_integracao', "status IN ('erro','falha_definitiva')");
    $filaPendente = self::count('fila_integracao', "status='pendente'");
    $xmlErro = self::count('pedidos_hub', "status_hub IN ('erro_xml','erro_envio_tiny','erro_retorno_vsm')");
    $estoqueErro = self::count('fila_estoque', "status IN ('erro','falha_definitiva')");
    $alertas = [];
    if(!$tinyV2Ok) $alertas[] = ['nivel'=>'atencao','titulo'=>'Tiny V2 sem token','mensagem'=>'Configure o token antes de produção.'];
    if(!$tinyV3Operacional) $alertas[] = ['nivel'=>'atencao','titulo'=>'Tiny V3 em homologação','mensagem'=>'Mantenha V2 em produção até OAuth e testes reais passarem.'];
    if(!$vsmOk) $alertas[] = ['nivel'=>'erro','titulo'=>'VSM sem URL','mensagem'=>'Configure a URL/base da VSM.'];
    if($filaErro>0) $alertas[] = ['nivel'=>'erro','titulo'=>'Fila com erro','mensagem'=>$filaErro.' item(ns) com erro definitivo.'];
    if($xmlErro>0) $alertas[] = ['nivel'=>'erro','titulo'=>'XML/NF-e com erro','mensagem'=>$xmlErro.' pedido(s) com problema de XML/NF-e.'];
    if($estoqueErro>0) $alertas[] = ['nivel'=>'erro','titulo'=>'Estoque com erro','mensagem'=>$estoqueErro.' item(ns) com falha na fila de estoque.'];
    $geral='online'; foreach($alertas as $a){ if($a['nivel']==='erro'){ $geral='erro'; break; } if($a['nivel']==='atencao') $geral='atencao'; }
    return ['geral'=>$geral,'alertas'=>$alertas,'integracoes'=>[
      ['nome'=>'Tiny V2','status'=>$tinyV2Ok?'online':'atencao','modo'=>'Produção','detalhe'=>$tinyV2Ok?'Token configurado':'Token pendente'],
      ['nome'=>'Tiny V3','status'=>$tinyV3Operacional?'online':'atencao','modo'=>$tinyV3Operacional?'Produção':'Homologação','detalhe'=>$tinyV3OAuth?'OAuth/configuração parcial':'OAuth pendente'],
      ['nome'=>'VSM','status'=>$vsmOk?'online':'erro','modo'=>'Produção','detalhe'=>$vsmOk?'URL configurada':'URL pendente'],
    ], 'filas'=>['pendentes'=>$filaPendente,'erros'=>$filaErro]];
  }

  private static function workerCards(): array {
    $workers = [
      ['arquivo'=>'worker_fiscal.php','titulo'=>'XML/NF-e'],
      ['arquivo'=>'worker_estoque.php','titulo'=>'Estoque'],
      ['arquivo'=>'worker_consulta_estoque_vsm.php','titulo'=>'Consulta VSM'],
      ['arquivo'=>'worker_fila.php','titulo'=>'Fila'],
    ];
    $out=[]; foreach($workers as $w){ $file=__DIR__.'/../../public/'.$w['arquivo']; $exists=is_file($file); $out[]=['titulo'=>$w['titulo'],'arquivo'=>$w['arquivo'],'status'=>$exists?'online':'erro','detalhe'=>$exists?'Arquivo disponível para agendamento':'Arquivo não encontrado']; }
    return $out;
  }

  private static function assertTable(string $table): void { if(!in_array($table, self::SAFE_TABLES, true)) throw new InvalidArgumentException('Tabela não permitida.'); }
  private static function safeWhere(string $table, string $where): string {
    $where = trim($where) ?: '1=1';
    if (preg_match('/;|--|\/\*|\b(UNION|DROP|DELETE|UPDATE|INSERT|ALTER|CREATE|TRUNCATE|REPLACE)\b/i', $where)) return '1=1';
    return $where;
  }
  private static function safeOrder(string $table, string $order): string {
    $order = trim($order) ?: 'id DESC';
    if(!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\s+(ASC|DESC))?$/i', $order)) return 'id DESC';
    return $order;
  }
}
