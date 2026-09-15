<?php
class EnterpriseQualityGateService {
  public static function evaluate(): array {
    $checks = [];
    $schema = SchemaMigrationService::status();
    $schemaOk = $schema['status'] === 'ok' && (int)$schema['ok'] === (int)$schema['total'];
    $schemaPending = [];
    foreach (($schema['checks'] ?? []) as $check) {
      if (($check['status'] ?? 'pendente') !== 'ok') $schemaPending[] = (string)($check['item'] ?? 'schema');
    }
    $checks[] = [
      'gate'=>'schema_enterprise',
      'status'=>$schemaOk?'ok':'bloqueio',
      'score'=>$schemaOk?100:max(0, (int)round(100 * (int)$schema['ok'] / max(1, (int)$schema['total']))),
      'mensagem'=>'Contratos Enterprise (tabelas, colunas, tipos, padrões e índices): '.$schema['ok'].'/'.$schema['total'].($schemaPending ? '. Pendências: '.implode(', ', array_slice($schemaPending, 0, 8)).(count($schemaPending)>8?' e mais '.(count($schemaPending)-8):'') : ''),
      'schema_pending'=>$schemaPending,
    ];
    $obs = EnterpriseObservabilityService::snapshot();
    $dlq = 0; foreach ($obs['cards'] as $card) if (($card['key'] ?? '') === 'dlq.aberta') $dlq = (int)$card['value'];
    $checks[] = ['gate'=>'dlq_operacional','status'=>$dlq===0?'ok':($dlq>20?'bloqueio':'atencao'),'score'=>$dlq===0?100:max(30,80-$dlq),'mensagem'=>'Fila morta aberta: '.$dlq];
    $llm = LlmGatewayService::readiness();
    $checks[] = ['gate'=>'llm_governance','status'=>$llm['status']==='ok'?'ok':'atencao','score'=>$llm['status']==='ok'?100:75,'mensagem'=>'LLM isolado/desativado ou governado por políticas.'];
    foreach ($checks as $c) self::persist($c);
    $score = (int)round(array_sum(array_column($checks,'score')) / max(1,count($checks)));
    $status = in_array('bloqueio', array_column($checks,'status'), true) ? 'bloqueio' : (in_array('atencao', array_column($checks,'status'), true) ? 'atencao' : 'ok');
    return ['status'=>$status,'score'=>$score,'checks'=>$checks,'trace_id'=>class_exists('RequestContext')?RequestContext::id():null];
  }

  private static function persist(array $check): void {
    try {
      if (!Database::tableExists('enterprise_quality_gates')) return;
      Database::forTable('enterprise_quality_gates')->prepare('INSERT INTO enterprise_quality_gates(gate_key,status,score,mensagem,detalhes_json,trace_id) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status), score=VALUES(score), mensagem=VALUES(mensagem), detalhes_json=VALUES(detalhes_json), trace_id=VALUES(trace_id), updated_at=NOW()')
        ->execute([$check['gate'],$check['status'],$check['score'],$check['mensagem'],json_encode($check,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),class_exists('RequestContext')?RequestContext::id():null]);
    } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
  }
}
