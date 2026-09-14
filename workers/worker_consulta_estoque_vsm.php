<?php
// Worker de consulta programada de estoque VSM com trava anti-concorrência.
// Execute no Windows/XAMPP pelo Agendador de Tarefas:
// php C:\xampp2\htdocs\hub-vsm-tiny\public\worker_consulta_estoque_vsm.php
// Opcional manual: php public/worker_consulta_estoque_vsm.php --force 100
require_once __DIR__.'/../app/Core/Autoload.php';
WorkerCliGuardService::enforce(basename(__FILE__));
require_once __DIR__.'/../app/Core/Helpers.php';
App::setupErrors();

$force = in_array('--force', $argv, true) || in_array('-f', $argv, true);
$limit = null;
foreach ($argv as $a) { if (ctype_digit((string)$a)) $limit = (int)$a; }
try {
  $res = EstoqueVsmSchedulerService::executarConsultaProgramada($force, $limit, $force ? 'manual' : 'automatico');
  echo "Worker consulta estoque VSM: ".json_encode($res, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
} catch (Throwable $e) {
  Audit::exception($e, 'estoque.vsm.worker_consulta.erro');
  fwrite(STDERR, 'Erro worker consulta estoque VSM: '.$e->getMessage().PHP_EOL);
  exit(1);
}
