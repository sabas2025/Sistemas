<?php
if (PHP_SAPI !== 'cli') {
  http_response_code(403);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Worker bloqueado para execução via navegador. Execute pelo terminal/Agendador do Windows usando php ".basename(__FILE__).".";
  exit;
}
// Worker de fila para Agendador do Windows/cron.
// Exemplo: C:\xampp\php\php.exe C:\xampp\htdocs\hub-vsm-tiny-php-puro\public\worker_fila.php
/**
 * ATENÇÃO — ESTE WORKER PROCESSA **UM** ITEM POR EXECUÇÃO.
 *
 * Auditoria de vazão 2026-09-14 (achado E-04): ApiController::processarFila() retira um único item
 * da fila (QueueService::pegarProximo(), sem laço). Este arquivo o chama uma vez e sai. Agendado no
 * cron de minuto em minuto, ele drena 1 item por minuto.
 *
 * Isso serve para diagnóstico e para volume baixo. NÃO serve para carga: com 500 pedidos por minuto
 * a fila cresceria ~499 itens por minuto e nunca drenaria.
 *
 * Para produção com volume use o worker de lote, em vários processos paralelos:
 *
 *     php workers/worker_enterprise.php 500
 *
 * A fila suporta concorrência com segurança (FOR UPDATE SKIP LOCKED, lease e heartbeat), então
 * rodar N processos em paralelo é seguro. Veja workers/README-WORKERS.md para o dimensionamento.
 */
$_SERVER['REQUEST_METHOD']='CLI';
require_once __DIR__.'/../app/Core/Autoload.php';
WorkerCliGuardService::enforce(basename(__FILE__));
require_once __DIR__.'/../app/Core/Helpers.php';
session_start();
App::setupErrors();
RequestContext::id();
try {
  if (class_exists('WorkerHeartbeatService')) WorkerHeartbeatService::beat('worker_fila','rodando');
  QueueService::liberarTravados();
  $api = new ApiController();
  // usuário artificial para permitir rotina CLI sem sessão web
  $_SESSION['user'] = ['id'=>0,'nome'=>'Worker','perfil'=>'admin','deve_trocar_senha'=>0];
  $api->processarFila();
  if (class_exists('WorkerHeartbeatService')) WorkerHeartbeatService::beat('worker_fila','ok');
} catch(Throwable $e) {
  if (class_exists('WorkerHeartbeatService')) WorkerHeartbeatService::beat('worker_fila','erro',[], $e->getMessage());
  Audit::exception($e,'worker_fila.erro');
  echo json_encode(['success'=>false,'trace_id'=>RequestContext::id(),'erro'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
