<?php
class EnterpriseObservabilityService {
  public static function snapshot(): array {
    $out = ['trace_id'=>class_exists('RequestContext')?RequestContext::id():null,'captured_at'=>date('c'),'cards'=>[],'workers'=>[],'quality_gates'=>[],'recent_events'=>[]];
    $out['cards'][] = self::countCard('Fila pendente','fila_integracao',"status='pendente'",'fila.pendente');
    $out['cards'][] = self::countCard('Fila em erro','fila_integracao',"status IN ('erro','falha_definitiva')",'fila.erro');
    $out['cards'][] = self::countCard('Fila morta aberta','fila_morta',"status='aberto'",'dlq.aberta');
    $out['cards'][] = self::countCard('Eventos processando','integration_events',"status='processando'",'integration_events.processando');
    $out['cards'][] = self::countCard('Eventos ignorados','integration_events',"status='ignorado'",'integration_events.ignorado');
    $out['cards'][] = self::countCard('Idempotência ativa','integration_idempotency',"status='claimed'",'idempotency.claimed');
    $out['cards'][] = self::countCard('Idempotência concluída','integration_idempotency',"status='completed'",'idempotency.completed');
    $out['workers'] = WorkerHeartbeatService::recent(20);
    $out['recent_events'] = IntegrationEventService::recent(20);
    $out['schema'] = SchemaMigrationService::status();
    self::persistSnapshot($out);
    return $out;
  }

  private static function countCard(string $title, string $table, string $where, string $key): array {
    try {
      if (!Database::tableExists($table)) return ['title'=>$title,'value'=>0,'status'=>'atencao','message'=>'Tabela ausente.','key'=>$key];
      $value = (int)Database::forTable($table)->query("SELECT COUNT(*) FROM `{$table}` WHERE {$where}")->fetchColumn();
      $status = $value === 0 ? 'ok' : ($value > 50 ? 'erro' : 'atencao');
      return ['title'=>$title,'value'=>$value,'status'=>$status,'message'=>$value===0?'Sem pendências.':'Exige acompanhamento operacional.','key'=>$key];
    } catch (Throwable $e) { if(class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e, ['metric'=>$key]); return ['title'=>$title,'value'=>0,'status'=>'erro','message'=>'Métrica indisponível. Consulte a auditoria pelo Trace ID.','key'=>$key]; }
  }

  private static function persistSnapshot(array $snapshot): void {
    try {
      if (!Database::tableExists('observability_snapshots')) return;
      foreach ($snapshot['cards'] as $card) {
        Database::forTable('observability_snapshots')->prepare('INSERT INTO observability_snapshots(metric_key,metric_value,status,tags_json,trace_id) VALUES(?,?,?,?,?)')
          ->execute([$card['key'],(float)$card['value'],$card['status'],json_encode($card,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$snapshot['trace_id']]);
      }
    } catch (Throwable $e) { if(class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e, ['operation'=>'persist_snapshot']); }
  }
}
