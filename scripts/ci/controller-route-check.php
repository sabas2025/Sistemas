<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);
require_once $root.'/app/Services/FastRouteDispatcherService.php';
$classes=[];
$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app',FilesystemIterator::SKIP_DOTS));
foreach($it as $file){
  if(!$file->isFile()||strtolower($file->getExtension())!=='php')continue;
  $code=(string)file_get_contents($file->getPathname());
  if(!preg_match_all('/(?<!::)\b(?:abstract\s+|final\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)/',$code,$cm))continue;
  $methods=[];if(preg_match_all('/\bfunction\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/',$code,$mm))$methods=array_map('strtolower',$mm[1]);
  foreach($cm[1] as $name)$classes[$name]=['file'=>$file->getPathname(),'methods'=>array_values(array_unique($methods))];
}
$ref=new ReflectionClass(FastRouteDispatcherService::class);
$get=function(string $prop)use($ref){$p=$ref->getProperty($prop);$p->setAccessible(true);return $p->getValue();};
$direct=$get('directActions');$groups=$get('dispatchGroups');$errors=[];$routes=[];
foreach($direct as $route=>$target){
  [$class,$method]=$target;$routes[]=$route;
  if(!isset($classes[$class])){$errors[]="Controller direto ausente: {$class} ({$route})";continue;}
  if(!in_array(strtolower($method),$classes[$class]['methods'],true))$errors[]="Método direto ausente: {$class}::{$method} ({$route})";
}
foreach($groups as $class=>$items){
  if(!isset($classes[$class])){$errors[]="Controller de grupo ausente: {$class}";continue;}
  if(!in_array('dispatch',$classes[$class]['methods'],true)&&!hasParentDispatch((string)file_get_contents($classes[$class]['file']),$classes))$errors[]="Método dispatch ausente: {$class}";
  foreach($items as $route){$routes[]=$route;if(!is_string($route)||trim($route)==='')$errors[]="Rota vazia em {$class}";}
}
foreach([
  LoginController::class=>['login','form','logout'],
  MigrationController::class=>['dispatch'],
  DashboardController::class=>['dispatch'],
  CommercialController::class=>['dispatch'],
] as $class=>$methods){
  if(!isset($classes[$class])){$errors[]="Controller especial ausente: {$class}";continue;}
  foreach($methods as $method)if(!in_array(strtolower($method),$classes[$class]['methods'],true)&&!hasParentDispatch((string)file_get_contents($classes[$class]['file']),$classes,$method))$errors[]="Método especial ausente: {$class}::{$method}";
}
$duplicates=array_keys(array_filter(array_count_values($routes),fn($n)=>$n>1));
if($duplicates)$errors[]='Rotas duplicadas: '.implode(',',$duplicates);
if($errors){foreach($errors as $e)fwrite(STDERR,"[FALHA] {$e}\n");exit(1);} 
echo '[OK] Rotas: '.count($routes).' mapeamentos, '.count($classes)." classes indexadas, controllers/métodos presentes e sem duplicidade.\n";

function hasParentDispatch(string $code,array $classes,string $method='dispatch'):bool{
  if(!preg_match('/\bclass\s+[A-Za-z_][A-Za-z0-9_]*\s+extends\s+([A-Za-z_][A-Za-z0-9_]*)/',$code,$m))return false;
  $parent=$m[1];return isset($classes[$parent])&&in_array(strtolower($method),$classes[$parent]['methods'],true);
}
