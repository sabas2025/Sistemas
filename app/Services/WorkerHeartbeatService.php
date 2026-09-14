<?php
class WorkerHeartbeatService {
  public static function beat(string $workerName, string $status = 'rodando', array $metrics = [], ?string $error = null): void {
    try {
      if (!Database::tableExists('worker_heartbeats')) return;
      $pid = function_exists('getmypid') ? getmypid() : null;
      $host = gethostname() ?: php_uname('n');
      Database::forTable('worker_heartbeats')->prepare("INSERT INTO worker_heartbeats(worker_name,status,pid,host,started_at,last_seen_at,last_error,metrics_json,trace_id) VALUES(?,?,?,?,NOW(),NOW(),?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status), pid=VALUES(pid), host=VALUES(host), last_seen_at=NOW(), last_error=VALUES(last_error), metrics_json=VALUES(metrics_json), trace_id=VALUES(trace_id)")
        ->execute([$workerName,$status,$pid,$host,$error,json_encode($metrics,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),class_exists('RequestContext')?RequestContext::id():null]);
    } catch (Throwable $e) { if(class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e, ['worker'=>$workerName,'status'=>$status]); }
  }

  public static function recent(int $limit = 20): array {
    try {
      if (!Database::tableExists('worker_heartbeats')) return [];
      $st = Database::forTable('worker_heartbeats')->prepare('SELECT * FROM worker_heartbeats ORDER BY last_seen_at DESC LIMIT ?');
      $st->bindValue(1, max(1,min(100,$limit)), PDO::PARAM_INT); $st->execute(); return $st->fetchAll();
    } catch (Throwable $e) { if(class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); return []; }
  }
}
