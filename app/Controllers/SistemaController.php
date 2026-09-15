<?php
/**
 * SistemaController
 * Centraliza telas institucionais essenciais, mantendo a interface limpa.
 */
class SistemaController {
  public function dispatch(string $page): void {
    Auth::requireLogin();
    switch($page){
      case 'sobre': $this->sobre(); break;
      case 'tutorial-sistema': $this->tutorialSistema(); break;
      default: redirect('index.php?page=dashboard');
    }
  }

  private function sobre(): void {
    PermissionService::require('configuracoes','visualizar');
    $pageTitle = 'Sobre';
    $branding = 'Hub de Integração Enterprise';
    $assinatura = 'Desenvolvido por Sabas';
    require __DIR__.'/../../views/sobre.php';
  }

  private function tutorialSistema(): void {
    PermissionService::require('configuracoes','visualizar');
    $pageTitle = 'Tutorial do Sistema';
    require __DIR__.'/../../views/tutorial_sistema.php';
  }
}
