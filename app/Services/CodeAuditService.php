<?php
class CodeAuditService {
  public static function analisar(): array {
    $root = dirname(__DIR__, 2);
    $controllers = glob($root.'/app/Controllers/*.php') ?: [];
    $services = glob($root.'/app/Services/*.php') ?: [];
    $views = glob($root.'/views/*.php') ?: [];
    $allPhp = array_merge($controllers, $services, $views, glob($root.'/public/*.php') ?: []);
    $corpus = '';
    foreach($allPhp as $f){ $corpus .= "\n/*FILE:$f*/\n".@file_get_contents($f); }
    $classes=[]; $possiveisOrfaos=[];
    foreach($services as $f){
      $txt = @file_get_contents($f) ?: '';
      if(preg_match('/class\s+([A-Za-z0-9_]+)/', $txt, $m)){
        $cls=$m[1]; $classes[]=$cls;
        $count = substr_count($corpus, $cls);
        if($count <= 1 && $cls !== 'CodeAuditService') $possiveisOrfaos[]=['tipo'=>'service','nome'=>$cls,'arquivo'=>str_replace($root.'/','',$f),'ocorrencias'=>$count];
      }
    }
    $viewsSemRota=[];
    foreach($views as $f){
      $base=basename($f,'.php');
      $slug=str_replace('_','-',$base);
      if(in_array($base, ['layout_top','layout_bottom','login'], true)) continue;
      if(strpos($corpus, "'$slug'")===false && strpos($corpus, '"'.$slug.'"')===false && strpos($corpus, $base)===false){
        $viewsSemRota[]=['tipo'=>'view','nome'=>$base,'arquivo'=>str_replace($root.'/','',$f),'ocorrencias'=>0];
      }
    }
    return [
      'totais'=>['controllers'=>count($controllers),'services'=>count($services),'views'=>count($views),'php_total'=>count($allPhp)],
      'possiveis_orfaos'=>$possiveisOrfaos,
      'views_sem_rota_aparente'=>$viewsSemRota,
      'recomendacao'=>'Revise manualmente antes de apagar. O detector é conservador e pode marcar classes chamadas dinamicamente.'
    ];
  }
}
