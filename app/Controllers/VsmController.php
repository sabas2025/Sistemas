<?php
/**
 * V49 - Controller real da integração VSM.
 * Remove rotas VSM configuráveis do DashboardController e centraliza telas, testes,
 * validação de endpoints, logs e health check.
 */
class VsmController {
  public function dispatch(string $page): void {
    Auth::requireLogin();
    switch ($page) {
      case 'vsm-endpoints': $this->endpoints(); break;
      case 'vsm-endpoint-salvar': $this->endpointSalvar(); break;
      case 'vsm-endpoint-testar': $this->endpointTestar(); break;
      case 'vsm-campos': $this->campos(); break;
      case 'vsm-campo-salvar': $this->campoSalvar(); break;
      case 'vsm-saude': $this->saude(); break;
      case 'vsm-logs': $this->logs(); break;
      case 'vsm-testes': $this->testes(); break;
      case 'testar-vsm': $this->testarVsm(); break;
      case 'vsm-ficha-tecnica': $this->vsmFichaTecnica(); break;
      default: redirect('index.php?page=vsm-endpoints');
    }
  }

  private function endpoints(): void {
    PermissionService::require('configuracoes','visualizar');
    $categoria = trim((string)($_GET['categoria'] ?? '')) ?: null;
    $erro=null; $resultado=$_SESSION['vsm_endpoint_teste_resultado'] ?? null; unset($_SESSION['vsm_endpoint_teste_resultado']);
    try { $endpoints=VsmEndpointService::listEndpoints($categoria); }
    catch(Throwable $e){ $erro=$e->getMessage(); $endpoints=[]; }
    $pageTitle='Mapeamento de Endpoints VSM';
    require __DIR__.'/../../views/vsm_endpoints.php';
  }

  private function endpointSalvar(): void {
    PermissionService::require('configuracoes','editar');
    $this->requirePost();
    Csrf::validate();
    try {
      $id=VsmEndpointService::saveEndpoint($_POST);
      Audit::event('vsm.endpoint.salvo','sucesso',['entidade'=>'vsm_endpoints','entidade_id'=>$id,'mensagem'=>'Endpoint VSM salvo pelo painel V49.','contexto'=>['contract_verified'=>!empty($_POST['confirmar_contrato']),'contract_source_sha256'=>trim((string)($_POST['contract_source'] ?? ''))!==''?hash('sha256',trim((string)$_POST['contract_source'])):null]]);
      redirect('index.php?page=vsm-endpoints&salvo=1');
    } catch(Throwable $e){ Audit::exception($e,'vsm.endpoint.salvar.erro'); $_SESSION['form_error']=$e->getMessage(); redirect('index.php?page=vsm-endpoints&erro=1'); }
  }

  private function endpointTestar(): void {
    PermissionService::require('configuracoes','editar');
    $this->requirePost();
    Csrf::validate();
    $id=(int)($_POST['id'] ?? 0);
    $params=[];
    foreach($_POST as $k=>$v){ if(str_starts_with($k,'param_')) $params[substr($k,6)]=$v; }
    try { $_SESSION['vsm_endpoint_teste_resultado']=VsmEndpointService::testEndpoint($id,$params,true); redirect('index.php?page=vsm-endpoints&testado=1'); }
    catch(Throwable $e){ Audit::exception($e,'vsm.endpoint.testar.erro'); $_SESSION['form_error']=$e->getMessage(); redirect('index.php?page=vsm-endpoints&erro=1'); }
  }

  private function campos(): void {
    PermissionService::require('configuracoes','visualizar');
    $categoria=trim((string)($_GET['categoria'] ?? '')) ?: null;
    $erro=null;
    try { $campos=VsmEndpointService::listCampos($categoria); }
    catch(Throwable $e){ $erro=$e->getMessage(); $campos=[]; }
    $pageTitle='Mapeamento de Campos VSM';
    require __DIR__.'/../../views/vsm_campos.php';
  }

  private function campoSalvar(): void {
    PermissionService::require('configuracoes','editar');
    $this->requirePost();
    Csrf::validate();
    try { $id=VsmEndpointService::saveCampo($_POST); Audit::event('vsm.campo.salvo','sucesso',['entidade'=>'vsm_campos_mapeamento','entidade_id'=>$id,'mensagem'=>'Mapeamento de campo VSM salvo.','contexto'=>['contract_verified'=>!empty($_POST['confirmar_contrato']),'contract_source_sha256'=>trim((string)($_POST['contract_source'] ?? ''))!==''?hash('sha256',trim((string)$_POST['contract_source'])):null]]); redirect('index.php?page=vsm-campos&salvo=1'); }
    catch(Throwable $e){ Audit::exception($e,'vsm.campo.salvar.erro'); $_SESSION['form_error']=$e->getMessage(); redirect('index.php?page=vsm-campos&erro=1'); }
  }

