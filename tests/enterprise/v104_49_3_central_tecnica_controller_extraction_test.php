<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks = [];

/**
 * Fase 3 (decomposição do controller-deus, 2026-09-27) — etapa Central Técnica / Prod-Ready.
 *
 * A Central Técnica, o diagnóstico, a integridade do dashboard (tela + executar), o menu de testes,
 * a ficha técnica 100%, o guia de hospedagem InfinityFree e os painéis Production-Ready
 * (producao-ready, V24/V25/V26 e o relatório de prontidão) saíram do DashboardController (A3-01)
 * para o CentralTecnicaController — que era um stub V42 e agora recebe a implementação real,
 * despachado pelo FastRouteDispatcherService::$dispatchGroups. Reprova sobre o código antigo.
 *
 * Comportamento medido contra MariaDB provisionado por HTTP: central-tecnica/diagnostico/
 * dashboard-integridade/menu-testes/ficha-tecnica-100/hosting-infinityfree/producao-ready/
 * production-ready-v24..v26/relatorio-prontidao-producao -> 200; dashboard-integridade-executar
 * (POST/CSRF) -> 403 no GET; sem fatal.
 */
$dash = hub_read('app/Controllers/DashboardController.php');
$ct   = hub_read('app/Controllers/CentralTecnicaController.php');
$disp = hub_read('app/Services/FastRouteDispatcherService.php');

$metodos = ['centralTecnica','diagnostico','dashboardIntegridade','dashboardIntegridadeExecutar','menuTestes','fichaTecnica100','hostingInfinityFree','producaoReady','productionReadyV24','productionReadyV25','productionReadyV26','relatorioProntidaoProducao'];
$rotas   = ['central-tecnica','diagnostico','dashboard-integridade','dashboard-integridade-executar','menu-testes','ficha-tecnica-100','hosting-infinityfree','producao-ready','production-ready-v24','production-ready-v25','production-ready-v26','relatorio-prontidao-producao'];

hub_check($checks, 'CentralTecnicaController lido (N>0)', strlen($ct) > 800);
hub_check($checks, 'CentralTecnicaController tem dispatch()', str_contains($ct, 'function dispatch(string $page)'));
foreach ($metodos as $m) {
    hub_check($checks, "CentralTecnicaController recebeu o handler {$m}", str_contains($ct, "function {$m}("));
}
foreach (array_slice($rotas,1) as $r) { // todas menos central-tecnica (é o default)
    hub_check($checks, "CentralTecnicaController despacha {$r}", str_contains($ct, "case '{$r}':"));
}

hub_check($checks, 'dispatchGroups registra CentralTecnicaController', str_contains($disp, 'CentralTecnicaController::class => ['));
foreach ($rotas as $r) {
    hub_check($checks, "rota {$r} mapeada a CentralTecnicaController", (bool)preg_match('/CentralTecnicaController::class => \[[^\]]*\''.preg_quote($r,'/').'\'/', $disp));
}

hub_check($checks, 'DashboardController lido (N>0)', strlen($dash) > 1000);
foreach ($metodos as $m) {
    hub_check($checks, "DashboardController não tem mais o handler {$m}", $dash !== '' && !str_contains($dash, "function {$m}("));
}
foreach ($rotas as $r) {
    hub_check($checks, "DashboardController não tem mais o case {$r}", !str_contains($dash, "case '{$r}'"));
}

hub_check($checks, 'DashboardController abaixo de 160 KB', strlen($dash) < 160*1024, 'bytes='.strlen($dash));

hub_finish($checks);
