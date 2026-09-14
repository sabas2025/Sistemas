<?php
/** Fila de aprovação humana para qualquer uso real de LLM. */
class LlmApprovalService {
  public static function ensureSchema(): void {
    SchemaRuntimePolicyService::requireTable('llm_approval_queue', 'aprovação humana LLM');
  }

  public static function create(string $promptKey, string $prompt, array $validation): array {
    LlmPolicyService::requireAccess(false);
    self::ensureSchema();
    $policy = LlmPolicyService::policy();
    $uuid = 'LLM-APR-'.date('Ymd-His').'-'.strtoupper(substr(bin2hex(random_bytes(4)),0,8));
    $redacted = LlmPolicyService::minimizeContext($prompt, $policy);
    Database::forTable('llm_approval_queue')->prepare('INSERT INTO llm_approval_queue(approval_uuid,requester_user_id,prompt_key,provider,model,environment,risk_level,status,input_hash,redacted_context,reason,trace_id,expires_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,DATE_ADD(NOW(), INTERVAL 24 HOUR))')
      ->execute([$uuid, RequestContext::userId(), $promptKey, $policy['provider'], $policy['model'], $policy['environment'], $validation['risk_level'] ?? 'baixo', 'pendente', $validation['input_hash'] ?? hash('sha256',$prompt), $redacted, implode('; ', $validation['findings'] ?? []), RequestContext::id()]);
    Audit::event('llm.approval.created','alerta',['mensagem'=>'Solicitação LLM enviada para aprovação humana.', 'contexto'=>['approval_uuid'=>$uuid,'risk'=>$validation['risk_level'] ?? 'baixo']]);
    return ['success'=>true,'approval_uuid'=>$uuid];
  }

  public static function decide(int $id, string $decision, string $reason=''): array {
    LlmPolicyService::requireAccess(true);
    if (!in_array($decision, ['aprovado','rejeitado','cancelado'], true)) return ['success'=>false,'message'=>'Decisão inválida.'];
    self::ensureSchema();
    Database::forTable('llm_approval_queue')->prepare('UPDATE llm_approval_queue SET status=?, approver_user_id=?, decision_reason=?, decided_at=NOW() WHERE id=? AND status="pendente"')
      ->execute([$decision, RequestContext::userId(), $reason, $id]);
    Audit::event('llm.approval.decided',$decision==='aprovado'?'sucesso':'alerta',['mensagem'=>'Aprovação LLM decidida.', 'contexto'=>['id'=>$id,'decision'=>$decision]]);
    return ['success'=>true,'message'=>'Decisão registrada.'];
  }

  public static function recent(int $limit=20): array {
    try {
      self::ensureSchema();
      $limit = max(1, min(100, $limit));
      return Database::forTable('llm_approval_queue')->query('SELECT * FROM llm_approval_queue ORDER BY id DESC LIMIT '.$limit)->fetchAll();
    } catch (Throwable $e) { return []; }
  }
}
