<?php
class CommercialProductionController extends BaseModuleController {
  public function dispatch(string $page): void {
    Auth::requireLogin();
    PermissionService::require('configuracoes','visualizar');
    $pageTitle = 'Prontidão Produção Comercial';
    $readiness = ProductionCommercialReadinessService::run();
    $license = LicenseEnforcementService::status();
    $connectors = ConnectorRegistryService::catalog();
    $this->view('commercial_production_ready', compact('pageTitle','readiness','license','connectors'));
  }
}
