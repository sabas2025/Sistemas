<?php
declare(strict_types=1);
require __DIR__ . '/_helpers.php';

/**
 * Fase B2+B5 (2026-09-20) — execução REAL de LLM em homologação, sob aprovação humana.
 *
 * planExecution() é puro (gates + custo + hash), exercitado sem banco nem rede.
 * executeHomologacao() é exercitado com TODAS as dependências injetadas (política, aprovação,
 * chave, uso, minimizer, transporte falso, breaker, recorder, auditor, skip_access), então
 * nenhuma classe de banco/rede é tocada. Stubs de App/BestEffortLogService no topo.
 */
$checks = [];

if (!class_exists('App')) { class App { public static array $cfg = []; public static function config(): array { return self::$cfg; } } }
if (!class_exists('BestEffortLogService')) { class BestEffortLogService { public static function warning(string $w, ?Throwable $e = null, array $c = []): void {} } }

require_once hub_root() . '/app/Services/LlmHttpClientService.php';
require_once hub_root() . '/app/Services/LlmCostGuardService.php';
require_once hub_root() . '/app/Services/LlmRealExecutionService.php';

// ---- fixtures ----------------------------------------------------------------------------
$raw = 'classifique este pedido de teste';
$basePolicy = [
  'provider' => 'anthropic', 'model' => 'claude-haiku-4-5', 'environment' => 'homologacao',
  'enabled' => true, 'allow_external_calls' => true, 'max_tokens' => 256, 'temperature' => 0.2,
  'per_request_cost_limit' => 1.0, 'daily_cost_limit' => 10.0, 'monthly_cost_limit' => 100.0,
];
$goodApproval = [
  'status' => 'aprovado', 'provider' => 'anthropic', 'environment' => 'homologacao',
  'input_hash' => hash('sha256', $raw), 'expires_at' => date('Y-m-d H:i:s', time() + 3600),
  'approval_uuid' => 'LLM-APR-TESTE', 'prompt_key' => 'teste', 'risk_level' => 'baixo',
];
$noUsage = ['today' => 0.0, 'month' => 0.0];
$plan = static fn(array $pol, ?array $apr, string $rawP, string $sendP, bool $key, array $u) => LlmRealExecutionService::planExecution($pol, $apr, $rawP, $sendP, $key, $u);
$hasBlock = static fn(array $p, string $frag): bool => (bool)array_filter($p['blocked'], static fn($b) => stripos($b, $frag) !== false);

// ---- B2 planExecution (puro) -------------------------------------------------------------
$ok = $plan($basePolicy, $goodApproval, $raw, $raw, true, $noUsage);
hub_check($checks, 'B2: caminho feliz libera e monta o request', $ok['ok'] === true && $ok['request']['model'] === 'claude-haiku-4-5' && $ok['request']['messages'][0]['content'] === $raw && $ok['request']['max_tokens'] === 256);

$prod = $plan(['environment' => 'producao'] + $basePolicy, $goodApproval, $raw, $raw, true, $noUsage);
hub_check($checks, 'B2: produção é bloqueada (só homologação nesta fase)', $prod['ok'] === false && $hasBlock($prod, 'homologação'));

$off = $plan(['enabled' => false] + $basePolicy, $goodApproval, $raw, $raw, true, $noUsage);
hub_check($checks, 'B2: LLM desabilitado bloqueia', $off['ok'] === false && $hasBlock($off, 'desabilitado'));

$noext = $plan(['allow_external_calls' => false] + $basePolicy, $goodApproval, $raw, $raw, true, $noUsage);
hub_check($checks, 'B2: chamadas externas bloqueadas na política bloqueia', $noext['ok'] === false && $hasBlock($noext, 'externas'));

$openai = $plan(['provider' => 'openai'] + $basePolicy, ['provider' => 'openai'] + $goodApproval, $raw, $raw, true, $noUsage);
hub_check($checks, 'B2: provider != anthropic bloqueia (só Anthropic nesta fase)', $openai['ok'] === false && $hasBlock($openai, 'Anthropic'));

