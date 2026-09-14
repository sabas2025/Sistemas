<?php
/**
 * Gateway de Governança LLM.
 * V104.33: seguro por padrão, sem chamadas externas automáticas.
 */
class LlmGatewayService {
  public static function config(): array { return LlmPolicyService::policy(); }

  public static function readiness(): array {
    $cfg = self::config();
    $provider = (string)($cfg['provider'] ?? 'none');
    $environment = (string)($cfg['environment'] ?? 'homologacao');
    $hasKey = LlmPolicyService::hasApiKey($provider, $environment);
    $usage = LlmCostGuardService::usage($provider, (string)($cfg['model'] ?? ''), $environment);
    $checks = [];
    $checks[] = ['item'=>'LLM habilitado','status'=>!empty($cfg['enabled'])?'atencao':'ok','mensagem'=>!empty($cfg['enabled'])?'LLM habilitado; exige checklist, custo e aprovação.':'LLM desativado por padrão, sem impacto em Tiny/VSM.'];
    $checks[] = ['item'=>'Ambiente LLM','status'=>$environment==='homologacao'?'ok':'atencao','mensagem'=>$environment==='homologacao'?'Homologação por padrão.':'Produção exige aprovação humana e chave em cofre.'];
    $checks[] = ['item'=>'Provider/modelo','status'=>($provider !== 'none' && (string)($cfg['model'] ?? '') !== '')?'ok':'atencao','mensagem'=>($provider !== 'none')?'Provider configurado.':'Provider não configurado.'];
    $checks[] = ['item'=>'API key no Token Vault','status'=>$hasKey?'ok':'atencao','mensagem'=>$hasKey?'Chave existe no cofre e não é exibida.':'Nenhuma chave LLM ativa no cofre para ambiente/provider atual.'];
    $checks[] = ['item'=>'Chamadas externas','status'=>!empty($cfg['allow_external_calls'])?'atencao':'ok','mensagem'=>!empty($cfg['allow_external_calls'])?'Chamadas externas permitidas por política; ainda exigem aprovação/custo.':'Chamadas externas bloqueadas.'];
    $checks[] = ['item'=>'Aprovação humana','status'=>!empty($cfg['require_human_approval'])?'ok':'atencao','mensagem'=>!empty($cfg['require_human_approval'])?'Qualquer uso real exige aprovação humana.':'Aprovação humana desativada; não recomendado.'];
    $checks[] = ['item'=>'Ações automáticas','status'=>empty($cfg['allow_automatic_actions'])?'ok':'bloqueio','mensagem'=>empty($cfg['allow_automatic_actions'])?'IA não altera banco/Tiny/VSM automaticamente.':'Ações automáticas por IA não são permitidas nesta versão.'];
    $checks[] = ['item'=>'Prompt Injection Guard','status'=>!empty($cfg['prompt_injection_guard'])?'ok':'atencao','mensagem'=>'Heurística anti prompt injection ativa antes de qualquer uso.'];
    $checks[] = ['item'=>'Redaction/mascaramento','status'=>!empty($cfg['redact_sensitive_data'])?'ok':'atencao','mensagem'=>'Tokens, e-mails, CPF/CNPJ e segredos são mascarados em contexto/logs.'];
    $checks[] = ['item'=>'Contexto mínimo','status'=>!empty($cfg['context_minimization'])?'ok':'atencao','mensagem'=>'Contexto enviado à IA deve ser mínimo, truncado e mascarado.'];
    $checks[] = ['item'=>'Limites de custo','status'=>((float)($cfg['daily_cost_limit'] ?? 0)>0 && (float)($cfg['monthly_cost_limit'] ?? 0)>0)?'ok':'atencao','mensagem'=>'Dia: R$ '.number_format((float)($usage['today'] ?? 0),2,',','.').' / Mês: R$ '.number_format((float)($usage['month'] ?? 0),2,',','.')];
    $statuses = array_column($checks,'status');
    $status = in_array('bloqueio',$statuses,true) ? 'bloqueio' : (in_array('atencao',$statuses,true) ? 'atencao' : 'ok');
    return ['status'=>$status,'checks'=>$checks,'config'=>$cfg,'usage'=>$usage,'has_api_key'=>$hasKey,'approvals'=>class_exists('LlmApprovalService') ? LlmApprovalService::recent(20) : []];
  }

