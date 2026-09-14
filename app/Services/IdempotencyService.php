<?php
class IdempotencyService {
  public static function key(string $entityType, string $entityId, string $operation, $payload = null): string {
    $hash = hash('sha256', is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    return substr($entityType.':'.$operation.':'.$entityId.':'.$hash, 0, 190);
  }

  public static function claim(string $key, string $entityType = '', string $entityId = '', string $operation = '', $payload = null, ?string $traceId = null): array {
    try {
      $pdo = Database::forTable('integration_idempotency');
      $hash = hash('sha256', is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
      $st = $pdo->prepare("INSERT INTO integration_idempotency(idempotency_key,entity_type,entity_id,operation,request_hash,status,trace_id,expires_at,metadata_json) VALUES(?,?,?,?,?,'claimed',?,DATE_ADD(NOW(), INTERVAL 7 DAY),?) ON DUPLICATE KEY UPDATE updated_at=NOW()");
      $st->execute([$key,$entityType ?: null,$entityId ?: null,$operation ?: null,$hash,$traceId ?: (class_exists('RequestContext')?RequestContext::id():null),json_encode(['source'=>'IdempotencyService'],JSON_UNESCAPED_UNICODE)]);
      $exists = self::get($key);
      return ['ok'=>true,'key'=>$key,'status'=>$exists['status'] ?? 'claimed','duplicate'=>($st->rowCount() === 0)];
    } catch (Throwable $e) {
      try { Audit::exception($e,'idempotencia.claim.erro'); } catch(Throwable $ignored){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $ignored); }
      return ['ok'=>false,'key'=>$key,'status'=>'erro','erro'=>$e->getMessage(),'duplicate'=>false];
    }
  }

  public static function markCompleted(string $key, $response = null): void {
    self::mark($key, 'completed', $response);
  }

  public static function markFailed(string $key, $response = null): void {
    self::mark($key, 'failed', $response);
  }

  private static function mark(string $key, string $status, $response = null): void {
    try {
      $hash = $response === null ? null : hash('sha256', is_string($response) ? $response : json_encode($response, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
      Database::forTable('integration_idempotency')->prepare('UPDATE integration_idempotency SET status=?, response_hash=?, updated_at=NOW() WHERE idempotency_key=?')->execute([$status,$hash,$key]);
    } catch (Throwable $e) { try { Audit::exception($e,'idempotencia.mark.erro'); } catch(Throwable $ignored){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $ignored); } }
  }

  public static function get(string $key): ?array {
    try { $st=Database::forTable('integration_idempotency')->prepare('SELECT * FROM integration_idempotency WHERE idempotency_key=? LIMIT 1'); $st->execute([$key]); $r=$st->fetch(); return $r ?: null; }
    catch (Throwable $e) { return null; }
  }
}
