<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks = [];

/**
 * Fase 3 (decomposição do controller-deus, 2026-09-26) — etapa Homologação.
 *
 * Homologação (manual/automática/relatório/ação), self-test e checklist OAuth V3 saíram do
 * DashboardController (A3-01) para o HomologacaoController, despachados pelo
 * FastRouteDispatcherService::$dispatchGroups. Reprova sobre o código antigo (HomologacaoController
 * era stub). Comportamento medido contra MariaDB provisionado: selftest, homologacao,
 * homologacao-relatorio, relatorio-homologacao, homologacao-automatica e oauth-v3-checklist
 * respondem HTTP 200 autenticado, sem erro no corpo.
 */
$dash = hub_read('app/Controllers/DashboardController.php');
$hom  = hub_read('app/Controllers/HomologacaoController.php');
$disp = hub_read('app/Services/FastRouteDispatcherService.php');

$metodos = ['selftest','selftestExecutar','homologacao','homologacaoAcao','homologacaoRelatorio','homologacaoAutomatica','homologacaoAutomaticaExecutar','oauthV3Checklist'];

hub_check($checks, 'HomologacaoController lido (N>0)', strlen($hom) > 800);
hub_check($checks, 'HomologacaoController estende BaseModuleController', str_contains($hom, 'extends BaseModuleController'));
hub_check($checks, 'HomologacaoController inicializa $pdo próprio', str_contains($hom, 'Database::getConnection()'));
foreach ($metodos as $m) {
    hub_check($checks, "HomologacaoController tem o handler {$m}", str_contains($hom, "function {$m}("));
}

hub_check($checks, 'dispatchGroups registra HomologacaoController', str_contains($disp, 'HomologacaoController::class => ['));
foreach (['selftest','homologacao','homologacao-acao','homologacao-relatorio','relatorio-homologacao','homologacao-automatica','oauth-v3-checklist'] as $rota) {
    hub_check($checks, "rota {$rota} mapeada no dispatchGroups", (bool)preg_match('/HomologacaoController::class => \[[^\]]*\''.preg_quote($rota,'/').'\'/', $disp));
}

hub_check($checks, 'DashboardController lido (N>0)', strlen($dash) > 1000);
foreach ($metodos as $m) {
    hub_check($checks, "DashboardController não tem mais o handler {$m}", $dash !== '' && !str_contains($dash, "function {$m}("));
}
hub_check($checks, 'DashboardController não tem mais o case homologacao-acao', !str_contains($dash, "case 'homologacao-acao'"));
hub_check($checks, 'DashboardController abaixo de 160 KB com folga', strlen($dash) < 160*1024, 'bytes='.strlen($dash));

hub_finish($checks);