$nokey = $plan($basePolicy, $goodApproval, $raw, $raw, false, $noUsage);
hub_check($checks, 'B2: sem chave no cofre bloqueia', $nokey['ok'] === false && $hasBlock($nokey, 'chave'));

$noapr = $plan($basePolicy, null, $raw, $raw, true, $noUsage);
hub_check($checks, 'B2: aprovação inexistente bloqueia', $noapr['ok'] === false && $hasBlock($noapr, 'inexistente'));

$pend = $plan($basePolicy, ['status' => 'pendente'] + $goodApproval, $raw, $raw, true, $noUsage);
hub_check($checks, 'B2: aprovação pendente bloqueia', $pend['ok'] === false && $hasBlock($pend, 'aprovada'));

$exp = $plan($basePolicy, ['expires_at' => date('Y-m-d H:i:s', time() - 60)] + $goodApproval, $raw, $raw, true, $noUsage);
hub_check($checks, 'B2: aprovação expirada bloqueia', $exp['ok'] === false && $hasBlock($exp, 'expirada'));

$swap = $plan($basePolicy, $goodApproval, 'texto DIFERENTE do aprovado', 'texto DIFERENTE do aprovado', true, $noUsage);
hub_check($checks, 'B2: prompt que não confere com o hash aprovado bloqueia (anti-troca)', $swap['ok'] === false && $hasBlock($swap, 'não confere'));

$pricey = $plan(['per_request_cost_limit' => 0.0001] + $basePolicy, $goodApproval, $raw, $raw, true, $noUsage);
hub_check($checks, 'B2: custo por requisição acima do limite bloqueia', $pricey['ok'] === false && $hasBlock($pricey, 'por requisição'));

$daily = $plan($basePolicy, $goodApproval, $raw, $raw, true, ['today' => 10.0, 'month' => 10.0]);
hub_check($checks, 'B2: limite diário estourado bloqueia', $daily['ok'] === false && $hasBlock($daily, 'diário'));

// ---- B5 executeHomologacao (dependências injetadas: sem banco, sem rede) ------------------
$mkTransport = static function (array &$capture, int $status, array $bodyArr, string $err = '') {
  return static function (string $url, array $headers, string $jsonBody) use (&$capture, $status, $bodyArr, $err): array {
    $capture['chamado'] = true; $capture['url'] = $url; $capture['headers'] = $headers; $capture['body'] = $jsonBody;
    return ['status' => $status, 'body' => json_encode($bodyArr), 'error' => $err];
  };
};
$baseDeps = static function (array $over = []) use ($basePolicy, $goodApproval, $noUsage): array {
  return array_merge([
    'skip_access' => true, 'policy' => $basePolicy, 'approval' => $goodApproval,
    'api_key' => 'sk-teste', 'usage' => $noUsage,
    'minimizer' => static fn(string $t): string => $t,
    'breaker_gate' => static fn(string $p): bool => true,
    'breaker_report' => static function (string $p, bool $ok, string $m): void {},
  ], $over);
};

