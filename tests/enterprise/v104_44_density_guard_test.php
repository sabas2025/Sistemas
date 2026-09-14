<?php
$root=dirname(__DIR__,2);
$js=file_get_contents($root.'/public/assets/minimalist-ui.js');
$layout=file_get_contents($root.'/views/layout_top.php');
$errors=[];
$check=function($ok,$msg)use(&$errors){echo ($ok?'OK':'ERRO')." - $msg\n";if(!$ok)$errors[]=$msg;};
$check(str_contains($layout,'data-hub-density="comfortable"'),'Body mantém somente estado de densidade');
$check(str_contains($layout,'data-hub-density-toggle'),'Botão usa seletor exclusivo de alternância');
$check(!str_contains($layout,'button type="button" data-hub-density>'),'Botão legado removido');
$check(str_contains($js,"[data-hub-density-toggle]"),'JS usa seletor exclusivo');
$check(!str_contains($js,"querySelectorAll('[data-hub-density]')"),'JS não altera o body ao atualizar rótulo');
$check(!str_contains($js,"closest('[data-hub-density]')"),'Cliques comuns não selecionam o body');
exit($errors?1:0);
