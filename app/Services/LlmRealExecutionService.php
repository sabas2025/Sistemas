<?php
/**
 * Fase B2+B5 (2026-09-20) — Execução REAL de LLM, restrita a HOMOLOGAÇÃO e sob aprovação humana.
 *
 * Este é o executeReal() que a Fase B1 (LlmHttpClientService) e a governança preparavam
 * (LlmPolicyService, LlmApprovalService, LlmCostGuardService, CircuitBreakerService). Continua
 * SEGURO POR PADRÃO: sem `enabled` + `allow_external_calls` + chave no cofre + aprovação HUMANA
 * aprovada + custo dentro do limite + ambiente `homologacao`, NADA sai para a rede.
 *
 * Produção é DELIBERADAMENTE fora de escopo nesta fase: a liberação de go-live exige as guardas
 * próprias (coerência ambiente × endpoint, I-23; ProductionGoLiveService). Aqui só homologação.
 *
 * Anti-troca de prompt: a aprovação guarda apenas o HASH do prompt original (o texto cru nunca
 * fica em repouso). Para executar, o operador cola o MESMO texto; se o hash não conferir, bloqueia.
 *
 * planExecution() é PURO (sem rede, sem banco) para teste determinístico. executeHomologacao()
 * orquestra: resolve política/aprovação/chave dos serviços reais (todos injetáveis para teste) e
 * só então dispara o transporte do LlmHttpClientService (também injetável). Nunca lança para fora.
 */
class LlmRealExecutionService {
  /** System prompt fixo e neutro para a chamada de homologação (não induz ação). */
  public const SYSTEM_HOMOLOGACAO = 'Você é um assistente de homologação do Hub de Integração Tiny/VSM. Responda de forma objetiva e apenas com texto; não execute nem proponha ações operacionais.';

  /**
   * Decisão pura de liberação da execução real. Não toca rede nem banco.
   *
   * @param array      $policy     saída de LlmPolicyService::policy()
   * @param array|null $approval   linha de llm_approval_queue (ou null se inexistente)
   * @param string     $promptRaw  prompt ORIGINAL — confere contra o hash aprovado
   * @param string     $promptSend prompt já minimizado/mascarado — é o que iria à rede
   * @param bool       $hasKey     há chave ativa no cofre para provider/ambiente
   * @param array      $usage      ['today'=>float,'month'=>float] uso corrente (para limites)
   * @return array{ok:bool,blocked:string[],request:array,cost_estimate:float,tokens_input:int}
   */
  public static function planExecution(array $policy, ?array $approval, string $promptRaw, string $promptSend, bool $hasKey, array $usage): array {
    $blocked = [];
    $provider = strtolower((string)($policy['provider'] ?? 'none'));
    $model = (string)($policy['model'] ?? '');
    $env = (string)($policy['environment'] ?? 'homologacao');

    if ($env !== 'homologacao') $blocked[] = 'Execução real só é permitida em homologação nesta fase.';
    if (empty($policy['enabled'])) $blocked[] = 'LLM desabilitado na política.';
    if (empty($policy['allow_external_calls'])) $blocked[] = 'Chamadas externas bloqueadas na política.';
    if ($provider !== 'anthropic') $blocked[] = 'Cliente real implementado apenas para Anthropic nesta fase (provider atual: ' . ($provider !== '' ? $provider : 'none') . ').';
    if ($model === '') $blocked[] = 'Modelo não configurado na política.';
    if (!$hasKey) $blocked[] = 'Nenhuma chave de API ativa no Token Vault para o ambiente/provedor.';

    // Aprovação humana obrigatória e coerente com ESTA execução.
    if (!$approval) {
      $blocked[] = 'Aprovação humana inexistente.';
    } else {
      $status = strtolower((string)($approval['status'] ?? ''));
      if ($status !== 'aprovado') $blocked[] = 'Aprovação não está aprovada (status: ' . ($status !== '' ? $status : 'desconhecido') . ').';
      if (!empty($approval['expires_at']) && strtotime((string)$approval['expires_at']) <= time()) $blocked[] = 'Aprovação expirada.';
      if (strtolower((string)($approval['provider'] ?? '')) !== $provider) $blocked[] = 'Aprovação diverge do provedor da política.';
      if ((string)($approval['environment'] ?? '') !== $env) $blocked[] = 'Aprovação diverge do ambiente da política.';
      $hash = hash('sha256', $promptRaw);
      if (!hash_equals((string)($approval['input_hash'] ?? ''), $hash)) $blocked[] = 'O texto informado não confere com o prompt aprovado (hash não confere).';
    }

    // Custo (B3, puro): estimativa + limites por requisição / dia / mês.
    $tokensIn = class_exists('LlmCostGuardService') ? LlmCostGuardService::estimateTokens($promptSend) : max(1, (int)ceil(strlen($promptSend) / 4));
    $tokensOut = max(1, (int)($policy['max_tokens'] ?? 1024));
    $cost = class_exists('LlmCostGuardService') ? LlmCostGuardService::estimateCost($tokensIn, $tokensOut, $provider, $model) : 0.0;
    if ($cost > (float)($policy['per_request_cost_limit'] ?? 1.0)) $blocked[] = 'Custo estimado por requisição acima do limite.';
    if (((float)($usage['today'] ?? 0) + $cost) > (float)($policy['daily_cost_limit'] ?? 10.0)) $blocked[] = 'Limite diário de custo seria ultrapassado.';
    if (((float)($usage['month'] ?? 0) + $cost) > (float)($policy['monthly_cost_limit'] ?? 100.0)) $blocked[] = 'Limite mensal de custo seria ultrapassado.';

    $request = [
      'model' => $model,
      'max_tokens' => $tokensOut,
      'temperature' => (float)($policy['temperature'] ?? 0.2),
      'system' => self::SYSTEM_HOMOLOGACAO,
      'messages' => [['role' => 'user', 'content' => $promptSend]],
    ];
    return ['ok' => empty($blocked), 'blocked' => $blocked, 'request' => $request, 'cost_estimate' => $cost, 'tokens_input' => $tokensIn];
  }

