<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks = [];

/**
 * Fase 3 (decomposição do controller-deus, 2026-09-27) — etapa Migração/Bancos (última do plano).
 *
 * O atualizador seguro (atualizador-seguro, -executar) e os bancos/módulos (bancos-modulos,
 * bancos-modulos-instalar) saíram do DashboardController (A3-01) para o DatabaseMaintenanceController,
 * que já era o dono do domínio de banco (validar-banco, health-modulos, mapa-banco) e está no
 * FastRouteDispatcherService::$dispatchGroups. Handlers movidos VERBATIM. Reprova sobre o antigo.
 *
 * Comportamento medido contra MariaDB provisionado por HTTP: bancos-modulos/atualizador-seguro -> 200
 * autenticado; bancos-modulos-instalar/atualizador-seguro-executar (POST/CSRF) -> 403 no GET; sem fatal.
 */
$dash = hub_read('app/Controllers/DashboardController.php');
$db   = hub_read('app/Controllers/DatabaseMaintenanceController.php');
$disp = hub_read('app/Services/FastRouteDispatcherService.php');

$movidos = ['bancosModulos','bancosModulosInstalar','atualizadorSeguro','atualizadorSeguroExecutar'];
$rotasNovas = ['bancos-modulos','bancos-modulos-instalar','atualizador-seguro','atualizador-seguro-executar'];
$rotasTodas = array_merge(['validar-banco','health-modulos','mapa-banco'], $rotasNovas);

hub_check($checks, 'DatabaseMaintenanceController lido (N>0)', strlen($db) > 800);
hub_check($checks, 'DatabaseMaintenanceController preserva validarBanco/healthModulos/mapaBanco',
    str_contains($db, 'function validarBanco(') && str_contains($db, 'function healthModulos(') && str_contains($db, 'function mapaBanco('));
foreach ($movidos as $m) {
    hub_check($checks, "DatabaseMaintenanceController recebeu o handler {$m}", str_contains($db, "function {$m}("));
}
foreach ($rotasNovas as $r) {
    hub_check($checks, "DatabaseMaintenanceController despacha {$r}", str_contains($db, "\$page === '{$r}'"));
}
// O atualizador seguro chama o serviço correto (achado I-11) — segue no novo dono.
hub_check($checks, 'atualizadorSeguroExecutar usa UniversalUpgradeService(...)->run()', str_contains($db, 'UniversalUpgradeService(dirname(__DIR__,2)))->run()'));

hub_check($checks, 'dispatchGroups registra as rotas no DatabaseMaintenanceController', str_contains($disp, 'DatabaseMaintenanceController::class => ['));
foreach ($rotasTodas as $r) {
    hub_check($checks, "rota {$r} mapeada a DatabaseMaintenanceController", (bool)preg_match('/DatabaseMaintenanceController::class => \[[^\]]*\''.preg_quote($r,'/').'\'/', $disp));
}

hub_check($checks, 'DashboardController lido (N>0)', strlen($dash) > 1000);
foreach ($movidos as $m) {
    hub_check($checks, "DashboardController não tem mais o handler {$m}", $dash !== '' && !str_contains($dash, "function {$m}("));
}
foreach ($rotasNovas as $r) {
    hub_check($checks, "DashboardController não tem mais o case {$r}", !str_contains($dash, "case '{$r}'"));
}

hub_check($checks, 'DashboardController abaixo de 160 KB', strlen($dash) < 160*1024, 'bytes='.strlen($dash));

hub_finish($checks);
