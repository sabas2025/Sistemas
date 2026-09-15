<?php
class CommercialController extends BaseModuleController {
  public function dispatch(string $page): void {
    if ($page === 'produto-institucional') { $this->publicLanding(); return; }
    if ($page === 'demo-online') { $this->publicDemo(); return; }

    Auth::requireLogin();
    PermissionService::require('configuracoes','visualizar');

    switch ($page) {
      case 'planos-comerciais': $this->plans(); break;
      case 'licencas-clientes': $this->licenses(); break;
      case 'licencas-clientes-demo': $this->createDemoLicense(); break;
      case 'conectores-plugaveis': $this->connectors(); break;
      case 'painel-cobranca': $this->billing(); break;
      case 'painel-cobranca-demo': $this->createDemoInvoice(); break;
      case 'ambiente-demo': $this->demoEnvironment(); break;
      case 'ambiente-demo-reset': $this->resetDemoEnvironment(); break;
      case 'cliente-portal': $this->clientPortal(); break;
      case 'suporte-sla': $this->supportSla(); break;
      case 'suporte-sla-demo': $this->createDemoTicket(); break;
      case 'documentos-comerciais': $this->docs(); break;
      case 'documento-comercial': $this->docView(); break;
      default: redirect('index.php?page=planos-comerciais');
    }
  }

  public function publicLanding(): void {
    $pageTitle = 'Hub de Integração Enterprise';
    $phrases = CommercialProductService::phrases();
    $plans = CommercialProductService::plans();
    $values = CommercialProductService::valuePropositions();
    require __DIR__.'/../../views/commercial_landing_public.php';
  }

  public function publicDemo(): void {
    $pageTitle = 'Demonstração Online';
    $phrases = CommercialProductService::phrases();
    $values = CommercialProductService::valuePropositions();
    require __DIR__.'/../../views/demo_online_public.php';
  }

  private function plans(): void {
    $pageTitle = 'Planos Comerciais';
    $phrases = CommercialProductService::phrases();
    $plans = CommercialProductService::plans();
    $values = CommercialProductService::valuePropositions();
    $this->view('commercial_plans', compact('pageTitle','phrases','plans','values'));
  }

  private function licenses(): void {
    $pageTitle = 'Licenças por Cliente';
    $licenses = CommercialProductService::licenses();
    $this->view('commercial_licenses', compact('pageTitle','licenses'));
  }

  private function createDemoLicense(): void {
    Csrf::validate();
    $key = CommercialProductService::createDemoLicense();
    $_SESSION['flash_success'] = 'Licença demo criada. Chave temporária: '.$key.' — salve agora; depois será exibido apenas o hash.';
    redirect('index.php?page=licencas-clientes');
  }

  private function connectors(): void {
    $pageTitle = 'Conectores Plugáveis';
    $connectors = CommercialProductService::connectors();
    $this->view('commercial_connectors', compact('pageTitle','connectors'));
  }

  private function billing(): void {
    $pageTitle = 'Painel de Cobrança';
    $invoices = CommercialProductService::invoices();
    $licenses = CommercialProductService::licenses();
    $this->view('commercial_billing', compact('pageTitle','invoices','licenses'));
  }

  private function createDemoInvoice(): void {
    Csrf::validate();
    CommercialProductService::createSampleInvoice();
    $_SESSION['flash_success'] = 'Fatura demonstrativa criada. Integre Mercado Pago, banco ou emissão fiscal somente após contrato.';
    redirect('index.php?page=painel-cobranca');
  }

  private function demoEnvironment(): void {
    $pageTitle = 'Ambiente Demo Sem Dados Reais';
    $demos = CommercialProductService::demos();
    $values = CommercialProductService::valuePropositions();
    $this->view('commercial_demo_environment', compact('pageTitle','demos','values'));
  }

  /**
   * P1-09 (reauditoria 2026-08-23): as 4 rotas abaixo (ambiente-demo-reset, cliente-portal,
   * suporte-sla, suporte-sla-demo) já existiam no switch do dispatch() e chamavam estes
   * métodos, mas eles nunca tinham sido escritos - qualquer clique nelas gerava um fatal
   * error "Call to undefined method". As views (commercial_client_portal.php,
   * commercial_support_sla.php) e os métodos de dados em CommercialProductService
   * (supportTickets/createDemoTicket/resetDemoEnvironment) já existiam prontos; faltava
   * só esta ligação.
   */
  private function resetDemoEnvironment(): void {
    Csrf::validate();
    $resultado = CommercialProductService::resetDemoEnvironment();
    $_SESSION['flash_success'] = $resultado['mensagem'] ?? 'Ambiente demo reiniciado.';
    redirect('index.php?page=ambiente-demo');
  }

  private function clientPortal(): void {
    $pageTitle = 'Portal Self-Service do Cliente';
    $licenseStatus = LicenseEnforcementService::status();
    $licenses = CommercialProductService::licenses();
    $connectors = CommercialProductService::connectors();
    $invoices = CommercialProductService::invoices();
    $tickets = CommercialProductService::supportTickets();
    $this->view('commercial_client_portal', compact('pageTitle','licenseStatus','licenses','connectors','invoices','tickets'));
  }

  private function supportSla(): void {
    $pageTitle = 'SLA e Suporte';
    $tickets = CommercialProductService::supportTickets();
    $this->view('commercial_support_sla', compact('pageTitle','tickets'));
  }

  private function createDemoTicket(): void {
    Csrf::validate();
    CommercialProductService::createDemoTicket();
    $_SESSION['flash_success'] = 'Chamado demo criado.';
    redirect('index.php?page=suporte-sla');
  }

  private function docs(): void {
    $pageTitle = 'Documentos Comerciais e Técnicos';
    $docs = CommercialProductService::docs();
    $this->view('commercial_docs', compact('pageTitle','docs'));
  }

  private function docView(): void {
    $file = (string)($_GET['file'] ?? '');
    $content = CommercialProductService::docContent($file);
    if ($content === null) { http_response_code(404); echo 'Documento não encontrado.'; return; }
    $pageTitle = 'Documento Comercial';
    $this->view('commercial_doc_view', compact('pageTitle','file','content'));
  }
}
