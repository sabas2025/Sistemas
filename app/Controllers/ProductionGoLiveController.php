<?php
class ProductionGoLiveController {
  public function dispatch(string $page): void {
    Auth::requireLogin();
    switch ($page) {
      case 'entrada-producao': $this->index(); break;
      case 'entrada-producao-validar': $this->validar(); break;
      case 'entrada-producao-lock-install': $this->lockInstall(); break;
      default: redirect('index.php?page=entrada-producao');
    }
  }
  private function index(): void {
    PermissionService::require('configuracoes','visualizar');
    $resultado = $_SESSION['go_live_resultado'] ?? null; unset($_SESSION['go_live_resultado']);
    $lockResultado = $_SESSION['go_live_lock'] ?? null; unset($_SESSION['go_live_lock']);
    $historico = [];
    try { $historico = ProductionGoLiveService::historico(5); } catch(Throwable $e) { $erro = $e->getMessage(); }
    $pageTitle='Entrada em Produção';
    require __DIR__.'/../../views/entrada_producao.php';
  }
  private function validar(): void {
    PermissionService::require('configuracoes','visualizar');
    Csrf::validate();
    try { $_SESSION['go_live_resultado'] = ProductionGoLiveService::executar(); }
    catch(Throwable $e){ Audit::exception($e,'production.golive.erro'); $_SESSION['form_error']=$e->getMessage(); }
    redirect('index.php?page=entrada-producao');
  }
  private function lockInstall(): void {
    PermissionService::require('configuracoes','editar');
    Csrf::validate();
    if (trim((string)($_POST['confirmacao'] ?? '')) !== 'BLOQUEAR') {
      $_SESSION['form_error']='Digite BLOQUEAR para criar o lock de instalação.';
      redirect('index.php?page=entrada-producao');
    }
    try { $_SESSION['go_live_lock'] = ProductionGoLiveService::bloquearInstalador(); }
    catch(Throwable $e){ Audit::exception($e,'production.golive.lock.erro'); $_SESSION['form_error']=$e->getMessage(); }
    redirect('index.php?page=entrada-producao');
  }
}
