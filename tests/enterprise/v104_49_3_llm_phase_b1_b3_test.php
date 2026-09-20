<?php
declare(strict_types=1);
require __DIR__ . '/_helpers.php';

/**
 * Fase B1+B3 (2026-09-20) — cliente HTTP Anthropic (sem gatilho) + tabela de preço por modelo.
 *
 * B1 é exercitado com transporte FALSO (sem rede) e chave fornecida — o serviço nunca é chamado
 * por rota. B3 confere que estimateCost usa a tabela real por modelo e o fallback genérico.
 * Stubs de App/BestEffortLogService no topo mantêm o teste sem banco.
 */
$checks = [];

// Stubs para B3 (estimateCost lê App::config()['llm']['pricing'] se existir)
if (!class_exists('App')) { class App { public static array $cfg = []; public static function config(): array { return self::$cfg; } } }
if (!class_exists('BestEffortLogService')) { class BestEffortLogService { public static function warning(string $w, ?Throwable $e = null, array $c = []): void {} } }

require_once hub_root() . '/app/Services/LlmHttpClientService.php';
require_once hub_root() . '/app/Services/LlmCostGuardService.php';

// ---- B1: montagem do request (pura) ------------------------------------------------------
$req = LlmHttpClientService::buildAnthropicRequest([
  'model' => 'claude-haiku-4-5', 'max_tokens' => 512,
  'system' => 'Você é um classificador.', 'temperature' => 0.2,
  'messages' => [['role' => 'user', 'content' => 'oi']],
]);
hub_check($checks, 'B1: URL fixa da Anthropic (host não controlável)', $req['url'] === 'https://api.anthropic.com/v1/messages');
hub_check($checks, 'B1: header anthropic-version presente', ($req['headers']['anthropic-version'] ?? '') === '2023-06-01');
hub_check($checks, 'B1: corpo carrega model/max_tokens/system/temperature/messages',
  $req['body']['model'] === 'claude-haiku-4-5' && $req['body']['max_tokens'] === 512
  && $req['body']['system'] === 'Você é um classificador.' && $req['body']['temperature'] === 0.2
  && $req['body']['messages'][0]['role'] === 'user');

// ---- B1: send com transporte FALSO (sem rede); a chave entra no header x-api-key ----------
$capturado = [];
$transporteOk = static function (string $url, array $headers, string $jsonBody) use (&$capturado): array {
  $capturado = ['url' => $url, 'headers' => $headers, 'body' => $jsonBody];
  return ['status' => 200, 'body' => json_encode([
    'stop_reason' => 'end_turn',
    'content' => [['type' => 'text', 'text' => 'resposta']],
    'usage' => ['input_tokens' => 12, 'output_tokens' => 3],
  ]), 'error' => ''];
};
$r = LlmHttpClientService::anthropicMessages(['model' => 'claude-haiku-4-5', 'max_tokens' => 64, 'messages' => [['role' => 'user', 'content' => 'oi']]], 'sk-teste', $transporteOk);
hub_check($checks, 'B1: sucesso extrai texto e usage', $r['ok'] === true && $r['text'] === 'resposta' && $r['usage']['input_tokens'] === 12 && $r['usage']['output_tokens'] === 3);
hub_check($checks, 'B1: a chave vai no header x-api-key (nunca no corpo)', ($capturado['headers']['x-api-key'] ?? '') === 'sk-teste' && !str_contains($capturado['body'], 'sk-teste'));

// ---- B1: normalização de erro e recusa ---------------------------------------------------
$err500 = LlmHttpClientService::normalizeAnthropic(500, json_encode(['error' => ['message' => 'server']]));
hub_check($checks, 'B1: HTTP 500 é falha recuperável (tenta próximo provedor)', $err500['ok'] === false && $err500['tentar_proximo'] === true);
$err400 = LlmHttpClientService::normalizeAnthropic(400, json_encode(['error' => ['message' => 'bad']]));
hub_check($checks, 'B1: HTTP 400 é falha global (não tenta próximo)', $err400['ok'] === false && $err400['tentar_proximo'] === false);
$ref = LlmHttpClientService::normalizeAnthropic(200, json_encode(['stop_reason' => 'refusal', 'content' => []]));
hub_check($checks, 'B1: refusal é falha global, não recuperável', $ref['ok'] === false && $ref['stop_reason'] === 'refusal' && $ref['tentar_proximo'] === false);
$trans = LlmHttpClientService::normalizeAnthropic(0, '', 'connection reset');
hub_check($checks, 'B1: erro de transporte é recuperável', $trans['ok'] === false && $trans['tentar_proximo'] === true);
$naojson = LlmHttpClientService::normalizeAnthropic(200, '<html>não json</html>');
hub_check($checks, 'B1: resposta não-JSON reprova sem lançar exceção', $naojson['ok'] === false);

// ---- B3: tabela de preço por modelo ------------------------------------------------------
// 1M in + 1M out em haiku-4-5 = $1 + $5 = $6
hub_check($checks, 'B3: preço haiku-4-5 usa a tabela real ($1/$5 por 1M)', abs(LlmCostGuardService::estimateCost(1000000, 1000000, 'anthropic', 'claude-haiku-4-5') - 6.0) < 1e-6);
// sonnet-5 = $2 + $10 = $12
hub_check($checks, 'B3: preço sonnet-5 usa a tabela real ($2/$10 por 1M)', abs(LlmCostGuardService::estimateCost(1000000, 1000000, 'anthropic', 'claude-sonnet-5') - 12.0) < 1e-6);
// opus-5 = $5 + $25 = $30
hub_check($checks, 'B3: preço opus-5 usa a tabela real ($5/$25 por 1M)', abs(LlmCostGuardService::estimateCost(1000000, 1000000, 'anthropic', 'claude-opus-5') - 30.0) < 1e-6);
// modelo desconhecido cai no genérico ($5/$15 por 1M = $20)
hub_check($checks, 'B3: modelo desconhecido cai no genérico conservador', abs(LlmCostGuardService::estimateCost(1000000, 1000000, 'custom', 'modelo-x') - 20.0) < 1e-6);
// override por config
App::$cfg = ['llm' => ['pricing' => ['claude-haiku-4-5' => [0.002, 0.008]]]];
hub_check($checks, 'B3: config llm.pricing sobrescreve a tabela', abs(LlmCostGuardService::estimateCost(1000000, 1000000, 'anthropic', 'claude-haiku-4-5') - 10.0) < 1e-6);
App::$cfg = [];

hub_finish($checks);
