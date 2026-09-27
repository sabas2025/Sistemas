<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks = [];

/**
 * Fase 3 (decomposição do controller-deus, 2026-09-26) — achado A3-02: remoção de código morto de Backup.
 *
 * O BackupController já atende as 6 rotas de backup e está registrado no
 * FastRouteDispatcherService::$dispatchGroups. Como o dispatch() resolve o dispatchGroups ANTES do
 * fallback (new DashboardController())->dispatch(), os handlers de backup que restavam no
 * DashboardController (backup, backups, backupDownload, backupExcluir, backupImportar,
 * backupRestaurar) e seus 6 cases eram INALCANÇÁVEIS — código morto. Foram removidos; nenhuma rota
 * muda de comportamento (continua no BackupController). Reprova sobre o código antigo (o Dashboard
 * ainda tinha os handlers/cases).
 */
$dash   = hub_read('app/Controllers/DashboardController.php');
$backup = hub_read('app/Controllers/BackupController.php');
$disp   = hub_read('app/Services/FastRouteDispatcherService.php');

$rotas   = ['backup','backups','backup-download','backup-excluir','backup-importar','backup-restaurar'];
$metodos = ['backup','backups','backupDownload','backupExcluir','backupImportar','backupRestaurar'];

// O BackupController continua sendo o dono vivo das rotas.
hub_check($checks, 'BackupController lido (N>0)', strlen($backup) > 800);
hub_check($checks, 'BackupController estende BaseModuleController', str_contains($backup, 'extends BaseModuleController'));
hub_check($checks, 'dispatchGroups registra BackupController', str_contains($disp, 'BackupController::class => ['));
foreach ($rotas as $rota) {
    hub_check($checks, "rota {$rota} mapeada para BackupController", (bool)preg_match('/BackupController::class => \[[^\]]*\''.preg_quote($rota,'/').'\'/', $disp));
}

// O DashboardController não carrega mais o código morto.
hub_check($checks, 'DashboardController lido (N>0)', strlen($dash) > 1000);
foreach ($metodos as $m) {
    hub_check($checks, "DashboardController não tem mais o handler {$m}", $dash !== '' && !str_contains($dash, "function {$m}("));
}
foreach ($rotas as $rota) {
    hub_check($checks, "DashboardController não tem mais o case {$rota}", !str_contains($dash, "case '{$rota}'"));
}

hub_check($checks, 'DashboardController abaixo de 160 KB', strlen($dash) < 160*1024, 'bytes='.strlen($dash));

hub_finish($checks);
