<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks = [];

/**
 * Observabilidade do limite de requisições do Tiny — achados T-02/T-05 (auditoria Fase 5, 2026-09-21).
 *
 * O Hub não lia os cabeçalhos de limite que o Tiny devolve (`x-limit-api` na V2; `X-RateLimit-*` na
 * V3), ficando "às cegas" quanto ao teto. Esta correção captura e registra esses valores — SÓ
 * observabilidade, sem throttle. Este teste prova o parser e o wiring, e REPROVA sobre o código
 * antigo (onde a classe e as chamadas não existiam).
 *
 * O QUE NÃO PROVA: a captura ao vivo depende de uma resposta real do Tiny (fora de alcance sem
 * credencial/rede). Aqui se prova a normalização dos headers e que os dois clientes chamam o observador.
 */
$raiz = hub_root();

// (1) Funcional: a classe existe e normaliza os headers.
require_once $raiz.'/app/Services/TinyRateLimitObserverService.php';

$v2 = TinyRateLimitObserverService::v2(['x-limit-api' => '60']);
hub_check($checks, 'V2: x-limit-api=60 vira limite_por_minuto=60', ($v2['limite_por_minuto'] ?? null) === 60, json_encode($v2));

$v2vazio = TinyRateLimitObserverService::v2(['content-type' => 'application/json']);
hub_check($checks, 'V2: sem x-limit-api devolve null', $v2vazio === null, var_export($v2vazio, true));

$v2lixo = TinyRateLimitObserverService::v2(['x-limit-api' => 'abc']);
hub_check($checks, 'V2: valor não-numérico devolve null', $v2lixo === null, var_export($v2lixo, true));

$v3 = TinyRateLimitObserverService::v3([
  'x-ratelimit-limit' => '120', 'x-ratelimit-remaining' => '100', 'x-ratelimit-reset' => '5',
]);
hub_check($checks, 'V3: X-RateLimit-* normalizado (120/100/5)',
  ($v3['limite'] ?? null) === 120 && ($v3['restante'] ?? null) === 100 && ($v3['reset_segundos'] ?? null) === 5,
  json_encode($v3));

$v3parcial = TinyRateLimitObserverService::v3(['x-ratelimit-remaining' => '7']);
hub_check($checks, 'V3: header parcial ainda é lido (restante=7)', ($v3parcial['restante'] ?? null) === 7, json_encode($v3parcial));

$v3vazio = TinyRateLimitObserverService::v3(['x-trace-id' => 'abc']);
hub_check($checks, 'V3: sem X-RateLimit-* devolve null', $v3vazio === null, var_export($v3vazio, true));

// A closure de captura preenche o sink e devolve o tamanho da linha (contrato do CURLOPT_HEADERFUNCTION).
$sink = [];
$cb = TinyRateLimitObserverService::headerCapture($sink);
$linha = "X-Limit-Api: 30\r\n";
$ret = $cb(null, $linha);
hub_check($checks, 'headerCapture devolve o tamanho da linha', $ret === strlen($linha), (string)$ret);
hub_check($checks, 'headerCapture grava chave minúscula e valor', ($sink['x-limit-api'] ?? null) === '30', json_encode($sink));

// (2) Estrutural: os dois clientes chamam o observador (ancorado no CÓDIGO, não em comentário).
$v2src = hub_read('app/Services/TinyV2Service.php');
$v3src = hub_read('app/Services/TinyV3Service.php');
hub_check($checks, 'TinyV2Service lê o arquivo (não-vazio)', $v2src !== '', 'len='.strlen($v2src));
hub_check($checks, 'TinyV3Service lê o arquivo (não-vazio)', $v3src !== '', 'len='.strlen($v3src));

hub_check($checks, 'V2 usa CURLOPT_HEADERFUNCTION com o observador',
  $v2src !== '' && str_contains($v2src, 'CURLOPT_HEADERFUNCTION=>TinyRateLimitObserverService::headerCapture'),
  'wiring V2 ausente');
hub_check($checks, 'V2 registra TinyRateLimitObserverService::v2(',
  $v2src !== '' && str_contains($v2src, 'TinyRateLimitObserverService::v2('),
  'chamada V2 ausente');
hub_check($checks, 'V3 usa CURLOPT_HEADERFUNCTION com o observador',
  $v3src !== '' && str_contains($v3src, 'CURLOPT_HEADERFUNCTION=>TinyRateLimitObserverService::headerCapture'),
  'wiring V3 ausente');
hub_check($checks, 'V3 registra TinyRateLimitObserverService::v3(',
  $v3src !== '' && str_contains($v3src, 'TinyRateLimitObserverService::v3('),
  'chamada V3 ausente');

hub_finish($checks);
