<?php
/**
 * Logging seguro para operações best-effort. Nunca interrompe o fluxo principal e nunca lança exceção.
 */
class BestEffortLogService {
  public static function warning(string $operation, Throwable $error, array $context=[]): void {
    $message = self::safe((string)$error->getMessage());
    if (!self::shouldLog($operation, $message)) return;
    $payload = array_merge($context, [
      'operation'=>$operation,
      'error'=>$message,
      'exception'=>get_class($error),
      'trace_id'=>class_exists('RequestContext') ? RequestContext::id() : null,
    ]);
    try {
      if (class_exists('JsonLogger')) {
        JsonLogger::write('best_effort','warning','Falha não bloqueante registrada',$payload);
        return;
      }
    } catch (Throwable $ignored) {}
    try { error_log('[BEST_EFFORT] '.json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); }
    catch (Throwable $ignored) { error_log('[BEST_EFFORT] '.$operation.' falhou.'); }
  }

  private static function shouldLog(string $operation, string $message, int $windowSeconds=60): bool {
    try {
      $root = dirname(__DIR__, 2);
      $dir = $root.'/storage/logs/.best-effort-rate';
      if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) return true;
      $key = hash('sha256', $operation."
".$message);
      $path = $dir.'/'.$key.'.stamp';
      $fp = @fopen($path, 'c+');
      if ($fp === false) return true;
      try {
        if (!flock($fp, LOCK_EX)) return true;
        $raw = stream_get_contents($fp);
        $last = is_string($raw) ? (int)trim($raw) : 0;
        $now = time();
        if ($last > 0 && ($now - $last) < max(10, $windowSeconds)) return false;
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, (string)$now);
        fflush($fp);
        @chmod($path, 0660);
        return true;
      } finally {
        @flock($fp, LOCK_UN);
        @fclose($fp);
      }
    } catch (Throwable $ignored) {
      return true;
    }
  }

  private static function safe(string $value): string {
    if (class_exists('SensitiveDataService')) {
      try { return (string)SensitiveDataService::maskJson($value); } catch (Throwable $ignored) {}
    }
    $value = preg_replace('/(authorization|token|secret|password|senha)\\s*[:=]\\s*[^\\s,;]+/i', '$1=[REDACTED]', $value) ?? 'erro interno';
    return mb_substr($value,0,1000);
  }
}
