<?php
declare(strict_types=1);
require __DIR__ . '/_helpers.php';

/**
 * Fase A da evolução LLM (2026-09-20) — structured output + fallback/circuit-breaker.
 *
 * Prova que os dois serviços locais são determinísticos e não dependem de rede nem de banco.
 * O fallback é exercitado com gate/report/executor FALSOS, então CircuitBreakerService não é
 * necessário aqui (o default só é usado em runtime real).
 */
$checks = [];
require_once hub_root() . '/app/Services/LlmStructuredOutputService.php';
require_once hub_root() . '/app/Services/LlmFallbackService.php';

// ---- A1: structured output ----------------------------------------------------------------
$ok = LlmStructuredOutputService::validate('{"intencao":"pedido","confianca":0.9}', 'classificacao_intencao');
hub_check($checks, 'A1: JSON válido passa', $ok['valid'] === true && ($ok['data']['intencao'] ?? null) === 'pedido');

$falta = LlmStructuredOutputService::validate('{"confianca":0.5}', 'classificacao_intencao');
hub_check($checks, 'A1: campo obrigatório ausente reprova', $falta['valid'] === false && (bool)$falta['errors']);

$tipo = LlmStructuredOutputService::validate('{"intencao":"pedido","confianca":"alta"}', 'classificacao_intencao');
hub_check($checks, 'A1: tipo errado reprova (confianca string)', $tipo['valid'] === false);

$enum = LlmStructuredOutputService::validate('{"intencao":"hackear","confianca":0.5}', 'classificacao_intencao');
hub_check($checks, 'A1: valor fora do enum reprova', $enum['valid'] === false);

$faixa = LlmStructuredOutputService::validate('{"intencao":"pedido","confianca":5}', 'classificacao_intencao');
hub_check($checks, 'A1: número fora da faixa reprova (confianca=5)', $faixa['valid'] === false);

$cerca = LlmStructuredOutputService::validate("Claro!\n```json\n{\"intencao\":\"estoque\",\"confianca\":0.7}\n```\n", 'classificacao_intencao');
hub_check($checks, 'A1: JSON em cerca markdown é reparado e passa', $cerca['valid'] === true && ($cerca['data']['intencao'] ?? null) === 'estoque');

$lixo = LlmStructuredOutputService::validate('desculpe, não sei responder', 'classificacao_intencao');
hub_check($checks, 'A1: texto sem JSON reprova sem lançar exceção', $lixo['valid'] === false && $lixo['data'] === null);

$desconhecido = LlmStructuredOutputService::validate('{"x":1}', 'schema_que_nao_existe');
hub_check($checks, 'A1: schema desconhecido reprova', $desconhecido['valid'] === false);

// ---- A2: fallback + breaker ---------------------------------------------------------------
$gateSempre = static fn(string $p): bool => true;
$reportNulo = static function (string $p, bool $ok, string $m): void {};

// provedor 1 falha (recuperável), provedor 2 sucede
$log = [];
$exec = static function (string $p) use (&$log): array {
  $log[] = $p;
  if ($p === 'anthropic') return ['ok' => false, 'tentar_proximo' => true, 'resultado' => null, 'erro' => 'timeout'];
  return ['ok' => true, 'tentar_proximo' => false, 'resultado' => 'resp-' . $p, 'erro' => ''];
};
$r = LlmFallbackService::run(['anthropic', 'openai'], $exec, $gateSempre, $reportNulo);
hub_check($checks, 'A2: avança ao próximo provedor em falha recuperável', $r['ok'] === true && $r['provider'] === 'openai' && $r['resultado'] === 'resp-openai' && $log === ['anthropic', 'openai']);

// breaker aberto no provedor 1 → pula sem chamar o executor
$log2 = [];
$exec2 = static function (string $p) use (&$log2): array { $log2[] = $p; return ['ok' => true, 'tentar_proximo' => false, 'resultado' => 'r', 'erro' => '']; };
$gatePula = static fn(string $p): bool => $p !== 'anthropic';
$r2 = LlmFallbackService::run(['anthropic', 'openai'], $exec2, $gatePula, $reportNulo);
hub_check($checks, 'A2: breaker aberto pula o provedor sem executá-lo', $r2['ok'] === true && $r2['provider'] === 'openai' && $log2 === ['openai']);

// falha global (tentar_proximo=false) para o fallback no primeiro provedor
$log3 = [];
$exec3 = static function (string $p) use (&$log3): array { $log3[] = $p; return ['ok' => false, 'tentar_proximo' => false, 'resultado' => null, 'erro' => 'custo excedido']; };
$r3 = LlmFallbackService::run(['anthropic', 'openai'], $exec3, $gateSempre, $reportNulo);
hub_check($checks, 'A2: falha global interrompe o fallback (não tenta o 2º)', $r3['ok'] === false && $log3 === ['anthropic'] && $r3['erro'] === 'custo excedido');

// todos falham (recuperável) → falha agregada, sem exceção
$exec4 = static fn(string $p): array => ['ok' => false, 'tentar_proximo' => true, 'resultado' => null, 'erro' => 'down'];
$r4 = LlmFallbackService::run(['anthropic', 'openai'], $exec4, $gateSempre, $reportNulo);
hub_check($checks, 'A2: todos falham devolve falha agregada sem exceção', $r4['ok'] === false && $r4['provider'] === null);

// ordem vazia → erro claro, sem chamar executor
$chamou = false;
$r5 = LlmFallbackService::run([], static function (string $p) use (&$chamou): array { $chamou = true; return ['ok' => true]; }, $gateSempre, $reportNulo);
hub_check($checks, 'A2: ordem vazia devolve erro sem chamar executor', $r5['ok'] === false && $chamou === false);

// ordemDaPolitica: sem fallback_order usa o provedor único (retrocompatível)
hub_check($checks, 'A2: ordemDaPolitica usa provider único quando não há fallback_order', LlmFallbackService::ordemDaPolitica(['provider' => 'anthropic']) === ['anthropic']);
hub_check($checks, 'A2: ordemDaPolitica lê lista explícita quando declarada', LlmFallbackService::ordemDaPolitica(['provider' => 'anthropic', 'fallback_order' => ['openai', 'gemini']]) === ['openai', 'gemini']);
hub_check($checks, 'A2: ordemDaPolitica com provider none e sem lista devolve vazio', LlmFallbackService::ordemDaPolitica(['provider' => 'none']) === []);

hub_finish($checks);
