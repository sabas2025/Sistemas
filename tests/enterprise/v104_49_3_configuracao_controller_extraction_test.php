<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks = [];

/**
 * Fase 3 (decomposição do controller-deus, 2026-09-27) — etapa Configurações.
 *
 * A tela e o salvamento de Configurações, os fluxos ativos, as regras de sincronização e o
 * mapeamento de categorias saíram do DashboardController (A3-01) para o ConfiguracaoController
 * (antes um stub V42 que só lia o RouteModuleRegistry e nunca esteve no dispatchGroups), agora
 * despachado pelo FastRouteDispatcherService::$dispatchGroups. Reprova sobre o código antigo.
 * Vieram junto os 3 helpers PRIVADOS exclusivos do cluster (garantirEstruturaConfiguracoesVsm,
 * tinyV3OperationalReady, columnExistsForUpdate). PublicUrlService é estático e segue servindo o
 * callback OAuth que permanece no Dashboard.
 *
 * Comportamento medido contra MariaDB provisionado por HTTP: configuracoes/regras-sincronizacao/
 * categorias-mapeamento -> 200 autenticado; salvar-* (POST/CSRF) -> 403 no GET; sem fatal.
 */
$dash = hub_read('app/Controllers/DashboardController.php');
$cfg  = hub_read('app/Controllers/ConfiguracaoController.php');
$disp = hub_read('app/Services/FastRouteDispatcherService.php');

$metodos = ['configuracoes','salvarConfiguracoes','salvarFluxos','regrasSincronizacao','salvarRegrasSincronizacao','categoriasMapeamento','categoriaMapeamentoSalvar'];
$helpers = ['garantirEstruturaConfiguracoesVsm','tinyV3OperationalReady','columnExistsForUpdate'];
$rotas   = ['configuracoes','salvar-configuracoes','salvar-fluxos','regras-sincronizacao','salvar-regras-sincronizacao','categorias-mapeamento','categoria-mapeamento-salvar'];

hub_check($checks, 'ConfiguracaoController lido (N>0)', strlen($cfg) > 800);
hub_check($checks, 'ConfiguracaoController estende BaseModuleController', str_contains($cfg, 'extends BaseModuleController'));
hub_check($checks, 'ConfiguracaoController tem helper db()', str_contains($cfg, 'function db(string $table): PDO'));
foreach ($metodos as $m) {
    hub_check($checks, "ConfiguracaoController tem o handler {$m}", str_contains($cfg, "function {$m}("));
}
foreach ($helpers as $h) {
    hub_check($checks, "ConfiguracaoController trouxe o helper {$h}", str_contains($cfg, "function {$h}("));
}
foreach (['salvar-configuracoes','salvar-fluxos','regras-sincronizacao','salvar-regras-sincronizacao','categorias-mapeamento','categoria-mapeamento-salvar'] as $r) {
    hub_check($checks, "ConfiguracaoController despacha {$r}", str_contains($cfg, "case '{$r}':"));
}
// Preservação: mascaramento de segredos, allowlist Tiny e guardas VSM viajaram junto.
hub_check($checks, 'ConfiguracaoController preserva Csrf::validate', str_contains($cfg, 'Csrf::validate'));
hub_check($checks, 'ConfiguracaoController preserva Secrets::keepIfMasked', str_contains($cfg, 'Secrets::keepIfMasked'));
hub_check($checks, 'ConfiguracaoController preserva a allowlist de URL Tiny', str_contains($cfg, 'TinyEndpointSecurityService::validateUrl'));

hub_check($checks, 'dispatchGroups registra ConfiguracaoController', str_contains($disp, 'ConfiguracaoController::class => ['));
foreach ($rotas as $r) {
    hub_check($checks, "rota {$r} mapeada no dispatchGroups", (bool)preg_match('/ConfiguracaoController::class => \[[^\]]*\''.preg_quote($r,'/').'\'/', $disp));
}

hub_check($checks, 'DashboardController lido (N>0)', strlen($dash) > 1000);
foreach (array_merge($metodos,$helpers) as $m) {
    hub_check($checks, "DashboardController não tem mais {$m}", $dash !== '' && !str_contains($dash, "function {$m}("));
}
foreach ($rotas as $r) {
    hub_check($checks, "DashboardController não tem mais o case {$r}", !str_contains($dash, "case '{$r}'"));
}
// O callback OAuth (que fica) segue usando o PublicUrlService estático.
hub_check($checks, 'callback OAuth no Dashboard ainda usa PublicUrlService', str_contains($dash, 'PublicUrlService::tinyV3RedirectUri('));

hub_check($checks, 'DashboardController abaixo de 160 KB', strlen($dash) < 160*1024, 'bytes='.strlen($dash));

hub_finish($checks);
