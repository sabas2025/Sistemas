<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks = [];

/**
 * Fase 3 (decomposição do controller-deus, 2026-09-26) — etapa Tiny.
 *
 * Os handlers Tiny (V2/V3, webhooks, teste real, tokens) saíram do DashboardController (A3-01) para
 * o TinyController, despachados pelo FastRouteDispatcherService::$dispatchGroups. Os helpers de URL
 * pública (Redirect URI do Tiny V3) foram centralizados em PublicUrlService, compartilhado com o
 * DashboardController (que ainda os usa no formulário de Configurações e no callback OAuth).
 * Reprova sobre o código antigo (TinyController era stub; PublicUrlService não existia).
 *
 * Comportamento medido contra MariaDB provisionado: tiny-v3-ficha, tiny-v2-ficha-tecnica,
 * tiny-webhooks, teste-real-tiny e tiny-ambientes respondem HTTP 200 autenticado, sem erro no corpo.
 */
$dash = hub_read('app/Controllers/DashboardController.php');
$tiny = hub_read('app/Controllers/TinyController.php');
$disp = hub_read('app/Services/FastRouteDispatcherService.php');
$url  = hub_read('app/Services/PublicUrlService.php');

$metodos = ['testarTiny','testeRealTiny','testeRealTinyExecutar','tinyWebhooks','tinyV3Ficha','tinyV2FichaTecnica','tinyV3TokenSalvar','tinyV3TokenRenovar','tinyV3TokenRevogar','tinyV3Testar','tinyV3TestarModulo','tinyV3EndpointsSalvar','tinyAmbientes'];
$urlHelpers = ['normalizeTinyV3RedirectUri','absolutePublicBaseUrl','safeHostForUrls','appBaseUrl'];

// 1) TinyController real, com os handlers
hub_check($checks, 'TinyController lido (N>0)', strlen($tiny) > 1000);
hub_check($checks, 'TinyController estende BaseModuleController', str_contains($tiny, 'extends BaseModuleController'));
foreach ($metodos as $m) {
    hub_check($checks, "TinyController tem o handler {$m}", str_contains($tiny, "function {$m}("));
}

// 2) Rotas Tiny no dispatchGroups → TinyController
hub_check($checks, 'dispatchGroups registra TinyController', str_contains($disp, 'TinyController::class => ['));
foreach (['testar-tiny','teste-real-tiny','tiny-webhooks','tiny-v3-ficha','tiny-v2-ficha-tecnica','tiny-v3-token-salvar','tiny-v3-testar','tiny-v3-endpoints-salvar','tiny-ambientes'] as $rota) {
    hub_check($checks, "rota {$rota} mapeada no dispatchGroups", (bool)preg_match('/TinyController::class => \[[^\]]*\''.preg_quote($rota,'/').'\'/', $disp));
}

// 3) PublicUrlService com os 4 helpers estáticos
hub_check($checks, 'PublicUrlService lido (N>0)', strlen($url) > 300);
foreach (['safeHost','baseUrl','appBaseUrl','tinyV3RedirectUri'] as $m) {
    hub_check($checks, "PublicUrlService::{$m} existe", str_contains($url, "function {$m}("));
}

// 4) DashboardController não define mais os handlers Tiny nem os 4 helpers de URL
hub_check($checks, 'DashboardController lido (N>0)', strlen($dash) > 1000);
foreach ($metodos as $m) {
    hub_check($checks, "DashboardController não tem mais o handler {$m}", $dash !== '' && !str_contains($dash, "function {$m}("));
}
foreach ($urlHelpers as $h) {
    hub_check($checks, "DashboardController não define mais {$h}", $dash !== '' && !str_contains($dash, "function {$h}("));
}
// 5) Dashboard passou a chamar o helper compartilhado
hub_check($checks, 'DashboardController usa PublicUrlService::tinyV3RedirectUri', str_contains($dash, 'PublicUrlService::tinyV3RedirectUri('));
hub_check($checks, 'DashboardController usa PublicUrlService::appBaseUrl', str_contains($dash, 'PublicUrlService::appBaseUrl('));
// nenhum $this-><helper de url> remanescente
foreach ($urlHelpers as $h) {
    hub_check($checks, "DashboardController não chama mais \$this->{$h}", $dash !== '' && !str_contains($dash, '$this->'.$h.'('));
}

// 6) Teto recuperado
hub_check($checks, 'DashboardController abaixo de 160 KB com folga', strlen($dash) < 160*1024, 'bytes='.strlen($dash));

hub_finish($checks);
