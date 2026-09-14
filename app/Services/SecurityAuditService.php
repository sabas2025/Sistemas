<?php
class SecurityAuditService {
  public static function record(string $evento, string $nivel='info', array $data=[]): void {
    try {
      $pdo=Database::forTable('security_audit');
      $st=$pdo->prepare("INSERT INTO security_audit(trace_id, usuario_id, evento, nivel, ip, user_agent, session_id, detalhes, criado_em) VALUES(?,?,?,?,?,?,?,?,NOW())");
      $st->execute([RequestContext::id(), RequestContext::userId(), $evento, $nivel, RequestContext::ip(), RequestContext::userAgent(), session_id(), Audit::json($data)]);
    } catch(Throwable $e) { @file_put_contents(__DIR__.'/../../storage/logs/security.log','['.date('c').'] '.$evento.' '.$e->getMessage().PHP_EOL,FILE_APPEND); }
  }
  public static function recentes(int $limit=100): array {
    return Database::forTable('security_audit')->query("SELECT * FROM security_audit ORDER BY id DESC LIMIT ".(int)$limit)->fetchAll();
  }
}
