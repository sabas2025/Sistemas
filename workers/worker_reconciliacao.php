<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); echo 'Este worker deve ser executado somente via CLI/Agendador do Windows.'; exit; }
require_once __DIR__.'/../app/Core/Autoload.php';
WorkerCliGuardService::enforce(basename(__FILE__));
require_once __DIR__.'/../app/Core/Helpers.php';
session_start();
$_SESSION['user']=['id'=>1,'nome'=>'Worker','perfil'=>'admin','deve_trocar_senha'=>0];
App::setupErrors();
// Worker preparado para reconciliação automática. Nesta versão ele registra self-test e aguarda endpoints reais Tiny/VSM para saldo.
$r=SelfTestService::executar(); echo 'Reconciliação automática preparada. Configure endpoints reais de saldo Tiny/VSM para comparação automática.'.PHP_EOL;