// sucesso
$cap = []; $rec = []; $aud = [];
$deps = $baseDeps([
  'transport' => $mkTransport($cap, 200, ['stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => 'ok resposta']], 'usage' => ['input_tokens' => 9, 'output_tokens' => 4]]),
  'recorder' => static function (array $pol, int $i, int $o, float $c, bool $b) use (&$rec): void { $rec = ['i' => $i, 'o' => $o, 'c' => $c, 'b' => $b]; },
  'auditor' => static function (string $st, int $i, int $o, float $c, array $f) use (&$aud): void { $aud = ['status' => $st, 'i' => $i, 'o' => $o]; },
]);
$r = LlmRealExecutionService::executeHomologacao(1, $raw, $deps);
hub_check($checks, 'B5: sucesso extrai texto/uso e não fica bloqueado', $r['ok'] === true && $r['text'] === 'ok resposta' && $r['usage']['input_tokens'] === 9 && $r['usage']['output_tokens'] === 4 && $r['blocked'] === []);
hub_check($checks, 'B5: sucesso registra uso com blocked=false e tokens reais', $rec['b'] === false && $rec['i'] === 9 && $rec['o'] === 4 && $rec['c'] > 0.0);
hub_check($checks, 'B5: sucesso audita status permitido', ($aud['status'] ?? '') === 'permitido');
hub_check($checks, 'B5: a chave vai no header x-api-key, nunca no corpo', ($cap['headers']['x-api-key'] ?? '') === 'sk-teste' && !str_contains($cap['body'] ?? '', 'sk-teste'));

// bloqueado por governança (aprovação ausente) → transporte NÃO é chamado
$cap2 = ['chamado' => false]; $aud2 = [];
$deps2 = $baseDeps([
  'approval' => null,
  'transport' => $mkTransport($cap2, 200, ['content' => [['type' => 'text', 'text' => 'nao deveria']]]),
  'recorder' => static function (array $pol, int $i, int $o, float $c, bool $b): void {},
  'auditor' => static function (string $st, int $i, int $o, float $c, array $f) use (&$aud2): void { $aud2 = ['status' => $st]; },
]);
$r2 = LlmRealExecutionService::executeHomologacao(0, $raw, $deps2);
hub_check($checks, 'B5: bloqueio de governança não chama o provedor e audita bloqueado', $r2['ok'] === false && ($cap2['chamado'] ?? false) === false && (bool)$r2['blocked'] && ($aud2['status'] ?? '') === 'bloqueado');

// breaker aberto → transporte não é chamado
$cap3 = ['chamado' => false];
$deps3 = $baseDeps([
  'breaker_gate' => static fn(string $p): bool => false,
  'transport' => $mkTransport($cap3, 200, ['content' => [['type' => 'text', 'text' => 'x']]]),
  'recorder' => static function (array $pol, int $i, int $o, float $c, bool $b): void {},
  'auditor' => static function (string $st, int $i, int $o, float $c, array $f): void {},
]);
$r3 = LlmRealExecutionService::executeHomologacao(1, $raw, $deps3);
hub_check($checks, 'B5: circuit breaker aberto bloqueia sem chamar o provedor', $r3['ok'] === false && ($cap3['chamado'] ?? false) === false && stripos(implode(' ', $r3['blocked']), 'breaker') !== false);

// erro do provedor (HTTP 500) → registra blocked=true e audita erro
$cap4 = []; $rec4 = []; $aud4 = [];
$deps4 = $baseDeps([
  'transport' => $mkTransport($cap4, 500, ['error' => ['message' => 'server down']]),
  'recorder' => static function (array $pol, int $i, int $o, float $c, bool $b) use (&$rec4): void { $rec4 = ['b' => $b, 'i' => $i]; },
  'auditor' => static function (string $st, int $i, int $o, float $c, array $f) use (&$aud4): void { $aud4 = ['status' => $st]; },
]);
$r4 = LlmRealExecutionService::executeHomologacao(1, $raw, $deps4);
hub_check($checks, 'B5: erro do provedor devolve ok=false, registra blocked=true e audita erro', $r4['ok'] === false && ($rec4['b'] ?? null) === true && ($aud4['status'] ?? '') === 'erro');

// o hash confere sobre o texto CRU, e o request leva o texto MINIMIZADO (mascarado)
$cap5 = [];
$deps5 = $baseDeps([
  'minimizer' => static fn(string $t): string => strtoupper($t),
  'transport' => $mkTransport($cap5, 200, ['stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => 'y']], 'usage' => ['input_tokens' => 3, 'output_tokens' => 1]]),
  'recorder' => static function (array $pol, int $i, int $o, float $c, bool $b): void {},
  'auditor' => static function (string $st, int $i, int $o, float $c, array $f): void {},
]);
$r5 = LlmRealExecutionService::executeHomologacao(1, $raw, $deps5);
$sent = json_decode($cap5['body'] ?? '{}', true);
hub_check($checks, 'B5: hash confere no texto cru e o request envia o minimizado', $r5['ok'] === true && ($sent['messages'][0]['content'] ?? '') === strtoupper($raw));

hub_finish($checks);
