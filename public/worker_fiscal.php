<?php
/**
 * V103 - Worker público bloqueado.
 * A lógica real foi movida para ../workers/worker_fiscal.php.
 */
if (PHP_SAPI !== 'cli') {
  http_response_code(403);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Worker bloqueado para execução via navegador. Use CLI/Cron/Agendador: php workers/worker_fiscal.php";
  exit;
}
require __DIR__.'/../workers/worker_fiscal.php';
