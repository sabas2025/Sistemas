<?php
class AuditDailySignatureService {
  public static function ensureSchema(): void {
    SchemaRuntimePolicyService::requireTable('audit_daily_signatures', 'assinatura diária da auditoria');
  }

  public static function sign(?string $date=null): array {
    self::ensureSchema();
    $date = $date ?: date('Y-m-d');
    $pdo=Database::forTable('auditoria_eventos');
    $rows=[];
    try {
      $st=$pdo->prepare('SELECT id,trace_id,tipo,nivel,usuario_id,ip,created_at FROM auditoria_eventos WHERE DATE(created_at)=? ORDER BY id ASC');
      $st->execute([$date]); $rows=$st->fetchAll(PDO::FETCH_ASSOC);
    } catch(Throwable $e) {
      try { $st=$pdo->prepare('SELECT id,trace_id,evento AS tipo,nivel,usuario_id,ip,criado_em AS created_at FROM auditoria_eventos WHERE DATE(criado_em)=? ORDER BY id ASC'); $st->execute([$date]); $rows=$st->fetchAll(PDO::FETCH_ASSOC); } catch(Throwable $e2) { $rows=[]; }
    }
    $canonical=json_encode($rows, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $sha=hash('sha256', $canonical);
    $key=self::key();
    $hmac=hash_hmac('sha256', $date.'.'.$sha.'.'.count($rows), $key);
    $dir=dirname(__DIR__,2).'/storage/audit-signatures'; if(!is_dir($dir)) @mkdir($dir,0775,true);
    $file='audit-signature-'.$date.'.sig.json';
    $payload=['audit_date'=>$date,'event_count'=>count($rows),'first_audit_id'=>$rows[0]['id'] ?? null,'last_audit_id'=>$rows[count($rows)-1]['id'] ?? null,'sha256'=>$sha,'hmac'=>$hmac,'trace_id'=>RequestContext::id(),'created_at'=>date('c')];
    file_put_contents($dir.'/'.$file, json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    Database::forTable('audit_daily_signatures')->prepare('INSERT INTO audit_daily_signatures(audit_date,first_audit_id,last_audit_id,event_count,sha256,hmac,signature_file,trace_id) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE first_audit_id=VALUES(first_audit_id),last_audit_id=VALUES(last_audit_id),event_count=VALUES(event_count),sha256=VALUES(sha256),hmac=VALUES(hmac),signature_file=VALUES(signature_file),trace_id=VALUES(trace_id)')
      ->execute([$date,$payload['first_audit_id'],$payload['last_audit_id'],$payload['event_count'],$sha,$hmac,$file,RequestContext::id()]);
    SecurityEventService::log('auditoria.assinatura_diaria','baixo','Assinatura diária de auditoria gerada.', ['date'=>$date,'event_count'=>count($rows),'file'=>$file]);
    return $payload + ['signature_file'=>$file];
  }

  public static function latest(int $limit=10): array {
    self::ensureSchema();
    try { return Database::forTable('audit_daily_signatures')->query('SELECT * FROM audit_daily_signatures ORDER BY audit_date DESC LIMIT '.max(1,min(60,$limit)))->fetchAll(); }
    catch(Throwable $e){ return []; }
  }
  private static function key(): string { $sec=App::config()['security'] ?? []; $k=(string)($sec['audit_daily_signature_key'] ?? $sec['fim_manifest_hmac_key'] ?? $sec['backup_signature_key'] ?? $sec['encryption_key'] ?? ''); return hash('sha256', $k !== '' ? $k : 'dev-audit-key', true); }
}