  /**
   * Orquestra a execução real em homologação. Dependências injetáveis (teste sem banco/rede):
   *   policy, approval, api_key, usage, minimizer, transport, breaker_gate, breaker_report,
   *   recorder, auditor, skip_access. Ausentes → resolvem dos serviços reais.
   *
   * @return array{ok:bool,blocked:string[],text:string,stop_reason:?string,usage:array,cost:float,approval_uuid:?string,erro:string}
   */
  public static function executeHomologacao(int $approvalId, string $prompt, array $deps = []): array {
    if (empty($deps['skip_access'])) LlmPolicyService::requireAccess(true);
    $policy = $deps['policy'] ?? LlmPolicyService::policy();
    $provider = strtolower((string)($policy['provider'] ?? 'none'));
    $model = (string)($policy['model'] ?? '');
    $env = (string)($policy['environment'] ?? 'homologacao');

    $approval = array_key_exists('approval', $deps) ? $deps['approval'] : self::loadApproval($approvalId);
    $apiKey = array_key_exists('api_key', $deps) ? (string)$deps['api_key'] : TokenVaultService::getActive('llm_' . $provider, $env, 'api_key');
    $hasKey = $apiKey !== '';
    $minimizer = $deps['minimizer'] ?? static fn(string $t): string => LlmPolicyService::minimizeContext($t, $policy);
    $promptSend = $minimizer($prompt);
    $usage = $deps['usage'] ?? (class_exists('LlmCostGuardService') ? LlmCostGuardService::usage($provider, $model, $env) : ['today' => 0.0, 'month' => 0.0]);

    $plan = self::planExecution($policy, $approval, $prompt, $promptSend, $hasKey, $usage);
    $uuid = $approval['approval_uuid'] ?? null;
    if (!$plan['ok']) {
      self::runAudit($deps, $policy, $approval, 'bloqueado', 0, 0, 0.0, '', $plan['blocked']);
      return self::blockedResult($plan['blocked'], $uuid, 'Execução bloqueada pela governança.');
    }

    // Circuit breaker: como no LlmFallbackService, degrada PERMITINDO se o banco faltar.
    $gate = $deps['breaker_gate'] ?? static function (string $p): bool {
      if (!class_exists('CircuitBreakerService')) return true;
      try { return CircuitBreakerService::permitir('llm_' . $p); } catch (Throwable $e) { return true; }
    };
    if (!$gate($provider)) {
      $motivo = ['Circuit breaker aberto para o provedor.'];
      self::runAudit($deps, $policy, $approval, 'bloqueado', 0, 0, 0.0, '', $motivo);
      return self::blockedResult($motivo, $uuid, 'Circuit breaker aberto.');
    }

    $transport = $deps['transport'] ?? null; // null → cURL endurecido do LlmHttpClientService
    $res = LlmHttpClientService::anthropicMessages($plan['request'], $apiKey, $transport);

    $report = $deps['breaker_report'] ?? static function (string $p, bool $ok, string $msg): void {
      if (!class_exists('CircuitBreakerService')) return;
      try { $ok ? CircuitBreakerService::sucesso('llm_' . $p) : CircuitBreakerService::falha('llm_' . $p, $msg !== '' ? $msg : 'falha llm'); }
      catch (Throwable $e) { /* best-effort */ }
    };
    $report($provider, (bool)$res['ok'], (string)($res['erro'] ?? ''));

    $inTok = (int)($res['usage']['input_tokens'] ?? 0);
    $outTok = (int)($res['usage']['output_tokens'] ?? 0);
    $realCost = ($res['ok'] && class_exists('LlmCostGuardService')) ? LlmCostGuardService::estimateCost($inTok, $outTok, $provider, $model) : 0.0;

    $recorder = $deps['recorder'] ?? static function (array $pol, int $i, int $o, float $c, bool $blocked): void {
      if (class_exists('LlmCostGuardService')) LlmCostGuardService::record($pol, $i, $o, $c, $blocked);
    };
    if ($res['ok']) $recorder($policy, $inTok, $outTok, $realCost, false);
    else $recorder($policy, $plan['tokens_input'], 0, 0.0, true);

    self::runAudit($deps, $policy, $approval, $res['ok'] ? 'permitido' : 'erro', $inTok, $outTok, $realCost, (string)($res['text'] ?? ''), $res['ok'] ? [] : [(string)($res['erro'] ?? 'falha')]);

    return [
      'ok' => (bool)$res['ok'],
      'blocked' => [],
      'text' => (string)($res['text'] ?? ''),
      'stop_reason' => $res['stop_reason'] ?? null,
      'usage' => ['input_tokens' => $inTok, 'output_tokens' => $outTok],
      'cost' => $realCost,
      'approval_uuid' => $uuid,
      'erro' => (string)($res['erro'] ?? ''),
    ];
  }

