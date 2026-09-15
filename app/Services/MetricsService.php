<?php
class MetricsService {
  public static function registrar(string $sistema, ?string $endpoint, ?int $httpCode, ?int $tempoMs, bool $sucesso, ?string $codigoErro=null): void {
    try {
      Database::forTable('metricas_api')->prepare('INSERT INTO metricas_api(sistema,endpoint,http_code,tempo_ms,sucesso,codigo_erro,trace_id) VALUES(?,?,?,?,?,?,?)')
        ->execute([$sistema,$endpoint,$httpCode,$tempoMs,$sucesso?1:0,$codigoErro,RequestContext::id()]);
    } catch(Throwable $e) { if(class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e, ['metric_type'=>'api']); }
  }

  public static function snapshot(string $metricKey, float $value, string $status = 'ok', array $tags = []): void {
    try {
      if (!Database::tableExists('observability_snapshots')) return;
      Database::forTable('observability_snapshots')->prepare('INSERT INTO observability_snapshots(metric_key,metric_value,status,tags_json,trace_id) VALUES(?,?,?,?,?)')
        ->execute([$metricKey,$value,$status,json_encode($tags,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),RequestContext::id()]);
    } catch(Throwable $e) { if(class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e, ['metric_type'=>'snapshot','metric_key'=>$metricKey]); }
  }
}
