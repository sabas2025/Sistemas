<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);
require_once $root.'/app/Services/VsmOpenApiContractService.php';
$files=glob($root.'/contracts/vsm/*.json')?:[];
if(!$files){
  echo "[OK] Nenhum contrato VSM foi empacotado; catálogo permanece não verificado até a importação de um OpenAPI oficial.\n";
  exit(0);
}
$failed=0;
foreach($files as $file){
  try{$result=VsmOpenApiContractService::validateFile($file);echo basename($file).': '.($result['ok']?'OK':'FALHA').' - operações='.count($result['operations']??[]).' sha256='.($result['sha256']??'').PHP_EOL;if(!$result['ok']){foreach($result['errors'] as $e)echo '  ERRO: '.$e.PHP_EOL;$failed++;}foreach($result['warnings'] as $w)echo '  AVISO: '.$w.PHP_EOL;}
  catch(Throwable $e){echo basename($file).': FALHA - '.$e->getMessage().PHP_EOL;$failed++;}
}
exit($failed?1:0);
