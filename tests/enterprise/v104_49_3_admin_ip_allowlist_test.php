<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks=[];

/**
 * Reauditoria 2026-09-14: security.admin_ip_allowlist estava documentado em
 * config.example.php e no install.php mas nunca era lido em nenhum lugar do código -
 * a restrição de painel por IP não existia de fato. Este teste cobre a implementação
 * feita em AdminIpAllowlistService: casamento de IP exato, CIDR v4/v6 e as rotas que
 * devem ficar sempre fora da restrição (site comercial público e webhooks Tiny/VSM).
 */
require_once hub_root().'/app/Services/RouteCatalogService.php';
require_once hub_root().'/app/Services/AdminIpAllowlistService.php';

$ref = new ReflectionClass('AdminIpAllowlistService');
$matches = $ref->getMethod('matches'); $matches->setAccessible(true);
$callMatches = static fn(string $ip, array $list): bool => $matches->invoke(null, $ip, $list);

hub_check($checks,'IP exato na lista é aceito',$callMatches('177.10.10.10',['177.10.10.10']));
hub_check($checks,'IP fora da lista é rejeitado',!$callMatches('177.10.10.99',['177.10.10.10']));
hub_check($checks,'CIDR /24 aceita IP dentro da faixa',$callMatches('177.10.10.55',['177.10.10.0/24']));
hub_check($checks,'CIDR /24 rejeita IP fora da faixa',!$callMatches('177.10.11.1',['177.10.10.0/24']));
hub_check($checks,'CIDR /32 se comporta como IP exato',$callMatches('10.0.0.5',['10.0.0.5/32']) && !$callMatches('10.0.0.6',['10.0.0.5/32']));
hub_check($checks,'CIDR /0 aceita qualquer IPv4',$callMatches('1.2.3.4',['0.0.0.0/0']));
hub_check($checks,'CIDR IPv6 aceita IP dentro da faixa',$callMatches('2001:db8::1',['2001:db8::/32']));
hub_check($checks,'CIDR IPv6 rejeita IP fora da faixa',!$callMatches('2001:db9::1',['2001:db8::/32']));
hub_check($checks,'Entrada CIDR malformada não derruba a checagem',!$callMatches('10.0.0.1',['10.0.0.0/xyz']));
hub_check($checks,'Lista com múltiplas entradas casa qualquer uma delas',$callMatches('192.168.1.1',['10.0.0.1','192.168.1.0/24']));

// isAllowed() é a decisão pura usada por enforceForRequest() (que só adiciona I/O:
// http_response_code/header/exit). Testar por aqui evita depender de SAPI/exit.
$secComAllowlist = ['admin_ip_allowlist' => '203.0.113.9'];
$ipForaDaLista = '198.51.100.1';

hub_check($checks,'Allowlist vazia não restringe nada (comportamento padrão/retrocompatível)',AdminIpAllowlistService::isAllowed('login',$ipForaDaLista,['admin_ip_allowlist'=>'']));
hub_check($checks,'Webhook VSM não é bloqueado mesmo com IP fora da allowlist',AdminIpAllowlistService::isAllowed('api/webhook/vsm/pedido',$ipForaDaLista,$secComAllowlist));
hub_check($checks,'Página institucional pública não é bloqueada',AdminIpAllowlistService::isAllowed('produto-institucional',$ipForaDaLista,$secComAllowlist));
hub_check($checks,'api/status não é bloqueado (status público)',AdminIpAllowlistService::isAllowed('api/status',$ipForaDaLista,$secComAllowlist));
hub_check($checks,'Rota do painel (login) é bloqueada com IP fora da allowlist',!AdminIpAllowlistService::isAllowed('login',$ipForaDaLista,$secComAllowlist));
hub_check($checks,'AJAX interno do painel (api/processar-fila) é bloqueado com IP fora da allowlist',!AdminIpAllowlistService::isAllowed('api/processar-fila',$ipForaDaLista,$secComAllowlist));
hub_check($checks,'Rota do painel é liberada quando o IP está na allowlist',AdminIpAllowlistService::isAllowed('login','203.0.113.9',$secComAllowlist));
hub_check($checks,'IP nulo (não detectável) é bloqueado quando a allowlist está ativa',!AdminIpAllowlistService::isAllowed('login',null,$secComAllowlist));

