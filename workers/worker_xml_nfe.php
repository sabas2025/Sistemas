<?php
require_once __DIR__.'/../app/Core/Autoload.php';
WorkerCliGuardService::enforce(basename(__FILE__));
// V82: nome semântico para o worker de XML/NF-e. Mantém compatibilidade com worker_fiscal.php.
require __DIR__.'/worker_fiscal.php';
