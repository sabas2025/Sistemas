<?php
class QueueService {
  public const MAX_TENTATIVAS = 5;

  private static function configRow(): array {
    try { return class_exists('IntegrationConfig') ? IntegrationConfig::get() : []; }
    catch (Throwable $e) { return []; }
  }

  private static function processingTimeoutMinutes(): int {
    $cfg = class_exists('App') ? App::config() : [];
    $row = self::configRow();
    $minutes = (int)($row['queue_processing_timeout_minutes'] ?? ($cfg['security']['queue_processing_timeout_minutes'] ?? 30));
    return max(10, min(240, $minutes));
  }

  private static function leaseMinutes(?string $type=null): int {
    $cfg = class_exists('App') ? App::config() : [];
    $row = self::configRow();
    $default = (int)($row['queue_lease_minutes'] ?? ($cfg['security']['queue_lease_minutes'] ?? 5));
    $map = $cfg['security']['queue_lease_minutes_by_type'] ?? [];
    $json = trim((string)($row['queue_lease_by_type_json'] ?? ''));
    if ($json !== '') {
      $decoded = json_decode($json, true);
      if (is_array($decoded)) $map = array_merge(is_array($map)?$map:[], $decoded);
    }
    $minutes = $type !== null && isset($map[$type]) ? (int)$map[$type] : $default;
    return max(1, min(120, $minutes));
  }

  private static function hasLockColumns(): bool {
    return Database::columnExists('fila_integracao','locked_by') && Database::columnExists('fila_integracao','locked_at');
  }

  private static function hasLeaseColumns(): bool {
    return self::hasLockColumns()
      && Database::columnExists('fila_integracao','lease_expires_at')
      && Database::columnExists('fila_integracao','heartbeat_at');
  }

