<?php
class DashboardController {
  private PDO $pdo;
  public function __construct(){ $this->pdo = Database::getConnection(); }
  private function db(string $table): PDO { return Database::forTable($table); }

  /**
   * P0-08 (reauditoria 2026-08-23): host/scheme passam pelo TrustedProxyService
   * (que já respeita trusted_proxies) em vez de ler HTTP_HOST/XFP crus, e usam o
   * primeiro canonical_host configurado quando existir, para não deixar um Host
   * header adulterado ditar a URL de redirect_uri usada no OAuth.
   */
  /**
   * Reauditoria 2026-09-14 (achado A-02): entrada exclusiva dos callbacks OAuth, que chegam
   * por navegação cross-site sem o cookie de sessão SameSite=Strict e por isso não podem passar
   * pelo Auth::requireLogin() de dispatch(). A autorização é feita dentro do próprio callback,
   * contra o perfil vinculado à transação OAuth assinada. Só rotas de callback entram aqui.
   */
  public function dispatchOAuthCallback(string $page): void {
    if ($page === 'tiny-v3-callback') { $this->tinyV3Callback(); return; }
    http_response_code(404);
    exit('Callback não encontrado.');
  }

  public function dispatch(string $page): void {
    Auth::requireLogin();
    switch($page){
      case 'pedidos': (new PedidoController())->dispatch($page); break;
      case 'pedido-detalhe': (new PedidoController())->dispatch($page); break;
      case 'fila': (new FilaController())->dispatch($page); break;
      case 'fila-reprocessar': (new FilaController())->dispatch($page); break;
      case 'fila-criar-teste': (new FilaController())->dispatch($page); break;
      case 'integracoes': $this->integracoes(); break;
      case 'bancos-modulos': $this->bancosModulos(); break;
      case 'bancos-modulos-instalar': $this->bancosModulosInstalar(); break;
      case 'central-tecnica': $this->centralTecnica(); break;
      case 'atualizador-seguro': $this->atualizadorSeguro(); break;
      case 'atualizador-seguro-executar': $this->atualizadorSeguroExecutar(); break;
      case 'logs': $this->logs(); break;
      case 'notificacoes': $this->notificacoes(); break;
      case 'notificacao-lida': $this->marcarNotificacaoLida(); break;
      case 'diagnostico': $this->diagnostico(); break;
      case 'dashboard-integridade': $this->dashboardIntegridade(); break;
      case 'producao-ready': $this->producaoReady(); break;
      case 'dashboard-integridade-executar': $this->dashboardIntegridadeExecutar(); break;
      case 'menu-testes': $this->menuTestes(); break;
      case 'ficha-tecnica-100': $this->fichaTecnica100(); break;
      case 'production-ready-v24': $this->productionReadyV24(); break;
      case 'production-ready-v25': $this->productionReadyV25(); break;
      case 'production-ready-v26': $this->productionReadyV26(); break;
      case 'fila-analytics-v24': $this->filaAnalyticsV24(); break;
      case 'hosting-infinityfree': $this->hostingInfinityFree(); break;
      // Bloco de Segurança extraído para SecurityController (Fase 3) — despachado pelo
      // FastRouteDispatcherService::$dispatchGroups; os cases aqui eram inalcançáveis.
      case 'relatorio-prontidao-producao': $this->relatorioProntidaoProducao(); break;
      case 'fila-morta': $this->filaMorta(); break;
      case 'fila-morta-reprocessar': (new FilaController())->dispatch($page); break;
      case 'metricas': $this->metricas(); break;
      case 'logs-exportar': $this->logsExportar(); break;
      // Reauditoria 2026-09-14 (achado A-13): antes qualquer rota desconhecida renderizava o
      // dashboard com HTTP 200, escondendo links quebrados e dando falso positivo em smoke test.
      // Melhoria 10 da seção 8 (relatório V104.49.3-R6): as 19 rotas 'atualizar-v*' que apontavam
      // para LegacyDatabaseUpgradeController foram removidas daqui porque já eram CÓDIGO MORTO:
      // FastRouteDispatcherService intercepta todo 'atualizar-v*' antes do DashboardController e
      // manda para MigrationController::legacyBlocked(). Elas davam a impressão de existir uma
      // superfície de DDL em runtime que, na prática, o roteamento já não alcançava. O próprio
      // controller passou a recusar execução salvo liberação explícita (ver o arquivo dele).
      // Fase 3 (A3-03): cases fantasma removidos — rotas já interceptadas pelo
      // FastRouteDispatcherService::$dispatchGroups (controllers dedicados) antes deste fallback.
      default: $this->naoEncontrado($page);
    }
  }


