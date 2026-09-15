<?php
/**
 * Guarda de idempotência forte e atômica.
 * Nenhuma integração externa deve prosseguir sem uma reserva válida em produção.
 */
class EnterpriseIdempotencyGuardService {
  public static function enabled(): bool {
    $cfg = class_exists('App') ? App::config() : [];
    return !empty($cfg['enterprise']['idempotency_strict_mode']);
  }

  public static function failurePolicy(): string {
    $cfg = class_exists('App') ? App::config() : [];
    $policy = strtolower((string)($cfg['enterprise']['idempotency_failure_policy'] ?? 'dlq'));
    return in_array($policy, ['block','retry','dlq','allow'], true) ? $policy : 'dlq';
  }

  public static function canonicalPayload($payload): string {
    if (is_string($payload)) {
      $decoded = json_decode($payload, true);
      if (json_last_error() === JSON_ERROR_NONE) $payload = $decoded;
      else return trim($payload);
    }
    $normalize = static function ($value) use (&$normalize) {
      if (is_array($value)) {
        if (array_keys($value) !== range(0, count($value) - 1)) ksort($value, SORT_STRING);
        foreach ($value as $k => $v) {
          if (in_array((string)$k, ['updated_at','processando_desde','locked_at','lease_expires_at','heartbeat_at'], true)) {
            unset($value[$k]);
            continue;
          }
          $value[$k] = $normalize($v);
        }
      } elseif (is_float($value)) {
        return rtrim(rtrim(number_format($value, 8, '.', ''), '0'), '.');
      } elseif (is_string($value)) {
        return trim($value);
      }
      return $value;
    };
    $normalized = $normalize($payload);
    return json_encode($normalized, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION) ?: '';
  }

  public static function keyForQueueItem(array $item): string {
    $tipo = trim((string)($item['tipo'] ?? 'fila')) ?: 'fila';
    $referencia = trim((string)($item['referencia'] ?? ''));
    if ($referencia === '') {
      $fallback = isset($item['id']) ? (string)$item['id'] : bin2hex(random_bytes(16));
      $referencia = 'fila_' . $fallback;
    }
    $tenant = trim((string)($item['tenant_id'] ?? $item['empresa_id'] ?? 'default')) ?: 'default';
    $payload = self::canonicalPayload($item['payload'] ?? '');
    return IdempotencyService::key('fila_integracao', $tenant.':'.$referencia, $tipo, $payload);
  }

