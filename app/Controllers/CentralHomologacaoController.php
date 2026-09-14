<?php
class CentralHomologacaoController extends BaseModuleController {
  public function dispatch(string $page): void {
    Auth::requireLogin();
    switch ($page) {
      case 'central-homologacao': $this->index(); break;
      default: $this->index();
    }
  }
  public static function routes(): array { return ['central-homologacao']; }
  public function index(): void {
    PermissionService::require('homologacao','visualizar');
    $cfg = IntegrationConfig::get();
    $v2 = class_exists('TinyV2HomologationService') ? TinyV2HomologationService::resumo($cfg) : ['checks'=>[], 'progresso'=>0, 'status_geral'=>'pendente', 'historico'=>[], 'ultimo'=>null];
    $v3 = class_exists('TinyV3HomologationService') ? TinyV3HomologationService::resumo($cfg) : ['checks'=>[], 'progresso'=>0, 'status_geral'=>'pendente', 'historico'=>[], 'ultimo'=>null];
    $vsmOk = !empty($cfg['vsm_url']);
    $xmlResumo = XmlNfeHomologationService::resumoOperacional();
    $pageTitle = 'Central de Homologação';
    $this->view('central_homologacao', compact('pageTitle','cfg','v2','v3','vsmOk','xmlResumo'));
  }
}
