<?php
class BackupSignatureService {
  private static function key(): string {
    $cfg = App::config()['security'] ?? [];
    $k = (string)($cfg['backup_signature_key'] ?? '');
    if ($k === '') $k = (string)($cfg['encryption_key'] ?? '');
    if ((class_exists('App') && App::isProduction()) && strlen($k) < 32) {
      throw new RuntimeException('Chave HMAC de backup ausente/fraca em produção. Configure security.backup_signature_key com 32+ caracteres.');
    }
    if ($k === '') $k = 'hub-backup-dev-key-change-me';
    return hash('sha256', $k, true);
  }
  public static function signaturePath(string $file): string { return $file.'.sig.json'; }
  /** Proveniências reconhecidas (A-11). */
  public const PROV_LOCAL = 'local_generated';
  public const PROV_IMPORTED = 'imported_untrusted';

  /**
   * Reauditoria 2026-09-14 (achado A-11): a assinatura provava apenas que ESTE Hub ingeriu o
   * arquivo, não que ele foi gerado por origem confiável - um SQL/ZIP externo passava a ser
   * aceito como "confiável" para restauração após a validação estrutural. A proveniência agora
   * é gravada DENTRO do payload assinado, portanto coberta pelo HMAC e não falsificável sem a
   * chave, e o restore trata importados de forma distinta.
   */
  public static function sign(string $file, string $provenance = self::PROV_LOCAL): void {
    if (!is_file($file)) throw new RuntimeException('Arquivo de backup inexistente para assinatura.');
    if (!in_array($provenance, [self::PROV_LOCAL, self::PROV_IMPORTED], true)) {
      throw new InvalidArgumentException('Proveniência de backup desconhecida: '.$provenance);
    }
    $payload = [
      'version'=>2,
      'file'=>basename($file),
      'sha256'=>hash_file('sha256', $file),
      'size'=>filesize($file) ?: 0,
      'created_at'=>date('c'),
      'provenance'=>$provenance,
      'trace_id'=>class_exists('RequestContext') ? RequestContext::id() : null,
    ];
    $data = json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    $payload['hmac'] = hash_hmac('sha256', $data, self::key());
    file_put_contents(self::signaturePath($file), json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
  }
  /** @return array<string,mixed> payload assinado, para o chamador ler a proveniência (A-11). */
  public static function verify(string $file): array {
    $sigFile = self::signaturePath($file);
    if (!is_file($sigFile)) throw new RuntimeException('Restore bloqueado: assinatura do backup ausente.');
    $sig = json_decode((string)file_get_contents($sigFile), true);
    if (!is_array($sig) || empty($sig['hmac']) || empty($sig['sha256'])) throw new RuntimeException('Restore bloqueado: assinatura inválida.');
    if (!hash_equals((string)$sig['sha256'], hash_file('sha256', $file))) throw new RuntimeException('Restore bloqueado: SHA-256 do backup não confere com assinatura.');
    $hmac = (string)$sig['hmac']; unset($sig['hmac']);
    $data = json_encode($sig, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    $calc = hash_hmac('sha256', $data, self::key());
    if (!hash_equals($hmac, $calc)) throw new RuntimeException('Restore bloqueado: HMAC da assinatura inválido.');
    return $sig;
  }

  /**
   * Proveniência assinada do arquivo. Assinaturas v1 (anteriores ao achado A-11) não a
   * declaram; nesse caso o nome importado_* decide, e na dúvida tratamos como importado -
   * falhar para o lado mais restritivo é o comportamento correto aqui.
   */
  public static function provenance(string $file): string {
    try { $sig = self::verify($file); } catch (Throwable $e) { return self::PROV_IMPORTED; }
    $declared = (string)($sig['provenance'] ?? '');
    if ($declared !== '') return $declared;
    return str_starts_with(basename($file), 'importado_') ? self::PROV_IMPORTED : self::PROV_LOCAL;
  }
}
