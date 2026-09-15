<?php
class CryptoService {
  private const PREFIX = 'encgcm::';

  private static function key(): string {
    $cfg = App::config();
    $key = trim((string)($cfg['security']['encryption_key'] ?? ''));
    $unsafe = ['', 'troque-esta-chave-apos-instalar', 'hub-vsm-tiny-local-key'];
    if (in_array($key, $unsafe, true)) {
      throw new RuntimeException('Chave de criptografia ausente ou insegura. Ajuste security.encryption_key no config/config.php antes de salvar tokens.');
    }
    return hash('sha256', $key, true);
  }

  public static function isEncrypted(?string $value): bool {
    return is_string($value) && (str_starts_with($value, self::PREFIX) || str_starts_with($value, 'enc::'));
  }

  private static function aad(?string $context=null): string { return $context ? ('hub-vsm-tiny|'.$context) : 'hub-vsm-tiny'; }

  public static function encrypt(?string $plain, ?string $context=null): ?string {
    if ($plain === null || $plain === '') return $plain;
    if (self::isEncrypted($plain)) return $plain;
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag, self::aad($context), 16);
    if ($cipher === false) throw new RuntimeException('Falha ao criptografar segredo. Verifique OpenSSL no PHP.');
    return self::PREFIX.base64_encode(json_encode([
      'v' => 2,
      'alg' => 'AES-256-GCM',
      'iv' => base64_encode($iv),
      'tag' => base64_encode($tag),
      'cipher' => base64_encode($cipher),
      'ctx' => $context ? hash('sha256', $context) : null,
    ], JSON_UNESCAPED_SLASHES));
  }

  public static function decrypt(?string $value, ?string $context=null): ?string {
    if ($value === null || $value === '') return $value;
    if (str_starts_with($value, self::PREFIX)) {
      $json = base64_decode(substr($value, strlen(self::PREFIX)), true);
      $data = $json ? json_decode($json, true) : null;
      if (!is_array($data)) return '';
      $iv = base64_decode((string)($data['iv'] ?? ''), true);
      $tag = base64_decode((string)($data['tag'] ?? ''), true);
      $cipher = base64_decode((string)($data['cipher'] ?? ''), true);
      if (!$iv || !$tag || !$cipher) return '';
      $plain = openssl_decrypt($cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag, self::aad($context));
      if ($plain === false && $context !== null) { $plain = openssl_decrypt($cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag, self::aad(null)); }
      return $plain === false ? '' : $plain;
    }
    // Compatibilidade com versões antigas AES-CBC enc:: para não quebrar instalações anteriores.
    if (str_starts_with($value, 'enc::')) {
      $cfg = App::config();
      $legacyKey = trim((string)($cfg['security']['encryption_key'] ?? '')) ?: trim((string)($cfg['security']['webhook_secret'] ?? ''));
      if ($legacyKey === '') return '';
      $raw=base64_decode(substr($value,5), true);
      if ($raw===false || strlen($raw)<17) return '';
      $iv=substr($raw,0,16); $cipher=substr($raw,16);
      $plain=openssl_decrypt($cipher, 'AES-256-CBC', hash('sha256', $legacyKey, true), OPENSSL_RAW_DATA, $iv);
      return $plain===false ? '' : $plain;
    }
    return $value;
  }
}
