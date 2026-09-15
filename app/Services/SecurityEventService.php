<?php
class SecurityEventService {
  public static function ensureSchema(): void {
    SchemaRuntimePolicyService::requireTables(['security_events','ips_bloqueados'], 'eventos de segurança');
  }
  public static function ip(): string {
    if (class_exists('TrustedProxyService')) return TrustedProxyService::clientIp();
    $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
  }
  public static function log(string $tipo, string $severidade='medio', string $detalhe='', array $contexto=[]): void {
    // Logging de segurança é best-effort: o WAF deve continuar bloqueando mesmo se o banco de auditoria estiver indisponível.
    try {
      self::ensureSchema();
      $u = class_exists('Auth') ? Auth::user() : null;
      $pdo = Database::getConnection();
      $st = $pdo->prepare('INSERT INTO security_events(trace_id,tipo,severidade,ip,usuario_id,rota,metodo,user_agent,detalhe,contexto) VALUES (?,?,?,?,?,?,?,?,?,?)');
      $st->execute([
        class_exists('RequestContext') ? RequestContext::id() : null,
        substr($tipo,0,80), $severidade, self::ip(), $u['id'] ?? null,
        substr((string)($_GET['page'] ?? ''),0,190), substr((string)($_SERVER['REQUEST_METHOD'] ?? ''),0,12),
        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''),0,255), substr($detalhe,0,60000), json_encode($contexto, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
      ]);
    } catch (Throwable $e) {
      SecurityHealthService::degrade('event_log', 'Falha ao gravar em security_events: '.$e->getMessage());
      $dir = dirname(__DIR__, 2).'/storage/logs';
      if (!is_dir($dir)) @mkdir($dir, 0770, true);
      $trace = class_exists('RequestContext') ? RequestContext::id() : 'SEC-'.date('YmdHis');
      $line = '['.date('c').'] '.$trace.' '.substr($tipo,0,80).' '.substr($severidade,0,20).' '.substr($e->getMessage(),0,500).PHP_EOL;
      @file_put_contents($dir.'/security-fallback.log', $line, FILE_APPEND|LOCK_EX);
      error_log('SecurityEvent log degradado: '.$e->getMessage());
    }
  }
}
