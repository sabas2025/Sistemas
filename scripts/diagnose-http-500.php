<?php
/** Diagnóstico CLI seguro para HTTP 500. Não imprime senhas, tokens ou chaves. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$rows = [];
$add = static function(string $item, bool $ok, string $detail='') use (&$rows): void { $rows[] = [$item, $ok ? 'OK' : 'FALHA', $detail]; };
$add('PHP >= 8.1', version_compare(PHP_VERSION, '8.1.0', '>='), PHP_VERSION);
foreach (['pdo','pdo_mysql','curl','openssl','json','mbstring'] as $ext) $add('Extensão '.$ext, extension_loaded($ext));
$configFile = $root.'/config/config.php';
$add('config/config.php existe', is_file($configFile));
$cfg = null;
if (is_file($configFile)) {
  try { $cfg = require $configFile; $add('config/config.php válido', is_array($cfg)); }
  catch(Throwable $e) { $add('config/config.php válido', false, get_class($e).': '.$e->getMessage()); }
}
foreach (['storage','storage/logs','storage/cache','storage/backups'] as $dir) {
  $path=$root.'/'.$dir; $add($dir.' gravável', is_dir($path)&&is_writable($path), $path);
}
if (is_array($cfg) && extension_loaded('pdo_mysql')) {
  $db=$cfg['db']??[];
  try {
    $pdo=new PDO('mysql:host='.($db['host']??'').';dbname='.($db['name']??'').';charset='.($db['charset']??'utf8mb4'),(string)($db['user']??''),(string)($db['pass']??''),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $add('Conexão MySQL', true, (string)($db['host']??'').' / '.(string)($db['name']??''));
    foreach(['usuarios','security_events','ips_bloqueados','rate_limit_hits','schema_migrations'] as $table){
      $st=$pdo->prepare('SHOW TABLES LIKE ?');$st->execute([$table]);$add('Tabela '.$table,(bool)$st->fetchColumn());
    }
  } catch(Throwable $e) { $add('Conexão MySQL', false, get_class($e).': '.$e->getMessage()); }
}
$width=max(array_map(fn($r)=>strlen($r[0]),$rows));
foreach($rows as [$item,$status,$detail]) printf("%-{$width}s  %-5s  %s\n",$item,$status,$detail);
$failed=array_filter($rows,fn($r)=>$r[1]!=='OK');
exit($failed?1:0);
