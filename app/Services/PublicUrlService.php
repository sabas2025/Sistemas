<?php
/**
 * Fase 3 (decomposição do controller-deus, 2026-09-26) — helpers de URL pública compartilhados.
 *
 * Estes quatro helpers eram privados do DashboardController, mas o cálculo da Redirect URI do
 * Tiny V3 é usado por dois lados: o formulário de Configurações e o callback OAuth (que ficam no
 * DashboardController) E a ficha Tiny V3 (extraída para o TinyController). Centralizados aqui como
 * estáticos puros (leem apenas $_SERVER, config e TrustedProxyService) para haver UMA fonte da
 * verdade — comportamento idêntico ao original, sem estado de instância.
 */
class PublicUrlService {
  public static function safeHost(): string {
    $canonical = class_exists('TrustedProxyService') ? TrustedProxyService::canonicalHosts() : [];
    if ($canonical !== []) return $canonical[0];
    return $_SERVER['HTTP_HOST'] ?? 'localhost';
  }

  public static function baseUrl(): string {
    $https = class_exists('TrustedProxyService') ? TrustedProxyService::isHttps() : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'));
    $scheme = $https ? 'https' : 'http';
    $host = self::safeHost();
    $script = $_SERVER['SCRIPT_NAME'] ?? '/public/index.php';
    $dir = rtrim(str_replace('\\','/', dirname($script)), '/');
    if ($dir === '' || $dir === '.') $dir = '';
    return $scheme . '://' . $host . $dir;
  }

  public static function appBaseUrl(): string {
    $cfgFile = __DIR__.'/../../config/config.php';
    if (is_file($cfgFile)) {
      $cfg = require $cfgFile;
      return rtrim((string)($cfg['base_url'] ?? ''), '/');
    }
    return '';
  }

  public static function tinyV3RedirectUri(string $uri = ''): string {
    $uri = trim($uri);
    if ($uri === '') {
      return self::baseUrl() . '/index.php?page=tiny-v3-callback';
    }
    if (preg_match('#^https?://#i', $uri)) {
      return $uri;
    }
    if (str_starts_with($uri, '/')) {
      $https = class_exists('TrustedProxyService') ? TrustedProxyService::isHttps() : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'));
      $scheme = $https ? 'https' : 'http';
      $host = self::safeHost();
      return $scheme . '://' . $host . $uri;
    }
    return self::baseUrl() . '/' . ltrim($uri, '/');
  }
}
