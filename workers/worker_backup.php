<?php
if (PHP_SAPI !== 'cli') {
  http_response_code(403);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Worker bloqueado para execução via navegador. Execute pelo terminal/Agendador do Windows usando php ".basename(__FILE__).".";
  exit;
}
// Worker de backup diário.
require_once __DIR__.'/../app/Core/Autoload.php';
WorkerCliGuardService::enforce(basename(__FILE__));
require_once __DIR__.'/../app/Core/Helpers.php';
App::setupErrors();
session_start();
$_SESSION['user'] = ['id'=>0,'nome'=>'Worker','perfil'=>'admin','deve_trocar_senha'=>0];
try { $file=BackupService::gerarZip(); $ret=RetentionService::limparOperacional(); echo "Backup gerado: ".basename($file).PHP_EOL; echo "Retenção executada: ".json_encode($ret, JSON_UNESCAPED_UNICODE).PHP_EOL; }
catch(Throwable $e){ Audit::exception($e,'worker_backup.erro'); echo "Erro: ".$e->getMessage().PHP_EOL; }
