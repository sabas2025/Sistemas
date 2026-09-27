<?php
/**
 * Fase 3 (decomposição do controller-deus, 2026-09-27) — etapa Configurações.
 *
 * Reúne a tela e o salvamento de Configurações, os fluxos ativos, as regras de sincronização e o
 * mapeamento de categorias que viviam no DashboardController (achado A3-01): configuracoes,
 * salvar-configuracoes, salvar-fluxos, regras-sincronizacao, salvar-regras-sincronizacao,
 * categorias-mapeamento, categoria-mapeamento-salvar. Despachado pelo
 * FastRouteDispatcherService::$dispatchGroups; nenhuma URL muda.
 *
 * Handlers movidos verbatim; PermissionService/Csrf, o mascaramento de segredos (CryptoService,
 * Secrets::keepIfMasked), a allowlist de URL do Tiny (TinyEndpointSecurityService) e as guardas de
 * ambiente VSM (VsmEnvironmentService) preservados. Vieram junto os 3 helpers PRIVADOS que só o
 * cluster usava: garantirEstruturaConfiguracoesVsm, tinyV3OperationalReady e columnExistsForUpdate.
 * PublicUrlService é estático e continua servindo o callback OAuth que permanece no Dashboard.
 * Precisa apenas do helper db() (usado por tinyV3OperationalReady).
 */
class ConfiguracaoController extends BaseModuleController {
  public static function routes(): array {
    return ['configuracoes','salvar-configuracoes','salvar-fluxos','regras-sincronizacao','salvar-regras-sincronizacao','categorias-mapeamento','categoria-mapeamento-salvar'];
  }

  public function dispatch(string $page): void {
    switch ($page) {
      case 'salvar-configuracoes': $this->salvarConfiguracoes(); break;
      case 'salvar-fluxos': $this->salvarFluxos(); break;
      case 'regras-sincronizacao': $this->regrasSincronizacao(); break;
      case 'salvar-regras-sincronizacao': $this->salvarRegrasSincronizacao(); break;
      case 'categorias-mapeamento': $this->categoriasMapeamento(); break;
      case 'categoria-mapeamento-salvar': $this->categoriaMapeamentoSalvar(); break;
      default: $this->configuracoes(); break; // configuracoes
    }
  }

  private function db(string $table): PDO { return Database::forTable($table); }

  private function configuracoes(): void {
    PermissionService::require('configuracoes','visualizar');
    $this->garantirEstruturaConfiguracoesVsm();
    $config = Database::forTable('configuracoes_integracao')->query("SELECT * FROM configuracoes_integracao WHERE id=1 LIMIT 1")->fetch();
    foreach(['tiny_v2_token','tiny_v3_token','tiny_v3_client_secret','vsm_token','webhook_secret','tiny_webhook_secret'] as $k){ if(isset($config[$k])) $config[$k]=CryptoService::decrypt($config[$k]); }
    // Defaults profissionais exibidos no painel, sem depender do install.php.
    $config['tiny_v2_url'] = $config['tiny_v2_url'] ?: 'https://api.tiny.com.br/api2';
    $config['tiny_v3_url'] = $config['tiny_v3_url'] ?: 'https://api.tiny.com.br/public-api/v3';
    $config['tiny_v3_auth_url'] = $config['tiny_v3_auth_url'] ?: 'https://accounts.tiny.com.br/realms/tiny/protocol/openid-connect/auth';
    $config['tiny_v3_token_url'] = $config['tiny_v3_token_url'] ?: 'https://accounts.tiny.com.br/realms/tiny/protocol/openid-connect/token';
    $config['tiny_v3_redirect_uri'] = $config['tiny_v3_redirect_uri'] ?: (PublicUrlService::appBaseUrl() . '/index.php?page=tiny-v3-callback');
    // Tiny/Olist V3 não aceita escopos livres como 'produtos estoque pedidos notas-fiscais'.
    // As permissões de Produtos/Estoque/Pedidos/NF-e são liberadas no aplicativo do Tiny.
    // Por padrão deixamos vazio e o parâmetro scope só é enviado se o usuário preencher um valor aceito pelo Tiny.
    $config['tiny_v3_scopes'] = trim((string)($config['tiny_v3_scopes'] ?? ''));
    $config['vsm_url'] = $config['vsm_url'] ?: 'https://conectavenda.homolog.vsm.com.br';
    $config['vsm_url_consulta'] = (string)($config['vsm_url_consulta'] ?? '');
    $config['vsm_api_principal'] = $config['vsm_api_principal'] ?: 'pedidos-integradora';
    $config['vsm_api_loja'] = $config['vsm_api_loja'] ?: 'desativado';
    $config['vsm_swagger_integradora'] = $config['vsm_swagger_integradora'] ?: 'https://conectavenda.homolog.vsm.com.br/swagger-ui/index.html?urls.primaryName=pedidos-integradora';
    $config['vsm_swagger_loja'] = $config['vsm_swagger_loja'] ?: 'https://conectavenda.homolog.vsm.com.br/swagger-ui/index.html?urls.primaryName=pedidos-loja';
    $config['vsm_waf_agressivo'] = 0;
    $securityCfg = class_exists('App') ? (App::config()['security'] ?? []) : [];
    $config['queue_processing_timeout_minutes'] = max(10, min(240, (int)($config['queue_processing_timeout_minutes'] ?? ($securityCfg['queue_processing_timeout_minutes'] ?? 30))));
    $config['queue_lease_minutes'] = max(1, min(120, (int)($config['queue_lease_minutes'] ?? ($securityCfg['queue_lease_minutes'] ?? 5))));
    $queueLeaseMap = $securityCfg['queue_lease_minutes_by_type'] ?? [];
    $queueLeaseDb = json_decode((string)($config['queue_lease_by_type_json'] ?? ''), true);
    if (is_array($queueLeaseDb)) $queueLeaseMap = array_merge(is_array($queueLeaseMap)?$queueLeaseMap:[], $queueLeaseDb);
    $config['queue_lease_map'] = $queueLeaseMap;
    $config['vsm_status_operacional'] = class_exists('VsmEnvironmentService') ? VsmEnvironmentService::dashboardStatus($config) : ['modo'=>'Homologação','detalhe'=>'URL configurada','acao'=>'Validar VSM'];
    $pageTitle = 'Configurações';
    require __DIR__.'/../../views/configuracoes.php';
  }

