<?php
/**
 * Fase 3 (decomposição do controller-deus, 2026-09-26) — etapa Tiny.
 *
 * Reúne as telas e ações do Tiny (V2/V3, webhooks, teste real, tokens) que viviam no
 * DashboardController (achado A3-01). Rotas despachadas pelo FastRouteDispatcherService::$dispatchGroups;
 * nenhuma URL muda, só quem a atende. Handlers movidos verbatim (CSRF e PermissionService preservados);
 * o cálculo da Redirect URI passou a vir de PublicUrlService (fonte única compartilhada com o
 * DashboardController, que ainda a usa no formulário de Configurações e no callback OAuth).
 *
 * OBS: o callback OAuth (tiny-v3-callback) continua no DashboardController::dispatchOAuthCallback,
 * fora do gate de sessão — não faz parte deste controller.
 */
class TinyController extends BaseModuleController {
  public static function routes(): array {
    return [
      'testar-tiny','teste-real-tiny','teste-real-tiny-executar','tiny-webhooks',
      'tiny-v3-ficha','tiny-v2-ficha-tecnica','tiny-v3-token-salvar','tiny-v3-token-renovar',
      'tiny-v3-token-revogar','tiny-v3-testar','tiny-v3-testar-modulo','tiny-v3-endpoints-salvar',
      'tiny-ambientes',
    ];
  }

  public function dispatch(string $page): void {
    switch ($page) {
      case 'testar-tiny': $this->testarTiny(); break;
      case 'teste-real-tiny': $this->testeRealTiny(); break;
      case 'teste-real-tiny-executar': $this->testeRealTinyExecutar(); break;
      case 'tiny-webhooks': $this->tinyWebhooks(); break;
      case 'tiny-v2-ficha-tecnica': $this->tinyV2FichaTecnica(); break;
      case 'tiny-v3-token-salvar': $this->tinyV3TokenSalvar(); break;
      case 'tiny-v3-token-renovar': $this->tinyV3TokenRenovar(); break;
      case 'tiny-v3-token-revogar': $this->tinyV3TokenRevogar(); break;
      case 'tiny-v3-testar': $this->tinyV3Testar(); break;
      case 'tiny-v3-testar-modulo': $this->tinyV3TestarModulo(); break;
      case 'tiny-v3-endpoints-salvar': $this->tinyV3EndpointsSalvar(); break;
      case 'tiny-ambientes': $this->tinyAmbientes(); break;
      default: $this->tinyV3Ficha(); break; // tiny-v3-ficha
    }
  }

  private function db(string $table): PDO { return Database::forTable($table); }

  private function testarTiny(): void {
    PermissionService::require('configuracoes','editar');
    Csrf::validate();
    try {
      $tiny = TinyFactory::make();
      $ret = $tiny->consultarProduto($_POST['sku'] ?? 'TESTE');
      $okTiny = !isset($ret['erro']) && !isset($ret['errors']);
      if (class_exists('DiagnosticoApiService')) DiagnosticoApiService::registrar('tiny', 'produto.consultar', $okTiny ? 'online' : 'erro', null, null, $okTiny ? 'Tiny respondeu ao teste.' : 'Tiny retornou erro no teste.', $ret);
      Audit::event('tiny.teste_conexao','sucesso',['mensagem'=>'Teste de conexão Tiny executado.','retorno'=>$ret]);
      NotificationService::criar('sistema','Teste Tiny executado','Veja o retorno completo na Auditoria.','info',['trace_id'=>RequestContext::id()]);
      redirect('index.php?page=configuracoes&teste=ok');
    } catch(Throwable $e){
      Audit::exception($e,'tiny.teste_conexao.erro');
      redirect('index.php?page=configuracoes&teste=erro');
    }
  }

  private function testeRealTiny(): void {
    PermissionService::require('laboratorio','executar');
    $resultadoTeste = $_SESSION['ultimo_teste_real_tiny'] ?? null;
    unset($_SESSION['ultimo_teste_real_tiny']);
    $historicoTestes = TesteRealTinyService::ultimos(20);
    $pageTitle = 'Teste Real Tiny';
    require __DIR__.'/../../views/teste_real_tiny.php';
  }

