<?php
/**
 * Dispatcher central e leve para reduzir cadeia de if/elseif em public/index.php.
 * Mantém compatibilidade com as rotas antigas, mas deixa o fluxo de roteamento organizado.
 */
class FastRouteDispatcherService {
  /** @var array<string, array{0:string,1:string}> */
  private static array $directActions = [
    'api/processar-fila' => [ApiController::class, 'processarFila'],
    'api/notificacoes/recentes' => [ApiController::class, 'notificacoesRecentes'],
    'api/notificacao/lida' => [ApiController::class, 'marcarNotificacaoLidaApi'],
    'api/status' => [ApiController::class, 'statusJson'],
  ];

  /** @var array<string, string[]> */
  private static array $dispatchGroups = [
    MyOuroController::class => ['myouro-configuracoes','myouro-salvar','myouro-testar','integracao-vincular-empresa'],
    ApiVsmWebhookController::class => ['api/webhook/vsm/pedido','api/webhook/vsm/produto','api/webhook/vsm/estoque','api/webhook/vsm/pedido-retorno'],
    ApiTinyController::class => ['api/webhook/tiny/evento','api/tiny/webhook/estoque','api/tiny/webhook/produto','api/tiny/webhook/nota-fiscal','api/tiny/webhook/situacao-pedido','api/tiny/webhook/pedido'],
    SistemaController::class => ['sobre','tutorial-sistema'],
    PwaController::class => ['pwa-status'],
    ProductionGoLiveController::class => ['entrada-producao','entrada-producao-validar','entrada-producao-lock-install'],
    OperationCenterController::class => ['centro-operacoes','alertas-operacionais','dashboard-executivo'],
    DivergenceMonitorController::class => ['monitor-divergencias','divergencia-estoque','divergencia-acao'],
    EvidenceController::class => ['evidencias-homologacao','evidencia-trace'],
    ProductionSecurityController::class => ['producao-segura','producao-segura-executar'],
    V50Controller::class => ['tiny-validacao','tiny-validacao-executar','vsm-simulador','vsm-simulador-executar','limpeza-retencao','limpeza-retencao-executar'],
    XmlNfeController::class => ['fiscal','fiscal-reenviar','fiscal-dashboard','fiscal-xml','fiscal-timeline','fiscal-reconciliacao','fiscal-health'],
    V51Controller::class => ['produto-novo-politica','produto-novo-politica-salvar','pedidos-validacao-vsm','pedido-validacao-detalhe','pedido-validacao-enfileirar','pedido-ciclo-vida','pedido-ciclo-detalhe','pedido-ciclo-enviar-tiny'],
    CentralHomologacaoController::class => ['central-homologacao'],
    TinyHomologacaoController::class => ['tiny-v2-homologacao','tiny-v2-homologacao-executar','tiny-v3-homologacao','tiny-v3-homologacao-executar'],
    BackupController::class => ['backup','backup-download','backup-excluir','backup-importar','backup-restaurar','backups'],
    OrquestracaoController::class => ['orquestracao-integracoes','salvar-orquestracao-integracoes','testar-orquestracao-fluxo'],
    DatabaseMaintenanceController::class => ['validar-banco','health-modulos','mapa-banco'],
    ConfigDiagnosticController::class => ['diagnostico-config-real'],
    SecurityAssistedTestController::class => ['security-assisted-test','security-assisted-test-run','security-assisted-test-download'],
    CommercialController::class => ['planos-comerciais','licencas-clientes','licencas-clientes-demo','conectores-plugaveis','painel-cobranca','painel-cobranca-demo','ambiente-demo','ambiente-demo-reset','cliente-portal','suporte-sla','suporte-sla-demo','documentos-comerciais','documento-comercial'],
    CommercialProductionController::class => ['producao-comercial'],
    CommercialHardeningController::class => ['producao-comercial-final'],
    DashboardHomeController::class => ['dashboard'],
    CommercialReadinessController::class => ['analise-comercial-tecnica'],
    EnterpriseCoreController::class => ['enterprise-core','enterprise-core-aplicar','enterprise-observabilidade','integration-events','llm-governance','enterprise-regression-tests','design-system-enterprise'],
    VsmController::class => ['vsm-endpoints','vsm-endpoint-salvar','vsm-endpoint-testar','vsm-campos','vsm-campo-salvar','vsm-saude','vsm-logs','vsm-testes'],
  ];

