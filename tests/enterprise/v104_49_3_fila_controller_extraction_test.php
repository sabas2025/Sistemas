<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks = [];

/**
 * Fase 3 (decomposição do controller-deus, 2026-09-27) — etapa Fila.
 *
 * O FilaController já atendia fila/fila-reprocessar/fila-criar-teste/fila-morta-reprocessar por
 * delegação de fallback; agora é registrado no FastRouteDispatcherService::$dispatchGroups com as 6
 * rotas e recebe os 2 handlers que ainda viviam no DashboardController (A3-01): filaAnalyticsV24
 * (fila-analytics-v24) e filaMorta (fila-morta). Handlers movidos VERBATIM. Reprova sobre o antigo.
 *
 * Comportamento medido contra MariaDB provisionado por HTTP: fila/fila-morta/fila-analytics-v24 -> 200
 * autenticado; fila-reprocessar/fila-criar-teste/fila-morta-reprocessar (POST/CSRF) -> 403 no GET; sem fatal.
 */
$dash = hub_read('app/Controllers/DashboardController.php');
$fila = hub_read('app/Controllers/FilaController.php');
$disp = hub_read('app/Services/FastRouteDispatcherService.php');

$movidos = ['filaAnalyticsV24','filaMorta'];
$rotas   = ['fila','fila-reprocessar','fila-criar-teste','fila-morta-reprocessar','fila-analytics-v24','fila-morta'];

hub_check($checks, 'FilaController lido (N>0)', strlen($fila) > 800);
hub_check($checks, 'FilaController estende BaseModuleController', str_contains($fila, 'extends BaseModuleController'));
hub_check($checks, 'FilaController preserva index()/reprocessar()/mortaReprocessar()',
    str_contains($fila, 'function index(') && str_contains($fila, 'function reprocessar(') && str_contains($fila, 'function mortaReprocessar('));
foreach ($movidos as $m) {
    hub_check($checks, "FilaController recebeu o handler {$m}", str_contains($fila, "function {$m}("));
}
foreach (['fila-reprocessar','fila-criar-teste','fila-morta-reprocessar','fila-analytics-v24','fila-morta'] as $r) {
    hub_check($checks, "FilaController despacha {$r}", str_contains($fila, "case '{$r}':"));
}
// Preservação: guarda de id da fila morta (I-12) e CSRF nas ações.
hub_check($checks, 'FilaController preserva a guarda de id da fila morta (I-12)', str_contains($fila, "if(\$id<1) redirect") && str_contains($fila, 'catch(RuntimeException'));
hub_check($checks, 'FilaController preserva Csrf::validate', str_contains($fila, 'Csrf::validate'));

hub_check($checks, 'dispatchGroups registra FilaController', str_contains($disp, 'FilaController::class => ['));
foreach ($rotas as $r) {
    hub_check($checks, "rota {$r} mapeada a FilaController", (bool)preg_match('/FilaController::class => \[[^\]]*\''.preg_quote($r,'/').'\'/', $disp));
}

hub_check($checks, 'DashboardController lido (N>0)', strlen($dash) > 1000);
foreach ($movidos as $m) {
    hub_check($checks, "DashboardController não tem mais o handler {$m}", $dash !== '' && !str_contains($dash, "function {$m}("));
}
foreach ($rotas as $r) {
    hub_check($checks, "DashboardController não tem mais o case {$r}", !str_contains($dash, "case '{$r}'"));
}
hub_check($checks, 'DashboardController não delega mais (new FilaController())', !str_contains($dash, '(new FilaController())'));

hub_check($checks, 'DashboardController abaixo de 160 KB', strlen($dash) < 160*1024, 'bytes='.strlen($dash));

hub_finish($checks);