  private function testeRealTinyExecutar(): void {
    PermissionService::require('laboratorio','executar');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
      header('Location: index.php?page=teste-real-tiny');
      return;
    }
    Csrf::validate();
    try {
      $resultado = TesteRealTinyService::executar($_POST);
    } catch (Throwable $e) {
      $resultado = [
        'success' => false,
        'trace_id' => RequestContext::id(),
        'sku' => trim((string)($_POST['sku'] ?? '')),
        'acao' => (string)($_POST['acao_teste'] ?? ''),
        'modo_tiny' => (string)($_POST['modo_tiny'] ?? ''),
        'versao_efetiva' => 'bloqueado_pelo_hub',
        'fallback_detectado' => false,
        'resultados' => [[
          'etapa' => 'pre_validacao_segura',
          'ok' => false,
          'mensagem' => $e->getMessage(),
          'codigo_erro' => 'HUB_TESTE_REAL_BLOQUEADO'
        ]],
        'executado_em' => date('Y-m-d H:i:s'),
      ];
      Audit::exception($e, 'teste_real_tiny.bloqueado');
    }
    $_SESSION['ultimo_teste_real_tiny'] = $resultado;
    header('Location: index.php?page=teste-real-tiny');
  }

  private function tinyWebhooks(): void {
    PermissionService::require('tiny_webhooks','visualizar');
    $tipo = $_GET['tipo'] ?? '';
    $status = $_GET['status'] ?? '';
    $busca = trim($_GET['busca'] ?? '');
    $sql = "SELECT * FROM tiny_webhooks WHERE 1=1"; $params=[];
    if($tipo){ $sql .= " AND tipo=?"; $params[]=$tipo; }
    if($status){ $sql .= " AND status=?"; $params[]=$status; }
    if($busca){ $sql .= " AND (referencia LIKE ? OR trace_id LIKE ? OR cnpj LIKE ? OR id_ecommerce LIKE ?)"; for($i=0;$i<4;$i++) $params[]="%$busca%"; }
    $sql .= " ORDER BY id DESC LIMIT 300";
    $st=Database::tableConnectionForSql($sql)->prepare($sql); $st->execute($params); $webhooks=$st->fetchAll();
    $pageTitle='Webhooks Tiny/Olist';
    require __DIR__.'/../../views/tiny_webhooks.php';
  }

  private function tinyV3Ficha(): void {
    PermissionService::require('configuracoes','editar');
    $config = IntegrationConfig::get();
    $config['tiny_v3_auth_url_error'] = null;
    try { $config['tiny_v3_auth_url'] = TinyEndpointSecurityService::validateUrl((string)($config['tiny_v3_auth_url'] ?? '')); }
    catch(Throwable $e) {
      $config['tiny_v3_auth_url_error'] = 'Auth URL Tiny V3 bloqueada pela allowlist de segurança.';
      $config['tiny_v3_auth_url'] = '';
      if(class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e, ['provider'=>'tiny_v3','field'=>'auth_url']);
    }
    $config['tiny_v3_redirect_uri_effective'] = PublicUrlService::tinyV3RedirectUri((string)($config['tiny_v3_redirect_uri'] ?? ''));
    $config['tiny_v3_redirect_uri_is_relative'] = !empty($config['tiny_v3_redirect_uri']) && !preg_match('#^https?://#i', (string)$config['tiny_v3_redirect_uri']);
    $tokenStatus = TinyV3TokenService::status();
    $ficha = TinyV3FichaTecnicaService::itens();
    $score = TinyV3FichaTecnicaService::score();
    $endpoints = TinyV3EndpointCatalog::defaults();
    $endpointValues = [];
    foreach($endpoints as $ek=>$ev){ $endpointValues[$ek] = TinyV3EndpointCatalog::get($ek); }
    try { $tokenRows = Database::forTable('tiny_v3_tokens')->query("SELECT id, ambiente, expires_at, scope, origem, criado_em, atualizado_em FROM tiny_v3_tokens ORDER BY ambiente ASC, id DESC LIMIT 20")->fetchAll(); } catch(Throwable $e) { $tokenRows = []; }
    try { $tinyV3Logs = Database::forTable('tiny_v3_endpoint_logs')->query("SELECT endpoint, metodo, http_code, sucesso, tempo_ms, trace_id, erro, criado_em FROM tiny_v3_endpoint_logs ORDER BY id DESC LIMIT 10")->fetchAll(); } catch(Throwable $e) { $tinyV3Logs = []; }
    $tinyV3Diagnostico = [
      'client_id' => !empty($config['tiny_v3_client_id']),
      'client_secret' => !empty($config['tiny_v3_client_secret']),
      'redirect_uri' => !empty($config['tiny_v3_redirect_uri_effective']) && preg_match('#^https?://#i', (string)$config['tiny_v3_redirect_uri_effective']),
      'scope_seguro' => trim((string)($config['tiny_v3_scopes'] ?? '')) === '' || !str_contains((string)$config['tiny_v3_scopes'], 'produtos estoque pedidos notas-fiscais'),
      'token' => !empty($tokenStatus['tem_token_tabela']) || !empty($tokenStatus['tem_token_manual']),
      'operacional' => !empty($config['tiny_v3_operacional']),
    ];
    // P0-01 (reauditoria 2026-08-23): state OAuth aleatório de uso único + PKCE S256,
    // gerado antes de qualquer saída HTML para poder setar o cookie de correlação.
    $config['tiny_v3_oauth_state'] = OAuthStateService::start('tiny_v3');
    $pageTitle='Tiny V3 - OAuth, Token e Diagnóstico';
    require __DIR__.'/../../views/tiny_v3_ficha.php';
  }

  private function tinyV2FichaTecnica(): void {
    PermissionService::require('ficha_tecnica','visualizar');
    $metricas = TinyV2ObservabilityService::metricas();
    $erros = TinyV2ObservabilityService::erros();
    $cfg = IntegrationConfig::get();
    $pageTitle = 'Ficha Técnica Tiny V2';
    require __DIR__.'/../../views/tiny_v2_ficha_tecnica.php';
  }

  private function tinyV3TokenSalvar(): void {
    PermissionService::require('configuracoes','editar');
    Csrf::validate();
    try {
      TinyV3TokenService::saveManual((string)($_POST['access_token'] ?? ''), (string)($_POST['refresh_token'] ?? ''), (int)($_POST['expires_in'] ?? 3600), (string)($_POST['scope'] ?? ''), (string)($_POST['ambiente_token'] ?? (IntegrationConfig::get()['tiny_v3_ambiente'] ?? 'homologacao')));
      NotificationService::criar('sistema','Tiny V3 token salvo','Token Tiny V3 foi salvo criptografado.','sucesso',['link'=>'index.php?page=tiny-v3-ficha']);
      redirect('index.php?page=tiny-v3-ficha&token=ok');
    } catch(Throwable $e){ Audit::exception($e,'tiny.v3.token.salvar.erro'); redirect('index.php?page=tiny-v3-ficha&token=erro'); }
  }

  private function tinyV3TokenRenovar(): void {
    PermissionService::require('configuracoes','editar');
    Csrf::validate();
    $ret=TinyV3TokenService::refresh(true);
    Audit::event('tiny.v3.token.renovar',empty($ret['erro'])?'sucesso':'erro',['mensagem'=>'Renovação de token Tiny V3 executada.','retorno'=>$ret]);
    redirect('index.php?page=tiny-v3-ficha&refresh='.(empty($ret['erro'])?'ok':'erro'));
  }

  private function tinyV3TokenRevogar(): void {
    PermissionService::require('configuracoes','editar');
    Csrf::validate();
    $ambiente = (string)($_POST['ambiente_token'] ?? (IntegrationConfig::get()['tiny_v3_ambiente'] ?? 'homologacao'));
    if (!in_array($ambiente, ['homologacao','producao'], true)) $ambiente = 'homologacao';
    try {
      $st = $this->db('tiny_v3_tokens')->prepare('DELETE FROM tiny_v3_tokens WHERE ambiente=?');
      $st->execute([$ambiente]);
      Audit::event('tiny.v3.token.revogar','sucesso',[
        'mensagem'=>'Tokens Tiny V3 revogados/removidos para o ambiente selecionado.',
        'contexto'=>['ambiente'=>$ambiente,'removidos'=>$st->rowCount()],
        'acao_recomendada'=>'Reconectar via OAuth antes de usar Tiny V3 neste ambiente.'
      ]);
      NotificationService::criar('sistema','Token Tiny V3 revogado','Tokens removidos para o ambiente '.$ambiente.'.','alerta',['link'=>'index.php?page=tiny-v3-ficha']);
      redirect('index.php?page=tiny-v3-ficha&revogar=ok');
    } catch(Throwable $e) {
      Audit::exception($e,'tiny.v3.token.revogar.erro',['codigo_erro'=>'TINY_V3_TOKEN_REVOKE_ERROR']);
      redirect('index.php?page=tiny-v3-ficha&revogar=erro');
    }
  }

  private function tinyV3Testar(): void {
    PermissionService::require('configuracoes','editar');
    Csrf::validate();
    $sku=trim((string)($_POST['sku'] ?? '')) ?: 'TESTE';
    $tiny=new TinyV3Service(IntegrationConfig::get());
    $ret=$tiny->consultarProduto($sku);
    Audit::event('tiny.v3.teste','info',['mensagem'=>'Teste Tiny V3 executado por SKU.','payload'=>['sku'=>$sku],'retorno'=>$ret]);
    redirect('index.php?page=tiny-v3-ficha&teste=ok');
  }

  private function tinyV3TestarModulo(): void {
    PermissionService::require('configuracoes','editar');
    Csrf::validate();
    $modulo = (string)($_POST['modulo'] ?? 'produto');
    $params = [
      'sku' => trim((string)($_POST['sku'] ?? '')),
      'id_pedido' => trim((string)($_POST['id_pedido'] ?? '')),
      'id_nota' => trim((string)($_POST['id_nota'] ?? '')),
    ];
    $tiny = new TinyV3Service(IntegrationConfig::get());
    $ret = $tiny->testarModulo($modulo, $params);
    Audit::event('tiny.v3.teste_modulo', empty($ret['erro']) ? 'info' : 'erro', [
      'mensagem' => 'Teste Tiny V3 por módulo executado.',
      'payload' => ['modulo'=>$modulo,'params'=>$params],
      'retorno' => $ret,
      'codigo_erro' => $ret['codigo_erro'] ?? null,
      'acao_recomendada' => empty($ret['erro']) ? 'Guardar evidência no checklist de homologação.' : 'Conferir token, endpoint, permissões e parâmetros reais da Tiny V3.'
    ]);
    redirect('index.php?page=tiny-v3-ficha&teste_modulo='.urlencode($modulo));
  }

  private function tinyV3EndpointsSalvar(): void {
    PermissionService::require('configuracoes','editar');
    Csrf::validate();
    $defaults = TinyV3EndpointCatalog::defaults();
    $sets=[]; $values=[]; $historico=[];
    $atual = IntegrationConfig::get();
    foreach($defaults as $key=>$default){
      $col = 'tiny_v3_'.$key;
      $novo = trim((string)($_POST[$col] ?? ''));
      if($novo === '') $novo = $default;
      $sets[] = "$col=?";
      $values[] = $novo;
      if((string)($atual[$col] ?? '') !== $novo) $historico[$col] = ['antes'=>$atual[$col] ?? '', 'depois'=>$novo];
    }
    if($sets){
      $sql = 'UPDATE configuracoes_integracao SET '.implode(',', $sets).' WHERE id=1';
      Database::tableConnectionForSql($sql)->prepare($sql)->execute($values);
    }
    Audit::event('tiny.v3.endpoints.salvar','sucesso',[
      'mensagem'=>'Endpoints Tiny V3 salvos/atualizados pelo painel.',
      'contexto'=>$historico
    ]);
    redirect('index.php?page=tiny-v3-ficha&endpoints=ok');
  }

  private function tinyAmbientes(): void {
    PermissionService::require('configuracoes','visualizar');
    $cfg = IntegrationConfig::get();
    try { $itens = $this->db('homologacao_checklist')->query("SELECT id, chave, titulo, descricao, status, resultado, trace_id, atualizado_em, criado_em FROM homologacao_checklist ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC); }
    catch (Throwable $e) { $itens = []; }
    $analise = TinyEnvironmentReadinessService::analisar($cfg, $itens);
    $pageTitle = 'Tiny V2/V3 - Ambientes';
    require __DIR__.'/../../views/tiny_ambientes.php';
  }
}
