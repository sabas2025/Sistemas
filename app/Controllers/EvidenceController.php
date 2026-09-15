<?php
class EvidenceController extends BaseModuleController {
  public function dispatch(string $page): void {
    Auth::requireLogin();
    switch($page){
      case 'evidencias-homologacao': $this->index(); break;
      case 'evidencia-trace': $this->trace(); break;
      default: $this->index();
    }
  }
  public static function routes(): array { return ['evidencias-homologacao','evidencia-trace']; }
  public function index(): void {
    PermissionService::require('auditoria','visualizar');
    $trace = trim($_GET['trace'] ?? '');
    $eventos=[];
    if($trace !== ''){
      try { $st=Database::forTable('auditoria_eventos')->prepare('SELECT * FROM auditoria_eventos WHERE trace_id=? ORDER BY id ASC LIMIT 500'); $st->execute([$trace]); $eventos=$st->fetchAll(); } catch(Throwable $e) { $eventos=[]; }
    }
    $pageTitle='Evidências de Homologação';
    $this->view('evidencias_homologacao', compact('pageTitle','trace','eventos'));
  }
  public function trace(): void { $this->index(); }
}
