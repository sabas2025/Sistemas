<?php
class IntegrationEventService {
  public static function onQueueClaimed(array $item): void {
    try {
      if (!Database::tableExists('integration_events')) return;
      $tipo = (string)($item['tipo'] ?? 'desconhecido');
      $ref = (string)($item['referencia'] ?? ($item['id'] ?? ''));
      $payload = (string)($item['payload'] ?? '');
      $key = (string)($item['idempotency_key'] ?? '');
      if ($key === '') $key = IdempotencyService::key('fila_integracao', $ref ?: (string)($item['id'] ?? ''), $tipo, $payload);
      IdempotencyService::claim($key, 'fila_integracao', $ref, $tipo, $payload, $item['trace_id'] ?? null);
      $sourceTarget = self::sourceTarget($tipo);
      $eventUuid = 'evt_'.hash('sha256', 'fila:'.($item['id'] ?? '').':'.$key);
      Database::forTable('integration_events')->prepare("INSERT INTO integration_events(event_uuid,fila_id,idempotency_key,source_system,target_system,entity_type,entity_id,operation,status,payload_hash,attempts,trace_id,metadata_json) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE status='processando', attempts=VALUES(attempts), updated_at=NOW(), trace_id=VALUES(trace_id)")
        ->execute([$eventUuid,(int)($item['id'] ?? 0),$key,$sourceTarget[0],$sourceTarget[1],self::entityType($tipo),$ref,$tipo,'processando',hash('sha256',$payload),(int)($item['tentativas'] ?? 0),$item['trace_id'] ?? (class_exists('RequestContext')?RequestContext::id():null),json_encode(['prioridade'=>$item['prioridade'] ?? null,'categoria'=>$item['categoria'] ?? null],JSON_UNESCAPED_UNICODE)]);
      self::correlacionar($item['trace_id'] ?? null, $tipo, $ref, (int)($item['id'] ?? 0), $sourceTarget[0], 'fila.claimed', ['idempotency_key'=>$key, 'tentativas'=>(int)($item['tentativas'] ?? 0)]);
      if (Database::columnExists('fila_integracao','idempotency_key')) {
        TenantScopeService::run('fila_integracao', 'UPDATE fila_integracao SET idempotency_key=COALESCE(idempotency_key, ?) WHERE id=?', [$key,(int)($item['id'] ?? 0)]);
      }
    } catch (Throwable $e) { try { Audit::exception($e,'integration_event.claim.erro'); } catch(Throwable $ignored){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $ignored); } }
  }

  public static function onQueueFinished(int $filaId, bool $success, array $retorno = [], ?string $codigoErro = null): void {
    try {
      if (!Database::tableExists('integration_events')) return;
      $responseHash = hash('sha256', json_encode($retorno, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
      $status = $success ? 'sucesso' : 'erro';
      Database::forTable('integration_events')->prepare('UPDATE integration_events SET status=?, response_hash=?, error_code=?, error_message=?, finished_at=NOW(), updated_at=NOW() WHERE fila_id=? ORDER BY id DESC LIMIT 1')
        ->execute([$status,$responseHash,$codigoErro,$success?null:substr(json_encode($retorno,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),0,4000),$filaId]);
      $st = Database::forTable('integration_events')->prepare('SELECT idempotency_key, trace_id, source_system, operation, entity_id FROM integration_events WHERE fila_id=? ORDER BY id DESC LIMIT 1');
      $st->execute([$filaId]);
      $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
      $key = (string)($row['idempotency_key'] ?? '');
      if ($key !== '') $success ? IdempotencyService::markCompleted($key,$retorno) : IdempotencyService::markFailed($key,$retorno);
      self::correlacionar($row['trace_id'] ?? null, (string)($row['operation'] ?? ''), (string)($row['entity_id'] ?? ''), $filaId, (string)($row['source_system'] ?? ''), 'fila.'.$status, ['success'=>$success, 'codigo_erro'=>$codigoErro]);
    } catch (Throwable $e) { try { Audit::exception($e,'integration_event.finish.erro'); } catch(Throwable $ignored){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $ignored); } }
  }


  public static function onQueueIgnored(array $item, array $claim): void {
    try {
      if (!Database::tableExists('integration_events')) return;
      $tipo = (string)($item['tipo'] ?? 'desconhecido');
      $ref = (string)($item['referencia'] ?? ($item['id'] ?? ''));
      $payload = (string)($item['payload'] ?? '');
      $key = (string)($claim['key'] ?? ($item['idempotency_key'] ?? ''));
      if ($key === '') $key = IdempotencyService::key('fila_integracao', $ref ?: (string)($item['id'] ?? ''), $tipo, $payload);
      $sourceTarget = self::sourceTarget($tipo);
      $eventUuid = 'evt_'.hash('sha256', 'ignorado:'.($item['id'] ?? '').':'.$key);
      Database::forTable('integration_events')->prepare("INSERT INTO integration_events(event_uuid,fila_id,idempotency_key,source_system,target_system,entity_type,entity_id,operation,status,payload_hash,attempts,trace_id,metadata_json,finished_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE status='ignorado', finished_at=NOW(), updated_at=NOW(), metadata_json=VALUES(metadata_json)")
        ->execute([$eventUuid,(int)($item['id'] ?? 0),$key,$sourceTarget[0],$sourceTarget[1],self::entityType($tipo),$ref,$tipo,'ignorado',hash('sha256',$payload),(int)($item['tentativas'] ?? 0),$item['trace_id'] ?? (class_exists('RequestContext')?RequestContext::id():null),json_encode(['claim'=>$claim,'prioridade'=>$item['prioridade'] ?? null],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
      self::correlacionar($item['trace_id'] ?? null, $tipo, $ref, (int)($item['id'] ?? 0), $sourceTarget[0], 'fila.ignorado', ['motivo'=>$claim['reason'] ?? ($claim['motivo'] ?? null)]);
    } catch (Throwable $e) { try { Audit::exception($e,'integration_event.ignored.erro'); } catch(Throwable $ignored){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $ignored); } }
  }


  public static function recent(int $limit = 50): array {
    try {
      if (!Database::tableExists('integration_events')) return [];
      $st = Database::forTable('integration_events')->prepare('SELECT * FROM integration_events ORDER BY id DESC LIMIT ?');
      $st->bindValue(1, max(1,min(200,$limit)), PDO::PARAM_INT);
      $st->execute();
      return $st->fetchAll();
    } catch (Throwable $e) { return []; }
  }

  private static function sourceTarget(string $tipo): array {
    return match($tipo) {
      'baixa_estoque_vsm','pedido_tiny_para_vsm' => ['tiny','vsm'],
      'produto_vsm_para_tiny','produto_vsm_atualizar_tiny','produto_vsm_estoque_para_tiny','produto_vsm_status_para_tiny','pedido_vsm' => ['vsm','tiny'],
      default => ['hub','hub']
    };
  }

  private static function entityType(string $tipo): string {
    if (str_contains($tipo,'produto')) return 'produto';
    if (str_contains($tipo,'estoque')) return 'estoque';
    if (str_contains($tipo,'pedido')) return 'pedido';
    if (str_contains($tipo,'fiscal') || str_contains($tipo,'nfe') || str_contains($tipo,'xml')) return 'fiscal';
    return 'integracao';
  }

  /**
   * Grava uma linha de correlação (evento_correlacao) para o item de fila (Fase 11 — O11-1).
   * O eixo é o trace de ORIGEM (T1, carimbado no item pelo webhook); o trace do worker (T2) vai
   * em `detalhes` quando difere, amarrado pelo `fila_id` — assim a linha única carrega as duas
   * pontas do fluxo assíncrono. Best-effort: o EventCorrelationService nunca lança.
   */
  private static function correlacionar(?string $traceOrigem, string $tipo, string $ref, int $filaId, string $origem, string $tipoEvento, array $detalhes=[]): void {
    if (!class_exists('EventCorrelationService')) return;
    $entity = self::entityType($tipo);
    $traceWorker = class_exists('RequestContext') ? RequestContext::id() : null;
    if ($traceWorker !== null && $traceWorker !== $traceOrigem) $detalhes['trace_worker'] = $traceWorker;
    $detalhes['entity_type'] = $entity;
    $detalhes['operation'] = $tipo;
    EventCorrelationService::registrar([
      'trace_id'    => ($traceOrigem !== null && $traceOrigem !== '') ? $traceOrigem : $traceWorker,
      'origem'      => $origem,
      'tipo_evento' => $tipoEvento,
      'pedido_id'   => $entity === 'pedido' ? $ref : null,
      'produto_sku' => ($entity === 'produto' || $entity === 'estoque') ? $ref : null,
      'fila_id'     => $filaId,
      'detalhes'    => $detalhes,
    ]);
  }
}
