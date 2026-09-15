<?php
class IntegrationSecurityService {
  public static function traceHeaders(): array {
    return ['X-Trace-ID: '.RequestContext::id()];
  }

  public static function vsmHmacHeaders(string $rawBody): array {
    $sec = (class_exists('App') ? (App::config()['security'] ?? []) : []);
    if (empty($sec['vsm_hmac_enabled'])) return [];
    $secret = (string)($sec['vsm_hmac_secret'] ?? '');
    if ($secret === '') return [];
    $ts = (string)time();
    $nonce = bin2hex(random_bytes(12));
    $sig = 'sha256='.hash_hmac('sha256', $ts.'.'.$nonce.'.'.$rawBody, $secret);
    return [
      'X-HUB-TIMESTAMP: '.$ts,
      'X-HUB-NONCE: '.$nonce,
      'X-HUB-SIGNATURE: '.$sig,
      'X-HUB-TRACE-ID: '.RequestContext::id(),
    ];
  }

  public static function maskHeaders(array $headers): array {
    return array_map(function($h){
      $l = strtolower((string)$h);
      if (str_starts_with($l,'authorization:')) return 'Authorization: ***mascarado***';
      if (str_starts_with($l,'x-hub-signature:')) return 'X-HUB-SIGNATURE: ***mascarado***';
      if (str_starts_with($l,'x-hub-secret:')) return 'X-HUB-SECRET: ***mascarado***';
      return $h;
    }, $headers);
  }
}
