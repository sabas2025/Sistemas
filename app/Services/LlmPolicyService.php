<?php
/**
 * Política central de Governança LLM.
 * Não executa chamada externa. Controla permissão, política, cofre de chave,
 * redaction, ambiente e limites antes de qualquer uso futuro de IA.
 */
class LlmPolicyService {
  public static function defaultPolicy(): array {
    $cfg = class_exists('App') ? App::config() : (function_exists('cfg') ? cfg() : []);
    $llm = $cfg['llm'] ?? [];
    return [
      'enabled' => (bool)($llm['enabled'] ?? false),
      'environment' => (string)($llm['environment'] ?? 'homologacao'),
      'provider' => (string)($llm['provider'] ?? 'none'),
      'model' => (string)($llm['model'] ?? ''),
      'max_tokens' => (int)($llm['max_tokens'] ?? 2048),
      'temperature' => (float)($llm['temperature'] ?? 0.20),
      'allow_external_calls' => (bool)($llm['allow_external_calls'] ?? false),
      'api_key_storage' => (string)($llm['api_key_storage'] ?? 'token_vault'),
      'allowed_roles' => (string)($llm['allowed_roles'] ?? 'admin,supervisor'),
      'prompt_injection_guard' => (bool)($llm['prompt_injection_guard'] ?? true),
      'log_prompts' => (bool)($llm['log_prompts'] ?? true),
      'redact_sensitive_data' => (bool)($llm['redact_sensitive_data'] ?? true),
      'context_minimization' => (bool)($llm['context_minimization'] ?? true),
      'require_human_approval' => (bool)($llm['require_human_approval'] ?? true),
      'allow_automatic_actions' => (bool)($llm['allow_automatic_actions'] ?? false),
      'allow_sensitive_context' => (bool)($llm['allow_sensitive_context'] ?? false),
      'monthly_cost_limit' => (float)($llm['monthly_cost_limit'] ?? 100.00),
      'daily_cost_limit' => (float)($llm['daily_cost_limit'] ?? 10.00),
      'per_request_cost_limit' => (float)($llm['per_request_cost_limit'] ?? 1.00),
      'max_input_chars' => (int)($llm['max_input_chars'] ?? 12000),
      'audit_retention_days' => (int)($llm['audit_retention_days'] ?? 180),
    ];
  }

  public static function ensureSchema(): void {
    SchemaRuntimePolicyService::requireTable('llm_policy_settings', 'governança LLM');
  }

  public static function policy(): array {
    $policy = self::defaultPolicy();
    try {
      if (!Database::tableExists('llm_policy_settings')) return $policy;
      $rows = Database::forTable('llm_policy_settings')->query('SELECT setting_key, setting_value, value_type FROM llm_policy_settings')->fetchAll();
      foreach ($rows as $r) {
        $key = (string)$r['setting_key'];
        if (!array_key_exists($key, $policy)) continue;
        $policy[$key] = self::castValue($r['setting_value'], (string)$r['value_type'], $policy[$key]);
      }
    } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    // Guard rails absolutos: produção/ações automáticas continuam conservadoras.
    if (($policy['environment'] ?? 'homologacao') !== 'producao') {
      $policy['allow_automatic_actions'] = false;
    }
    return $policy;
  }

  private static function castValue(mixed $value, string $type, mixed $default): mixed {
    if ($type === 'bool') return in_array(strtolower((string)$value), ['1','true','sim','yes','on'], true);
    if ($type === 'int') return (int)$value;
    if ($type === 'float') return (float)$value;
    if ($type === 'json') { $j = json_decode((string)$value, true); return $j ?? $default; }
    return (string)$value;
  }

  private static function typeOf(mixed $value): string {
    if (is_bool($value)) return 'bool';
    if (is_int($value)) return 'int';
    if (is_float($value)) return 'float';
    if (is_array($value)) return 'json';
    return 'string';
  }

