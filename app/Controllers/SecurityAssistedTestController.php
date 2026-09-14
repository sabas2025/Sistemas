<?php
class SecurityAssistedTestController extends BaseModuleController {
  public function dispatch(string $page): void {
    Auth::requireLogin();
    PermissionService::require('seguranca','visualizar');
    if ($page === 'security-assisted-test-run') { $this->run(); return; }
    if ($page === 'security-assisted-test-download') { $this->download(); return; }
    $this->index();
  }

  private function index(): void {
    $pageTitle = 'Teste de Segurança Assistido';
    $report = SecurityAssistedTestService::run(false);
    $reports = SecurityAssistedTestService::reports();
    $this->view('security_assisted_test', compact('pageTitle','report','reports'));
  }

  private function run(): void {
    Csrf::validate();
    $report = SecurityAssistedTestService::run(true);
    $_SESSION['flash_success'] = 'Teste de Segurança Assistido gerado com sucesso. Score: '.($report['resumo']['score'] ?? 0).'%. Trace ID: '.($report['trace_id'] ?? '');
    redirect('index.php?page=security-assisted-test');
  }

  private function download(): void {
    $file = (string)($_GET['file'] ?? '');
    $path = SecurityAssistedTestService::resolveReportPath($file);
    if (!$path) {
      http_response_code(404);
      echo 'Relatório não encontrado.';
      return;
    }
    $mime = str_ends_with($path, '.json') ? 'application/json' : 'text/markdown; charset=UTF-8';
    header('Content-Type: '.$mime);
    header('Content-Disposition: attachment; filename="'.basename($path).'"');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
  }
}