  /** @var array<string, string>|null */
  private static ?array $routeToController = null;

  public static function dispatch(string $page, string $method): void {
    self::sendPerfHeaderOnFinish();

    // V104.16 - rotas públicas comerciais sem dados reais.
    if (in_array($page, ['produto-institucional','demo-online'], true)) { (new CommercialController())->dispatch($page); return; }

    // A allowlist de IP do painel precisa valer também para login/logout - senão um IP fora
    // da lista ainda conseguiria autenticar normalmente antes de qualquer outra rota barrar.
    if (class_exists('AdminIpAllowlistService')) AdminIpAllowlistService::enforceForRequest($page);

    // Reauditoria 2026-09-14 (achado A-02): o callback OAuth do Tiny V3 chega por navegação
    // cross-site vinda de accounts.tiny.com.br, e o navegador não envia o cookie de sessão
    // SameSite=Strict nesse retorno. Se ele caísse no DashboardController, o Auth::requireLogin()
    // do dispatch() redirigiria para o login antes de consumir o state - o fluxo OAuth nunca
    // se completava. Ele roda aqui, fora do gate de sessão, mas NÃO é anônimo: autoriza contra
    // o perfil vinculado à transação OAuth assinada, de uso único e expirável.
    if ($page === 'tiny-v3-callback') { (new DashboardController())->dispatchOAuthCallback($page); return; }

    if ($page === 'login' && $method === 'POST') { (new LoginController())->login(); return; }
    if ($page === 'login') { (new LoginController())->form(); return; }
    if ($page === 'logout') { (new LoginController())->logout(); return; }
    if ($page === 'manutencao') { require __DIR__ . '/../../views/manutencao.php'; return; }
    if ($page === 'api/csp-report') { CspReportService::handle(); return; }

    IntegrationTenantService::enforceRequest($page);
    if (class_exists('TenantContextService')) TenantContextService::requireScopeForOperationalRoute($page);
    if (class_exists('LicenseEnforcementService')) LicenseEnforcementService::enforceForRequest($page);

    // P1-10 (reauditoria 2026-08-23): este gate ficava DEPOIS do bloco $directActions,
    // então uma conta com troca de senha obrigatória ainda conseguia chamar
    // api/processar-fila e outras ações autenticadas antes de trocar a senha. Movido
    // para antes de qualquer dispatch de ação.
    if (Auth::check() && (int)(Auth::user()['deve_trocar_senha'] ?? 0) === 1 && $page !== 'trocar-senha' && $page !== 'logout') {
      redirect('index.php?page=trocar-senha');
    }

    if (isset(self::$directActions[$page])) {
      [$controller, $action] = self::$directActions[$page];
      (new $controller())->{$action}();
      return;
    }

    if (str_starts_with($page, 'atualizar-v')) { (new MigrationController())->dispatch($page); return; }
    if (in_array($page, ['migracoes-seguras','migracao-aplicar'], true)) { (new MigrationController())->dispatch($page); return; }

    $controller = self::routeToController()[$page] ?? null;
    if ($controller) { LegacyRouteGuardService::inspect($page, $controller); (new $controller())->dispatch($page); return; }

    (new DashboardController())->dispatch($page);
  }

  /** @return array<string, string> */
  private static function routeToController(): array {
    if (self::$routeToController !== null) return self::$routeToController;
    $map = [];
    foreach (self::$dispatchGroups as $controller => $routes) {
      foreach ($routes as $route) $map[$route] = $controller;
    }
    return self::$routeToController = $map;
  }

  private static function sendPerfHeaderOnFinish(): void {
    static $registered = false;
    if ($registered || PHP_SAPI === 'cli') return;
    $registered = true;
    $start = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
    register_shutdown_function(function() use ($start) {
      if (!headers_sent()) {
        $ms = round((microtime(true) - (float)$start) * 1000, 2);
        header('X-Hub-Response-Time-Ms: ' . $ms);
      }
    });
  }
}
