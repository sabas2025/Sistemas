<?php
class TokenVaultService {
  public static function ensureSchema(): void {
    SchemaRuntimePolicyService::requireTable('token_vault', 'cofre de tokens');
  }

  public static function store(string $provider,string $ambiente,string $type,string $plain,?string $expiresAt=null): void {
    if (trim($plain)==='') return;
    self::ensureSchema();
    $hash=hash_hmac('sha256',$provider.'|'.$ambiente.'|'.$type.'|'.$plain,self::hashKey());
    $pdo=Database::forTable('token_vault');
    $pdo->prepare('UPDATE token_vault SET active=0, rotated_at=NOW() WHERE provider=? AND ambiente=? AND token_type=? AND active=1')->execute([$provider,$ambiente,$type]);
    $pdo->prepare('INSERT INTO token_vault(provider,ambiente,token_type,token_hash,ciphertext,version,expires_at,trace_id) VALUES(?,?,?,?,?,?,?,?)')
      ->execute([$provider,$ambiente,$type,$hash,CryptoService::encrypt($plain,'vault:'.$provider.':'.$ambiente.':'.$type),1,$expiresAt,RequestContext::id()]);
  }

  public static function getActive(string $provider,string $ambiente,string $type): string {
    $row = self::getActiveRow($provider,$ambiente,$type);
    if (!$row) return '';
    if (!empty($row['expires_at']) && strtotime((string)$row['expires_at']) <= time()+60) return '';
    return CryptoService::decrypt((string)$row['ciphertext'],'vault:'.$provider.':'.$ambiente.':'.$type) ?: '';
  }

  public static function getActiveRow(string $provider,string $ambiente,string $type): ?array {
    self::ensureSchema();
    try {
      $st=Database::forTable('token_vault')->prepare("SELECT * FROM token_vault WHERE provider=? AND ambiente=? AND token_type=? AND active=1 ORDER BY id DESC LIMIT 1");
      $st->execute([$provider,$ambiente,$type]);
      $row=$st->fetch();
      return $row ?: null;
    } catch(Throwable $e){ if(class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e, ['provider'=>$provider,'ambiente'=>$ambiente,'type'=>$type]); return null; }
  }

  public static function rotateIfExpiring(string $provider,string $ambiente,string $type,int $seconds=3600): bool {
    $row=self::getActiveRow($provider,$ambiente,$type);
    if (!$row || empty($row['expires_at'])) return false;
    return strtotime((string)$row['expires_at']) <= time()+$seconds;
  }

  public static function health(): array {
    self::ensureSchema();
    try { $rows=Database::forTable('token_vault')->query('SELECT provider,ambiente,token_type,COUNT(*) total,SUM(active=1) ativos,MAX(created_at) ultimo,MAX(expires_at) expira FROM token_vault GROUP BY provider,ambiente,token_type')->fetchAll(); }
    catch(Throwable $e){ $rows=[]; }
    return ['rows'=>$rows,'ok'=>true,'gerado_em'=>date('Y-m-d H:i:s')];
  }

  private static function hashKey(): string {
    $sec=App::config()['security'] ?? [];
    $key=(string)($sec['token_vault_hmac_key'] ?? ($sec['encryption_key'] ?? ($sec['audit_daily_signature_key'] ?? '')));
    return $key !== '' ? $key : 'hub-token-vault-local-key';
  }
}
