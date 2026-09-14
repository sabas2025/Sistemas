<?php
if (PHP_SAPI !== 'cli') {
  http_response_code(403);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Worker bloqueado para execução via navegador. Execute pelo terminal/Agendador do Windows usando php ".basename(__FILE__).".";
  exit;
}
// Worker de monitoramento: alerta fila parada e muitas falhas.
require_once __DIR__.'/../app/Core/Autoload.php';
WorkerCliGuardService::enforce(basename(__FILE__));
require_once __DIR__.'/../app/Core/Helpers.php';
App::setupErrors();
session_start();
$_SESSION['user'] = ['id'=>0,'nome'=>'Worker','perfil'=>'admin','deve_trocar_senha'=>0];
try {
  $pdo=Database::getConnection();
  $travados=(int)($pdo->query("SELECT COUNT(*) c FROM fila_integracao WHERE status='processando' AND COALESCE(processando_desde,criado_em) < (NOW() - INTERVAL 10 MINUTE)")->fetch()['c'] ?? 0);
  if($travados>0) NotificationService::criar('fila','Fila parada detectada',"Existem {$travados} item(ns) processando há mais de 10 minutos.",'alerta',['trace_id'=>RequestContext::id(),'link'=>'index.php?page=fila&status=processando']);
  $falhas=(int)($pdo->query("SELECT COUNT(*) c FROM fila_integracao WHERE status IN ('erro','falha_definitiva') AND processado_em >= (NOW() - INTERVAL 1 HOUR)")->fetch()['c'] ?? 0);
  if($falhas>=5) NotificationService::criar('erro_integracao','Muitas falhas recentes',"Foram encontradas {$falhas} falhas na última hora.",'erro',['trace_id'=>RequestContext::id(),'link'=>'index.php?page=fila&status=erro']);
  echo "Monitoramento concluído. Travados={$travados}; Falhas={$falhas}".PHP_EOL;
} catch(Throwable $e){ Audit::exception($e,'worker_notificacoes.erro'); echo "Erro: ".$e->getMessage().PHP_EOL; }
