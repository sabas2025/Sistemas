<?php
class FiscalController extends BaseModuleController {
  public function dispatch(string $page): void {
    switch($page){
      case 'fiscal-reenviar': $this->reenviar(); break;
      case 'fiscal-dashboard': $this->dashboardFiscal(); break;
      case 'fiscal-xml': $this->xml(); break;
      case 'fiscal-timeline': $this->timeline(); break;
      case 'fiscal-reconciliacao': $this->reconciliacao(); break;
      case 'fiscal-health': $this->health(); break;
      default: $this->index();
    }
  }
  public static function routes(): array { return ['fiscal','fiscal-reenviar','fiscal-dashboard','fiscal-xml','fiscal-timeline','fiscal-reconciliacao','fiscal-health']; }
  public function index(): void {
    PermissionService::require('fiscal','visualizar');
    $erroFiscal = null;
    $resumoFiscal=FiscalIntegrationService::resumo();
    $dashboardFiscal=FiscalEnterpriseService::dashboard();
    $reconciliacaoFiscal=FiscalEnterpriseService::reconciliacao();
    $notas=[]; $nfeIntegracoes=[]; $erroFiscal=null;
    try {
      $notas=FiscalIntegrationService::listar($_GET['status'] ?? '',100);
      $nfeIntegracoes=TenantScopeService::run('nfe_integracao', 'SELECT i.*, n.numero, n.serie, n.chave_acesso FROM nfe_integracao i LEFT JOIN notas_fiscais n ON n.id=i.nota_fiscal_id ORDER BY i.id DESC LIMIT 50')->fetchAll();
    } catch(Throwable $e){ $erroFiscal=$e->getMessage(); }
    $pageTitle='XML / NF-e Enterprise';
    $this->view('fiscal',compact('pageTitle','resumoFiscal','dashboardFiscal','reconciliacaoFiscal','notas','nfeIntegracoes','erroFiscal'));
  }
  public function dashboardFiscal(): void { PermissionService::require('fiscal','visualizar'); $pageTitle='Dashboard XML/NF-e'; $dashboard=FiscalEnterpriseService::dashboard(); $reconciliacao=FiscalEnterpriseService::reconciliacao(); $this->view('fiscal_dashboard',compact('pageTitle','dashboard','reconciliacao')); }
  public function xml(): void { PermissionService::require('fiscal','visualizar'); $pageTitle='XML Fiscal'; $xmls=FiscalEnterpriseService::xmls($_GET['status'] ?? ''); $this->view('fiscal_xml',compact('pageTitle','xmls')); }
  public function timeline(): void { PermissionService::require('fiscal','visualizar'); $erro=null; $id=(int)($_GET['id'] ?? 0); $pageTitle='Timeline XML/NF-e'; $nota=null; $eventos=[]; if($id>0){ try { $st = TenantScopeService::run('notas_fiscais', 'SELECT * FROM notas_fiscais WHERE id=?', [$id]); $nota=$st->fetch(); $eventos=FiscalEnterpriseService::timeline($id); } catch(Throwable $e){ $erro=$e->getMessage(); } } $this->view('fiscal_timeline',compact('pageTitle','id','nota','eventos','erro')); }
  public function reconciliacao(): void { PermissionService::require('fiscal','reconciliar'); $pageTitle='Reconciliação XML/NF-e'; $reconciliacao=FiscalEnterpriseService::reconciliacao(); $this->view('fiscal_reconciliacao',compact('pageTitle','reconciliacao')); }
  public function health(): void { PermissionService::require('fiscal','visualizar'); $pageTitle='Saúde XML/NF-e'; $checks=FiscalEnterpriseService::health(); $this->view('fiscal_health',compact('pageTitle','checks')); }
  public function reenviar(): void { PermissionService::require('fiscal','reenviar'); Csrf::validate(); $id=(int)($_POST['id'] ?? 0); if($id>0) FiscalEnterpriseService::reprocessar($id); Audit::event('fiscal.reenviar','sucesso',['entidade'=>'nfe_integracao','entidade_id'=>$id,'mensagem'=>'XML/NF-e marcado para reenvio/reprocessamento ao Tiny.']); redirect('index.php?page=fiscal&reenviar=1'); }
}
