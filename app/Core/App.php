<?php
class App {
  public static function cspNonce(): string {
    if (empty($GLOBALS['HUB_CSP_NONCE'])) $GLOBALS['HUB_CSP_NONCE'] = base64_encode(random_bytes(16));
    return $GLOBALS['HUB_CSP_NONCE'];
  }
  public static function config(): array {
    static $cfg = null;
    if ($cfg === null) {
      $active = __DIR__.'/../../config/config.php';
      $example = __DIR__.'/../../config/config.example.php';
      if (!is_file($active)) {
        if (PHP_SAPI === 'cli' && is_file($example)) $active = $example;
        else throw new RuntimeException('config/config.php ausente. Preserve o arquivo na atualização ou execute public/install.php.');
      }
      $cfg = require $active;
      if (!is_array($cfg)) throw new RuntimeException('Arquivo de configuração inválido.');
      // Metadados efetivos acompanham o código; preserva o arquivo e todos os segredos existentes.
      $cfg['app_version'] = SystemVersionService::artifactVersion();
      $cfg['release_date'] = SystemVersionService::RELEASE_DATE;
    }
    return $cfg;
  }
  public static function env(): string { return strtolower((string)(self::config()['app_env'] ?? 'local')); }
  public static function isLocal(): bool { return in_array(self::env(), ['local','dev','development'], true); }
  public static function isProduction(): bool { return in_array(self::env(), ['production','producao','prod'], true); }
  public static function isPublicHost(): bool {
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '' || str_contains($host, 'localhost') || str_starts_with($host, '127.') || str_starts_with($host, '192.168.') || str_starts_with($host, '10.')) return false;
    return true;
  }
  public static function enforceProductionSafety(): void {
    if (PHP_SAPI === 'cli') return;
    if (self::isLocal() && self::isPublicHost()) {
      error_log('Hub bloqueado: app_env local em host público '.($_SERVER['HTTP_HOST'] ?? ''));
      http_response_code(503);
      exit('Sistema bloqueado por segurança: configure app_env=production no servidor público.');
    }

    // P0-08 (reauditoria 2026-08-23): Host e X-Forwarded-Proto não são mais confiados
    // isoladamente para decisões de segurança em produção pública.
    if (self::isProduction() && self::isPublicHost() && class_exists('TrustedProxyService')) {
      if (!TrustedProxyService::isHostAllowed()) {
        error_log('Hub bloqueado: Host não permitido em produção: '.($_SERVER['HTTP_HOST'] ?? ''));
        http_response_code(421);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('Host não permitido.');
      }
      $sec = self::config()['security'] ?? [];
      $forceHttps = array_key_exists('force_https', $sec) ? !empty($sec['force_https']) : true;
      if ($forceHttps && !TrustedProxyService::isHttps() && PHP_SAPI !== 'cli') {
        $host = TrustedProxyService::currentHost() ?: ($_SERVER['HTTP_HOST'] ?? '');
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        if ($host !== '' && !headers_sent()) {
          header('Location: https://'.$host.$uri, true, 301);
          exit;
        }
      }
    }

    // V104.26: fail-safe para produção pública. Evita rodar com config padrão/segredos vazios,
    // mas não interfere no install.php nem em ambiente local/XAMPP.
    if (self::isProduction() && self::isPublicHost()) {
      $cfg = self::config();
      $sec = $cfg['security'] ?? [];
      $required = [
        'encryption_key' => 'chave de criptografia',
        'backup_signature_key' => 'assinatura HMAC de backup',
        'integration_replay_hmac_key' => 'anti-replay das integrações',
        'audit_daily_signature_key' => 'assinatura diária da auditoria',
        'token_vault_hmac_key' => 'HMAC do cofre de tokens',
        'fim_manifest_hmac_key' => 'HMAC do manifesto de integridade',
      ];
      $weak = [];
      foreach ($required as $key => $label) {
        $value = trim((string)($sec[$key] ?? ''));
        if (strlen($value) < 32 || in_array($value, ['changeme','dev','default','secret','password'], true)) {
          $weak[] = $label.' (security.'.$key.')';
        }
      }
      if ($weak) {
        error_log('Hub bloqueado: segredos de produção ausentes/fracos: '.implode(', ', $weak));
        http_response_code(503);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('Sistema bloqueado por segurança: configure chaves fortes no config/config.php ou execute o install.php antes de abrir produção.');
      }
    }
  }
  public static function sendSecurityHeaders(): void {
    if (headers_sent()) return;
    $cfg = self::config();
    $security = $cfg['security'] ?? [];
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    $page = (string)($_GET['page'] ?? '');
    $commercial = $cfg['commercial'] ?? [];
    $allowIndexLanding = in_array($page, ['produto-institucional'], true) && empty($commercial['public_landing_noindex']);
    header('X-Robots-Tag: '.($allowIndexLanding ? 'index, follow' : 'noindex, nofollow, noarchive, nosnippet, noimageindex'));
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=(), bluetooth=(), clipboard-read=(), clipboard-write=(self)');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('X-Permitted-Cross-Domain-Policies: none');
    // Páginas autenticadas e operacionais nunca devem permanecer no cache do navegador/PWA.
    $publicCachePages = ['produto-institucional'];
    if (!in_array($page, $publicCachePages, true)) {
      header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0');
      header('Pragma: no-cache');
      header('Expires: 0');
    }
    $nonce = self::cspNonce();
    $csp = $security['content_security_policy'] ?? "default-src 'self'; base-uri 'self'; frame-ancestors 'self'; object-src 'none'; img-src 'self' data:; font-src 'self' data:; style-src 'self' 'nonce-__NONCE__'; script-src 'self' 'nonce-__NONCE__'; connect-src 'self'; form-action 'self'; upgrade-insecure-requests";
    $csp = str_replace('__NONCE__', $nonce, $csp);
    $csp = str_replace('__CSP_REPORT_URI__', (string)($security['csp_report_uri'] ?? 'index.php?page=api/csp-report'), $csp);
    header('Content-Security-Policy: '.$csp);
    if (self::isProduction()) {
      header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
  }
  public static function setupErrors(): void {
    error_reporting(E_ALL);
    if (self::isLocal() && !self::isPublicHost()) {
      ini_set('display_errors','1');
      ini_set('log_errors','1');
    } else {
      ini_set('display_errors','0');
      ini_set('log_errors','1');
    }
  }
}
