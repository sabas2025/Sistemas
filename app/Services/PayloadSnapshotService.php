<?php
class PayloadSnapshotService {
  public static function registrar(?int $filaId, string $etapa, $conteudo, array $extra=[]): void {
    try {
      $original = is_string($conteudo) ? $conteudo : json_encode($conteudo, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
      $original = (string)$original;
      $json = class_exists('SensitiveDataService') ? SensitiveDataService::sanitizeForStorage($conteudo, 200000) : substr($original,0,200000);
      $pdo = Database::forTable('payload_snapshots');
      $pdo->prepare('INSERT INTO payload_snapshots(trace_id,fila_id,origem,destino,referencia,etapa,conteudo,hash_conteudo) VALUES(?,?,?,?,?,?,?,?)')
        ->execute([
          $extra['trace_id'] ?? RequestContext::id(),
          $filaId,
          $extra['origem'] ?? null,
          $extra['destino'] ?? null,
          $extra['referencia'] ?? null,
          $etapa,
          $json,
          hash('sha256', $original)
        ]);
    } catch(Throwable $e) { Logger::log('snapshot','Falha ao registrar snapshot',['erro'=>$e->getMessage()],'erro'); }
  }
}
