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
      case 'atualizacao': $this->atualizacao(); break;
      default: redirect('index.php?page=dashboard');
    }
  }

  /**
   * Painel de status de atualização (somente leitura). Mostra a versão
   * instalada, um selo de integridade (reusa FileIntegrityService) e o
   * passo a passo para aplicar um pacote novo com scripts/ops/deploy.sh.
   * NÃO aplica nada: a atualização de código é manual por decisão de
   * produto (modelo "painel + instruções"), sem tornar o app auto-gravável.
   */
  private function atualizacao(): void {
    PermissionService::require('configuracoes','visualizar');
    $pageTitle = 'Atualização do Hub';
    $fim = class_exists('FileIntegrityService') ? FileIntegrityService::check() : null;
    require __DIR__.'/../../views/atualizacao.php';
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
