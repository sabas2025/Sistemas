<?php
/**
 * V103 - Worker público bloqueado.
 * A lógica real foi movida para ../workers/worker_consulta_estoque_vsm.php.
 */
if (PHP_SAPI !== 'cli') {
  http_response_code(403);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Worker bloqueado para execução via navegador. Use CLI/Cron/Agendador: php workers/worker_consulta_estoque_vsm.php";
  exit;
}
require __DIR__.'/../workers/worker_consulta_estoque_vsm.php';
