<?php
class ConfigDiagnosticController extends BaseModuleController {
  public function dispatch(string $page): void {
    Auth::requireLogin();
    PermissionService::require('configuracoes','visualizar');
    $pageTitle = 'Diagnóstico de Configuração Real';
    $diagnostic = DatabaseConfigDiagnosticService::run();
    $this->view('config_diagnostic', compact('pageTitle','diagnostic'));
  }
}
