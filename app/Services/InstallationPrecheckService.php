<?php
class InstallationPrecheckService {
  public static function run(): array {
    $storage = realpath(__DIR__.'/../../storage') ?: __DIR__.'/../../storage';
    $checks = [
      ['categoria'=>'PHP','item'=>'Versão PHP 8.1+','ok'=>version_compare(PHP_VERSION,'8.1.0','>='),'detalhe'=>PHP_VERSION,'acao'=>'Atualize o PHP/XAMPP para 8.1+ se falhar.'],
      ['categoria'=>'Extensão','item'=>'PDO MySQL','ok'=>extension_loaded('pdo_mysql'),'detalhe'=>extension_loaded('pdo_mysql')?'Ativa':'Inativa','acao'=>'Ative pdo_mysql no php.ini.'],
      ['categoria'=>'Extensão','item'=>'cURL','ok'=>extension_loaded('curl'),'detalhe'=>extension_loaded('curl')?'Ativa':'Inativa','acao'=>'Ative curl no php.ini.'],
      ['categoria'=>'Extensão','item'=>'OpenSSL','ok'=>extension_loaded('openssl'),'detalhe'=>extension_loaded('openssl')?'Ativa':'Inativa','acao'=>'Ative openssl para criptografia e HTTPS.'],
      ['categoria'=>'Extensão','item'=>'JSON','ok'=>extension_loaded('json'),'detalhe'=>extension_loaded('json')?'Ativa':'Inativa','acao'=>'Ative json no PHP.'],
      ['categoria'=>'Extensão','item'=>'ZIP','ok'=>extension_loaded('zip'),'detalhe'=>extension_loaded('zip')?'Ativa':'Inativa','acao'=>'Ative zip para backup compactado.'],
      ['categoria'=>'Extensão','item'=>'MBString','ok'=>extension_loaded('mbstring'),'detalhe'=>extension_loaded('mbstring')?'Ativa':'Inativa','acao'=>'Ative mbstring para textos UTF-8.'],
      ['categoria'=>'Pasta','item'=>'storage gravável','ok'=>is_writable($storage),'detalhe'=>$storage,'acao'=>'Dê permissão de escrita na pasta storage.'],
      ['categoria'=>'Pasta','item'=>'storage/logs gravável','ok'=>self::dirWritable($storage.'/logs'),'detalhe'=>$storage.'/logs','acao'=>'Crie a pasta logs e permita escrita.'],
      ['categoria'=>'Pasta','item'=>'storage/backups gravável','ok'=>self::dirWritable($storage.'/backups'),'detalhe'=>$storage.'/backups','acao'=>'Crie a pasta backups e permita escrita.'],
    ];
    try { Database::getConnection()->query('SELECT 1'); $checks[]=['categoria'=>'Banco','item'=>'Conexão MySQL','ok'=>true,'detalhe'=>'PDO conectado','acao'=>'OK']; }
    catch(Throwable $e){ $checks[]=['categoria'=>'Banco','item'=>'Conexão MySQL','ok'=>false,'detalhe'=>$e->getMessage(),'acao'=>'Execute install.php e confira host/usuário/senha.']; }
    return $checks;
  }
  private static function dirWritable(string $path): bool { if(!is_dir($path)) @mkdir($path,0775,true); return is_dir($path) && is_writable($path); }
  public static function score(): int { $c=self::run(); $ok=count(array_filter($c,fn($x)=>$x['ok'])); return (int)round(($ok/max(1,count($c)))*100); }
}