  public static function savePolicy(array $input): array {
    self::requireAccess(true);
    self::ensureSchema();
    $allowed = array_keys(self::defaultPolicy());
    $bools = ['enabled','allow_external_calls','prompt_injection_guard','log_prompts','redact_sensitive_data','context_minimization','require_human_approval','allow_automatic_actions','allow_sensitive_context'];
    $ints = ['max_tokens','max_input_chars','audit_retention_days'];
    $floats = ['temperature','monthly_cost_limit','daily_cost_limit','per_request_cost_limit'];
    $saved = [];
    foreach ($allowed as $key) {
      if (in_array($key, $bools, true)) {
        $value = !empty($input[$key]);
      } elseif (!array_key_exists($key, $input)) {
        continue;
      } elseif (in_array($key, $ints, true)) {
        $value = max(0, (int)$input[$key]);
      } elseif (in_array($key, $floats, true)) {
        $value = max(0, (float)str_replace(',', '.', (string)$input[$key]));
      } else {
        $value = trim((string)$input[$key]);
      }

      if ($key === 'environment' && !in_array($value, ['homologacao','producao'], true)) $value = 'homologacao';
      if ($key === 'provider' && !in_array($value, ['none','openai','anthropic','gemini','deepseek','qwen','llama_local','custom'], true)) $value = 'none';
      if ($key === 'temperature') $value = min(1.0, max(0.0, (float)$value));
      if ($key === 'max_tokens') $value = min(32768, max(256, (int)$value));
      if ($key === 'max_input_chars') $value = min(50000, max(1000, (int)$value));
      if ($key === 'allow_automatic_actions' && $value === true) {
        // O Hub permite cadastrar a intenção, mas por segurança mantém false salvo.
        $value = false;
      }
      self::upsertSetting($key, $value, self::typeOf($value));
      $saved[] = $key;
    }
    Audit::event('llm.policy.updated','sucesso',['mensagem'=>'Política LLM atualizada com segurança.', 'contexto'=>['campos'=>$saved]]);
    return ['success'=>true,'saved'=>$saved,'policy'=>self::policy()];
  }

  private static function upsertSetting(string $key, mixed $value, string $type): void {
    $serialized = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : (string)(is_bool($value) ? ($value ? '1' : '0') : $value);
    Database::forTable('llm_policy_settings')->prepare('INSERT INTO llm_policy_settings(setting_key, setting_value, value_type, updated_by, trace_id) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), value_type=VALUES(value_type), updated_by=VALUES(updated_by), trace_id=VALUES(trace_id)')
      ->execute([$key, $serialized, $type, RequestContext::userId(), RequestContext::id()]);
  }

  public static function storeApiKey(string $provider, string $environment, string $apiKey): array {
    self::requireAccess(true);
    $provider = strtolower(trim($provider));
    $environment = in_array($environment, ['homologacao','producao'], true) ? $environment : 'homologacao';
    if (!in_array($provider, ['openai','anthropic','gemini','deepseek','qwen','llama_local','custom'], true)) {
      return ['success'=>false,'message'=>'Provider LLM inválido.'];
    }
    if (trim($apiKey) === '') return ['success'=>false,'message'=>'Chave vazia não foi salva.'];
    TokenVaultService::store('llm_'.$provider, $environment, 'api_key', $apiKey, null);
    Audit::event('llm.api_key.stored','sucesso',['mensagem'=>'Chave LLM salva no Token Vault.', 'contexto'=>['provider'=>$provider,'environment'=>$environment,'token_mask'=>SensitiveDataService::mask($apiKey)]]);
    return ['success'=>true,'message'=>'Chave salva criptografada no Token Vault.'];
  }

  public static function hasApiKey(string $provider, string $environment): bool {
    try {
      $provider = strtolower(trim($provider ?: 'none'));
      if ($provider === 'none') return false;
      $row = TokenVaultService::getActiveRow('llm_'.$provider, $environment, 'api_key');
      return (bool)$row;
    } catch (Throwable $e) { return false; }
  }

  public static function currentRole(): string { return strtolower((string)(Auth::user()['perfil'] ?? '')); }

  public static function canUse(?array $policy = null, bool $write=false): bool {
    $u = Auth::user(); if (!$u) return false;
    $role = strtolower((string)($u['perfil'] ?? ''));
    $policy = $policy ?: self::policy();
    $allowed = array_filter(array_map('trim', explode(',', strtolower((string)($policy['allowed_roles'] ?? 'admin,supervisor')))));
    if (in_array($role, $allowed, true)) return true;
    if (class_exists('PermissionService') && PermissionService::can('llm', $write ? 'administrar' : 'usar')) return true;
    return false;
  }

  public static function requireAccess(bool $write=false): void {
    Auth::requireLogin();
    if (!self::canUse(null, $write)) {
      Audit::event('llm.access.denied','erro',['codigo_erro'=>'LLM_PERMISSION_DENIED','mensagem'=>'Usuário sem permissão para Governança LLM.']);
      http_response_code(403);
      exit('Acesso negado para Governança LLM.');
    }
  }

  public static function minimizeContext(string $text, ?array $policy=null): string {
    $policy = $policy ?: self::policy();
    $max = (int)($policy['max_input_chars'] ?? 12000);
    $out = trim($text);
    if (!empty($policy['redact_sensitive_data'])) $out = SensitiveDataService::maskJson($out);
    if (!empty($policy['context_minimization']) && strlen($out) > $max) {
      $out = substr($out, 0, $max) . "\n[TRUNCADO_POR_POLITICA_LLM]";
    }
    return $out;
  }
}
