<?php
/** V104.19 - análise comercial/técnica priorizada, sem expor segredos. */
class CommercialReadinessController {
  public function dispatch(string $page): void {
    Auth::requireLogin();
    PermissionService::require('configuracoes','visualizar');
    $pageTitle = 'Análise Comercial e Técnica';
    $readiness = CommercialReadinessAdvisorService::analyze();
    $schemaPolicy = SchemaRuntimePolicyService::report();
    $connectors = class_exists('ConnectorCapabilityMatrixService') ? ConnectorCapabilityMatrixService::matrix() : [];
    require __DIR__.'/../../views/commercial_readiness.php';
  }
}
