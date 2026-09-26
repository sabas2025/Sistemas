<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks = [];

/**
 * F6-07 etapa 3 (2026-09-26) — VsmService fala o contrato real da VSM Conecta Venda.
 *
 * Trava as cinco correções da etapa: endpoint /v1/pedido/integradora, clientTokenLoja +
 * clientTokenIntegradora na QUERY, Bearer via VsmTokenService (JWT), URL mascarada no log/métrica
 * (os token não podem vazar), e 409 não-retentado (validação permanente na VSM).
 *
 * Parte COMPORTAMENTAL: a máscara de URL (redactUrlForLog) é pura e é exercida de verdade.
 * Reprova sobre o código antigo: default era /api/pedidos, Bearer estático, sem query, 409 retentado.
 */

require_once hub_root().'/app/Services/VsmEndpointSecurityService.php';
$svc = hub_read('app/Services/VsmService.php');
hub_check($checks, 'VsmService lido (N>0)', strlen($svc) > 1000);

// 1) endpoint real de cadastro de pedido
hub_check($checks, 'default do endpoint de pedido é /v1/pedido/integradora', str_contains($svc, "'/v1/pedido/integradora'"));
hub_check($checks, 'default legado /api/pedidos foi removido', !str_contains($svc, "'/api/pedidos'"));

// 2) os dois clientToken vão na QUERY do pedido
hub_check($checks, 'enviarPedido injeta clientTokenLoja na query', str_contains($svc, "\$query['clientTokenLoja']"));
hub_check($checks, 'enviarPedido injeta clientTokenIntegradora na query', str_contains($svc, "\$query['clientTokenIntegradora']"));
hub_check($checks, 'request aceita parâmetro de query', preg_match('/function request\([^)]*array \$query/', $svc) === 1);

// 3) Bearer via JWT do VsmTokenService, com fallback legado
hub_check($checks, 'resolveBearer usa VsmTokenService::accessToken', str_contains($svc, 'VsmTokenService::accessToken'));
hub_check($checks, 'resolveBearer cai no token legado (vsm_token) como fallback', str_contains($svc, 'return $this->token'));

// 4) log/métrica com URL mascarada (nunca token em claro)
hub_check($checks, 'usa redactUrlForLog para a URL de log', str_contains($svc, 'redactUrlForLog'));
hub_check($checks, 'Audit/JsonLogger recebem $urlLog, não a URL crua', str_contains($svc, "'url'=>\$urlLog"));
hub_check($checks, 'MetricsService recebe a URL mascarada', str_contains($svc, "MetricsService::registrar('vsm',\$urlLog"));
hub_check($checks, 'a URL crua ($url) não é mais logada nem medida', !preg_match("/'url'=>\\\$url\b/", $svc) && !str_contains($svc, "registrar('vsm',\$url,"));

// 5) 409 não é retentado na VSM
hub_check($checks, '409 excluído do retry (validação permanente)', str_contains($svc, '$http !== 409'));

// COMPORTAMENTAL: a máscara realmente esconde os valores de token na query
$base = 'https://conectavenda.stage.vsm.com.br';
$url = $base.'/v1/pedido/integradora?'.http_build_query(['clientTokenLoja'=>'LOJA-SECRET-1','clientTokenIntegradora'=>'INTEG-SECRET-2']);
$log = VsmEndpointSecurityService::redactUrlForLog($base, substr($url, strlen($base)));
hub_check($checks, 'redactUrlForLog mascara os valores dos token', strpos($log,'LOJA-SECRET')===false && strpos($log,'INTEG-SECRET')===false);
hub_check($checks, 'redactUrlForLog preserva os nomes dos parâmetros', str_contains($log,'clientTokenLoja='));

hub_finish($checks);