  public static function claimQueueItem(array $item): array {
    if (!self::enabled()) return ['allowed'=>true,'reason'=>'disabled','key'=>''];
    $key = '';
    try {
      if (!Database::tableExists('integration_idempotency')) {
        return self::failure('table_missing', 'Tabela integration_idempotency ausente.', $key);
      }
      $key = self::keyForQueueItem($item);
      $payload = self::canonicalPayload($item['payload'] ?? '');
      $tipo = (string)($item['tipo'] ?? 'fila');
      $referencia = trim((string)($item['referencia'] ?? ($item['id'] ?? '')));
      $trace = (string)($item['trace_id'] ?? (class_exists('RequestContext') ? RequestContext::id() : ''));
      $owner = trim((string)($item['locked_by'] ?? '')) ?: self::ownerToken();
      $pdo = Database::forTable('integration_idempotency');
      $hash = hash('sha256', $payload);
      $meta = ['source'=>__CLASS__,'fila_id'=>(int)($item['id'] ?? 0),'tipo'=>$tipo,'referencia'=>$referencia,'trace_id'=>$trace,'owner_token'=>$owner];

      // INSERT IGNORE torna a aquisição atômica: somente um worker recebe rowCount=1.
      $sql = "INSERT IGNORE INTO integration_idempotency
        (idempotency_key,entity_type,entity_id,operation,request_hash,status,trace_id,expires_at,metadata_json,claim_count,original_fila_id,owner_token,locked_until)
        VALUES(?,?,?,?,?,'claimed',?,DATE_ADD(NOW(), INTERVAL 14 DAY),?,1,?,?,DATE_ADD(NOW(), INTERVAL 5 MINUTE))";
      try {
        $st = $pdo->prepare($sql);
        $st->execute([$key,'fila_integracao',$referencia,$tipo,$hash,$trace,json_encode($meta,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(int)($item['id'] ?? 0),$owner]);
      } catch (Throwable $compat) {
        // Compatibilidade temporária antes da migration 104.36.
        $st = $pdo->prepare("INSERT IGNORE INTO integration_idempotency(idempotency_key,entity_type,entity_id,operation,request_hash,status,trace_id,expires_at,metadata_json,claim_count,original_fila_id) VALUES(?,?,?,?,?,'claimed',?,DATE_ADD(NOW(), INTERVAL 14 DAY),?,1,?)");
        $st->execute([$key,'fila_integracao',$referencia,$tipo,$hash,$trace,json_encode($meta,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(int)($item['id'] ?? 0)]);
      }

      if ($st->rowCount() === 1) {
        return ['allowed'=>true,'duplicate'=>false,'key'=>$key,'reason'=>'claimed','owner_token'=>$owner,'message'=>'Idempotência reservada atomicamente.'];
      }

      $existing = self::getStrict($key);
      if (!$existing) return self::failure('claim_conflict_unreadable', 'Reserva existente não pôde ser consultada.', $key);
      $status = (string)($existing['status'] ?? 'claimed');
      if ($status === 'completed') {
        self::touchDuplicate($key, $trace, $item, 'completed_duplicate');
        return ['allowed'=>false,'duplicate'=>true,'completed'=>true,'key'=>$key,'reason'=>'completed_duplicate','message'=>'Item duplicado já concluído anteriormente.'];
      }
      self::touchDuplicate($key, $trace, $item, 'active_duplicate');
      return ['allowed'=>false,'duplicate'=>true,'completed'=>false,'key'=>$key,'reason'=>'active_duplicate','message'=>'Item duplicado já está reservado ou processando.'];
    } catch (Throwable $e) {
      try { Audit::exception($e, 'enterprise_idempotency.claim.error'); } catch(Throwable $ignored){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $ignored); }
      return self::failure('guard_error', $e->getMessage(), $key);
    }
  }

  private static function failure(string $reason, string $message, string $key): array {
    $policy = self::failurePolicy();
    return ['allowed'=>$policy === 'allow','duplicate'=>false,'key'=>$key,'reason'=>$reason,'policy'=>$policy,'guard_failure'=>true,'message'=>$message];
  }

  private static function getStrict(string $key): ?array {
    $st = Database::forTable('integration_idempotency')->prepare('SELECT * FROM integration_idempotency WHERE idempotency_key=? LIMIT 1');
    $st->execute([$key]);
    $row = $st->fetch();
    return $row ?: null;
  }

  private static function ownerToken(): string {
    return hash('sha256', gethostname().'|'.(function_exists('getmypid') ? getmypid() : 0).'|'.microtime(true).'|'.random_bytes(16));
  }

  public static function markIgnoredQueueItem(array $item, array $claim): void {
    try {
      $id=(int)($item['id']??0);if($id<=0)return;
      $owner=trim((string)($item['locked_by']??''));
      $retorno=['success'=>true,'ignored'=>true,'reason'=>$claim['reason']??'duplicate','message'=>$claim['message']??'Item ignorado por idempotência.','idempotency_key'=>$claim['key']??null,'trace_id'=>$item['trace_id']??(class_exists('RequestContext')?RequestContext::id():null)];
      $pdo=Database::forTable('fila_integracao');
      try {
        $sql="UPDATE fila_integracao SET status='ignorado', retorno=?, codigo_erro=NULL, processado_em=NOW(), processando_desde=NULL, locked_by=NULL, locked_at=NULL, lease_expires_at=NULL, heartbeat_at=NULL WHERE id=? AND status='processando'".($owner!==''?' AND locked_by=?':'');
        $params=[json_encode($retorno,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$id];if($owner!=='')$params[]=$owner;
        $st=$pdo->prepare($sql);$st->execute($params);
      } catch(Throwable $compat) {
        $sql="UPDATE fila_integracao SET status='sucesso', retorno=?, codigo_erro=NULL, processado_em=NOW(), processando_desde=NULL WHERE id=? AND status='processando'";
        $params=[json_encode($retorno,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$id];
        if($owner!==''&&Database::columnExists('fila_integracao','locked_by')){$sql.=' AND locked_by=?';$params[]=$owner;}
        $st=$pdo->prepare($sql);$st->execute($params);
      }
      if($st->rowCount()!==1){
        if(class_exists('BestEffortLogService'))BestEffortLogService::warning(__METHOD__,new RuntimeException('Resultado de idempotência descartado: posse da fila expirada.'),['queue_id'=>$id]);
        return;
      }
      if(class_exists('IntegrationEventService'))IntegrationEventService::onQueueIgnored($item,$claim);
      Audit::event('fila.idempotencia.ignorada','sucesso',['entidade'=>'fila_integracao','entidade_id'=>$id,'mensagem'=>$retorno['message'],'contexto'=>$retorno]);
    } catch(Throwable $e){try{Audit::exception($e,'enterprise_idempotency.ignore.error');}catch(Throwable $ignored){if(class_exists('BestEffortLogService'))BestEffortLogService::warning(__METHOD__,$ignored);}}
  }

  public static function markGuardFailureQueueItem(array $item, array $claim): void {
    $id=(int)($item['id']??0);if($id<=0)return;
    $owner=trim((string)($item['locked_by']??''));
    $policy=(string)($claim['policy']??self::failurePolicy());
    $payload=['success'=>false,'guard_failure'=>true,'policy'=>$policy,'reason'=>$claim['reason']??'guard_error','message'=>$claim['message']??'Falha na proteção de idempotência.','trace_id'=>$item['trace_id']??null];
    // G-03: os 300s eram fixos e a falha de guard costuma ser sistêmica - todos os itens em voo
    // voltavam exatamente juntos 5 minutos depois. Mesmo atraso, agora com jitter aditivo.
    $status=$policy==='dlq'?'falha_definitiva':'erro';$next=$policy==='dlq'?null:RetryPolicyService::proximaTentativaEm(5);
    $sql="UPDATE fila_integracao SET status=?, retorno=?, codigo_erro='IDEMPOTENCY_GUARD_FAILURE', proxima_tentativa=?, processado_em=NOW(), processando_desde=NULL, locked_by=NULL, locked_at=NULL";
    if(Database::columnExists('fila_integracao','lease_expires_at'))$sql.=', lease_expires_at=NULL, heartbeat_at=NULL';
    $sql.=" WHERE id=? AND status='processando'".($owner!==''?' AND locked_by=?':'');
    $params=[$status,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$next,$id];if($owner!=='')$params[]=$owner;
    $st=Database::forTable('fila_integracao')->prepare($sql);$st->execute($params);
    if($st->rowCount()!==1){
      if(class_exists('BestEffortLogService'))BestEffortLogService::warning(__METHOD__,new RuntimeException('Falha de idempotência descartada: posse da fila expirada.'),['queue_id'=>$id]);
      return;
    }
    if($policy==='dlq'&&class_exists('DeadLetterQueueService'))DeadLetterQueueService::enviar($item,$payload,'IDEMPOTENCY_GUARD_FAILURE','Proteção idempotente indisponível; chamada externa não executada.');
    try{Audit::event('fila.idempotencia.bloqueada','erro',['entidade'=>'fila_integracao','entidade_id'=>$id,'mensagem'=>$payload['message'],'contexto'=>$payload]);}catch(Throwable $ignored){if(class_exists('BestEffortLogService'))BestEffortLogService::warning(__METHOD__,$ignored);}
  }

  private static function touchDuplicate(string $key, string $trace, array $item, string $reason): void {
    try {
      $meta = ['duplicate_reason'=>$reason,'duplicate_fila_id'=>(int)($item['id'] ?? 0),'trace_id'=>$trace,'last_duplicate_at'=>date('c')];
      Database::forTable('integration_idempotency')->prepare('UPDATE integration_idempotency SET updated_at=NOW(), trace_id=COALESCE(trace_id, ?), metadata_json=?, claim_count=COALESCE(claim_count,0)+1, last_duplicate_at=NOW() WHERE idempotency_key=?')
        ->execute([$trace,json_encode($meta,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$key]);
    } catch(Throwable $ignored){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $ignored); }
  }
}
