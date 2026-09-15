<?php
class CspReportService {
  public static function handle(): void {
    if (PHP_SAPI === 'cli') return;
    $raw = file_get_contents('php://input') ?: '';
    $max = 65536;
    if (strlen($raw) > $max) $raw = substr($raw, 0, $max);
    SecurityEventService::log('csp.violacao','medio','Violação CSP reportada pelo navegador', ['raw'=>$raw]);
    http_response_code(204);
    exit;
  }
  public static function ensureSchema(): void { SecurityEventService::ensureSchema(); }
}
