<?php
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function redirect($url){ header('Location: '.$url); exit; }
function cfg($key=null){ static $c; if(!$c){$file=__DIR__.'/../../config/config.php';if(!is_file($file)&&PHP_SAPI==='cli')$file=__DIR__.'/../../config/config.example.php';if(!is_file($file))throw new RuntimeException('config/config.php ausente.');$c=require $file;if(!is_array($c))throw new RuntimeException('Configuração inválida.');} if(!$key)return $c; $v=$c; foreach(explode('.',$key) as $p){$v=$v[$p]??null;} return $v; }
