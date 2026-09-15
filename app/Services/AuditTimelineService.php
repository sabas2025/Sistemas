<?php
class AuditTimelineService {
  public static function registrar(string $fase, string $status='info', array $dados=[]): void {
    try { $pdo=Database::forTable('auditoria_timeline'); $pdo->prepare("INSERT INTO auditoria_timeline(trace_id, fase, status, entidade, entidade_id, detalhes, criado_em) VALUES(?,?,?,?,?,?,NOW())")->execute([RequestContext::id(),$fase,$status,$dados['entidade']??null,$dados['entidade_id']??null,Audit::json($dados)]); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
  }
  public static function porTrace(string $trace): array { $st=Database::forTable('auditoria_timeline')->prepare("SELECT * FROM auditoria_timeline WHERE trace_id=? ORDER BY id ASC"); $st->execute([$trace]); return $st->fetchAll(); }
}
