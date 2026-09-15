<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); echo 'Este worker deve ser executado somente via CLI/Agendador/Cron.'; exit; }
require_once __DIR__.'/../app/Core/Autoload.php';
WorkerCliGuardService::enforce(basename(__FILE__));
require_once __DIR__.'/../app/Core/Helpers.php';
session_start();
$_SESSION['user']=['id'=>1,'nome'=>'Worker Homologação','perfil'=>'admin','deve_trocar_senha'=>0];
App::setupErrors();
$liberar = in_array('--liberar', $argv ?? [], true);
$r=(new AutoHomologationService())->executar($liberar);
echo json_encode($r, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
