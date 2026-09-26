<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks = [];

/**
 * Fase 3 (decomposição do controller-deus, 2026-09-26) — etapa Segurança.
 *
 * O bloco de Segurança saiu do DashboardController (achado A3-01) para o SecurityController,
 * despachado pelo FastRouteDispatcherService::$dispatchGroups. Este teste trava a extração e
 * reprova sobre o código antigo (onde os métodos viviam no DashboardController).
 *
 * Comportamento em runtime medido contra MariaDB provisionado: as 8 rotas de segurança
 * (security-center, -fim, -score, seguranca-auditoria, -extrema, -audit-signatures, -events,
 * -pentest) respondem HTTP 200 autenticado, sem erro no corpo. Não reproduzível em teste estático.
 */
$dash = hub_read('app/Controllers/DashboardController.php');
$sec  = hub_read('app/Controllers/SecurityController.php');
$disp = hub_read('app/Services/FastRouteDispatcherService.php');

$metodos = ['securityCenter','securityFim','securityFimGerar','securityScore','securityAuditSign','segurancaAuditoria','segurancaExtrema'];

// 1) SecurityController existe, é módulo, e tem os handlers
hub_check($checks, 'SecurityController existe (N>0)', strlen($sec) > 500);
hub_check($checks, 'SecurityController estende BaseModuleController', str_contains($sec, 'extends BaseModuleController'));
foreach ($metodos as $m) {
    hub_check($checks, "SecurityController tem o handler {$m}", str_contains($sec, "function {$m}("));
}

// 2) As rotas de segurança estão no dispatchGroups apontando para SecurityController
hub_check($checks, 'dispatchGroups registra SecurityController', str_contains($disp, 'SecurityController::class => ['));
foreach (['security-center','security-fim','security-fim-gerar','security-score','security-audit-sign','seguranca-auditoria','seguranca-extrema','security-pentest'] as $rota) {
    hub_check($checks, "rota {$rota} mapeada no dispatchGroups", (bool)preg_match('/SecurityController::class => \[[^\]]*\''.preg_quote($rota, '/').'\'/', $disp));
}

// 3) DashboardController NÃO define mais os handlers de segurança (guardado por N>0)
hub_check($checks, 'DashboardController lido (N>0)', strlen($dash) > 1000);
foreach ($metodos as $m) {
    hub_check($checks, "DashboardController não tem mais o handler {$m}", $dash !== '' && !str_contains($dash, "function {$m}("));
}
// e nenhum case de segurança inline sobrou
hub_check($checks, 'DashboardController não tem mais o case security-center', !str_contains($dash, "case 'security-center'"));

// 4) O objetivo do teto foi atendido: folga recuperada
hub_check($checks, 'DashboardController abaixo de 160 KB com folga', strlen($dash) < 160*1024, 'bytes='.strlen($dash));

hub_finish($checks);
