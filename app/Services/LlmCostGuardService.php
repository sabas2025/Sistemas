<?php
/** Controle defensivo de custo LLM. Não chama provedor externo. */
class LlmCostGuardService {
  public static function ensureSchema(): void {
    SchemaRuntimePolicyService::requireTable('llm_usage_daily', 'controle de custos LLM');
  }

  public static function estimateTokens(string $text): int { return max(1, (int)ceil(strlen($text) / 4)); }

  public static function estimateCost(int $tokensInput, int $tokensOutput, string $provider, string $model): float {
    // Estimativa conservadora genérica. Em produção real, substituir por tabela de preços atualizada por modelo.
    $per1kIn = 0.005; $per1kOut = 0.015;
    $p = strtolower($provider.' '.$model);
    if (str_contains($p, 'llama') || str_contains($p, 'local')) { $per1kIn = 0.0; $per1kOut = 0.0; }
    if (str_contains($p, 'mini') || str_contains($p, 'flash')) { $per1kIn = 0.001; $per1kOut = 0.004; }
    return round(($tokensInput / 1000 * $per1kIn) + ($tokensOutput / 1000 * $per1kOut), 6);
  }

  public static function usage(string $provider, string $model, string $environment): array {
    try {
      if (!Database::tableExists('llm_usage_daily')) return ['today'=>0.0,'month'=>0.0,'requests_today'=>0];
      $pdo = Database::forTable('llm_usage_daily');
      $today = date('Y-m-d');
      $monthStart = date('Y-m-01');
      $st = $pdo->prepare('SELECT COALESCE(SUM(cost_estimate),0) cost, COALESCE(SUM(requests),0) req FROM llm_usage_daily WHERE usage_date=? AND provider=? AND model=? AND environment=?');
      $st->execute([$today,$provider,$model,$environment]);
      $d = $st->fetch() ?: [];
      $st = $pdo->prepare('SELECT COALESCE(SUM(cost_estimate),0) cost FROM llm_usage_daily WHERE usage_date>=? AND provider=? AND model=? AND environment=?');
      $st->execute([$monthStart,$provider,$model,$environment]);
      $m = $st->fetch() ?: [];
      return ['today'=>(float)($d['cost'] ?? 0),'month'=>(float)($m['cost'] ?? 0),'requests_today'=>(int)($d['req'] ?? 0)];
    } catch (Throwable $e) { return ['today'=>0.0,'month'=>0.0,'requests_today'=>0]; }
  }

  public static function check(float $estimatedCost, array $policy): array {
    $provider = (string)($policy['provider'] ?? 'none');
    $model = (string)($policy['model'] ?? '');
    $environment = (string)($policy['environment'] ?? 'homologacao');
    $usage = self::usage($provider, $model, $environment);
    $findings = [];
    if ($estimatedCost > (float)($policy['per_request_cost_limit'] ?? 1.0)) $findings[] = 'Custo estimado por requisição acima do limite.';
    if (($usage['today'] + $estimatedCost) > (float)($policy['daily_cost_limit'] ?? 10.0)) $findings[] = 'Limite diário de custo seria ultrapassado.';
    if (($usage['month'] + $estimatedCost) > (float)($policy['monthly_cost_limit'] ?? 100.0)) $findings[] = 'Limite mensal de custo seria ultrapassado.';
    return ['allowed'=>empty($findings),'findings'=>$findings,'usage'=>$usage,'estimated_cost'=>$estimatedCost];
  }

  public static function record(array $policy, int $tokensInput, int $tokensOutput, float $cost, bool $blocked=false): void {
    try {
      self::ensureSchema();
      Database::forTable('llm_usage_daily')->prepare('INSERT INTO llm_usage_daily(usage_date,provider,model,environment,requests,blocked_requests,tokens_input,tokens_output,cost_estimate,trace_id) VALUES(CURDATE(),?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE requests=requests+VALUES(requests), blocked_requests=blocked_requests+VALUES(blocked_requests), tokens_input=tokens_input+VALUES(tokens_input), tokens_output=tokens_output+VALUES(tokens_output), cost_estimate=cost_estimate+VALUES(cost_estimate), trace_id=VALUES(trace_id)')
        ->execute([(string)($policy['provider'] ?? 'none'), (string)($policy['model'] ?? ''), (string)($policy['environment'] ?? 'homologacao'), 1, $blocked ? 1 : 0, $tokensInput, $tokensOutput, $cost, RequestContext::id()]);
    } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
  }
}
