<?php
/**
 * Controller ativo para checklist de produção segura.
 *
 * Substitui o acesso direto via V50Controller legado para a rota
 * `producao-segura`, evitando erro de caminho em app/Legacy/Controllers
 * e reduzindo dependência de rota histórica no uso diário do painel.
 */
class ProductionSecurityController {
  public function dispatch(string $page): void {
    Auth::requireLogin();
    switch ($page) {
      case 'producao-segura':
        $this->index();
        break;
      case 'producao-segura-executar':
        $this->executar();
        break;
      default:
        redirect('index.php?page=central-tecnica');
    }
  }

  private function index(): void {
    PermissionService::require('configuracoes', 'visualizar');
    $resultado = $_SESSION['producao_segura_resultado'] ?? null;
    unset($_SESSION['producao_segura_resultado']);
    $pageTitle = 'Checklist Produção Segura';
    require __DIR__ . '/../../views/producao_segura.php';
  }

  private function executar(): void {
    PermissionService::require('configuracoes', 'visualizar');
    Csrf::validate();
    try {
      $_SESSION['producao_segura_resultado'] = ProductionReadinessV50Service::executar();
      $_SESSION['form_success'] = 'Checklist de produção segura executado com sucesso.';
    } catch (Throwable $e) {
      Audit::exception($e, 'producao.segura.erro');
      $_SESSION['form_error'] = 'Falha ao executar checklist de produção segura. Trace registrado na auditoria.';
    }
    redirect('index.php?page=producao-segura');
  }
}