  private static function logBestEffortFailure(string $operation, Throwable $e, array $context=[]): void {
    try {
      if (class_exists('JsonLogger')) JsonLogger::write('queue','warning','Falha não bloqueante na fila', array_merge($context, [
        'operation'=>$operation,
        'error'=>class_exists('SensitiveDataService')?SensitiveDataService::maskJson($e->getMessage()):'erro interno',
        'trace_id'=>class_exists('RequestContext')?RequestContext::id():null
      ]));
    } catch(Throwable $ignored){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $ignored); }
  }

  private static function claimOwner(int $id): string {
    $random = function_exists('random_bytes') ? bin2hex(random_bytes(8)) : str_replace('.','',uniqid('',true));
    return substr(self::workerName().':'.$id.':'.$random, 0, 120);
  }

  public static function liberarTravados(): int {
    $pdo = Database::forTable('fila_integracao');
    $timeout = self::processingTimeoutMinutes();
    if (self::hasLeaseColumns()) {
      $st = $pdo->prepare("UPDATE fila_integracao SET status='pendente', retorno=CONCAT(COALESCE(retorno,''),'\n[Auto] Item liberado: lease/heartbeat expirado.'), locked_by=NULL, locked_at=NULL, lease_expires_at=NULL, heartbeat_at=NULL WHERE status='processando' AND (lease_expires_at < NOW() OR COALESCE(heartbeat_at,processando_desde,criado_em) < (NOW() - INTERVAL ? MINUTE))");
      $st->execute([$timeout]);
    } elseif (self::hasLockColumns()) {
      $st = $pdo->prepare("UPDATE fila_integracao SET status='pendente', retorno=CONCAT(COALESCE(retorno,''),'\n[Auto] Item liberado por timeout de processamento.'), locked_by=NULL, locked_at=NULL WHERE status='processando' AND COALESCE(processando_desde, criado_em) < (NOW() - INTERVAL ? MINUTE)");
      $st->execute([$timeout]);
    } else {
      $st = $pdo->prepare("UPDATE fila_integracao SET status='pendente', retorno=CONCAT(COALESCE(retorno,''),'\n[Auto] Item liberado por timeout de processamento.') WHERE status='processando' AND COALESCE(processando_desde, criado_em) < (NOW() - INTERVAL ? MINUTE)");
      $st->execute([$timeout]);
    }
    return $st->rowCount();
  }

  public static function heartbeat(int $id, ?string $owner=null, ?string $type=null): bool {
    if ($id <= 0) return false;
    try {
      $lease = self::leaseMinutes($type);
      if (self::hasLeaseColumns()) {
        if (trim((string)$owner) === '') return false;
        $st = Database::forTable('fila_integracao')->prepare("UPDATE fila_integracao SET processando_desde=NOW(), locked_at=NOW(), heartbeat_at=NOW(), lease_expires_at=DATE_ADD(NOW(), INTERVAL {$lease} MINUTE) WHERE id=? AND status='processando' AND locked_by=?");
        $st->execute([$id,$owner]);
      } elseif (self::hasLockColumns()) {
        if (trim((string)$owner) === '') return false;
        $st = Database::forTable('fila_integracao')->prepare("UPDATE fila_integracao SET processando_desde=NOW(), locked_at=NOW() WHERE id=? AND status='processando' AND locked_by=?");
        $st->execute([$id,$owner]);
      } else {
        $st = Database::forTable('fila_integracao')->prepare("UPDATE fila_integracao SET processando_desde=NOW() WHERE id=? AND status='processando'");
        $st->execute([$id]);
      }
      return $st->rowCount() === 1;
    } catch (Throwable $e) {
      self::logBestEffortFailure('heartbeat', $e, ['queue_id'=>$id]);
      return false;
    }
  }

  public static function pegarProximo(int $depth = 0): ?array {
    if ($depth > 5) return null;
    $pdo = Database::forTable('fila_integracao');
    $pdo->beginTransaction();
    try {
      $item = self::selectNextPending($pdo);
      if (!$item) { $pdo->commit(); return null; }
      $id = (int)$item['id'];
      $owner = self::claimOwner($id);
      $idempotencyKey = class_exists('EnterpriseIdempotencyGuardService') ? EnterpriseIdempotencyGuardService::keyForQueueItem($item) : null;
      $hasIdempotency = Database::columnExists('fila_integracao','idempotency_key');
      if (self::hasLeaseColumns()) {
        $lease = self::leaseMinutes((string)($item['tipo'] ?? ''));
        $sql = "UPDATE fila_integracao SET status='processando', processando_desde=NOW(), tentativas=tentativas+1, locked_by=?, locked_at=NOW(), heartbeat_at=NOW(), lease_expires_at=DATE_ADD(NOW(), INTERVAL {$lease} MINUTE)".($hasIdempotency?', idempotency_key=COALESCE(idempotency_key, ?)':'')." WHERE id=? AND status='pendente'";
        $params = $hasIdempotency ? [$owner,$idempotencyKey,$id] : [$owner,$id];
      } elseif (self::hasLockColumns()) {
        $sql = "UPDATE fila_integracao SET status='processando', processando_desde=NOW(), tentativas=tentativas+1, locked_by=?, locked_at=NOW()".($hasIdempotency?', idempotency_key=COALESCE(idempotency_key, ?)':'')." WHERE id=? AND status='pendente'";
        $params = $hasIdempotency ? [$owner,$idempotencyKey,$id] : [$owner,$id];
      } else {
        $sql = "UPDATE fila_integracao SET status='processando', processando_desde=NOW(), tentativas=tentativas+1 WHERE id=? AND status='pendente'";
        $params = [$id];
      }
      $st=$pdo->prepare($sql); $st->execute($params);
      if ($st->rowCount() !== 1) { if($pdo->inTransaction()) $pdo->rollBack(); return null; }
      $item['tentativas'] = (int)$item['tentativas'] + 1;
      $item['idempotency_key'] = $idempotencyKey;
      $item['locked_by'] = self::hasLockColumns() ? $owner : null;
      $pdo->commit();

      $claim = class_exists('EnterpriseIdempotencyGuardService') ? EnterpriseIdempotencyGuardService::claimQueueItem($item) : ['allowed'=>true];
      if (empty($claim['allowed'])) {
        if (!empty($claim['guard_failure'])) EnterpriseIdempotencyGuardService::markGuardFailureQueueItem($item, $claim);
        else EnterpriseIdempotencyGuardService::markIgnoredQueueItem($item, $claim);
        return self::pegarProximo($depth + 1);
      }
      try { if (class_exists('IntegrationEventService')) IntegrationEventService::onQueueClaimed($item); } catch (Throwable $e) { self::logBestEffortFailure('integration_event_claimed', $e, ['queue_id'=>$id]); }
      return $item;
    } catch(Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      throw $e;
    }
  }

  private static function selectNextPending(PDO $pdo): ?array {
    $empresa = IntegrationTenantService::boundEmpresaId();
    $base = "SELECT * FROM fila_integracao WHERE empresa_id=".(int)$empresa." AND status='pendente' AND (proxima_tentativa IS NULL OR proxima_tentativa <= NOW()) ORDER BY FIELD(prioridade,'critica','alta','normal','baixa'), id ASC LIMIT 1";
    $cfg = class_exists('App') ? App::config() : [];
    if (!empty($cfg['enterprise']['queue_skip_locked_enabled'])) {
      try { $row = $pdo->query($base . ' FOR UPDATE SKIP LOCKED')->fetch(); return $row ?: null; }
      catch (Throwable $e) {
        static $skipLockedWarningLogged = false;
        if (!$skipLockedWarningLogged) {
          $skipLockedWarningLogged = true;
          self::logBestEffortFailure('skip_locked_fallback', $e, ['fallback'=>'FOR UPDATE']);
        }
      }
    }
    $row = $pdo->query($base . ' FOR UPDATE')->fetch();
    return $row ?: null;
  }

  public static function marcarResultado(int $id, bool $sucesso, array $retorno, ?string $codigoErro=null, ?string $owner=null): bool {
    $pdo = Database::forTable('fila_integracao');
    $row = $pdo->prepare('SELECT * FROM fila_integracao WHERE id=? LIMIT 1');
    $row->execute([$id]);
    $itemAtual = $row->fetch() ?: [];
    if (!$itemAtual) return false;
    if (self::hasLockColumns()) {
      if (trim((string)$owner) === '' || (string)($itemAtual['status'] ?? '') !== 'processando' || !hash_equals((string)($itemAtual['locked_by'] ?? ''), (string)$owner)) {
        self::logBestEffortFailure('stale_worker_result', new RuntimeException('Resultado descartado: worker não possui mais o item.'), ['queue_id'=>$id]);
        return false;
      }
    }
    $decision = class_exists('QueueRetryPolicyEnterpriseService')
      ? QueueRetryPolicyEnterpriseService::decide($itemAtual, $sucesso, $retorno, $codigoErro)
      : ['status'=>$sucesso?'sucesso':'erro','retry'=>false,'delay_minutes'=>null,'classification'=>null];
    $status = (string)($decision['status'] ?? ($sucesso ? 'sucesso' : 'erro'));
    $proxima = null;
    if (!$sucesso && !empty($decision['retry'])) {
      $minutes = (int)($decision['delay_minutes'] ?? 5);
      // G-03: jitter aditivo, para que uma falha em lote não volte toda no mesmo segundo.
      $proxima = RetryPolicyService::proximaTentativaEm($minutes);
    }
    $retornoEnterprise = $retorno;
    if (!$sucesso && !empty($decision['classification'])) $retornoEnterprise['_classificacao'] = $decision['classification'];
    $json = json_encode($retornoEnterprise,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    if (self::hasLeaseColumns()) {
      $sql='UPDATE fila_integracao SET status=?, retorno=?, codigo_erro=?, proxima_tentativa=?, processado_em=NOW(), processando_desde=NULL, locked_by=NULL, locked_at=NULL, lease_expires_at=NULL, heartbeat_at=NULL WHERE id=? AND status=\'processando\' AND locked_by=?';
      $params=[$status,$json,$codigoErro,$proxima,$id,$owner];
    } elseif (self::hasLockColumns()) {
      $sql='UPDATE fila_integracao SET status=?, retorno=?, codigo_erro=?, proxima_tentativa=?, processado_em=NOW(), processando_desde=NULL, locked_by=NULL, locked_at=NULL WHERE id=? AND status=\'processando\' AND locked_by=?';
      $params=[$status,$json,$codigoErro,$proxima,$id,$owner];
    } else {
      $sql='UPDATE fila_integracao SET status=?, retorno=?, codigo_erro=?, proxima_tentativa=?, processado_em=NOW(), processando_desde=NULL WHERE id=? AND status=\'processando\'';
      $params=[$status,$json,$codigoErro,$proxima,$id];
    }
    $st=$pdo->prepare($sql); $st->execute($params);
    if ($st->rowCount() !== 1) return false;
    PayloadSnapshotService::registrar($id, $sucesso ? 'resposta' : 'erro', $retornoEnterprise, ['referencia'=>$itemAtual['referencia'] ?? null, 'origem'=>$itemAtual['tipo'] ?? null, 'trace_id'=>$itemAtual['trace_id'] ?? RequestContext::id()]);
    try { if (class_exists('IntegrationEventService')) IntegrationEventService::onQueueFinished($id, $sucesso, $retornoEnterprise, $codigoErro); } catch (Throwable $e) { self::logBestEffortFailure('integration_event_finished', $e, ['queue_id'=>$id]); }
    if (!$sucesso && $status === 'falha_definitiva') DeadLetterQueueService::enviar($itemAtual, $retornoEnterprise, $codigoErro, 'Limite de tentativas atingido ou erro não retryable pela política enterprise.');
    return true;
  }

  public static function reprocessar(int $id, string $motivo='Reprocessamento manual'): bool {
    if ($id <= 0) return false;
    $pdo = Database::forTable('fila_integracao');
    $pdo->beginTransaction();
    $previous=[]; $ok=false; $reason='';
    try {
      $st=$pdo->prepare('SELECT * FROM fila_integracao WHERE id=? LIMIT 1 FOR UPDATE');
      $st->execute([$id]);
      $previous=$st->fetch() ?: [];
      if (!$previous || !TenantScopeService::assertRow('fila_integracao',$previous,'reprocessar')) { $reason='Item não encontrado ou fora da empresa autorizada.'; $pdo->rollBack(); }
      else {
        $status=(string)($previous['status'] ?? '');
        $eligible=in_array($status,['erro','falha_definitiva','ignorado'],true);
        if ($status==='processando') {
          $leaseTs=!empty($previous['lease_expires_at'])?strtotime((string)$previous['lease_expires_at']):false;
          $heartbeatBase=$previous['heartbeat_at'] ?? $previous['processando_desde'] ?? $previous['criado_em'] ?? null;
          $timeoutTs=$heartbeatBase?strtotime((string)$heartbeatBase)+(self::processingTimeoutMinutes()*60):false;
          $eligible=($leaseTs!==false && $leaseTs<time()) || ($timeoutTs!==false && $timeoutTs<time());
          if(!$eligible)$reason='Item está sendo processado por worker ativo; reprocessamento bloqueado para evitar duplicidade.';
        }
        if(!$eligible && $reason==='')$reason='Estado não elegível para reprocessamento.';
        if($eligible){
          try {
            if (Database::tableExists('fila_reprocessamento_historico')) {
              $hist=$pdo->prepare('INSERT INTO fila_reprocessamento_historico(fila_id,status_anterior,tentativas_anteriores,codigo_erro_anterior,retorno_anterior,locked_by_anterior,solicitado_por,motivo,trace_id) VALUES(?,?,?,?,?,?,?,?,?)');
              $hist->execute([$id,$status,(int)($previous['tentativas']??0),$previous['codigo_erro']??null,$previous['retorno']??null,$previous['locked_by']??null,Auth::user()['id']??null,substr($motivo,0,500),class_exists('RequestContext')?RequestContext::id():null]);
            }
          } catch(Throwable $e){ self::logBestEffortFailure('reprocess_history', $e, ['queue_id'=>$id]); }
          $sets="status='pendente', tentativas=0, proxima_tentativa=NULL, retorno=NULL, codigo_erro=NULL, processado_em=NULL, processando_desde=NULL";
          if(self::hasLockColumns())$sets.=', locked_by=NULL, locked_at=NULL';
          if(self::hasLeaseColumns())$sets.=', lease_expires_at=NULL, heartbeat_at=NULL';
          $up=$pdo->prepare("UPDATE fila_integracao SET {$sets} WHERE id=? AND status=?");
          $up->execute([$id,$status]);
          $ok=$up->rowCount()===1;
          if($ok)$pdo->commit();else{$reason='Estado alterado concorrentemente.';$pdo->rollBack();}
        } elseif($pdo->inTransaction()) $pdo->rollBack();
      }
    } catch(Throwable $e){ if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
    try { Audit::event('fila.reprocessar',$ok?'sucesso':'alerta',['entidade'=>'fila_integracao','entidade_id'=>$id,'mensagem'=>$ok?'Item reenviado manualmente com novo ciclo de tentativas.':$reason,'contexto'=>['estado_anterior'=>$previous['status']??null,'tentativas_anteriores'=>(int)($previous['tentativas']??0),'codigo_erro_anterior'=>$previous['codigo_erro']??null]]); } catch(Throwable $e){ self::logBestEffortFailure('reprocess_audit',$e,['queue_id'=>$id]); }
    return $ok;
  }

  private static function workerName(): string {
    if (PHP_SAPI === 'cli') return 'cli:'.(basename($_SERVER['argv'][0] ?? 'worker')).':'.(function_exists('getmypid')?getmypid():'0');
    return 'web:'.substr(session_id() ?: (class_exists('RequestContext')?RequestContext::id():'request'),0,32);
  }
}
