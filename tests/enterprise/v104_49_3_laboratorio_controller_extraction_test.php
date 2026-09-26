<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks = [];

/**
 * Fase 3 (decomposição do controller-deus, 2026-09-26) — etapa Laboratório.
 *
 * A tela do Laboratório de Integração (laboratorio), o despachante por $tipo
 * (laboratorioExecutar → laboratorio-executar) e os quatro simuladores de fila (simular-baixa-tiny,
 * simular-produto-vsm, simular-estoque-vsm, simular-status-vsm) saíram do DashboardController
 * (A3-01) para o novo LaboratorioController, despachado pelo FastRouteDispatcherService::$dispatchGroups.
 * Reprova sobre o código antigo (os handlers estavam no Dashboard). O cluster ficou junto porque
 * laboratorioExecutar invoca os quatro simuladores por $this->.
 *
 * Comportamento medido contra MariaDB provisionado: laboratorio responde 200 autenticado sem erro no
 * corpo; laboratorio-executar e os simular-* (POST/CSRF) recusam GET com 403 (guarda de CSRF), sem fatal.
 */
$dash = hub_read('app/Controllers/DashboardController.php');
$lab  = hub_read('app/Controllers/LaboratorioController.php');
$disp = hub_read('app/Services/FastRouteDispatcherService.php');

$metodos = ['laboratorio','laboratorioExecutar','simularBaixaTiny','simularProdutoVsm','simularEstoqueVsm','simularStatusVsm'];
$rotas   = ['laboratorio','laboratorio-executar','simular-baixa-tiny','simular-produto-vsm','simular-estoque-vsm','simular-status-vsm'];

hub_check($checks, 'LaboratorioController lido (N>0)', strlen($lab) > 800);
hub_check($checks, 'LaboratorioController estende BaseModuleController', str_contains($lab, 'extends BaseModuleController'));
foreach ($metodos as $m) {
    hub_check($checks, "LaboratorioController tem o handler {$m}", str_contains($lab, "function {$m}("));
}
foreach (['laboratorio-executar','simular-baixa-tiny','simular-produto-vsm','simular-estoque-vsm','simular-status-vsm'] as $rota) {
    hub_check($checks, "LaboratorioController despacha {$rota}", str_contains($lab, "case '{$rota}':"));
}

hub_check($checks, 'dispatchGroups registra LaboratorioController', str_contains($disp, 'LaboratorioController::class => ['));
foreach ($rotas as $rota) {
    hub_check($checks, "rota {$rota} mapeada no dispatchGroups", (bool)preg_match('/LaboratorioController::class => \[[^\]]*\''.preg_quote($rota,'/').'\'/', $disp));
}

hub_check($checks, 'DashboardController lido (N>0)', strlen($dash) > 1000);
foreach ($metodos as $m) {
    hub_check($checks, "DashboardController não tem mais o handler {$m}", $dash !== '' && !str_contains($dash, "function {$m}("));
}
hub_check($checks, 'DashboardController não tem mais o case laboratorio-executar', !str_contains($dash, "case 'laboratorio-executar'"));
hub_check($checks, 'DashboardController não tem mais o case simular-baixa-tiny', !str_contains($dash, "case 'simular-baixa-tiny'"));

hub_check($checks, 'DashboardController abaixo de 160 KB', strlen($dash) < 160*1024, 'bytes='.strlen($dash));

hub_finish($checks);