// Reauditoria 2026-09-14 (achado B-06, revisão da própria correção acima): a isenção de webhooks
// era feita por substring, então 'tiny-webhooks' - a PÁGINA ADMINISTRATIVA que edita segredo,
// CNPJs autorizados e limites dos webhooks - também ficava isenta da allowlist. As checagens
// abaixo falham se o casamento por substring voltar.
hub_check($checks,'Página administrativa tiny-webhooks É bloqueada com IP fora da allowlist (B-06)',!AdminIpAllowlistService::isAllowed('tiny-webhooks',$ipForaDaLista,$secComAllowlist));
hub_check($checks,'Rota inventada contendo "webhook" não ganha isenção por semelhança de nome',!AdminIpAllowlistService::isAllowed('config-webhook-secreto',$ipForaDaLista,$secComAllowlist));
// Comentários são removidos antes da checagem: o comentário que documenta o B-06 cita o padrão
// antigo de propósito, e é o CÓDIGO EXECUTÁVEL que precisa estar limpo dele.
$fonteServico = (string)file_get_contents(hub_root().'/app/Services/AdminIpAllowlistService.php');
$codigoServico = '';
foreach (token_get_all($fonteServico) as $token) {
  if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) continue;
  $codigoServico .= is_array($token) ? $token[1] : $token;
}
hub_check($checks,'Isenção de webhook não usa casamento por substring no código executável',
  !preg_match('/str_contains\s*\(\s*\$page\s*,\s*.webhook./', $codigoServico));
hub_check($checks,'Isenção de webhook usa o catálogo com igualdade exata',
  str_contains($codigoServico, 'RouteCatalogService::isInboundWebhook($page)'));

// Melhoria 11: a MESMA decisão existia em WafService::shouldInspect(), com a MESMA heurística de
// substring - ou seja, o B-06 tinha uma segunda ocorrência. As duas passaram a usar o catálogo.
$codigoWaf = (string)file_get_contents(hub_root().'/app/Services/WafService.php');
$codigoWafExec = '';
foreach (token_get_all($codigoWaf) as $token) {
  if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) continue;
  $codigoWafExec .= is_array($token) ? $token[1] : $token;
}
hub_check($checks,'WAF não desliga inspeção por substring "webhook" (mesma família do B-06)',
  !preg_match('/str_contains\s*\(\s*\$page\s*,\s*.webhook./', $codigoWafExec));
hub_check($checks,'WAF classifica webhook de entrada pelo catálogo',
  str_contains($codigoWafExec, 'RouteCatalogService::isInboundWebhook($page)'));
require_once hub_root().'/app/Services/WafService.php';
hub_check($checks,'WAF continua inspecionando a página administrativa tiny-webhooks',
  WafService::shouldInspect('tiny-webhooks'));
hub_check($checks,'WAF não inspeciona webhook de entrada real',
  !WafService::shouldInspect('api/webhook/vsm/pedido'));

// O catálogo é a fonte única: as duas listas precisam bater com o que os controllers declaram.
hub_check($checks,'Catálogo lista os 10 webhooks de entrada', count(RouteCatalogService::inboundWebhooks()) === 10);
hub_check($checks,'Catálogo trata rota desconhecida como painel (falha fechada)',
  RouteCatalogService::isAdminPanel('rota-que-nao-existe-ainda'));
hub_check($checks,'Catálogo NÃO classifica tiny-webhooks como webhook de entrada',
  !RouteCatalogService::isInboundWebhook('tiny-webhooks') && RouteCatalogService::isAdminPanel('tiny-webhooks'));

// Guarda de deriva: todo webhook de ENTRADA declarado nos controllers precisa constar da lista de
// isenção. Se alguém acrescentar uma rota de webhook e esquecer da lista, ela falha FECHADA (fica
// bloqueada pela allowlist) - e este teste avisa antes de a integração quebrar em produção.
$rotasWebhookDeclaradas = [];
foreach (['ApiTinyController.php','ApiVsmWebhookController.php'] as $arquivo) {
  $fonte = (string)@file_get_contents(hub_root().'/app/Controllers/'.$arquivo);
  if (preg_match_all("/case '(api\/[^']*webhook[^']*)'/", $fonte, $m)) {
    foreach ($m[1] as $rota) $rotasWebhookDeclaradas[$rota] = true;
  }
}
$rotasWebhookDeclaradas = array_keys($rotasWebhookDeclaradas);
hub_check($checks,'Controllers de webhook declaram rotas de entrada detectáveis', count($rotasWebhookDeclaradas) >= 10);
$naoIsentas = array_values(array_filter($rotasWebhookDeclaradas, static fn(string $rota): bool =>
  !AdminIpAllowlistService::isAllowed($rota, $ipForaDaLista, $secComAllowlist)));
hub_check($checks,'Todo webhook de entrada declarado está isento da allowlist: '.(implode(', ', $naoIsentas) ?: 'nenhum pendente'), $naoIsentas === []);

hub_finish($checks);
