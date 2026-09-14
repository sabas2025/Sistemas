<?php
class CommercialHardeningController extends BaseModuleController {
  public function dispatch(string $page): void {
    Auth::requireLogin();
    PermissionService::require('configuracoes','visualizar');
    $pageTitle = 'Checklist Final Produção Comercial';
    $hardening = CommercialHardeningService::run();
    $tenantAudit = TenantScopeAuditService::run(false);
    $connectorMatrix = ConnectorCapabilityMatrixService::matrix();
    $connectorSummary = ConnectorCapabilityMatrixService::summary();
    $billing = BillingGatewayService::status();
    $licenseRemote = LicenseServerClientService::status();
    $cicd = CiCdPipelineService::status();
    $this->view('commercial_hardening_final', compact('pageTitle','hardening','tenantAudit','connectorMatrix','connectorSummary','billing','licenseRemote','cicd'));
  }
}
