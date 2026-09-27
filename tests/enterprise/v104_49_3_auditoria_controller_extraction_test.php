<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks = [];

/**
 * Fase 3 (decomposição do controller-deus, 2026-09-27) — etapa Auditoria.
 *
 * A trilha (auditoria), a auditoria de código, a cadeia de hash, a assinatura de trace e as
 * exportações (PDF/enterprise) saíram do DashboardController (A3-01) para o AuditoriaController,
 * que já atendia auditoria-detalhe por delegação de fallback e agora é registrado no
 * FastRouteDispatcherService::$dispatchGroups. Reprova sobre o código antigo. csvSafeRow foi
 * DUPLICADO no AuditoriaController (a exportação enterprise usa) — o DashboardController mantém a
 * sua cópia porque logsExportar ainda a usa.
 *
 * Comportamento medido contra MariaDB provisionado por HTTP: auditoria -> 200 autenticado;
 * auditoria-codigo -> 200; auditoria-assinar-trace/auditoria-exportar-* (POST/CSRF) -> 403 no GET; sem fatal.
 */
$dash = hub_read('app/Controllers/DashboardController.php');
$aud  = hub_read('app/Controllers/AuditoriaController.php');
$disp = hub_read('app/Services/FastRouteDispatcherService.php');

$metodos = ['auditoria','auditoriaCodigo','auditoriaHashChain','auditoriaAssinarTrace','auditoriaExportarPdf','auditoriaExportarEnterprise'];
$rotas   = ['auditoria','auditoria-detalhe','auditoria-codigo','auditoria-hash-chain','auditoria-assinar-trace','auditoria-exportar-pdf','auditoria-exportar-enterprise'];

hub_check($checks, 'AuditoriaController lido (N>0)', strlen($aud) > 800);
hub_check($checks, 'AuditoriaController preserva detalhe() (auditoria-detalhe)', str_contains($aud, 'function detalhe('));
foreach ($metodos as $m) {
    hub_check($checks, "AuditoriaController recebeu o handler {$m}", str_contains($aud, "function {$m}("));
}
hub_check($checks, 'AuditoriaController duplicou csvSafeRow (exportação enterprise usa)', str_contains($aud, 'function csvSafeRow('));
foreach (['auditoria','auditoria-codigo','auditoria-hash-chain','auditoria-assinar-trace','auditoria-exportar-pdf','auditoria-exportar-enterprise'] as $r) {
    hub_check($checks, "AuditoriaController despacha {$r}", str_contains($aud, "case '{$r}':"));
}

hub_check($checks, 'dispatchGroups registra AuditoriaController', str_contains($disp, 'AuditoriaController::class => ['));
foreach ($rotas as $r) {
    hub_check($checks, "rota {$r} mapeada a AuditoriaController", (bool)preg_match('/AuditoriaController::class => \[[^\]]*\''.preg_quote($r,'/').'\'/', $disp));
}

hub_check($checks, 'DashboardController lido (N>0)', strlen($dash) > 1000);
foreach ($metodos as $m) {
    hub_check($checks, "DashboardController não tem mais o handler {$m}", $dash !== '' && !str_contains($dash, "function {$m}("));
}
foreach ($rotas as $r) {
    hub_check($checks, "DashboardController não tem mais o case {$r}", !str_contains($dash, "case '{$r}'"));
}
// csvSafeRow FICA no Dashboard (logsExportar ainda usa) — não é órfão.
hub_check($checks, 'DashboardController mantém csvSafeRow (logsExportar usa)', str_contains($dash, 'function csvSafeRow('));
hub_check($checks, 'logsExportar (que usa csvSafeRow) permanece no Dashboard', str_contains($dash, 'function logsExportar('));

hub_check($checks, 'DashboardController abaixo de 160 KB', strlen($dash) < 160*1024, 'bytes='.strlen($dash));

hub_finish($checks);
