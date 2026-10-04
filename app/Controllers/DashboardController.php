<?php
class DashboardController {
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
      case 'integracoes': $this->integracoes(); break;
      case 'logs': $this->logs(); break;
      case 'notificacoes': $this->notificacoes(); break;
      case 'notificacao-lida': $this->marcarNotificacaoLida(); break;
      // Bloco de Segurança extraído para SecurityController (Fase 3) — despachado pelo
      // FastRouteDispatcherService::$dispatchGroups; os cases aqui eram inalcançáveis.
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




  private function metricas(): void {
    PermissionService::require('metricas','visualizar');
    $metricas=$this->db('metricas_api')->query('SELECT id, sistema, endpoint, metodo, http_code, tempo_ms, sucesso, criado_em FROM metricas_api ORDER BY id DESC LIMIT 300')->fetchAll();
    $cb=Database::forTable('circuit_breakers')->query('SELECT id, sistema, status, falhas_consecutivas, aberto_ate, atualizado_em FROM circuit_breakers ORDER BY sistema')->fetchAll();
    $pageTitle='Métricas e Circuit Breaker';
    require __DIR__.'/../../views/metricas.php';
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


}
