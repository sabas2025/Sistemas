<?php
class AuditIntegrityService {
  /**
   * Assina um evento de auditoria com SHA256 encadeado.
   * O hash atual considera: hash anterior + dados canônicos do evento.
   * Isso cria uma cadeia de integridade; qualquer alteração em evento antigo quebra os hashes seguintes.
   */
  public static function assinarEvento(int $auditoriaId): void {
    $pdo = Database::forTable('auditoria_eventos');
    if(!self::tableExists('auditoria_assinaturas')) return;
    $st=$pdo->prepare('SELECT * FROM auditoria_eventos WHERE id=? LIMIT 1');
    $st->execute([$auditoriaId]);
    $ev=$st->fetch();
    if(!$ev) return;

    $previousHash = self::ultimoHashAntes((int)$ev['id'], (string)($ev['trace_id'] ?? ''));
    $canonical = self::canonicalize($ev);
    $hash = hash('sha256', $previousHash . '|' . $canonical);

    self::ensureChainColumns();
    $ins=$pdo->prepare('INSERT INTO auditoria_assinaturas(auditoria_evento_id,trace_id,hash_sha256,algoritmo,hash_anterior,hash_canonico,cadeia_valida) VALUES(?,?,?,?,?,?,1)
      ON DUPLICATE KEY UPDATE hash_sha256=VALUES(hash_sha256), algoritmo=VALUES(algoritmo), hash_anterior=VALUES(hash_anterior), hash_canonico=VALUES(hash_canonico), cadeia_valida=1');
    $ins->execute([$auditoriaId,$ev['trace_id'] ?? null,$hash,'sha256-chain',$previousHash,$canonical]);
  }

  public static function assinarTrace(string $traceId): int {
    $pdo=Database::forTable('auditoria_eventos');
    $st=$pdo->prepare('SELECT id FROM auditoria_eventos WHERE trace_id=? ORDER BY id ASC');
    $st->execute([$traceId]);
    $n=0;
    foreach($st->fetchAll() as $r){ self::assinarEvento((int)$r['id']); $n++; }
    return $n;
  }

  public static function verificarTrace(string $traceId): array {
    $pdo=Database::forTable('auditoria_eventos');
    $st=$pdo->prepare('SELECT e.*, a.hash_sha256, a.hash_anterior, a.hash_canonico FROM auditoria_eventos e LEFT JOIN auditoria_assinaturas a ON a.auditoria_evento_id=e.id WHERE e.trace_id=? ORDER BY e.id ASC');
    $st->execute([$traceId]);
    $prev='GENESIS'; $ok=true; $falhas=[]; $total=0;
    foreach($st->fetchAll() as $row){
      $total++;
      $canonical=self::canonicalize($row);
      $expected=hash('sha256',$prev.'|'.$canonical);
      if(($row['hash_sha256'] ?? '') !== $expected){ $ok=false; $falhas[]=['id'=>$row['id'],'esperado'=>$expected,'atual'=>$row['hash_sha256'] ?? null]; }
      $prev=$row['hash_sha256'] ?: $expected;
    }
    return ['trace_id'=>$traceId,'total'=>$total,'integro'=>$ok,'falhas'=>$falhas];
  }

  private static function ultimoHashAntes(int $eventoId, string $traceId): string {
    try {
      $pdo=Database::forTable('auditoria_eventos');
      $st=$pdo->prepare('SELECT a.hash_sha256 FROM auditoria_assinaturas a JOIN auditoria_eventos e ON e.id=a.auditoria_evento_id WHERE e.trace_id=? AND e.id < ? ORDER BY e.id DESC LIMIT 1');
      $st->execute([$traceId,$eventoId]);
      $r=$st->fetch();
      return $r['hash_sha256'] ?? 'GENESIS';
    } catch(Throwable $e){ return 'GENESIS'; }
  }

  private static function canonicalize(array $ev): string {
    unset($ev['hash_sha256'],$ev['hash_anterior'],$ev['hash_canonico'],$ev['cadeia_valida']);
    ksort($ev);
    return json_encode($ev, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PARTIAL_OUTPUT_ON_ERROR);
  }

  private static function ensureChainColumns(): void {
    SchemaRuntimePolicyService::requireColumns('auditoria_assinaturas', ['hash_anterior','hash_canonico','cadeia_valida'], 'cadeia de integridade da auditoria');
  }

  private static function tableExists(string $table): bool { try { $st=Database::forTable($table)->prepare('SHOW TABLES LIKE ?'); $st->execute([$table]); return (bool)$st->fetch(); } catch(Throwable $e){ return false; } }
}