  public static function validatePrompt(string $prompt, string $promptKey = 'manual'): array {
    LlmPolicyService::requireAccess(false);
    $policy = self::config();
    $original = $prompt;
    $prompt = LlmPolicyService::minimizeContext($prompt, $policy);
    $findings = [];
    $risk = 'baixo';
    $patterns = [
      '/ignore\s+(all\s+)?previous|ignore\s+as\s+instru|desconsidere\s+as\s+instru/i' => 'Tentativa de ignorar instruções anteriores.',
      '/system\s+prompt|developer\s+message|chain\s+of\s+thought|racioc[ií]nio interno|prompt do sistema/i' => 'Tentativa de extrair prompt, política interna ou raciocínio privado.',
      '/token|secret|api[_-]?key|senha|oauth|refresh[_-]?token|client_secret/i' => 'Possível solicitação de segredo/token.',
      '/DROP\s+TABLE|DELETE\s+FROM|TRUNCATE\s+TABLE|ALTER\s+TABLE/i' => 'Comando SQL destrutivo detectado.',
      '/envie\s+pedido|alterar\s+estoque|emitir\s+nf|apagar\s+log|mudar\s+produ[cç][aã]o/i' => 'Pedido de ação operacional sensível detectado.',
      '/sem\s+aprova[cç][aã]o|burlar\s+aprova|ignore\s+govern/i' => 'Tentativa de burlar aprovação/governança.',
    ];
    foreach ($patterns as $regex=>$msg) { if (preg_match($regex,$original)) $findings[] = $msg; }
    if (count($findings) >= 4) $risk = 'critico'; elseif (count($findings) >= 2) $risk = 'alto'; elseif ($findings) $risk = 'medio';

    $tokensIn = LlmCostGuardService::estimateTokens($prompt);
    $tokensOut = (int)($policy['max_tokens'] ?? 2048);
    $cost = LlmCostGuardService::estimateCost($tokensIn, $tokensOut, (string)$policy['provider'], (string)$policy['model']);
    $costCheck = LlmCostGuardService::check($cost, $policy);
    $hasKey = LlmPolicyService::hasApiKey((string)$policy['provider'], (string)$policy['environment']);
    if (!$costCheck['allowed']) $findings = array_merge($findings, $costCheck['findings']);
    if (!empty($policy['enabled']) && !empty($policy['allow_external_calls']) && !$hasKey) $findings[] = 'LLM habilitado sem API key ativa no Token Vault.';

    $allowedForSimulation = !in_array($risk, ['alto','critico'], true) && $costCheck['allowed'];
    $requiresApproval = !empty($policy['require_human_approval']) || !empty($policy['allow_external_calls']) || !empty($policy['enabled']) || $risk !== 'baixo';
    $realExecutionAllowed = false; // V104.33: nunca executa IA real automaticamente.
    $result = [
      'allowed' => $allowedForSimulation,
      'real_execution_allowed' => $realExecutionAllowed,
      'requires_human_approval' => $requiresApproval,
      'risk_level' => $risk,
      'findings' => array_values(array_unique($findings)),
      'input_hash' => hash('sha256',$original),
      'redacted_preview' => substr($prompt, 0, 1200),
      'tokens_input_estimate' => $tokensIn,
      'tokens_output_limit' => $tokensOut,
      'cost_estimate' => $cost,
      'cost_check' => $costCheck,
      'policy' => [
        'provider'=>$policy['provider'], 'model'=>$policy['model'], 'environment'=>$policy['environment'],
        'enabled'=>$policy['enabled'], 'allow_external_calls'=>$policy['allow_external_calls'],
        'require_human_approval'=>$policy['require_human_approval']
      ]
    ];
    self::audit($promptKey, $result);
    LlmCostGuardService::record($policy, $tokensIn, 0, 0.0, !$allowedForSimulation);
    return $result;
  }

  public static function createApprovalFromPrompt(string $prompt, string $promptKey='manual'): array {
    $validation = self::validatePrompt($prompt, $promptKey);
    return LlmApprovalService::create($promptKey, $prompt, $validation);
  }

  private static function audit(string $promptKey, array $result): void {
    try {
      if (!Database::tableExists('llm_audit_logs')) return;
      $cfg = self::config();
      $pdo = Database::forTable('llm_audit_logs');
      $fields = [
        'trace_id'=>RequestContext::id(),
        'prompt_key'=>$promptKey,
        'provider'=>$cfg['provider'] ?? 'none',
        'model'=>$cfg['model'] ?? '',
        'risk_level'=>$result['risk_level'] ?? 'baixo',
        'status'=>($result['allowed'] ?? false)?'simulado':'bloqueado',
        'input_hash'=>$result['input_hash'] ?? null,
        'tokens_input'=>(int)($result['tokens_input_estimate'] ?? 0),
        'tokens_output'=>(int)($result['tokens_output_limit'] ?? 0),
        'cost_estimate'=>(float)($result['cost_estimate'] ?? 0),
        'findings_json'=>json_encode($result['findings'] ?? [],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
      ];
      // Colunas novas opcionais V104.33, inseridas somente se existirem para compatibilidade.
      $optional = [
        'user_id'=>RequestContext::userId(),
        'environment'=>$cfg['environment'] ?? 'homologacao',
        'approval_required'=>!empty($result['requires_human_approval']) ? 1 : 0,
        'real_execution_allowed'=>!empty($result['real_execution_allowed']) ? 1 : 0,
        'redacted_input_sample'=>$result['redacted_preview'] ?? null,
      ];
      foreach ($optional as $col=>$val) { if (Database::columnExists('llm_audit_logs',$col)) $fields[$col] = $val; }
      $cols = array_keys($fields);
      $placeholders = implode(',', array_fill(0, count($cols), '?'));
      $sql = 'INSERT INTO llm_audit_logs('.implode(',', $cols).') VALUES('.$placeholders.')';
      $pdo->prepare($sql)->execute(array_values($fields));
    } catch (Throwable $e) { if(class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e, ['operation'=>'llm_audit']); }
  }
}
