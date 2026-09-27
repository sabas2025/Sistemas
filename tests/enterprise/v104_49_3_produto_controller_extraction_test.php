<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks = [];

/**
 * Fase 3 (decomposição do controller-deus, 2026-09-27) — etapa Produtos.
 *
 * O ProdutoController já atendia produtos e produtos-pendencias por delegação de fallback; agora é
 * registrado no FastRouteDispatcherService::$dispatchGroups e recebe os 5 handlers de produtos que
 * ainda viviam no DashboardController (A3-01): produtosVsm (produtos-vsm), produtosPendentesIntegracao
 * (produtos-pendentes-integracao), produtoPendenteIntegracaoComparar/Acao (produto-pendente-integracao-*)
 * e produtoPendenciaAcao (produto-pendencia-acao). Handlers movidos VERBATIM. Reprova sobre o antigo.
 *
 * Comportamento medido contra MariaDB provisionado por HTTP: produtos/produtos-vsm/produtos-pendencias/
 * produtos-pendentes-integracao -> 200 autenticado; as ações (POST/CSRF) -> 403 no GET; sem fatal.
 */
$dash = hub_read('app/Controllers/DashboardController.php');
$prod = hub_read('app/Controllers/ProdutoController.php');
$disp = hub_read('app/Services/FastRouteDispatcherService.php');

$movidos = ['produtosVsm','produtosPendentesIntegracao','produtoPendenteIntegracaoComparar','produtoPendenteIntegracaoAcao','produtoPendenciaAcao'];
$rotas   = ['produtos','produtos-pendencias','produtos-vsm','produtos-pendentes-integracao','produto-pendente-integracao-comparar','produto-pendente-integracao-acao','produto-pendencia-acao'];

hub_check($checks, 'ProdutoController lido (N>0)', strlen($prod) > 800);
hub_check($checks, 'ProdutoController estende BaseModuleController', str_contains($prod, 'extends BaseModuleController'));
hub_check($checks, 'ProdutoController preserva index()/pendencias()', str_contains($prod, 'function index(') && str_contains($prod, 'function pendencias('));
foreach ($movidos as $m) {
    hub_check($checks, "ProdutoController recebeu o handler {$m}", str_contains($prod, "function {$m}("));
}
foreach (['produtos-pendencias','produtos-vsm','produtos-pendentes-integracao','produto-pendente-integracao-comparar','produto-pendente-integracao-acao','produto-pendencia-acao'] as $r) {
    hub_check($checks, "ProdutoController despacha {$r}", str_contains($prod, "case '{$r}':"));
}
// Preservação: CSRF nas ações e o vínculo por SKU (fluxo Produtos VSM→Tiny).
hub_check($checks, 'ProdutoController preserva Csrf::validate nas ações', str_contains($prod, 'Csrf::validate'));
hub_check($checks, 'ProdutoController preserva o vínculo por SKU (produtos_mapeamento)', str_contains($prod, 'produtos_mapeamento'));

hub_check($checks, 'dispatchGroups registra ProdutoController', str_contains($disp, 'ProdutoController::class => ['));
foreach ($rotas as $r) {
    hub_check($checks, "rota {$r} mapeada a ProdutoController", (bool)preg_match('/ProdutoController::class => \[[^\]]*\''.preg_quote($r,'/').'\'/', $disp));
}

hub_check($checks, 'DashboardController lido (N>0)', strlen($dash) > 1000);
foreach ($movidos as $m) {
    hub_check($checks, "DashboardController não tem mais o handler {$m}", $dash !== '' && !str_contains($dash, "function {$m}("));
}
foreach ($rotas as $r) {
    hub_check($checks, "DashboardController não tem mais o case {$r}", !str_contains($dash, "case '{$r}'"));
}
hub_check($checks, 'DashboardController não delega mais (new ProdutoController())', !str_contains($dash, '(new ProdutoController())'));

hub_check($checks, 'DashboardController abaixo de 160 KB', strlen($dash) < 160*1024, 'bytes='.strlen($dash));

hub_finish($checks);
