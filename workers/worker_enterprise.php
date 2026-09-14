<?php
if (PHP_SAPI !== 'cli') {
  http_response_code(403);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Worker enterprise bloqueado para navegador. Execute via CLI: php workers/worker_enterprise.php 50";
  exit;
}
$_SERVER['REQUEST_METHOD']='CLI';
require_once __DIR__.'/../app/Core/Autoload.php';
WorkerCliGuardService::enforce(basename(__FILE__));
require_once __DIR__.'/../app/Core/Helpers.php';
session_start();
$_SESSION['user'] = ['id'=>0,'nome'=>'Worker Enterprise','email'=>'worker.enterprise@local','perfil'=>'admin','deve_trocar_senha'=>0];
App::setupErrors();
RequestContext::id();
// Auditoria de vazão 2026-09-14 (achado E-03): o padrão era 50 itens por execução. Com a meta de
// 500 pedidos/min isso é 10x abaixo do necessário, e quem rodasse sem argumento (o caso comum)
// ficaria limitado a 50 sem perceber. O laço já para sozinho em duas condições — fila vazia e
// $maxSeconds —, então o limite alto não faz o worker girar à toa: ele apenas deixa de ser o
// fator que interrompe a drenagem antes da hora. O teto de 500 e o piso de 1 não mudaram.
$limit = max(1, min(500, (int)($argv[1] ?? 500)));
$maxSeconds = max(10, min(3600, (int)($argv[2] ?? ((App::config()['enterprise']['queue_worker_graceful_stop_seconds'] ?? 300)))));
$startedAt = time();
$processed = 0;
$empty = false;
WorkerHeartbeatService::beat('worker_enterprise','iniciando',['limit'=>$limit]);
try {
  SchemaMigrationService::applyEnterpriseCore(false);
  QueueService::liberarTravados();
  $api = new ApiController();
  for ($i=0; $i<$limit; $i++) {
    if ((time() - $startedAt) >= $maxSeconds) { break; }
    WorkerHeartbeatService::beat('worker_enterprise','rodando',['processed'=>$processed,'limit'=>$limit]);
    ob_start();
    $api->processarFila();
    $out = ob_get_clean();
    // Achado E-02: $processed era incrementado ANTES de testar a fila vazia, então a última
    // iteração — que não processou nada — entrava na conta. O número reportado no heartbeat e no
    // JSON ficava sempre 1 a mais quando a fila esvaziava, e é justamente esse número que o
    // alarme de backpressure usa para estimar a taxa de drenagem.
    if (str_contains($out, 'fila_vazia') || str_contains($out, 'Nenhum item pendente')) { $empty = true; break; }
    $processed++;
  }
  $snapshot = EnterpriseObservabilityService::snapshot();
  WorkerHeartbeatService::beat('worker_enterprise','ok',['processed'=>$processed,'empty'=>$empty,'max_seconds'=>$maxSeconds,'snapshot_cards'=>$snapshot['cards'] ?? []]);
  echo json_encode(['success'=>true,'trace_id'=>RequestContext::id(),'processed'=>$processed,'empty'=>$empty,'max_seconds'=>$maxSeconds], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
} catch (Throwable $e) {
  WorkerHeartbeatService::beat('worker_enterprise','erro',['processed'=>$processed],$e->getMessage());
  Audit::exception($e,'worker_enterprise.erro');
  fwrite(STDERR, json_encode(['success'=>false,'trace_id'=>RequestContext::id(),'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL);
  exit(1);
}
