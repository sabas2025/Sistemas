<?php
class OperationCenterController extends BaseModuleController {
  public function dispatch(string $page): void {
    Auth::requireLogin();
    switch($page){
      case 'centro-operacoes': $this->centro(); break;
      case 'alertas-operacionais': $this->alertas(); break;
      case 'dashboard-executivo': $this->dashboardExecutivo(); break;
      default: $this->centro();
    }
  }
  public static function routes(): array { return ['centro-operacoes','alertas-operacionais','dashboard-executivo']; }
  public function centro(): void {
    PermissionService::require('dashboard','visualizar');
    $operacao = OperationCenterService::summary();
    $workerCards = $operacao['workers'] ?? [];
    $estoqueResumo = $operacao['estoque'] ?? [];
    $fiscalResumo = $operacao['xml_nfe'] ?? [];
    $pedidoCicloResumo = $operacao['pedidos'] ?? [];
    $scores = OperationCenterService::score();
    $pageTitle = 'Centro de Operações';
    $this->view('centro_operacoes', compact('pageTitle','operacao','workerCards','estoqueResumo','fiscalResumo','pedidoCicloResumo','scores'));
  }
  public function alertas(): void {
    PermissionService::require('dashboard','visualizar');
    $operacao = OperationCenterService::summary();
    $pageTitle = 'Alertas Operacionais';
    $this->view('alertas_operacionais', compact('pageTitle','operacao'));
  }
  public function dashboardExecutivo(): void {
    PermissionService::require('dashboard','visualizar');
    $operacao = OperationCenterService::summary();
    $scores = OperationCenterService::score();
    $scoresDetalhados = OperationCenterService::scoreDetalhado();
    $pageTitle = 'Dashboard Executivo';
    $this->view('dashboard_executivo', compact('pageTitle','operacao','scores','scoresDetalhados'));
  }
}
