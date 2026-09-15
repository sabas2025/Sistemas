<?php
/** Validador local de contratos OpenAPI/Swagger VSM, sem executar operações reais. */
class VsmOpenApiContractService {

  public static function parse(string $json): array {
    if(strlen($json)>10*1024*1024) throw new RuntimeException('Contrato OpenAPI acima do limite seguro de 10MB.');
    $doc=json_decode($json,true,512,JSON_THROW_ON_ERROR);
    if(!is_array($doc)) throw new RuntimeException('Contrato OpenAPI inválido.');
    return $doc;
  }

  public static function validate(array $doc): array {
    $errors=[];$warnings=[];
    $version=(string)($doc['openapi']??$doc['swagger']??'');
    if($version===''||(!str_starts_with($version,'3.')&&!str_starts_with($version,'2.'))) $errors[]='Versão OpenAPI/Swagger ausente ou não suportada.';
    if(!is_array($doc['info']??null)||trim((string)($doc['info']['title']??''))==='') $errors[]='info.title ausente.';
    $paths=$doc['paths']??null;
    if(!is_array($paths)||$paths===[]) $errors[]='paths ausente ou vazio.';
    $operations=[];
    if(is_array($paths)) foreach($paths as $path=>$methods){
      if(!is_string($path)||!str_starts_with($path,'/')){$errors[]='Path inválido: '.(string)$path;continue;}
      if(!is_array($methods))continue;
      foreach($methods as $method=>$operation){
        $method=strtolower((string)$method);
        if(!in_array($method,['get','post','put','patch','delete'],true)||!is_array($operation))continue;
        $operationId=trim((string)($operation['operationId']??''));
        if($operationId==='')$warnings[]='OperationId ausente em '.strtoupper($method).' '.$path;
        $responses=$operation['responses']??null;
        if(!is_array($responses)||$responses===[])$errors[]='Responses ausentes em '.strtoupper($method).' '.$path;
        $operations[]=['method'=>strtoupper($method),'path'=>$path,'operation_id'=>$operationId,'responses'=>array_keys(is_array($responses)?$responses:[])];
      }
    }
    return ['ok'=>$errors===[],'version'=>$version,'title'=>(string)($doc['info']['title']??''),'operations'=>$operations,'errors'=>array_values(array_unique($errors)),'warnings'=>array_values(array_unique($warnings)),'sha256'=>hash('sha256',json_encode($doc,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))];
  }

  public static function validateFile(string $file): array {
    $real=realpath($file);$contracts=realpath(__DIR__.'/../../contracts/vsm');
    if($real===false||$contracts===false||!str_starts_with($real,$contracts.DIRECTORY_SEPARATOR))throw new RuntimeException('Contrato deve estar em contracts/vsm.');
    return self::validate(self::parse((string)file_get_contents($real)));
  }

  public static function compareCatalog(array $validation,array $catalog): array {
    $available=[];
    foreach($validation['operations']??[] as $op)$available[strtoupper((string)$op['method']).' '.(string)$op['path']]=true;
    $missing=[];
    foreach($catalog as $item){
      $method=strtoupper((string)($item['metodo_http']??$item['method']??''));$path=(string)($item['endpoint']??$item['path']??'');
      if($method===''||$path==='')continue;
      if(!isset($available[$method.' '.$path]))$missing[]=['method'=>$method,'path'=>$path,'key'=>$item['chave']??null];
    }
    return ['ok'=>$missing===[],'missing'=>$missing,'checked'=>count($catalog)];
  }
}
