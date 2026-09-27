<?php
/**
 * Fase 3 (decomposição do controller-deus, 2026-09-27) — etapa Central Técnica / Prod-Ready.
 *
 * Reúne a Central Técnica, o diagnóstico, a integridade do dashboard, o menu de testes, a ficha
 * técnica 100%, o guia de hospedagem InfinityFree e os painéis Production-Ready (V24/V25/V26,
 * producao-ready e o relatório de prontidão) que viviam no DashboardController (achado A3-01). O
 * CentralTecnicaController era um stub planejado (V42) que só lia o RouteModuleRegistry; agora
 * recebe a implementação real, despachado pelo FastRouteDispatcherService::$dispatchGroups.
 * Handlers movidos verbatim; PermissionService/Csrf preservados. Só serviços estáticos — sem $pdo/db.
 */
class CentralTecnicaController {
  public static function routes(): array {
    return ['central-tecnica','diagnostico','dashboard-integridade','dashboard-integridade-executar','menu-testes','ficha-tecnica-100','hosting-infinityfree','producao-ready','production-ready-v24','production-ready-v25','production-ready-v26','relatorio-prontidao-producao'];
  }

  public function dispatch(string $page): void {
    Auth::requireLogin();
    switch ($page) {
      case 'diagnostico': $this->diagnostico(); break;
      case 'dashboard-integridade': $this->dashboardIntegridade(); break;
      case 'dashboard-integridade-executar': $this->dashboardIntegridadeExecutar(); break;
      case 'menu-testes': $this->menuTestes(); break;
      case 'ficha-tecnica-100': $this->fichaTecnica100(); break;
      case 'hosting-infinityfree': $this->hostingInfinityFree(); break;
      case 'producao-ready': $this->producaoReady(); break;
      case 'production-ready-v24': $this->productionReadyV24(); break;
      case 'production-ready-v25': $this->productionReadyV25(); break;
      case 'production-ready-v26': $this->productionReadyV26(); break;
      case 'relatorio-prontidao-producao': $this->relatorioProntidaoProducao(); break;
      default: $this->centralTecnica(); break; // central-tecnica
    }
  }

  private function centralTecnica(): void {
    PermissionService::require('configuracoes','visualizar');
    $pageTitle = 'Central Técnica';
    require __DIR__.'/../../views/central_tecnica.php';
  }

  private function diagnostico(): void {
    PermissionService::require('dashboard','visualizar');
    $checks = HealthCheckService::run();
    $historicoDiagnostico = class_exists('DiagnosticoApiService') ? DiagnosticoApiService::ultimos(50) : [];
    $pageTitle = 'Diagnóstico';
    require __DIR__.'/../../views/diagnostico.php';
  }

  private function dashboardIntegridade(): void {
    PermissionService::require('dashboard','visualizar');
    $checks = DashboardIntegrityService::checks();
    $summary = DashboardIntegrityService::summary($checks);
    $pageTitle = 'Integridade do Dashboard';
    require __DIR__.'/../../views/dashboard_integridade.php';
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

  private function hostingInfinityFree(): void {
    PermissionService::require('hosting','visualizar');
    $compat = HostingCompatibilityService::ambiente();
    $recomendacoes = HostingCompatibilityService::recomendações();
    $pageTitle = 'Hospedagem InfinityFree';
    require __DIR__.'/../../views/hosting_infinityfree.php';
  }

  private function producaoReady(): void {
    PermissionService::require('configuracoes','visualizar');
    $pageTitle = 'Produção Segura';
    $resultado = ProductionReadyService::checks();
    require __DIR__.'/../../views/producao_ready.php';
  }

  private function productionReadyV24(): void {
    PermissionService::require('ficha_tecnica','visualizar');
    $scores = ProductionReadinessV24Service::scores();
    $checklist = ProductionReadinessV24Service::checklist();
    $prechecks = class_exists('InstallationPrecheckService') ? InstallationPrecheckService::run() : [];
    $pageTitle = 'Production Ready V24';
    require __DIR__.'/../../views/production_ready_v24.php';
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

  private function productionReadyV26(): void {
    PermissionService::require('production_ready','visualizar');
    $resumo = EnterpriseV26ReadinessService::resumo();
    $pageTitle = 'Production Ready V26 / InfinityFree';
    require __DIR__.'/../../views/production_ready_v26.php';
  }

  private function relatorioProntidaoProducao(): void {
    PermissionService::require('configuracoes','visualizar');
    $checks = class_exists('ProductionReadinessV24Service') ? ProductionReadinessV24Service::checks() : [];
    $host = class_exists('HostingCompatibilityService') ? HostingCompatibilityService::ambiente() : [];
    $pageTitle = 'Relatório de Prontidão para Produção';
    require __DIR__.'/../../views/relatorio_prontidao_producao.php';
  }
}
