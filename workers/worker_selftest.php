<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); echo 'Este worker deve ser executado somente via CLI/Agendador do Windows.'; exit; }
require_once __DIR__.'/../app/Core/Autoload.php';
WorkerCliGuardService::enforce(basename(__FILE__));
require_once __DIR__.'/../app/Core/Helpers.php';
session_start();
$_SESSION['user']=['id'=>1,'nome'=>'Worker','perfil'=>'admin','deve_trocar_senha'=>0];
App::setupErrors();
// Achado E-05: contrapressão da fila. Cada execução deste worker é uma amostra; o alarme dispara
// quando a fila cresce em ciclos seguidos — crescimento sustentado, não pico isolado.
$backpressure = class_exists('QueueBackpressureService') ? QueueBackpressureService::avaliar() : null;
$r=SelfTestService::executar();
if ($backpressure !== null) $r['fila_contrapressao'] = $backpressure;
echo json_encode($r, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
