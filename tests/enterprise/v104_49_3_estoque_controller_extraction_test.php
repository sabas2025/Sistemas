<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks = [];

/**
 * Fase 3 (decomposição do controller-deus, 2026-09-27) — etapa Estoque.
 *
 * Duas partes: (1) as 11 rotas de estoque que o DashboardController já DELEGAVA por fallback
 * ((new EstoqueController())->dispatch()) passaram a ser roteadas direto pelo
 * FastRouteDispatcherService::$dispatchGroups (delegações redundantes removidas); (2) os 3 handlers
 * que ainda viviam no DashboardController — baixasEstoque (baixas-estoque), reconciliacao e
 * reconciliacaoExecutar (reconciliacao, reconciliacao-executar) — foram movidos VERBATIM para o
 * EstoqueController, consolidando o domínio de estoque. Reprova sobre o código antigo.
 *
 * Comportamento medido contra MariaDB provisionado por HTTP: estoque-dashboard/baixas-estoque/
 * reconciliacao -> 200 autenticado; reconciliacao-executar (POST/CSRF) -> 403 no GET; sem fatal.
 */
$dash = hub_read('app/Controllers/DashboardController.php');
$est  = hub_read('app/Controllers/EstoqueController.php');
$disp = hub_read('app/Services/FastRouteDispatcherService.php');

$rotas = ['estoque-dashboard','estoque-config','estoque-config-salvar','estoque-alertas','estoque-sku-historico','estoque-consultas-vsm','estoque-consulta-vsm-resultados','estoque-consulta-vsm-testar-sku','estoque-reconciliar-agora','estoque-consulta-vsm-executar','estoque-consulta-tiny-executar','baixas-estoque','reconciliacao','reconciliacao-executar'];
$movidos = ['baixasEstoque','reconciliacao','reconciliacaoExecutar'];

hub_check($checks, 'dispatchGroups registra EstoqueController', str_contains($disp, 'EstoqueController::class => ['));
foreach ($rotas as $r) {
    hub_check($checks, "rota {$r} mapeada a EstoqueController no dispatchGroups", (bool)preg_match('/EstoqueController::class => \[[^\]]*\''.preg_quote($r,'/').'\'/', $disp));
}

hub_check($checks, 'EstoqueController lido (N>0)', strlen($est) > 800);
foreach ($movidos as $m) {
    hub_check($checks, "EstoqueController recebeu o handler {$m}", str_contains($est, "function {$m}("));
}
foreach (['baixas-estoque','reconciliacao','reconciliacao-executar'] as $r) {
    hub_check($checks, "EstoqueController despacha {$r}", str_contains($est, "case '{$r}':"));
}
// Preservação: a degradação elegante do L01 e o CSRF viajaram junto.
hub_check($checks, 'EstoqueController preserva o aviso amigável do L01', str_contains($est, 'provedor_indisponivel'));
hub_check($checks, 'EstoqueController preserva Csrf::validate na reconciliação', str_contains($est, 'Csrf::validate'));

hub_check($checks, 'DashboardController lido (N>0)', strlen($dash) > 1000);
foreach ($movidos as $m) {
    hub_check($checks, "DashboardController não tem mais o handler {$m}", $dash !== '' && !str_contains($dash, "function {$m}("));
}
// As delegações redundantes e os cases de handler saíram do Dashboard.
foreach (['estoque-dashboard','estoque-consulta-vsm-executar','baixas-estoque','reconciliacao','reconciliacao-executar'] as $r) {
    hub_check($checks, "DashboardController não tem mais o case {$r}", !str_contains($dash, "case '{$r}'"));
}
hub_check($checks, 'DashboardController não delega mais (new EstoqueController())', !str_contains($dash, '(new EstoqueController())'));

hub_check($checks, 'DashboardController abaixo de 160 KB', strlen($dash) < 160*1024, 'bytes='.strlen($dash));

hub_finish($checks);
