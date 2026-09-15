<?php
/** V104.18 - Cliente de servidor licenciador remoto em modo seguro. */
class LicenseServerClientService {
  public static function status(): array {
    $cfg = App::config()['commercial'] ?? [];
    $url = trim((string)($cfg['license_server_url'] ?? ''));
    $enabled = !empty($cfg['license_server_enabled']);
    if(!$enabled) { $r=['status'=>'alerta','mensagem'=>'Servidor licenciador remoto desativado. Licença local HMAC continua válida para uso interno/homologação.','cache'=>class_exists('LicenseRemoteCacheService')?LicenseRemoteCacheService::latest():null]; if(class_exists('LicenseRemoteCacheService')) LicenseRemoteCacheService::save($r['status'],$r['mensagem'],$r); return $r; }
    if($url === '') { $r=['status'=>'erro','mensagem'=>'license_server_enabled=true, mas license_server_url não foi configurado.']; if(class_exists('LicenseRemoteCacheService')) LicenseRemoteCacheService::save($r['status'],$r['mensagem'],$r); return $r; }
    if(!preg_match('#^https://#i', $url)) { $r=['status'=>'erro','mensagem'=>'Servidor licenciador deve usar HTTPS.']; if(class_exists('LicenseRemoteCacheService')) LicenseRemoteCacheService::save($r['status'],$r['mensagem'],$r); return $r; }
    $r=['status'=>'ok','mensagem'=>'Servidor licenciador remoto configurado em HTTPS.','url'=>self::maskUrl($url),'cache'=>class_exists('LicenseRemoteCacheService')?LicenseRemoteCacheService::latest():null]; if(class_exists('LicenseRemoteCacheService')) LicenseRemoteCacheService::save($r['status'],$r['mensagem'],$r); return $r;
  }

  public static function buildCheckPayload(array $license): array {
    return [
      'hub_version' => SystemVersionService::VERSION,
      'license_hash' => substr((string)($license['license_key_hash'] ?? ''),0,16).'...',
      'documento_hash' => hash('sha256',(string)($license['documento'] ?? '')),
      'plano' => (string)($license['plano'] ?? ''),
      'ambiente' => (string)($license['ambiente'] ?? ''),
      'checked_at' => date('c'),
    ];
  }

  public static function recordCheck(string $status, string $mensagem, array $context=[]): void {
    try {
      SchemaRuntimePolicyService::requireTable('comercial_license_checks', 'checagem do servidor de licenças');
      $pdo = Database::forTable('comercial_license_checks');
      $st = $pdo->prepare('INSERT INTO comercial_license_checks(status,mensagem,contexto_json,criado_em) VALUES(?,?,?,NOW())');
      $st->execute([$status,$mensagem,json_encode(SensitiveDataService::mask($context), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
    } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
  }

  private static function maskUrl(string $url): string {
    $p=parse_url($url); if(!$p || empty($p['host'])) return 'https://***';
    return ($p['scheme'] ?? 'https').'://'.$p['host'].(!empty($p['path']) ? $p['path'] : '');
  }
}
