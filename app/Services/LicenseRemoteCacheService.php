<?php
/** V104.19 - cache local seguro para respostas do servidor licenciador. */
class LicenseRemoteCacheService {
  public static function save(string $status, string $mensagem, array $payload=[]): void {
    try {
      SchemaRuntimePolicyService::requireTable('comercial_license_remote_cache', 'cache remoto de licença');
      $pdo=Database::forTable('comercial_license_remote_cache');
      $st=$pdo->prepare('INSERT INTO comercial_license_remote_cache(status,mensagem,payload_hash,payload_json,criado_em) VALUES(?,?,?,?,NOW())');
      $json=json_encode(SensitiveDataService::mask($payload), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
      $st->execute([$status,$mensagem,hash('sha256',$json ?: ''),$json]);
    } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
  }
  public static function latest(): ?array {
    try { SchemaRuntimePolicyService::requireTable('comercial_license_remote_cache', 'cache remoto de licença'); return Database::forTable('comercial_license_remote_cache')->query('SELECT id,status,mensagem,payload_hash,criado_em FROM comercial_license_remote_cache ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: null; } catch(Throwable $e){ if(class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); return null; }
  }
}
