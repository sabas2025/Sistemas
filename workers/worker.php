<?php
if (PHP_SAPI !== 'cli') {
  http_response_code(403);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Worker bloqueado para execução via navegador. Execute pelo terminal/Agendador do Windows usando php ".basename(__FILE__).".";
  exit;
}
// Worker de fila para XAMPP/Windows Task Scheduler:
// php C:\xampp\htdocs\hub-vsm-tiny-php-puro\public\worker.php
session_start();
$_SESSION['user'] = ['id'=>0,'nome'=>'Worker','email'=>'worker@local','perfil'=>'admin','deve_trocar_senha'=>0];
require_once __DIR__.'/../app/Core/Autoload.php';
WorkerCliGuardService::enforce(basename(__FILE__));
require_once __DIR__.'/../app/Core/Helpers.php';
App::setupErrors();
$max=(int)($argv[1] ?? $_GET['max'] ?? 10);
$resultados=[];
for($i=0;$i<$max;$i++){
  ob_start();
  (new ApiController())->processarFila();
  $out=ob_get_clean();
  $resultados[]=$out;
  if(str_contains($out,'Nenhum item pendente')) break;
}
if(PHP_SAPI !== 'cli') header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success'=>true,'processados'=>count($resultados),'resultados'=>$resultados], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
