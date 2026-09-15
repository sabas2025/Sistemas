<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); echo "CLI only"; exit(1); }
require_once __DIR__.'/../../app/Core/Autoload.php';
require_once __DIR__.'/../../app/Core/Helpers.php';
$_SERVER['REQUEST_METHOD']='CLI';
session_start();
$_SESSION['user']=['id'=>0,'nome'=>'Regression CLI','email'=>'regression@local','perfil'=>'admin','deve_trocar_senha'=>0];
App::setupErrors();
RequestContext::id();
$result = EnterpriseRegressionTestService::run();
echo json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit(($result['erro'] ?? 1) > 0 ? 1 : 0);
