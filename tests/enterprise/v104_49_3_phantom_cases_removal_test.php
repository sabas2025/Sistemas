<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks = [];

/**
 * Fase 3 (decomposição do controller-deus, 2026-09-27) — achado A3-03: remoção dos cases fantasma.
 *
 * Varredura do $dispatchGroups contra o switch do DashboardController: toda rota já roteada a um
 * controller dedicado (ou tratada antes do fallback) tinha, ainda assim, um case no Dashboard —
 * inalcançável, porque FastRouteDispatcherService::routeToController() resolve e retorna ANTES do
 * fallback (new DashboardController())->dispatch(). Eram 23 cases (13 com handler $this->x() e os
 * demais delegando por (new Ctrl)->dispatch()). Removidos com seus handlers mortos; nenhuma rota
 * muda de comportamento. Reprova sobre o código antigo (o Dashboard ainda tinha handlers/cases).
 *
 * Também travado aqui: DashboardIntegrityService passou a consultar o dispatcher real (não só o
 * switch legado), senão a rota 'fiscal' — agora servida pelo XmlNfeController — viraria um falso
 * "não localizada" (indicador que mente).
 */
$dash = hub_read('app/Controllers/DashboardController.php');
$disp = hub_read('app/Services/FastRouteDispatcherService.php');
$integ = hub_read('app/Services/DashboardIntegrityService.php');

// Handlers mortos que saíram do Dashboard.
$handlers = ['dashboard','alertasOperacionais','centroOperacoes','divergenciaEstoque','divergenciaAcao',
             'sobre','healthModulos','validarBanco','centralHomologacao',
             'tinyV2Homologacao','tinyV2HomologacaoExecutar','tinyV3Homologacao','tinyV3HomologacaoExecutar'];
hub_check($checks, 'DashboardController lido (N>0)', strlen($dash) > 1000);
foreach ($handlers as $h) {
    hub_check($checks, "DashboardController não tem mais o handler {$h}", $dash !== '' && !preg_match('/function\s+'.preg_quote($h,'/').'\s*\(/', $dash));
}
hub_check($checks, 'DashboardController não tem mais index() (só chamava dashboard())', $dash !== '' && !preg_match('/function\s+index\s*\(/', $dash));

// Cases fantasma que saíram do switch.
$rotasFantasma = ['dashboard','alertas-operacionais','centro-operacoes','divergencia-estoque','divergencia-acao',
                  'orquestracao-integracoes','salvar-orquestracao-integracoes','testar-orquestracao-fluxo',
                  'sobre','health-modulos','fiscal','fiscal-reenviar','fiscal-dashboard','fiscal-xml',
                  'fiscal-timeline','fiscal-reconciliacao','fiscal-health','validar-banco','central-homologacao',
                  'tiny-v2-homologacao','tiny-v2-homologacao-executar','tiny-v3-homologacao','tiny-v3-homologacao-executar'];
foreach ($rotasFantasma as $r) {
    hub_check($checks, "DashboardController não tem mais o case {$r}", !str_contains($dash, "case '{$r}'"));
}

// Cada rota fantasma continua com dono real, roteada antes do fallback.
$donos = [
  'DashboardHomeController' => ['dashboard'],
  'OperationCenterController' => ['alertas-operacionais','centro-operacoes'],
  'DivergenceMonitorController' => ['divergencia-estoque','divergencia-acao'],
  'OrquestracaoController' => ['orquestracao-integracoes','salvar-orquestracao-integracoes','testar-orquestracao-fluxo'],
  'SistemaController' => ['sobre'],
  'DatabaseMaintenanceController' => ['health-modulos','validar-banco'],
  'XmlNfeController' => ['fiscal','fiscal-reenviar','fiscal-xml','fiscal-health'],
  'CentralHomologacaoController' => ['central-homologacao'],
  'TinyHomologacaoController' => ['tiny-v2-homologacao','tiny-v3-homologacao'],
];
foreach ($donos as $ctrl => $rotas) {
  foreach ($rotas as $r) {
    hub_check($checks, "rota {$r} roteada a {$ctrl} no dispatchGroups", (bool)preg_match('/'.preg_quote($ctrl,'/').'::class => \[[^\]]*\''.preg_quote($r,'/').'\'/', $disp));
  }
}

// Delegações VIVAS (não estão no dispatchGroups) devem PERMANECER no Dashboard.
// (auditoria-detalhe e fila-morta-reprocessar deixaram de ser delegações vivas do Dashboard: foram
//  para o dispatchGroups nas etapas Auditoria e Fila. Não resta delegação viva no switch do Dashboard.)
// Rotas vivas do próprio Dashboard permanecem.
// (configuracoes migrou para o ConfiguracaoController na etapa Configurações — não é mais rota do Dashboard.)
// (produtos migrou para o ProdutoController na etapa Produtos — não é mais rota do Dashboard.)
foreach (['pedidos','integracoes','logs','diagnostico'] as $r) {
    hub_check($checks, "rota viva {$r} permanece no Dashboard", str_contains($dash, "case '{$r}'"));
}

// Indicador honesto: DashboardIntegrityService consulta o dispatcher real.
hub_check($checks, 'DashboardIntegrityService consulta o FastRouteDispatcherService', str_contains($integ, 'FastRouteDispatcherService.php'));
hub_check($checks, 'DashboardIntegrityService não cita mais o handler removido dashboard()', !str_contains($integ, 'DashboardController::dashboard()'));

hub_check($checks, 'DashboardController abaixo de 160 KB', strlen($dash) < 160*1024, 'bytes='.strlen($dash));

hub_finish($checks);
