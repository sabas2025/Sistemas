<?php
$root=dirname(__DIR__,2);
$checks=[];
function vcheck(array &$c,string $name,bool $ok,string $detail=''):void{$c[]=[$name,$ok,$detail];}
$config=file_get_contents($root.'/views/configuracoes.php');
$tiny=file_get_contents($root.'/views/tiny_v3_ficha.php');
$layout=file_get_contents($root.'/views/layout_top.php');
$css=file_get_contents($root.'/public/assets/integration-center.css');
$sw=file_get_contents($root.'/public/sw.js');
$manifest=json_decode(file_get_contents($root.'/public/manifest.webmanifest'),true);
$assetVersion=preg_match("/HUB_VERSION\s*=\s*'([^']+)'/",$sw,$versionMatch)?(string)$versionMatch[1]:'';
vcheck($checks,'CSS visual dedicado',$assetVersion!==''&&str_contains($layout,'integration-center.min.css?v='.$assetVersion)&&strlen($css)>5000);
vcheck($checks,'Configuração destaca Tiny/VSM',str_contains($config,'integration-summary-card tiny')&&str_contains($config,'integration-summary-card vsm')&&str_contains($config,'id="sec-vsm"'));
vcheck($checks,'Ficha Tiny V3 hierárquica',str_contains($tiny,'integration-hero--tiny')&&str_contains($tiny,'integration-tabs')&&str_contains($tiny,'id="tiny-central"'));
vcheck($checks,'Responsividade e standalone',str_contains($css,'@media (max-width:767.98px)')&&str_contains($css,'@media (display-mode:standalone)'));
vcheck($checks,'PWA usa a mesma paleta',($manifest['background_color']??'')==='#f4f7fb'&&($manifest['theme_color']??'')==='#2563eb');
vcheck($checks,'Service worker inclui CSS novo',$assetVersion!==''&&str_contains($sw,'integration-center.min.css?v='.$assetVersion));
vcheck($checks,'Versão sincronizada',$assetVersion==='104.49.3'&&str_contains($layout,'v='.$assetVersion));
vcheck($checks,'Sem CSS inválido',!str_contains($css,'NaN')&&!str_contains($css,'[object Object]'));
$fail=0;foreach($checks as [$n,$ok,$d]){echo ($ok?'[OK] ':'[ERRO] ').$n.($d!==''?' — '.$d:'').PHP_EOL;if(!$ok)$fail++;}echo 'Resumo: '.(count($checks)-$fail).'/'.count($checks).' aprovados'.PHP_EOL;exit($fail?1:0);