  private function salvarConfiguracoes(): void {
    PermissionService::require('configuracoes','editar');
    if($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php?page=configuracoes');
    Csrf::validate();
    $this->garantirEstruturaConfiguracoesVsm();
    $atual = Database::forTable('configuracoes_integracao')->query("SELECT * FROM configuracoes_integracao WHERE id=1 LIMIT 1")->fetch() ?: [];
    foreach(['tiny_v2_token','tiny_v3_token','tiny_v3_client_secret','vsm_token','webhook_secret','tiny_webhook_secret'] as $k){ if(isset($atual[$k])) $atual[$k]=CryptoService::decrypt($atual[$k]); }
    $dados = [
      'ambiente' => $_POST['ambiente'] ?? 'homologacao',
      'tiny_versao' => $_POST['tiny_versao'] ?? 'v2',
      'tiny_v2_url' => trim($_POST['tiny_v2_url'] ?? '') ?: 'https://api.tiny.com.br/api2',
      'tiny_v2_token' => Secrets::keepIfMasked((string)($_POST['tiny_v2_token'] ?? ''), $atual['tiny_v2_token'] ?? ''),
      'tiny_v3_url' => trim($_POST['tiny_v3_url'] ?? '') ?: 'https://api.tiny.com.br/public-api/v3',
      'tiny_v3_ambiente' => in_array(($_POST['tiny_v3_ambiente'] ?? 'homologacao'), ['homologacao','producao'], true) ? $_POST['tiny_v3_ambiente'] : 'homologacao',
      'tiny_v3_token' => Secrets::keepIfMasked((string)($_POST['tiny_v3_token'] ?? ''), $atual['tiny_v3_token'] ?? ''),
      'tiny_v3_auth_url' => trim((string)($_POST['tiny_v3_auth_url'] ?? ($atual['tiny_v3_auth_url'] ?? ''))) ?: 'https://accounts.tiny.com.br/realms/tiny/protocol/openid-connect/auth',
      'tiny_v3_token_url' => trim((string)($_POST['tiny_v3_token_url'] ?? ($atual['tiny_v3_token_url'] ?? ''))) ?: 'https://accounts.tiny.com.br/realms/tiny/protocol/openid-connect/token',
      'tiny_v3_client_id' => trim((string)($_POST['tiny_v3_client_id'] ?? ($atual['tiny_v3_client_id'] ?? ''))),
      'tiny_v3_client_secret' => Secrets::keepIfMasked((string)($_POST['tiny_v3_client_secret'] ?? ''), $atual['tiny_v3_client_secret'] ?? ''),
      'tiny_v3_redirect_uri' => PublicUrlService::tinyV3RedirectUri(trim((string)($_POST['tiny_v3_redirect_uri'] ?? ($atual['tiny_v3_redirect_uri'] ?? '')))),
      'tiny_v3_scopes' => trim((string)($_POST['tiny_v3_scopes'] ?? ($atual['tiny_v3_scopes'] ?? ''))),
      'vsm_url' => trim($_POST['vsm_url'] ?? '') ?: 'https://conectavenda.homolog.vsm.com.br',
      'vsm_url_consulta' => trim((string)($_POST['vsm_url_consulta'] ?? ($atual['vsm_url_consulta'] ?? ''))),
      'vsm_token' => Secrets::keepIfMasked((string)($_POST['vsm_token'] ?? ''), $atual['vsm_token'] ?? ''),
      'vsm_api_principal' => in_array(($_POST['vsm_api_principal'] ?? 'pedidos-integradora'), ['pedidos-integradora','pedidos-loja'], true) ? $_POST['vsm_api_principal'] : 'pedidos-integradora',
      'vsm_api_loja' => in_array(($_POST['vsm_api_loja'] ?? 'desativado'), ['desativado','pedidos-loja'], true) ? $_POST['vsm_api_loja'] : 'desativado',
      'vsm_swagger_integradora' => trim((string)($_POST['vsm_swagger_integradora'] ?? ($atual['vsm_swagger_integradora'] ?? ''))) ?: 'https://conectavenda.homolog.vsm.com.br/swagger-ui/index.html?urls.primaryName=pedidos-integradora',
      'vsm_swagger_loja' => trim((string)($_POST['vsm_swagger_loja'] ?? ($atual['vsm_swagger_loja'] ?? ''))) ?: 'https://conectavenda.homolog.vsm.com.br/swagger-ui/index.html?urls.primaryName=pedidos-loja',
      'vsm_waf_agressivo' => 0,
      'vsm_api_observacao' => trim((string)($_POST['vsm_api_observacao'] ?? ($atual['vsm_api_observacao'] ?? ''))),
      'vsm_ambiente' => 'homologacao',
      'vsm_producao_liberada' => 0,
      'vsm_host_producao_liberado' => null,
      'vsm_endpoint_baixa_estoque' => trim($_POST['vsm_endpoint_baixa_estoque'] ?? ($atual['vsm_endpoint_baixa_estoque'] ?? '/api/estoque/baixa')),
      'vsm_endpoint_produto_novo' => trim($_POST['vsm_endpoint_produto_novo'] ?? ($atual['vsm_endpoint_produto_novo'] ?? '/api/produtos')),
      'vsm_endpoint_consulta_estoque' => trim($_POST['vsm_endpoint_consulta_estoque'] ?? ($atual['vsm_endpoint_consulta_estoque'] ?? '/api/estoque/consulta')),
      'fluxo_tiny_vsm_estoque' => isset($_POST['fluxo_tiny_vsm_estoque']) ? 1 : 0,
      'fluxo_vsm_tiny_produto' => isset($_POST['fluxo_vsm_tiny_produto']) ? 1 : 0,
      'fluxo_vsm_tiny_pedido' => isset($_POST['fluxo_vsm_tiny_pedido']) ? 1 : 0,
      'webhook_secret' => Secrets::keepIfMasked((string)($_POST['webhook_secret'] ?? ''), $atual['webhook_secret'] ?? ''),
      'tiny_webhook_secret' => Secrets::keepIfMasked((string)($_POST['tiny_webhook_secret'] ?? ''), $atual['tiny_webhook_secret'] ?? ''),
      'tiny_webhook_cnpj_autorizados' => trim((string)($_POST['tiny_webhook_cnpj_autorizados'] ?? ($atual['tiny_webhook_cnpj_autorizados'] ?? ''))),
      'tiny_webhook_exigir_secret' => isset($_POST['tiny_webhook_exigir_secret']) ? 1 : 0,
      'tiny_webhook_rate_limit' => max(1, (int)($_POST['tiny_webhook_rate_limit'] ?? ($atual['tiny_webhook_rate_limit'] ?? 60))),
      'tiny_webhook_max_bytes' => max(1024, (int)($_POST['tiny_webhook_max_bytes'] ?? ($atual['tiny_webhook_max_bytes'] ?? 1048576))),
      'bloquear_inativo_com_estoque' => isset($_POST['bloquear_inativo_com_estoque']) ? 1 : 0,
      'tiny_v3_operacional' => isset($_POST['tiny_v3_operacional']) ? 1 : 0,
      'queue_processing_timeout_minutes' => max(10, min(240, (int)($_POST['queue_processing_timeout_minutes'] ?? ($atual['queue_processing_timeout_minutes'] ?? 30)))),
      'queue_lease_minutes' => max(1, min(120, (int)($_POST['queue_lease_minutes'] ?? ($atual['queue_lease_minutes'] ?? 5)))),
    ];
    try {
      $dados['tiny_v2_url'] = TinyEndpointSecurityService::validateUrl((string)$dados['tiny_v2_url']);
      $dados['tiny_v3_url'] = TinyEndpointSecurityService::validateUrl((string)$dados['tiny_v3_url']);
      $dados['tiny_v3_auth_url'] = TinyEndpointSecurityService::validateUrl((string)$dados['tiny_v3_auth_url']);
      $dados['tiny_v3_token_url'] = TinyEndpointSecurityService::validateUrl((string)$dados['tiny_v3_token_url']);
    } catch (Throwable $e) {
      Audit::exception($e,'configuracoes.tiny.url_bloqueada',['codigo_erro'=>'TINY_URL_BLOCKED']);
      NotificationService::criar('sistema','URL Tiny bloqueada','A configuração não foi salva porque uma URL Tiny/OAuth não pertence à allowlist segura.','erro',['link'=>'index.php?page=configuracoes']);
      redirect('index.php?page=configuracoes&tiny_url=erro');
    }

    $queueTypes = ['pedido_tiny_para_vsm','baixa_estoque_vsm','produto_vsm_para_tiny','produto_vsm_atualizar_tiny','produto_vsm_estoque_para_tiny','produto_vsm_status_para_tiny'];
    $queueLeaseMap = [];
    foreach ($queueTypes as $queueType) {
      $queueLeaseMap[$queueType] = max(1, min(120, (int)($_POST['queue_lease_'.$queueType] ?? $dados['queue_lease_minutes'])));
    }
    $dados['queue_lease_by_type_json'] = json_encode($queueLeaseMap, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $vsmAmbienteSeguro = class_exists('VsmEnvironmentService') ? VsmEnvironmentService::sanitizePost($_POST, $atual) : ['vsm_ambiente'=>'homologacao','vsm_producao_liberada'=>0,'vsm_host_producao_liberado'=>null];
    foreach($vsmAmbienteSeguro as $k=>$v){ $dados[$k] = $v; }
    if (!empty($dados['vsm_producao_liberada']) && empty($dados['vsm_token'])) {
      $dados['vsm_producao_liberada'] = 0;
      NotificationService::criar('sistema','VSM produção não liberada','A liberação de produção VSM foi bloqueada porque o token VSM não está configurado.','erro',['link'=>'index.php?page=configuracoes']);
    }
    // P0-04 (reauditoria 2026-08-23): mudar a URL ou o token VSM invalidava silenciosamente
    // o teste de conexão anterior sem revogar vsm_ultimo_teste_ok. Isso permitia que um
    // teste bem-sucedido no host/token A continuasse "provando" que o host/token B (novo)
    // está pronto para produção. Agora qualquer alteração de alvo exige novo teste.
    // Preserva o valor atual por padrão; só é sobrescrito abaixo se o alvo mudou.
    // (sem isso, toda vez que $dados não trouxesse essas chaves, o UPDATE dinâmico
    // mais abaixo gravaria NULL e apagaria um teste válido sem motivo.)
    $dados['vsm_ultimo_teste_ok'] = $atual['vsm_ultimo_teste_ok'] ?? 0;
    $dados['vsm_ultimo_teste_em'] = $atual['vsm_ultimo_teste_em'] ?? null;
    $vsmAlvoAlterado = trim((string)$dados['vsm_url']) !== trim((string)($atual['vsm_url'] ?? ''))
      || trim((string)$dados['vsm_token']) !== trim((string)($atual['vsm_token'] ?? ''));
    if ($vsmAlvoAlterado) {
      $dados['vsm_ultimo_teste_ok'] = 0;
      $dados['vsm_ultimo_teste_em'] = null;
      if (!empty($dados['vsm_producao_liberada'])) {
        $dados['vsm_producao_liberada'] = 0;
        NotificationService::criar('sistema','VSM produção não liberada','A URL ou o token VSM mudaram: a liberação de produção foi revogada e exige novo teste de conexão bem-sucedido para o novo alvo.','alerta',['link'=>'index.php?page=configuracoes']);
      }
      Audit::event('vsm.ambiente.alvo_alterado','alerta',['mensagem'=>'URL ou token VSM alterados: teste de conexão anterior invalidado.','codigo_erro'=>'VSM_TARGET_CHANGED_TEST_INVALIDATED']);
    }

    foreach(TinyV3EndpointCatalog::defaults() as $key=>$defaultEndpoint){
      $campo = 'tiny_v3_'.$key;
      $dados[$campo] = trim((string)($_POST[$campo] ?? ($atual[$campo] ?? '')));
    }
    if (!empty($dados['tiny_v3_operacional'])) {
      $ready = $this->tinyV3OperationalReady($dados);
      if (!$ready['ok']) {
        $dados['tiny_v3_operacional'] = 0;
        NotificationService::criar('sistema','Tiny V3 não ativado','A ativação operacional da Tiny V3 foi bloqueada porque o checklist obrigatório ainda possui pendências. Abra a Ficha Tiny V3 e a Homologação para concluir os itens.','erro',['issues'=>$ready['issues'],'link'=>'index.php?page=tiny-v3-ficha']);
        $_SESSION['tiny_v3_blocked_issues'] = $ready['issues'];
        Audit::event('tiny.v3.operacional.bloqueado','erro',[
          'mensagem'=>'Tentativa de ativar Tiny V3 operacional bloqueada pelo checklist obrigatório V29.',
          'codigo_erro'=>'TINY_V3_OPERATIONAL_CHECKLIST_FAILED',
          'contexto'=>$ready,
          'acao_recomendada'=>'Concluir OAuth, testes por módulo, endpoint VSM e checklist de homologação antes de marcar como operacional.'
        ]);
      }
    }
    $dadosSalvar = $dados;
    foreach(['tiny_v2_token','tiny_v3_token','tiny_v3_client_secret','vsm_token','webhook_secret','tiny_webhook_secret'] as $campoSecreto){
      $dadosSalvar[$campoSecreto] = CryptoService::encrypt((string)$dadosSalvar[$campoSecreto]);
    }
    $st = Database::forTable('configuracoes_integracao')->prepare("UPDATE configuracoes_integracao SET ambiente=?, tiny_versao=?, tiny_v2_url=?, tiny_v2_token=?, tiny_v3_url=?, tiny_v3_ambiente=?, tiny_v3_token=?, tiny_v3_auth_url=?, tiny_v3_token_url=?, tiny_v3_client_id=?, tiny_v3_client_secret=?, tiny_v3_redirect_uri=?, tiny_v3_scopes=?, vsm_url=?, vsm_token=?, vsm_endpoint_baixa_estoque=?, vsm_endpoint_produto_novo=?, vsm_endpoint_consulta_estoque=?, fluxo_tiny_vsm_estoque=?, fluxo_vsm_tiny_produto=?, fluxo_vsm_tiny_pedido=?, webhook_secret=?, tiny_webhook_secret=?, tiny_webhook_cnpj_autorizados=?, tiny_webhook_exigir_secret=?, tiny_webhook_rate_limit=?, tiny_webhook_max_bytes=?, bloquear_inativo_com_estoque=?, tiny_v3_operacional=? WHERE id=1");
    $st->execute([
      $dadosSalvar['ambiente'],$dadosSalvar['tiny_versao'],$dadosSalvar['tiny_v2_url'],$dadosSalvar['tiny_v2_token'],$dadosSalvar['tiny_v3_url'],$dadosSalvar['tiny_v3_ambiente'],$dadosSalvar['tiny_v3_token'],
      $dadosSalvar['tiny_v3_auth_url'],$dadosSalvar['tiny_v3_token_url'],$dadosSalvar['tiny_v3_client_id'],$dadosSalvar['tiny_v3_client_secret'],$dadosSalvar['tiny_v3_redirect_uri'],$dadosSalvar['tiny_v3_scopes'],
      $dadosSalvar['vsm_url'],$dadosSalvar['vsm_token'],$dadosSalvar['vsm_endpoint_baixa_estoque'],$dadosSalvar['vsm_endpoint_produto_novo'],$dadosSalvar['vsm_endpoint_consulta_estoque'],
      (int)$dadosSalvar['fluxo_tiny_vsm_estoque'],(int)$dadosSalvar['fluxo_vsm_tiny_produto'],(int)$dadosSalvar['fluxo_vsm_tiny_pedido'],$dadosSalvar['webhook_secret'],
      $dadosSalvar['tiny_webhook_secret'],$dadosSalvar['tiny_webhook_cnpj_autorizados'],(int)$dadosSalvar['tiny_webhook_exigir_secret'],(int)$dadosSalvar['tiny_webhook_rate_limit'],(int)$dadosSalvar['tiny_webhook_max_bytes'],(int)$dadosSalvar['bloquear_inativo_com_estoque'],(int)$dadosSalvar['tiny_v3_operacional']
    ]);
    // V104.4: campos VSM/API profile são salvos de forma compatível com bancos já instalados.
    try {
      $vsmSets=[]; $vsmValues=[];
      // P0-04: vsm_ultimo_teste_ok/vsm_ultimo_teste_em entram aqui para que a invalidação
      // calculada acima ($vsmAlvoAlterado) seja realmente persistida quando URL/token mudam.
      foreach(['vsm_url_consulta','vsm_api_principal','vsm_api_loja','vsm_swagger_integradora','vsm_swagger_loja','vsm_waf_agressivo','vsm_api_observacao','vsm_ambiente','vsm_producao_liberada','vsm_host_producao_liberado','vsm_ultimo_teste_ok','vsm_ultimo_teste_em'] as $campo){
        if($this->columnExistsForUpdate('configuracoes_integracao',$campo)){
          $vsmSets[] = $campo.'=?';
          $vsmValues[] = $dadosSalvar[$campo] ?? $dados[$campo] ?? null;
        }
      }
      if($vsmSets){
        Database::forTable('configuracoes_integracao')->prepare('UPDATE configuracoes_integracao SET '.implode(',', $vsmSets).' WHERE id=1')->execute($vsmValues);
      }
    } catch(Throwable $e){ Audit::exception($e,'configuracoes.vsm_api_profile.erro',['codigo_erro'=>'VSM_API_PROFILE_SAVE_ERROR']); }
    // F6-07: credenciais VSM (clientToken/secret/loja) — assembladas, cifradas e gravadas por
    // serviço (lógica de domínio fora do controller-deus, lição do teto do DashboardController).
    VsmCredentialsConfigService::salvarDoFormulario($_POST, $atual);
    if (class_exists('VsmEndpointService')) VsmEndpointService::invalidateBaseConfigCache();
    try {
      $queueSets=[]; $queueValues=[];
      foreach(['queue_processing_timeout_minutes','queue_lease_minutes','queue_lease_by_type_json'] as $campo){
        if($this->columnExistsForUpdate('configuracoes_integracao',$campo)){
          $queueSets[]=$campo.'=?'; $queueValues[]=$dados[$campo];
        }
      }
      if($queueSets) Database::forTable('configuracoes_integracao')->prepare('UPDATE configuracoes_integracao SET '.implode(',', $queueSets).' WHERE id=1')->execute($queueValues);
      else Audit::event('configuracoes.fila.schema_pendente','alerta',['mensagem'=>'Configuração de lease da fila não foi persistida porque a migration V104.49.3 ainda não foi aplicada.','acao_recomendada'=>'Aplicar database/migrations/20260712_001_queue_oauth_concurrency.sql.']);
    } catch(Throwable $e){ Audit::exception($e,'configuracoes.fila_runtime.erro',['codigo_erro'=>'QUEUE_RUNTIME_CONFIG_SAVE_ERROR']); }
    try {
      if (!empty($dados['vsm_producao_liberada']) && $this->columnExistsForUpdate('configuracoes_integracao','vsm_producao_liberada_em')) {
        Database::forTable('configuracoes_integracao')->prepare('UPDATE configuracoes_integracao SET vsm_producao_liberada_em=COALESCE(vsm_producao_liberada_em,NOW()), vsm_producao_liberada_por=COALESCE(vsm_producao_liberada_por,?) WHERE id=1')->execute([Auth::user()['id'] ?? null]);
      }
    } catch(Throwable $e){ Audit::exception($e,'configuracoes.vsm_producao_liberacao.erro',['codigo_erro'=>'VSM_PRODUCTION_RELEASE_SAVE_ERROR']); }
    // Endpoints Tiny V3 editáveis são salvos separadamente para manter compatibilidade com bancos antigos.
    try {
      $endpointSets=[]; $endpointValues=[];
      foreach(TinyV3EndpointCatalog::defaults() as $key=>$defaultEndpoint){
        $campo='tiny_v3_'.$key;
        if($this->columnExistsForUpdate('configuracoes_integracao',$campo)){
          $endpointSets[]="$campo=?";
          $endpointValues[]=$dados[$campo] ?: $defaultEndpoint;
        }
      }
      if($endpointSets){
        Database::forTable('configuracoes_integracao')->prepare('UPDATE configuracoes_integracao SET '.implode(',', $endpointSets).' WHERE id=1')->execute($endpointValues);
      }
    } catch(Throwable $e){ Audit::exception($e,'configuracoes.tiny_v3_endpoints.erro',['codigo_erro'=>'TINY_V3_ENDPOINT_CONFIG_SAVE_ERROR']); }

    foreach($dados as $campo=>$novo){
      $antigo = (string)($atual[$campo] ?? '');
      if($antigo !== (string)$novo){
        $mask = str_contains($campo,'token') || str_contains($campo,'secret');
        $this->db('configuracoes_historico')->prepare('INSERT INTO configuracoes_historico(usuario_id,campo,valor_antigo,valor_novo,trace_id) VALUES(?,?,?,?,?)')->execute([Auth::user()['id']??null,$campo,$mask?Secrets::mask($antigo):$antigo,$mask?Secrets::mask((string)$novo):(string)$novo,RequestContext::id()]);
      }
    }
    Audit::event('configuracoes.atualizar','sucesso',['mensagem'=>'Configurações de integração atualizadas','entidade'=>'configuracoes_integracao','entidade_id'=>'1']);
    redirect('index.php?page=configuracoes&salvo=1'.(!empty($_SESSION['tiny_v3_blocked_issues'])?'&tinyv3_bloqueado=1':''));
  }

  private function salvarFluxos(): void {
    PermissionService::require('fluxos','editar');
    Csrf::validate();
    Database::forTable('configuracoes_integracao')->prepare('UPDATE configuracoes_integracao SET fluxo_tiny_vsm_estoque=?, fluxo_vsm_tiny_produto=?, fluxo_vsm_tiny_pedido=? WHERE id=1')
      ->execute([isset($_POST['fluxo_tiny_vsm_estoque'])?1:0, isset($_POST['fluxo_vsm_tiny_produto'])?1:0, isset($_POST['fluxo_vsm_tiny_pedido'])?1:0]);
    Audit::event('fluxos.atualizar','sucesso',['mensagem'=>'Fluxos ativos atualizados no painel.','contexto'=>$_POST]);
    redirect('index.php?page=configuracoes&fluxos=salvo');
  }

  private function regrasSincronizacao(): void {
    PermissionService::require('configuracoes','visualizar');
    $config = IntegrationConfig::get();
    $rules = SyncRulesService::all();
    $pageTitle = 'Regras de Sincronização Tiny ⇄ VSM';
    require __DIR__.'/../../views/regras_sincronizacao.php';
  }

  private function salvarRegrasSincronizacao(): void {
    PermissionService::require('configuracoes','editar');
    Csrf::validate();
    $keys = [
      'sync_criar_produto_tiny',
      'sync_atualizar_produto_tiny',
      'sync_atualizar_estoque_tiny',
      'sync_atualizar_status_tiny',
      'sync_atualizar_preco_tiny',
      'sync_atualizar_descricao_tiny',
      'sync_atualizar_categoria_tiny',
      'sync_atualizar_marca_tiny',
      'sync_criar_produto_se_nao_existir',
      'sync_bloquear_estoque_negativo',
    ];
    $dados = [];
    foreach($keys as $k){ $dados[$k] = isset($_POST[$k]) ? 1 : 0; }
    try {
      SchemaRuntimePolicyService::requireColumns('configuracoes_integracao', $keys, 'regras de sincronização Tiny ⇄ VSM');
      Database::forTable('configuracoes_integracao')->exec("INSERT IGNORE INTO configuracoes_integracao(id) VALUES(1)");
      $sql = "UPDATE configuracoes_integracao SET ".implode(',', array_map(fn($k)=>$k.'=?', $keys))." WHERE id=1";
      $st = Database::tableConnectionForSql($sql)->prepare($sql);
      $st->execute(array_map(fn($k)=>(int)$dados[$k], $keys));
      Audit::event('sync.regras.salvas','sucesso',[ 'mensagem'=>'Regras de sincronização Tiny ⇄ VSM atualizadas.', 'contexto'=>$dados ]);
      NotificationService::criar('sistema','Regras de sincronização salvas','As regras de criação de produto, atualização de estoque e status foram atualizadas.','sucesso',['link'=>'index.php?page=regras-sincronizacao']);
      redirect('index.php?page=regras-sincronizacao&salvo=1');
    } catch(Throwable $e) {
      Audit::exception($e, 'sync.regras.salvar.erro');
      redirect('index.php?page=regras-sincronizacao&erro=1');
    }
  }

  private function categoriasMapeamento(): void {
    PermissionService::require('configuracoes','visualizar');
    $busca=trim((string)($_GET['busca'] ?? ''));
    $categorias=[];
    try{
      $pdo=Database::forTable('categorias_mapeamento');
      $sql='SELECT * FROM categorias_mapeamento WHERE 1=1'; $params=[];
      if($busca !== ''){ $sql.=' AND (id_categoria_vsm LIKE ? OR nome_categoria_vsm LIKE ? OR id_categoria_tiny LIKE ? OR nome_categoria_tiny LIKE ?)'; for($i=0;$i<4;$i++) $params[]='%'.$busca.'%'; }
      // Melhoria 1 da seção 8: o escopo de empresa entra DEPOIS do WHERE montado pelos filtros e
      // ANTES do ORDER BY/LIMIT — applyToSelect() cuida da posição do parâmetro.
      [$sql, $params] = TenantScopeService::applyToSelect('categorias_mapeamento', $sql, $params);
      $sql.=' ORDER BY ativo DESC, prioridade DESC, nome_categoria_vsm ASC LIMIT 500';
      $st=$pdo->prepare($sql); $st->execute($params); $categorias=$st->fetchAll();
    }catch(Throwable $e){ $erro=$e->getMessage(); }
    $pageTitle='Mapeamento de Categorias VSM ↔ Tiny';
    require __DIR__.'/../../views/categorias_mapeamento.php';
  }

  private function categoriaMapeamentoSalvar(): void {
    PermissionService::require('configuracoes','editar');
    Csrf::validate();
    $id=(int)($_POST['id'] ?? 0);
    $dados=[
      trim((string)($_POST['id_categoria_vsm'] ?? '')) ?: null,
      trim((string)($_POST['nome_categoria_vsm'] ?? '')),
      trim((string)($_POST['id_categoria_tiny'] ?? '')),
      trim((string)($_POST['nome_categoria_tiny'] ?? '')),
      isset($_POST['ativo']) ? 1 : 0,
      (int)($_POST['prioridade'] ?? 0),
      trim((string)($_POST['observacao'] ?? '')) ?: null,
      Auth::user()['id'] ?? null
    ];
    if($dados[1] === '' || $dados[2] === '' || $dados[3] === '') redirect('index.php?page=categorias-mapeamento&erro=campos');
    $pdo=Database::forTable('categorias_mapeamento');
    if($id>0){
      TenantScopeService::run('categorias_mapeamento', 'UPDATE categorias_mapeamento SET id_categoria_vsm=?, nome_categoria_vsm=?, id_categoria_tiny=?, nome_categoria_tiny=?, ativo=?, prioridade=?, observacao=?, atualizado_por=?, atualizado_em=NOW() WHERE id=?', [...$dados,$id]);
      Audit::event('categoria_mapeamento.atualizada','sucesso',['entidade'=>'categorias_mapeamento','entidade_id'=>$id,'mensagem'=>'Mapeamento de categoria atualizado.']);
    } else {
      TenantScopeService::run('categorias_mapeamento', 'INSERT INTO categorias_mapeamento(id_categoria_vsm,nome_categoria_vsm,id_categoria_tiny,nome_categoria_tiny,ativo,prioridade,observacao,criado_por) VALUES(?,?,?,?,?,?,?,?)', $dados);
      Audit::event('categoria_mapeamento.criada','sucesso',['entidade'=>'categorias_mapeamento','entidade_id'=>$pdo->lastInsertId(),'mensagem'=>'Mapeamento de categoria criado.']);
    }
    redirect('index.php?page=categorias-mapeamento&salvo=1');
  }

  private function garantirEstruturaConfiguracoesVsm(): void {
    $required=['vsm_url_consulta','vsm_api_principal','vsm_api_loja','vsm_swagger_integradora','vsm_swagger_loja','vsm_waf_agressivo','vsm_api_observacao','vsm_ambiente','vsm_producao_liberada','vsm_ultimo_teste_ok','vsm_ultimo_teste_em','vsm_host_producao_liberado','vsm_producao_liberada_em','vsm_producao_liberada_por'];
    $missing=[]; foreach($required as $column) if(!Database::columnExists('configuracoes_integracao',$column)) $missing[]=$column;
    if($missing){
      try { Audit::event('configuracoes.vsm_api_profile_schema.pendente','alerta',['mensagem'=>'Estrutura VSM incompleta; nenhuma alteração DDL foi executada durante a requisição.','codigo_erro'=>'VSM_API_PROFILE_MIGRATION_REQUIRED','contexto'=>['colunas_ausentes'=>$missing],'acao_recomendada'=>'Executar Central Técnica > Migrações Seguras (V104.49.3).']); }
      catch(Throwable $e){ if(class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__,$e); }
    }
  }

  private function tinyV3OperationalReady(array $dados = []): array {
    $issues = [];
    $ready = TinyV3TokenService::homologationReady();
    if (!$ready['ok']) $issues = array_merge($issues, $ready['issues']);
    $ambiente = $dados['ambiente'] ?? (IntegrationConfig::get()['ambiente'] ?? 'homologacao');
    $vsmEndpoint = trim((string)($dados['vsm_endpoint_consulta_estoque'] ?? ''));
    if ($vsmEndpoint === '') $issues[] = 'Endpoint VSM de consulta de estoque não configurado para reconciliação/homologação.';
    try {
      SchemaRuntimePolicyService::requireTable('homologacao_checklist', 'prontidão operacional Tiny V3');
      $obrigatorios = ['tiny_v3_token','tiny_v3_produto_sku','tiny_v3_estoque','vsm_conexao','vsm_estoque_consulta'];
      if ($ambiente === 'producao') {
        $obrigatorios[] = 'tiny_v3_refresh';
        $obrigatorios[] = 'tiny_v3_logs';
      }
      foreach ($obrigatorios as $chave) {
        $st = $this->db('homologacao_checklist')->prepare("SELECT status FROM homologacao_checklist WHERE chave=? LIMIT 1");
        $st->execute([$chave]);
        $row = $st->fetch();
        if (!$row || !in_array((string)$row['status'], ['ok','nao_aplicavel'], true)) {
          $issues[] = 'Checklist obrigatório pendente: '.$chave;
        }
      }
    } catch (Throwable $e) {
      $issues[] = 'Não foi possível validar checklist de homologação: '.$e->getMessage();
    }
    return ['ok'=>empty($issues), 'issues'=>array_values(array_unique($issues)), 'token_ready'=>$ready];
  }

  private function columnExistsForUpdate(string $table, string $column): bool {
    try { return Database::columnExists($table, $column); }
    catch (Throwable $e) { return false; }
  }
}