  /** Resposta 404 controlada para rotas inexistentes (A-13), sem expor detalhes internos. */
  private function naoEncontrado(string $page): void {
    http_response_code(404);
    if (class_exists('Audit')) {
      try {
        Audit::event('rota.nao_encontrada','alerta',[
          'codigo_erro'=>'ROUTE_NOT_FOUND',
          'mensagem'=>'Rota inexistente acessada.',
          'contexto'=>['page'=>substr($page,0,190)],
          'acao_recomendada'=>'Verifique links internos ou favoritos desatualizados.'
        ]);
      } catch (Throwable $e) { if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    }
    $pageTitle = 'Página não encontrada';
    require __DIR__.'/../../views/nao_encontrado.php';
  }

  // Reauditoria 2026-09-14: operação usa apenas 'empresas' como entidade de negócio.
  // 'filiais' saiu das telas/contadores e do seed; a tabela permanece no schema (vazia)
  // para não quebrar instalações existentes, mas não é mais consultada pelo painel.
  private const SAFE_TABLES = ['auditoria_eventos','empresas','estoque_divergencias','estoque_movimentos','fila_estoque','fila_fiscal','fila_integracao','logs_integracao','notificacoes','pedidos_hub','pedidos_integracao','pedidos_nfe_xml','produtos_mapeamento'];

  private const SAFE_COLUMNS = [
    'auditoria_eventos' => ['id','trace_id','usuario_id','acao','entidade','entidade_id','status','nivel','codigo_erro','mensagem','causa_provavel','acao_recomendada','payload','retorno','contexto','ip','criado_em'],
    'empresas' => ['id','nome','cnpj','ativo','criado_em'],
    'estoque_divergencias' => ['id','sku','estoque_vsm','estoque_tiny','diferenca','origem','status','acao_recomendada','trace_id','detalhes','criado_em','atualizado_em'],
    'estoque_movimentos' => ['id','origem','referencia','sku','quantidade','tipo_movimento','status','payload_origem','retorno_vsm','trace_id','criado_em','atualizado_em'],
    'fila_estoque' => ['id','origem','destino','sku','quantidade','status','tentativas','proxima_tentativa','payload','retorno','ultimo_erro','trace_id','criado_em','atualizado_em'],
    'fila_fiscal' => ['id','nota_fiscal_id','nfe_integracao_id','acao','prioridade','status','tentativas','proxima_tentativa','payload','retorno','ultimo_erro','trace_id','criado_em','atualizado_em'],
    'fila_integracao' => ['id','tipo','prioridade','categoria','referencia','payload','retorno','status','codigo_erro','tentativas','proxima_tentativa','processando_desde','processado_em','trace_id','criado_em'],
    'logs_integracao' => ['id','trace_id','tipo','nivel','codigo_erro','mensagem','payload','ip','criado_em'],
    'notificacoes' => ['id','tipo','titulo','mensagem','severidade','entidade','entidade_id','link','trace_id','payload','lida','lida_em','criada_em'],
    'pedidos_hub' => ['id','trace_id','pedido_tiny_id','pedido_vsm_id','numero_pedido','status_hub','status_tiny','status_vsm','cliente_nome','cliente_documento','valor_total','data_recebido_tiny','data_enviado_vsm','data_recebido_vsm','data_enviado_tiny','ultimo_erro','criado_em','atualizado_em'],
    'pedidos_integracao' => ['id','origem','pedido_origem_id','pedido_tiny_id','empresa_id','filial_id','cliente_nome','cliente_documento','valor_total','status','payload_origem','payload_tiny','retorno_tiny','erro','tentativas','trace_id','criado_em','atualizado_em'],
    'pedidos_nfe_xml' => ['id','pedido_hub_id','chave_nfe','numero_nfe','serie','xml_original','xml_hash','status_xml','validado','erro_validacao','retorno_tiny_json','enviado_tiny_em','criado_em','atualizado_em'],
    'produtos_mapeamento' => ['id','sku_tiny','sku_vsm','produto_tiny_id','produto_vsm_id','descricao','ativo','estoque_atual','status_tiny','ultima_sincronizacao','criado_em'],
  ];

  private function safeIdentifier(string $identifier): string {
    if (!in_array($identifier, self::SAFE_TABLES, true)) {
      throw new InvalidArgumentException('Tabela não permitida.');
    }
    return $identifier;
  }

  private function safeColumn(string $table, string $column): string {
    $table = $this->safeIdentifier($table);
    $column = trim($column, " `\t\n\r\0\x0B");
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column)) {
      throw new InvalidArgumentException('Coluna inválida.');
    }
    if (!in_array($column, self::SAFE_COLUMNS[$table] ?? [], true)) {
      throw new InvalidArgumentException('Coluna não permitida para a tabela.');
    }
    return '`'.$column.'`';
  }

  private function safeSqlFragment(string $table, string $fragment): string {
    $table = $this->safeIdentifier($table);
    $fragment = trim($fragment) ?: '1=1';
    if ($fragment === '1=1') return $fragment;
    if (preg_match('/(;|--|#|\/\*|\*\/|\b(UNION|SELECT|INSERT|UPDATE|DELETE|DROP|ALTER|TRUNCATE|CREATE|REPLACE|LOAD_FILE|OUTFILE)\b)/i', $fragment)) {
      throw new InvalidArgumentException('Filtro SQL não permitido.');
    }
    if (!preg_match('/^[A-Za-z0-9_`\s\.\(\)=<>,!\'"%-]+$/', $fragment)) {
      throw new InvalidArgumentException('Filtro SQL inválido.');
    }
    preg_match_all('/`?([A-Za-z_][A-Za-z0-9_]*)`?\s*(?:=|!=|<>|<|>|<=|>=|LIKE|IN\s*\(|IS\s+NOT\s+NULL|IS\s+NULL)/i', $fragment, $matches);
    foreach ($matches[1] ?? [] as $col) {
      $upper = strtoupper($col);
      if (in_array($upper, ['AND','OR','IN','LIKE','IS','NOT','NULL','NOW','DATE_SUB','INTERVAL','MINUTE','HOUR','DAY'], true)) continue;
      if (!in_array($col, self::SAFE_COLUMNS[$table] ?? [], true)) {
        throw new InvalidArgumentException('Filtro usa coluna não permitida: '.$col);
      }
    }
    return $fragment;
  }

  private function safeOrderBy(string $table, string $orderBy): string {
    $table = $this->safeIdentifier($table);
    $parts = array_map('trim', explode(',', $orderBy ?: 'id DESC'));
    $safe = [];
    foreach ($parts as $part) {
      if (!preg_match('/^`?([A-Za-z_][A-Za-z0-9_]*)`?(\s+(ASC|DESC))?$/i', $part, $m)) {
        throw new InvalidArgumentException('Ordenação não permitida.');
      }
      $safe[] = $this->safeColumn($table, $m[1]).(isset($m[3]) ? ' '.strtoupper($m[3]) : '');
    }
    return implode(', ', $safe);
  }

  private function count(string $table, string $where='1=1'): int {
    $table = $this->safeIdentifier($table);
    $where = $this->safeSqlFragment($table, $where);
    // Melhoria 1 da seção 8: nove das SAFE_TABLES são tabelas com escopo de empresa. Como estes
    // dois auxiliares montam o SQL interpolando o nome da tabela, eles escapavam da varredura do
    // tenant-scope-check (que procura "FROM tabela" literal) e continuariam contando linhas de
    // TODAS as empresas nos cartões do dashboard. applyToSelect() resolve, e o verificador foi
    // ensinado a cobrar SQL interpolado também.
    [$sql, $params] = TenantScopeService::applyToSelect($table, "SELECT COUNT(*) c FROM `$table` WHERE $where", []);
    $st = Database::forTable($table)->prepare($sql);
    $st->execute($params);
    return (int)($st->fetch()['c'] ?? 0);
  }


  private function safeCount(string $table, string $where='1=1'): int {
    try { return $this->count($table, $where); } catch (Throwable $e) { return 0; }
  }

  private function statusDot(string $status): string {
    return $status === 'online' ? '🟢' : ($status === 'erro' ? '🔴' : '🟡');
  }

  private function workerCards(): array {
    $workers = [
      ['arquivo'=>'worker_fiscal.php','titulo'=>'XML/NF-e'],
      ['arquivo'=>'worker_estoque.php','titulo'=>'Estoque'],
      ['arquivo'=>'worker_consulta_estoque_vsm.php','titulo'=>'Consulta VSM'],
      ['arquivo'=>'worker_fila.php','titulo'=>'Fila'],
    ];
    $out=[];
    foreach($workers as $w){
      $file = __DIR__.'/../../public/'.$w['arquivo'];
      $exists = is_file($file);
      $out[] = [
        'titulo'=>$w['titulo'],
        'arquivo'=>$w['arquivo'],
        'status'=>$exists ? 'online' : 'erro',
        'detalhe'=>$exists ? 'Arquivo disponível para agendamento' : 'Arquivo não encontrado',
      ];
    }
    return $out;
  }

  private function operationalSummary(array $cfgInt=[]): array {
    $tinyV2Ok = !empty($cfgInt['tiny_v2_token']);
    $tinyV3Operacional = !empty($cfgInt['tiny_v3_operacional']);
    $tinyV3OAuth = !empty($cfgInt['tiny_v3_access_token']) || !empty($cfgInt['tiny_v3_refresh_token']) || !empty($cfgInt['tiny_v3_client_id']);
    $vsmOk = !empty($cfgInt['vsm_url']);
    $vsmStatus = class_exists('VsmEnvironmentService') ? VsmEnvironmentService::dashboardStatus($cfgInt) : ['status'=>$vsmOk?'atencao':'erro','modo'=>$vsmOk?'Homologação':'Não configurado','detalhe'=>$vsmOk?'URL configurada':'URL pendente','acao'=>$vsmOk?'Validar VSM':'Configurar VSM'];
    $filaErro = $this->safeCount('fila_integracao', "status IN ('erro','falha_definitiva')");
    $filaPendente = $this->safeCount('fila_integracao', "status='pendente'");
    $xmlErro = $this->safeCount('pedidos_hub', "status_hub IN ('erro_xml','erro_envio_tiny','erro_retorno_vsm')");
    $estoqueErro = $this->safeCount('fila_estoque', "status IN ('erro','falha_definitiva')");
    $alertas = [];
    if(!$tinyV2Ok) $alertas[] = ['nivel'=>'atencao','titulo'=>'Tiny V2 sem token','mensagem'=>'Configure o token antes de produção.'];
    if(!$tinyV3Operacional) $alertas[] = ['nivel'=>'atencao','titulo'=>'Tiny V3 em homologação','mensagem'=>'Mantenha V2 em produção até OAuth e testes reais passarem.'];
    if(!$vsmOk) $alertas[] = ['nivel'=>'erro','titulo'=>'VSM sem URL','mensagem'=>'Configure a URL/base da VSM.'];
    if($filaErro>0) $alertas[] = ['nivel'=>'erro','titulo'=>'Fila com erro','mensagem'=>$filaErro.' item(ns) com erro definitivo.'];
    if($xmlErro>0) $alertas[] = ['nivel'=>'erro','titulo'=>'XML/NF-e com erro','mensagem'=>$xmlErro.' pedido(s) com problema de XML/NF-e.'];
    if($estoqueErro>0) $alertas[] = ['nivel'=>'erro','titulo'=>'Estoque com erro','mensagem'=>$estoqueErro.' item(ns) com falha na fila de estoque.'];
    $geral = 'online';
    foreach($alertas as $a){ if($a['nivel']==='erro'){ $geral='erro'; break; } if($a['nivel']==='atencao') $geral='atencao'; }
    return [
      'geral'=>$geral,
      'alertas'=>$alertas,
      'integracoes'=>[
        ['nome'=>'Tiny V2','status'=>$tinyV2Ok?'online':'atencao','modo'=>'Produção','detalhe'=>$tinyV2Ok?'Token configurado':'Token pendente'],
        ['nome'=>'Tiny V3','status'=>$tinyV3Operacional?'online':'atencao','modo'=>$tinyV3Operacional?'Produção':'Homologação','detalhe'=>$tinyV3OAuth?'OAuth/configuração parcial':'OAuth pendente'],
        ['nome'=>'VSM','status'=>$vsmStatus['status'],'modo'=>$vsmStatus['modo'],'detalhe'=>$vsmStatus['detalhe']],
      ],
      'filas'=>['pendentes'=>$filaPendente,'erros'=>$filaErro],
    ];
  }

  private function tableRows(string $table, string $orderBy='id DESC', int $limit=10, string $where='1=1'): array {
    $table = $this->safeIdentifier($table);
    $where = $this->safeSqlFragment($table, $where);
    $orderBy = $this->safeOrderBy($table, $orderBy);
    $limit = max(1, min(500, $limit));
    $cols = class_exists('HeavyQueryOptimizerService') ? HeavyQueryOptimizerService::columns($table, '*') : '*';
    // Melhoria 1 da seção 8: ver a nota em count(). O predicado entra antes de ORDER BY/LIMIT.
    [$sql, $params] = TenantScopeService::applyToSelect($table, "SELECT $cols FROM `$table` WHERE $where ORDER BY $orderBy LIMIT $limit", []);
    $st = Database::forTable($table)->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
  }

  private function tableOne(string $table, string $orderBy='id DESC', string $where='1=1'): ?array {
    $rows = $this->tableRows($table, $orderBy, 1, $where);
    return $rows[0] ?? null;
  }



  private function dashboardIntegridade(): void {
    PermissionService::require('dashboard','visualizar');
    $checks = DashboardIntegrityService::checks();
    $summary = DashboardIntegrityService::summary($checks);
    $pageTitle = 'Integridade do Dashboard';
    require __DIR__.'/../../views/dashboard_integridade.php';
  }

  private function pedidos(): void {
    PermissionService::require('pedidos','visualizar');
    $status = $_GET['status'] ?? '';
    $busca = trim($_GET['busca'] ?? '');
    $sql = "SELECT * FROM pedidos_integracao WHERE 1=1"; $params=[];
    if($status){ $sql .= " AND status=?"; $params[]=$status; }
    if($busca){ $sql .= " AND (pedido_origem_id LIKE ? OR pedido_tiny_id LIKE ? OR cliente_nome LIKE ?)"; $params[]="%$busca%"; $params[]="%$busca%"; $params[]="%$busca%"; }
    // Melhoria 1 da seção 8: o escopo de empresa entra DEPOIS do WHERE montado pelos filtros e
    // ANTES do ORDER BY/LIMIT — applyToSelect() cuida da posição do parâmetro.
    [$sql, $params] = TenantScopeService::applyToSelect('pedidos_integracao', $sql, $params);
    $sql .= " ORDER BY id DESC LIMIT 100";
    $st=Database::tableConnectionForSql($sql)->prepare($sql); $st->execute($params); $pedidos=$st->fetchAll();
    $pageTitle = 'Pedidos';
    require __DIR__.'/../../views/pedidos.php';
  }


  private function pedidoDetalhe(): void {
    PermissionService::require('pedidos','detalhe');
    $id = (int)($_GET['id'] ?? 0);
    $st = TenantScopeService::run('pedidos_integracao', "SELECT * FROM pedidos_integracao WHERE id=? LIMIT 1", [$id]);
    $pedido=$st->fetch();
    if(!$pedido){ http_response_code(404); echo 'Pedido não encontrado'; return; }
    $trace=$pedido['trace_id'] ?? '';
    $timeline=[]; $fila=[];
    if($trace){
      $st=Database::forTable('auditoria_eventos')->prepare("SELECT * FROM auditoria_eventos WHERE trace_id=? ORDER BY id ASC"); $st->execute([$trace]); $timeline=$st->fetchAll();
      $st = TenantScopeService::run('fila_integracao', "SELECT * FROM fila_integracao WHERE trace_id=? OR referencia=? ORDER BY id DESC", [$trace,$pedido['pedido_origem_id']]); $fila=$st->fetchAll();
    }
    $pageTitle='Detalhe do Pedido';
    require __DIR__.'/../../views/pedido_detalhe.php';
  }

  private function fila(): void {
    PermissionService::require('fila','visualizar');
    $status = $_GET['status'] ?? '';
    $sql = "SELECT id,tipo,referencia,status,tentativas,proxima_tentativa,trace_id,codigo_erro FROM fila_integracao WHERE 1=1"; $params=[];
    if($status){ $sql .= " AND status=?"; $params[]=$status; }
    // Melhoria 1 da seção 8: o escopo de empresa entra DEPOIS do WHERE montado pelos filtros e
    // ANTES do ORDER BY/LIMIT — applyToSelect() cuida da posição do parâmetro.
    [$sql, $params] = TenantScopeService::applyToSelect('fila_integracao', $sql, $params);
    $sql .= " ORDER BY id DESC LIMIT 200";
    $st=Database::tableConnectionForSql($sql)->prepare($sql); $st->execute($params); $itens=$st->fetchAll();
    $pageTitle = 'Fila de Integração';
    require __DIR__.'/../../views/fila.php';
  }

  private function filaReprocessar(): void {
    PermissionService::require('fila','reprocessar');
    Csrf::validate();
    $id=(int)($_POST['id'] ?? 0);
    if($id>0) QueueService::reprocessar($id);
    redirect('index.php?page=fila');
  }

  private function filaCriarTeste(): void {
    PermissionService::require('fila','reprocessar');
    Csrf::validate();
    $trace = RequestContext::id();
    $referencia = 'BAIXA-TESTE-'.date('Ymd-His');
    $payload = [
      'referencia' => $referencia,
      'origem' => 'tiny',
      'evento' => 'baixa_estoque_teste',
      'itens' => [[
        'sku' => 'TESTE001',
        'quantidade' => 1,
        'descricao' => 'Produto Teste Hub de Integração',
        'idProdutoTiny' => 'TESTE-TINY-001'
      ]],
      'observacao' => 'Baixa de estoque teste criada manualmente pelo painel. Este teste segue o fluxo correto: Tiny → Hub → VSM.',
      'criado_em' => date('c')
    ];
    $st = TenantScopeService::run('fila_integracao', 'INSERT INTO fila_integracao(tipo,referencia,payload,status,trace_id) VALUES(?,?,?,?,?)', ['baixa_estoque_vsm',$referencia,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'pendente',$trace]);
    Audit::event('fila.criar_baixa_teste','sucesso',[
      'entidade'=>'fila_integracao',
      'entidade_id'=>Database::forTable('fila_integracao')->lastInsertId(),
      'mensagem'=>'Baixa de estoque teste criada na fila conforme o fluxo correto Tiny → VSM.',
      'payload'=>$payload,
      'acao_recomendada'=>'Agora clique em Processar 1 item para testar o envio da baixa de estoque para a VSM.'
    ]);
    NotificationService::criar('sistema','Baixa Tiny teste criada','Foi criado um item pendente de baixa de estoque Tiny → VSM.','info',['trace_id'=>$trace,'link'=>'index.php?page=fila']);
    redirect('index.php?page=fila&baixa_teste_criada=1');
  }

  private function produtos(): void {
    PermissionService::require('produtos','visualizar');
    $produtos = TenantScopeService::run('produtos_mapeamento', "SELECT id, sku_tiny, sku_vsm, produto_tiny_id, produto_vsm_id, descricao, ativo, estoque_atual, status_tiny, ultima_sincronizacao, criado_em FROM produtos_mapeamento ORDER BY id DESC LIMIT 100")->fetchAll();
    $pageTitle = 'Produtos Mapeados';
    require __DIR__.'/../../views/produtos.php';
  }



  private function logs(): void {
    PermissionService::require('logs','visualizar');
    $nivel = $_GET['nivel'] ?? '';
    $sql = "SELECT id,nivel,trace_id,tipo,codigo_erro,mensagem,ip,criado_em FROM logs_integracao"; $params=[];
    if($nivel){ $sql .= " WHERE nivel=?"; $params[]=$nivel; }
    $sql .= " ORDER BY id DESC LIMIT 200";
    // Achado C-05: com 100 clientes, esta tela mostrava os logs de todos misturados.
    [$sql, $params] = TenantScopeService::applyToSelect('logs_integracao', $sql, $params);
    $st=Database::tableConnectionForSql($sql)->prepare($sql); $st->execute($params); $logs=$st->fetchAll();
    $pageTitle = 'Logs';
    require __DIR__.'/../../views/logs.php';
  }


  private function notificacoes(): void {
    PermissionService::require('notificacoes','visualizar');
    $tipo = $_GET['tipo'] ?? '';
    $lida = $_GET['lida'] ?? '';
    $sql = "SELECT * FROM notificacoes WHERE 1=1"; $params=[];
    if($tipo){ $sql .= " AND tipo=?"; $params[]=$tipo; }
    if($lida !== ''){ $sql .= " AND lida=?"; $params[]=(int)$lida; }
    $sql .= " ORDER BY id DESC LIMIT 300";
    $st=Database::tableConnectionForSql($sql)->prepare($sql); $st->execute($params); $notificacoes=$st->fetchAll();
    $resumo = Database::forTable('notificacoes')->query("SELECT severidade, COUNT(*) total FROM notificacoes WHERE lida=0 GROUP BY severidade")->fetchAll();
    $pageTitle = 'Notificações';
    require __DIR__.'/../../views/notificacoes.php';
  }

  private function marcarNotificacaoLida(): void {
    PermissionService::require('notificacoes','visualizar');
    $id = (int)($_POST['id'] ?? 0);
    $all = (int)($_POST['todas'] ?? 0);
    Csrf::validate();
    if($all){ NotificationService::marcarTodasLidas(); }
    elseif($id > 0){ NotificationService::marcarLida($id); }
    Audit::event('notificacao.marcar_lida','sucesso',['mensagem'=>$all?'Todas as notificações marcadas como lidas':'Notificação marcada como lida','entidade'=>'notificacoes','entidade_id'=>$id ?: 'todas']);
    redirect('index.php?page=notificacoes');
  }


  private function diagnostico(): void {
    PermissionService::require('dashboard','visualizar');
    $checks = HealthCheckService::run();
    $historicoDiagnostico = class_exists('DiagnosticoApiService') ? DiagnosticoApiService::ultimos(50) : [];
    $pageTitle = 'Diagnóstico';
    require __DIR__.'/../../views/diagnostico.php';
  }



  private function tinyV3Callback(): void {
    // Reauditoria 2026-09-14 (achado A-02): este callback roda ANTES do gate de sessão
    // (ver FastRouteDispatcherService), porque o retorno do provedor OAuth é cross-site e o
    // navegador não envia o cookie SameSite=Strict. A autorização acontece logo abaixo,
    // contra o perfil vinculado à transação OAuth assinada - nunca contra a sessão.
    $cfg = IntegrationConfig::get();
    $code = trim((string)($_GET['code'] ?? ''));
    $state = trim((string)($_GET['state'] ?? ''));
    if ($code === '') {
      Audit::event('tiny.v3.oauth.callback.erro','erro',[
        'mensagem'=>'Callback Tiny V3 recebido sem authorization code.',
        'codigo_erro'=>'TINY_V3_OAUTH_CODE_MISSING',
        'contexto'=>['query'=>$_GET],
        'acao_recomendada'=>'Clique novamente em Conectar Tiny V3 e confira Redirect URI no aplicativo Tiny/Olist.'
      ]);
      redirect('index.php?page=tiny-v3-ficha&oauth=erro');
    }
    // P0-01 (reauditoria 2026-08-23): valida o state real (aleatório, uso único, TTL)
    // ANTES de trocar o code por token. Sem isso o callback aceitava qualquer state.
    try {
      $oauthTransaction = OAuthStateService::consume('tiny_v3', $state);
      $codeVerifier = $oauthTransaction['verifier'];
    } catch (Throwable $e) {
      Audit::event('tiny.v3.oauth.state.invalido','erro',[
        'mensagem'=>'Callback Tiny V3 rejeitado: state OAuth inválido, expirado ou reaproveitado.',
        'codigo_erro'=>'TINY_V3_OAUTH_STATE_INVALID',
        'contexto'=>['detalhe'=>$e->getMessage()],
        'acao_recomendada'=>'Possível tentativa de OAuth CSRF ou link de callback reaproveitado. Inicie a conexão novamente pela Ficha Tiny V3.'
      ]);
      redirect('index.php?page=tiny-v3-ficha&oauth=erro');
    }
    // Reauditoria 2026-09-14 (achado B-01/B-03, revisão da própria correção do A-02): a transação
    // identifica QUEM iniciou, mas o perfil gravado nela tem até 10 minutos de idade. Se nesse
    // intervalo a conta foi desativada ou rebaixada, autorizar pelo instantâneo seria decidir por
    // dado vencido. A identidade vem da transação assinada; a autorização vem do estado atual do
    // banco. userById() já filtra ativo=1, então conta desativada não passa.
    $usuarioAtual = AuthRepository::userById((int)$oauthTransaction['user_id']);
    if (!$usuarioAtual) {
      Audit::event('tiny.v3.oauth.callback.usuario_invalido','erro',[
        'mensagem'=>'Callback Tiny V3 rejeitado: usuário que iniciou o fluxo não existe mais ou foi desativado.',
        'codigo_erro'=>'TINY_V3_OAUTH_USER_INACTIVE',
        'entidade'=>'usuarios','entidade_id'=>$oauthTransaction['user_id'],
        'acao_recomendada'=>'Refaça a conexão com uma conta ativa.'
      ]);
      redirect('index.php?page=tiny-v3-ficha&oauth=erro');
    }
    $oauthTransaction['perfil'] = (string)($usuarioAtual['perfil'] ?? '');
    if (!PermissionService::canForProfile($oauthTransaction['perfil'], 'configuracoes', 'editar')) {
      Audit::event('tiny.v3.oauth.callback.sem_permissao','erro',[
        'mensagem'=>'Callback Tiny V3 rejeitado: o usuário que iniciou o fluxo não tem permissão para editar configurações.',
        'codigo_erro'=>'TINY_V3_OAUTH_FORBIDDEN',
        'entidade'=>'usuarios',
        'entidade_id'=>$oauthTransaction['user_id'],
        'contexto'=>['perfil'=>$oauthTransaction['perfil']],
        'acao_recomendada'=>'Refaça a conexão com um usuário que tenha permissão configuracoes.editar.'
      ]);
      redirect('index.php?page=tiny-v3-ficha&oauth=erro');
    }
    try {
      $tokenUrl = (string)($cfg['tiny_v3_token_url'] ?? '');
      $clientId = (string)($cfg['tiny_v3_client_id'] ?? '');
      $clientSecret = (string)($cfg['tiny_v3_client_secret'] ?? '');
      $redirectUri = PublicUrlService::tinyV3RedirectUri((string)($cfg['tiny_v3_redirect_uri'] ?? ''));
      if ($tokenUrl==='' || $clientId==='' || $clientSecret==='' || $redirectUri==='') {
        throw new RuntimeException('Configuração OAuth Tiny V3 incompleta: Token URL, Client ID, Client Secret ou Redirect URI ausente.');
      }
      $post = [
        'grant_type'=>'authorization_code',
        'code'=>$code,
        'redirect_uri'=>$redirectUri,
        'client_id'=>$clientId,
        'client_secret'=>$clientSecret,
        'code_verifier'=>$codeVerifier,
      ];
      $tokenUrl = TinyEndpointSecurityService::validateUrl($tokenUrl);
      $securityOptions = TinyEndpointSecurityService::curlSecurityOptions($tokenUrl);
      $ch = curl_init($tokenUrl);
      curl_setopt_array($ch,$securityOptions+[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
      $body = curl_exec($ch); $err = curl_error($ch); $http = (int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
      if ($err) throw new RuntimeException('Erro cURL OAuth Tiny V3: '.$err);
      $json = json_decode((string)$body,true);
      if ($http < 200 || $http >= 300 || !is_array($json) || empty($json['access_token'])) {
        Audit::event('tiny.v3.oauth.token.erro','erro',[
          'mensagem'=>'Tiny V3 recusou troca do code por token.',
          'codigo_erro'=>'TINY_V3_OAUTH_TOKEN_EXCHANGE_FAILED',
          'http_code'=>$http,
          'retorno'=>SensitiveDataService::mask((string)$body),
          'acao_recomendada'=>'Confira Client ID, Client Secret, Redirect URI e permissões do aplicativo no Tiny/Olist.'
        ]);
        redirect('index.php?page=tiny-v3-ficha&oauth=erro');
      }
      TinyV3TokenService::saveOAuthToken((string)$json['access_token'], (string)($json['refresh_token'] ?? ''), (int)($json['expires_in'] ?? 3600), (string)($json['scope'] ?? ''), $cfg['tiny_v3_ambiente'] ?? 'homologacao');
      Audit::event('tiny.v3.oauth.callback.sucesso','sucesso',[
        'mensagem'=>'Tiny V3 conectado via OAuth e token salvo por ambiente.',
        'entidade'=>'usuarios',
        'entidade_id'=>$oauthTransaction['user_id'],
        'contexto'=>['ambiente'=>$cfg['tiny_v3_ambiente'] ?? 'homologacao','state'=>$state,'iniciado_por_usuario_id'=>$oauthTransaction['user_id'],'perfil'=>$oauthTransaction['perfil']]
      ]);
      redirect('index.php?page=tiny-v3-ficha&oauth=ok');
    } catch(Throwable $e) {
      Audit::exception($e,'tiny.v3.oauth.callback.exception',[ 'codigo_erro'=>'TINY_V3_OAUTH_CALLBACK_EXCEPTION', 'acao_recomendada'=>'Corrija a configuração OAuth no painel e tente conectar novamente.' ]);
      redirect('index.php?page=tiny-v3-ficha&oauth=erro');
    }
  }




  /**
   * P2 (reauditoria 2026-08-23): neutraliza injeção de fórmula CSV (CWE-1236). Um campo
   * gravado no banco (ex.: mensagem de log/auditoria vinda de payload externo) que comece
   * com =, +, -, @, tab ou CR poderia virar uma fórmula executável ao abrir o CSV no
   * Excel/Sheets/LibreOffice. Prefixa com aspas simples, que a maioria dos leitores trata
   * como "forçar texto" sem alterar o valor visível.
   */
  private function csvSafeRow(array $row): array {
    foreach ($row as $k => $v) {
      $s = (string)$v;
      if ($s !== '' && preg_match('/^[=+\-@\t\r]/', $s)) $row[$k] = "'".$s;
    }
    return $row;
  }

  private function logsExportar(): void {
    PermissionService::require('logs','exportar');
    // Achado C-05: a exportação entrega um arquivo ao cliente — aqui o escopo é ESTRITO, porque
    // incluir linhas legadas sem empresa num CSV entregue a um cliente seria vazamento.
    $st = TenantScopeService::run('logs_integracao', "SELECT id,trace_id,tipo,nivel,codigo_erro,mensagem,ip,criado_em FROM logs_integracao ORDER BY id DESC LIMIT 5000");
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="logs_integracao_'.date('Ymd_His').'.csv"');
    $out=fopen('php://output','w');
    fputcsv($out,['id','trace_id','tipo','nivel','codigo_erro','mensagem','ip','criado_em'],';');
    while($r=$st->fetch(PDO::FETCH_ASSOC)){ fputcsv($out,$this->csvSafeRow($r),';'); }
    Audit::event('logs.exportar','sucesso',['mensagem'=>'Logs exportados em CSV']);
    exit;
  }



  private function produtosPendencias(): void {
    PermissionService::require('produtos_vsm','visualizar');
    $status = $_GET['status'] ?? '';
    $busca = trim($_GET['busca'] ?? '');
    $sql = "SELECT * FROM produto_pendencias WHERE 1=1"; $params=[];
    if($status !== ''){ $sql .= " AND status=?"; $params[]=$status; }
    if($busca !== ''){ $sql .= " AND (sku LIKE ? OR motivo LIKE ? OR trace_id LIKE ?)"; $params[]="%$busca%"; $params[]="%$busca%"; $params[]="%$busca%"; }
    // Melhoria 1 da seção 8: o escopo de empresa entra DEPOIS do WHERE montado pelos filtros e
    // ANTES do ORDER BY/LIMIT — applyToSelect() cuida da posição do parâmetro.
    [$sql, $params] = TenantScopeService::applyToSelect('produto_pendencias', $sql, $params);
    $sql .= " ORDER BY id DESC LIMIT 300";
    $st=Database::tableConnectionForSql($sql)->prepare($sql); $st->execute($params); $pendencias=$st->fetchAll();
    $pageTitle='Pendências de Produtos';
    require __DIR__.'/../../views/produtos_pendencias.php';
  }


  private function filaMorta(): void {
    PermissionService::require('fila_morta','visualizar');
    $status=$_GET['status'] ?? 'aberto';
    $sql='SELECT * FROM fila_morta WHERE 1=1'; $params=[];
    if($status !== ''){ $sql.=' AND status=?'; $params[]=$status; }
    // Melhoria 1 da seção 8: o escopo de empresa entra DEPOIS do WHERE montado pelos filtros e
    // ANTES do ORDER BY/LIMIT — applyToSelect() cuida da posição do parâmetro.
    [$sql, $params] = TenantScopeService::applyToSelect('fila_morta', $sql, $params);
    $sql.=' ORDER BY id DESC LIMIT 300';
    $st=Database::tableConnectionForSql($sql)->prepare($sql); $st->execute($params); $itens=$st->fetchAll();
    $pageTitle='Fila Morta / DLQ';
    require __DIR__.'/../../views/fila_morta.php';
  }

  private function metricas(): void {
    PermissionService::require('metricas','visualizar');
    $metricas=$this->db('metricas_api')->query('SELECT id, sistema, endpoint, metodo, http_code, tempo_ms, sucesso, criado_em FROM metricas_api ORDER BY id DESC LIMIT 300')->fetchAll();
    $cb=Database::forTable('circuit_breakers')->query('SELECT id, sistema, status, falhas_consecutivas, aberto_ate, atualizado_em FROM circuit_breakers ORDER BY sistema')->fetchAll();
    $pageTitle='Métricas e Circuit Breaker';
    require __DIR__.'/../../views/metricas.php';
  }








  private function fichaTecnica100(): void {
    PermissionService::require('ficha_tecnica','visualizar');
    $prechecks = InstallationPrecheckService::run();
    $preScore = InstallationPrecheckService::score();
    $queue = QueueAnalyticsService::resumo();
    $tinyV2Errors = TinyV2ErrorCatalogService::all();
    $cfg = IntegrationConfig::get();
    $pageTitle = 'Ficha Técnica 100%';
    require __DIR__.'/../../views/ficha_tecnica_100.php';
  }




  private function productionReadyV25(): void {
    PermissionService::require('ficha_tecnica','visualizar');
    $scores = ProductionReadinessV24Service::scores();
    $checklist = ProductionReadinessV24Service::checklist();
    $postTests = PostInstallTestService::run();
    $postScore = PostInstallTestService::score($postTests);
    $trace = trim($_GET['trace_id'] ?? RequestContext::id());
    $integridade = class_exists('AuditIntegrityService') ? AuditIntegrityService::verificarTrace($trace) : ['integro'=>false,'mensagem'=>'Serviço indisponível'];
    $pageTitle = 'Production Ready V25';
    require __DIR__.'/../../views/production_ready_v25.php';
  }

  private function productionReadyV24(): void {
    PermissionService::require('ficha_tecnica','visualizar');
    $scores = ProductionReadinessV24Service::scores();
    $checklist = ProductionReadinessV24Service::checklist();
    $prechecks = class_exists('InstallationPrecheckService') ? InstallationPrecheckService::run() : [];
    $pageTitle = 'Production Ready V24';
    require __DIR__.'/../../views/production_ready_v24.php';
  }

  private function filaAnalyticsV24(): void {
    PermissionService::require('fila_morta','visualizar');
    $analytics = QueueV24AnalyticsService::resumoAvancado();
    $pageTitle = 'Fila / DLQ Analytics V24';
    require __DIR__.'/../../views/fila_analytics_v24.php';
  }


  private function productionReadyV26(): void {
    PermissionService::require('production_ready','visualizar');
    $resumo = EnterpriseV26ReadinessService::resumo();
    $pageTitle = 'Production Ready V26 / InfinityFree';
    require __DIR__.'/../../views/production_ready_v26.php';
  }

  private function hostingInfinityFree(): void {
    PermissionService::require('hosting','visualizar');
    $compat = HostingCompatibilityService::ambiente();
    $recomendacoes = HostingCompatibilityService::recomendações();
    $pageTitle = 'Hospedagem InfinityFree';
    require __DIR__.'/../../views/hosting_infinityfree.php';
  }



  private function integracoes(): void {
    PermissionService::require('configuracoes','visualizar');
    $rules = IntegrationOrchestratorService::all();
    $fluxos = IntegrationOrchestratorService::fluxos($rules);
    $ativos = count(array_filter($fluxos, fn($f)=>!empty($f['ativo'])));
    $ordem = IntegrationOrchestratorService::ordemLista();
    $arquitetura = class_exists('RouteModuleRegistry') ? RouteModuleRegistry::architectureControllers() : [];
    $pageTitle = 'Central de Integrações';
    require __DIR__.'/../../views/integracoes.php';
  }

  private function atualizadorSeguro(): void {
    PermissionService::require('database','validar');
    $arquivos = glob(dirname(__DIR__,2).'/database/update_v*.sql') ?: [];
    sort($arquivos, SORT_NATURAL);
    $pageTitle='Atualizador Seguro Universal';
    require __DIR__.'/../../views/atualizador_seguro.php';
  }

  private function atualizadorSeguroExecutar(): void {
    PermissionService::require('database','validar');
    Csrf::validate();
    $relatorio = (new UniversalUpgradeService(dirname(__DIR__,2)))->run();
    $_SESSION['upgrade_report_v32'] = $relatorio;
    redirect('index.php?page=atualizador-seguro&executado=1');
  }


  private function relatorioProntidaoProducao(): void {
    PermissionService::require('configuracoes','visualizar');
    $checks = class_exists('ProductionReadinessV24Service') ? ProductionReadinessV24Service::checks() : [];
    $host = class_exists('HostingCompatibilityService') ? HostingCompatibilityService::ambiente() : [];
    $pageTitle = 'Relatório de Prontidão para Produção';
    require __DIR__.'/../../views/relatorio_prontidao_producao.php';
  }

  private function fiscal(): void {
    PermissionService::require('configuracoes','visualizar');
    $resumoFiscal = class_exists('FiscalIntegrationService') ? FiscalIntegrationService::resumo() : [];
    $notas = [];
    try { $notas = FiscalIntegrationService::listar($_GET['status'] ?? '', 100); } catch(Throwable $e) { $erroFiscal = $e->getMessage(); }
    $nfeIntegracoes=[]; try { $nfeIntegracoes = TenantScopeService::run('nfe_integracao', 'SELECT i.*, n.numero, n.serie, n.chave_acesso FROM nfe_integracao i LEFT JOIN notas_fiscais n ON n.id=i.nota_fiscal_id ORDER BY i.id DESC LIMIT 50', [], 'i')->fetchAll(); } catch(Throwable $e) { $erroFiscal = ($erroFiscal ?? '').' '.$e->getMessage(); }
    $pageTitle = 'XML / NF-e';
    require __DIR__.'/../../views/fiscal.php';
  }

  private function dashboardIntegridadeExecutar(): void {
    PermissionService::require('dashboard','visualizar');
    Csrf::validate();
    $checks = DashboardIntegrityService::checks(true);
    $_SESSION['dashboard_integridade_v43'] = ['checks'=>$checks, 'summary'=>DashboardIntegrityService::summary($checks), 'executado_em'=>date('Y-m-d H:i:s')];
    Audit::event('dashboard.integridade.executar','sucesso',['mensagem'=>'Checklist real do dashboard V43 executado.','contexto'=>DashboardIntegrityService::summary($checks)]);
    redirect('index.php?page=dashboard-integridade&executado=1');
  }


  private function menuTestes(): void {
    PermissionService::require('dashboard','visualizar');
    $checks = class_exists('MenuActionTestService') ? MenuActionTestService::run() : [];
    $pageTitle = 'Teste de Menu e Botões';
    require __DIR__.'/../../views/menu_testes.php';
  }

  private function fiscalReenviar(): void {
    PermissionService::require('configuracoes','visualizar');
    Csrf::validate();
    $id=(int)($_POST['id'] ?? 0);
    if ($id>0 && class_exists('FiscalIntegrationService')) FiscalIntegrationService::marcarReenvio($id);
    Audit::event('fiscal.reenviar','sucesso',['mensagem'=>'NF-e marcada para reenvio à VSM.','entidade'=>'nfe_integracao','entidade_id'=>$id]);
    redirect('index.php?page=fiscal&reenviar=1');
  }

  private function centralTecnica(): void {
    PermissionService::require('configuracoes','visualizar');
    $pageTitle = 'Central Técnica';
    require __DIR__.'/../../views/central_tecnica.php';
  }

  private function bancosModulos(): void {
    PermissionService::require('database','validar');
    $relatorio = ModuleDatabaseService::relatorio();
    $pageTitle = 'Bancos por Módulo';
    require __DIR__.'/../../views/bancos_modulos.php';
  }


  private function bancosModulosInstalar(): void {
    PermissionService::require('database','validar');
    Csrf::validate();
    $modulo = trim((string)($_POST['modulo'] ?? 'todos'));
    if ($modulo === 'todos') $resultado = MultiDbMigrationService::instalarTodos();
    else $resultado = [$modulo => MultiDbMigrationService::instalarModulo($modulo)];
    $_SESSION['multidb_v42_resultado'] = $resultado;
    redirect('index.php?page=bancos-modulos&instalado=1');
  }


  private function producaoReady(): void {
    PermissionService::require('configuracoes','visualizar');
    $pageTitle = 'Produção Segura';
    $resultado = ProductionReadyService::checks();
    require __DIR__.'/../../views/producao_ready.php';
  }


}
