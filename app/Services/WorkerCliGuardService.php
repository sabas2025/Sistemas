<?php
/** V104.17 - Bloqueio central para workers reais. */
class WorkerCliGuardService {
  public static function enforce(string $name = 'worker'): void {
    if (PHP_SAPI === 'cli') return;
    http_response_code(403);
    if (!headers_sent()) header('Content-Type: text/plain; charset=utf-8');
    echo "Worker {$name} bloqueado para execução via navegador. Execute somente via CLI/Cron/Agendador.";
    exit;
  }
}
