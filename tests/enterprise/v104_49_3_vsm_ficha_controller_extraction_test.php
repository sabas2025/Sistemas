<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks = [];

/**
 * Fase 3 (decomposição do controller-deus, 2026-09-26) — etapa VSM (ficha/teste).
 *
 * Os handlers testarVsm (rota testar-vsm) e vsmFichaTecnica (rota vsm-ficha-tecnica) saíram do
 * DashboardController (A3-01) para o VsmController já existente, despachados pelo
 * FastRouteDispatcherService::$dispatchGroups. Reprova sobre o código antigo (estavam no Dashboard).
 *
 * Escopo: só ficha/teste. Os simular-* ficaram no DashboardController porque laboratorioExecutar
 * (rota laboratorio-executar, que permanece) os invoca — são um cluster "Laboratório" à parte.
 *
 * Comportamento medido contra MariaDB provisionado: vsm-ficha-tecnica responde 200 autenticado sem
 * erro no corpo; testar-vsm (POST/CSRF) recusa GET com 403 (guarda de CSRF), sem fatal.
 */
$dash = hub_read('app/Controllers/DashboardController.php');
$vsm  = hub_read('app/Controllers/VsmController.php');
$disp = hub_read('app/Services/FastRouteDispatcherService.php');

hub_check($checks, 'VsmController lido (N>0)', strlen($vsm) > 800);
hub_check($checks, 'VsmController tem o handler testarVsm', str_contains($vsm, 'function testarVsm('));
hub_check($checks, 'VsmController tem o handler vsmFichaTecnica', str_contains($vsm, 'function vsmFichaTecnica('));
hub_check($checks, 'VsmController despacha testar-vsm', str_contains($vsm, "case 'testar-vsm':"));
hub_check($checks, 'VsmController despacha vsm-ficha-tecnica', str_contains($vsm, "case 'vsm-ficha-tecnica':"));

hub_check($checks, 'dispatchGroups roteia testar-vsm para VsmController', (bool)preg_match('/VsmController::class => \[[^\]]*\'testar-vsm\'/', $disp));
hub_check($checks, 'dispatchGroups roteia vsm-ficha-tecnica para VsmController', (bool)preg_match('/VsmController::class => \[[^\]]*\'vsm-ficha-tecnica\'/', $disp));

hub_check($checks, 'DashboardController lido (N>0)', strlen($dash) > 1000);
hub_check($checks, 'DashboardController não tem mais testarVsm', $dash !== '' && !str_contains($dash, 'function testarVsm('));
hub_check($checks, 'DashboardController não tem mais vsmFichaTecnica', $dash !== '' && !str_contains($dash, 'function vsmFichaTecnica('));
hub_check($checks, 'DashboardController não tem mais o case testar-vsm', !str_contains($dash, "case 'testar-vsm'"));
hub_check($checks, 'DashboardController não tem mais o case vsm-ficha-tecnica', !str_contains($dash, "case 'vsm-ficha-tecnica'"));

// Os simular-* ficaram FORA do escopo desta etapa (VSM ficha): eram um cluster "Laboratório" à parte,
// acoplado ao laboratorioExecutar. Na etapa Laboratório (2026-09-26) esse cluster saiu do Dashboard
// para o LaboratorioController — a asserção acompanha o dono atual, preservando a intenção original
// (a etapa VSM não os tocou).
$lab = hub_read('app/Controllers/LaboratorioController.php');
hub_check($checks, 'simular*/laboratorioExecutar vivem no LaboratorioController (não no Dashboard)',
    str_contains($lab, 'function simularBaixaTiny(') && str_contains($lab, 'function laboratorioExecutar(')
    && !str_contains($dash, 'function simularBaixaTiny(') && !str_contains($dash, 'function laboratorioExecutar('));

hub_check($checks, 'DashboardController abaixo de 160 KB', strlen($dash) < 160*1024, 'bytes='.strlen($dash));

hub_finish($checks);