  private static function blockedResult(array $blocked, ?string $uuid, string $erro): array {
    return ['ok' => false, 'blocked' => $blocked, 'text' => '', 'stop_reason' => null, 'usage' => ['input_tokens' => 0, 'output_tokens' => 0], 'cost' => 0.0, 'approval_uuid' => $uuid, 'erro' => $erro];
  }

  private static function loadApproval(int $id): ?array {
    try {
      if ($id <= 0 || !class_exists('Database') || !Database::tableExists('llm_approval_queue')) return null;
      $st = Database::forTable('llm_approval_queue')->prepare('SELECT * FROM llm_approval_queue WHERE id=? LIMIT 1');
      $st->execute([$id]);
      $row = $st->fetch();
      return $row ?: null;
    } catch (Throwable $e) { return null; }
  }

  private static function runAudit(array $deps, array $policy, ?array $approval, string $status, int $inTok, int $outTok, float $cost, string $outputText, array $findings): void {
    if (isset($deps['auditor']) && is_callable($deps['auditor'])) { ($deps['auditor'])($status, $inTok, $outTok, $cost, $findings); return; }
    self::audit($policy, $approval, $status, $inTok, $outTok, $cost, $outputText, $findings);
  }

  /**
   * Trilha da execução real em llm_audit_logs. Best-effort e guardada por colunas existentes.
   * status ∈ {'permitido','erro','bloqueado'} — todos válidos no ENUM da tabela. Guarda apenas o
   * HASH da saída (nunca o texto cru), e real_execution_allowed=1 só no sucesso.
   */
  private static function audit(array $policy, ?array $approval, string $status, int $inTok, int $outTok, float $cost, string $outputText, array $findings): void {
    try {
      if (!class_exists('Database') || !Database::tableExists('llm_audit_logs')) return;
      $pdo = Database::forTable('llm_audit_logs');
      $fields = [
        'trace_id' => RequestContext::id(),
        'prompt_key' => (string)($approval['prompt_key'] ?? 'homologacao'),
        'provider' => (string)($policy['provider'] ?? 'none'),
        'model' => (string)($policy['model'] ?? ''),
        'risk_level' => (string)($approval['risk_level'] ?? 'baixo'),
        'status' => $status,
        'input_hash' => (string)($approval['input_hash'] ?? ''),
        'tokens_input' => $inTok,
        'tokens_output' => $outTok,
        'cost_estimate' => $cost,
        'findings_json' => json_encode(array_values($findings), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      ];
      $optional = [
        'user_id' => RequestContext::userId(),
        'environment' => (string)($policy['environment'] ?? 'homologacao'),
        'approval_required' => 1,
        'real_execution_allowed' => $status === 'permitido' ? 1 : 0,
        'output_hash' => $outputText !== '' ? hash('sha256', $outputText) : null,
      ];
      foreach ($optional as $col => $val) { if (Database::columnExists('llm_audit_logs', $col)) $fields[$col] = $val; }
      $cols = array_keys($fields);
      $sql = 'INSERT INTO llm_audit_logs(' . implode(',', $cols) . ') VALUES(' . implode(',', array_fill(0, count($cols), '?')) . ')';
      $pdo->prepare($sql)->execute(array_values($fields));
    } catch (Throwable $e) {
      if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e, ['operation' => 'llm_real_audit']);
    }
  }
}