  private function saude(): void {
    PermissionService::require('configuracoes','visualizar');
    $erro = null;
    try {
      $health = VsmEndpointService::health();
      if (!is_array($health)) {
        $health = [];
        $erro = 'O serviço de saúde VSM retornou um formato inválido.';
      }
    } catch (Throwable $e) {
      $erro = $e->getMessage();
      $health = [];
      Audit::exception($e, 'vsm.saude.carregar.erro');
    }
    $pageTitle='Saúde VSM'; require __DIR__.'/../../views/vsm_saude.php';
  }

  private function logs(): void {
    PermissionService::require('logs','visualizar');
    $limit=(int)($_GET['limit'] ?? 100); $erro=null; try { $logs=VsmEndpointService::logs($limit); } catch(Throwable $e){ $erro=$e->getMessage(); $logs=[]; }
    $pageTitle='Logs VSM'; require __DIR__.'/../../views/vsm_logs.php';
  }

  private function testes(): void {
    PermissionService::require('configuracoes','visualizar');
    $erro=null; try { $endpoints=VsmEndpointService::listEndpoints(); } catch(Throwable $e){ $erro=$e->getMessage(); $endpoints=[]; }
    $pageTitle='Testes VSM'; require __DIR__.'/../../views/vsm_testes.php';
  }

  private function requirePost(): void {
    if (RequestContext::method() === 'POST') return;
    http_response_code(405);
    header('Allow: POST');
    exit('Método não permitido. Use POST com token CSRF válido.');
  }

  private function testarVsm(): void {
    PermissionService::require('configuracoes','editar');
    Csrf::validate();
    try {
      $cfg = IntegrationConfig::get();
      $resultado = HealthCheckService::testarVsm($cfg);
      if (class_exists('VsmEnvironmentService')) VsmEnvironmentService::registerTestResult(!empty($resultado['ok']));
      $status = $resultado['ok'] ? 'sucesso' : 'erro';
      Audit::event('vsm.teste_conexao',$status,[
        'codigo_erro'=>$resultado['ok'] ? null : 'VSM_CONNECTION_TEST_FAILED',
        'mensagem'=>'Teste de conexão VSM executado.',
        'causa_provavel'=>$resultado['ok'] ? null : 'URL VSM incorreta, DNS indisponível, HTTPS bloqueado, token inválido ou endpoint de teste inexistente.',
        'acao_recomendada'=>$resultado['ok'] ? 'Conexão VSM validada.' : 'Confira VSM URL, token, internet do servidor, SSL/cURL e endpoint informado no Swagger.',
        'retorno'=>$resultado
      ]);
      NotificationService::criar('sistema',$resultado['ok']?'Teste VSM OK':'Teste VSM falhou',$resultado['mensagem'] ?? 'Veja detalhes na Auditoria.',$resultado['ok']?'sucesso':'erro',['trace_id'=>RequestContext::id()]);
      redirect('index.php?page=configuracoes&teste_vsm='.($resultado['ok']?'ok':'erro'));
    } catch(Throwable $e){
      Audit::exception($e,'vsm.teste_conexao.erro',[
        'codigo_erro'=>'VSM_CONNECTION_TEST_EXCEPTION',
        'causa_provavel'=>'Falha inesperada ao executar diagnóstico VSM.',
        'acao_recomendada'=>'Confira a configuração VSM e veja o erro técnico no evento de auditoria.'
      ]);
      redirect('index.php?page=configuracoes&teste_vsm=erro');
    }
  }

  private function vsmFichaTecnica(): void {
    PermissionService::require('ficha_tecnica','visualizar');
    $endpoints = VsmFichaTecnicaService::endpoints();
    $metricas = VsmFichaTecnicaService::metricas();
    $payloads = VsmFichaTecnicaService::payloads();
    $cfg = IntegrationConfig::get();
    $pageTitle = 'Ficha Técnica VSM';
    require __DIR__.'/../../views/vsm_ficha_tecnica.php';
  }

}
