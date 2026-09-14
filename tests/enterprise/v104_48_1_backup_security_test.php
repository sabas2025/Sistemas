<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
require_once hub_root().'/app/Services/BackupService.php';
$checks=[];

/** @return mixed */
function backup_private(string $method, mixed ...$args): mixed {
    $r=new ReflectionMethod(BackupService::class,$method);
    $r->setAccessible(true);
    return $r->invoke(null,...$args);
}

$sql="-- comment\nSET FOREIGN_KEY_CHECKS=0;\nINSERT INTO `clientes` (`nome`,`obs`) VALUES ('O''Reilly','texto; interno');\nINSERT INTO `clientes` (`nome`,`obs`) VALUES (\"A\\\"B\",'fim');\nSET FOREIGN_KEY_CHECKS=1;";
$parts=backup_private('splitSqlStatements',$sql);
hub_check($checks,'Parser preserva ponto e vírgula dentro de string',count($parts)===4&&str_contains($parts[1],"texto; interno"));
hub_check($checks,'Parser aceita escape SQL por aspas duplicadas',str_contains($parts[1],"O''Reilly"));

$allowed=[
  'SET FOREIGN_KEY_CHECKS=0',
  'DROP TABLE IF EXISTS `clientes`',
  'CREATE TABLE `clientes` (`id` INT)',
  "INSERT INTO `clientes` (`id`) VALUES (1)",
  'ALTER TABLE `clientes` ADD INDEX idx_id (`id`)',
  'UPDATE `clientes` SET `id`=2',
];
$allowedOk=true;
foreach($allowed as $stmt){try{backup_private('validarStatementRestore',$stmt);}catch(Throwable){$allowedOk=false;}}
hub_check($checks,'Restore aceita somente comandos estruturais/de dados esperados',$allowedOk);

$blocked=['SELECT * FROM usuarios','DELETE FROM usuarios','GRANT ALL ON *.* TO x','CREATE USER x'];
$blockedOk=true;
foreach($blocked as $stmt){try{backup_private('validarStatementRestore',$stmt);$blockedOk=false;}catch(RuntimeException){}}
hub_check($checks,'Restore bloqueia leitura, exclusão ampla e administração do servidor',$blockedOk);

$manifestBlocked=true;
foreach([
  ['backup_ok.sql','DROP DATABASE hub;'],
  ['backup_ok.sql','CREATE USER atacante IDENTIFIED BY \'x\';'],
  ['../backup.sql','CREATE TABLE x(id INT);'],
] as [$file,$payload]){
  try{backup_private('validarManifestoRestore',$file,$payload);$manifestBlocked=false;}catch(RuntimeException){}
}
hub_check($checks,'Manifesto bloqueia comandos perigosos e nome fora do padrão',$manifestBlocked);

$implicit=backup_private('containsImplicitCommitStatement',['INSERT INTO x VALUES(1)','ALTER TABLE x ADD y INT']);
$onlyData=backup_private('containsImplicitCommitStatement',['INSERT INTO x VALUES(1)','UPDATE x SET y=2']);
hub_check($checks,'Restore detecta DDL com commit implícito',$implicit===true&&$onlyData===false);

$source=hub_read('app/Services/BackupService.php');
hub_check($checks,'Restore cria ponto de retorno sem exigir permissão extra',str_contains($source,"self::gerarZipInterno('Ponto de retorno automático"));
hub_check($checks,'Backup usa nome aleatório, escrita com lock e permissão 0600',str_contains($source,'bin2hex(random_bytes(4))')&&str_contains($source,'LOCK_EX')&&str_contains($source,'@chmod($sqlFile,0600)'));


// Validação do manifesto e compatibilidade entre banco único/modular sem abrir ZIP.
class App { public static function config():array{return ['installation_id'=>'test-install'];} }
class Database {
  public static bool $single=true;
  public static function isSingleDatabaseMode(?array $cfg=null):bool{return self::$single;}
  public static function modules():array{return self::$single?['core']:['core','pedidos','fila','backups'];}
}
$sampleSql="SET FOREIGN_KEY_CHECKS=0; CREATE TABLE `x` (`id` INT); INSERT INTO `x` (`id`) VALUES (1); SET FOREIGN_KEY_CHECKS=1;";
$part=['module'=>'core','database'=>'hub_test','file'=>'database_core_hub_test.sql','sql'=>$sampleSql,'sha256'=>hash('sha256',$sampleSql),'bytes'=>strlen($sampleSql)];
$manifest=backup_private('buildManifest',[$part],'teste');
hub_check($checks,'Manifesto v2 identifica modo, módulo, hash e instalação',($manifest['format']??'')==='hub-backup-v2'&&($manifest['storage_mode']??'')==='single'&&($manifest['installation_id']??'')==='test-install'&&($manifest['parts'][0]['sha256']??'')===$part['sha256']);
try{backup_private('validarPacoteRestore','backup_test.zip',['mode'=>'single','parts'=>[$part]]);$singleAccepted=true;}catch(Throwable){$singleAccepted=false;}
hub_check($checks,'Pacote de banco único compatível é aceito',$singleAccepted);
Database::$single=false;
try{backup_private('validarPacoteRestore','backup_test.zip',['mode'=>'legacy','parts'=>[$part]]);$legacyModularBlocked=false;}catch(RuntimeException){$legacyModularBlocked=true;}
hub_check($checks,'Backup legado é bloqueado em ambiente modular para evitar restauração no banco errado',$legacyModularBlocked);
$modPart=$part;$modPart['module']='fila';
try{backup_private('validarPacoteRestore','backup_test.zip',['mode'=>'modular','parts'=>[$modPart]]);$modularAccepted=true;}catch(Throwable){$modularAccepted=false;}
hub_check($checks,'Pacote modular usa somente módulos configurados',$modularAccepted);

hub_check($checks,'ZIP aplica limites contra traversal e ZIP bomb',str_contains($source,"str_contains(\$name,'..')")&&str_contains($source,'possível ZIP bomb'));
hub_check($checks,'Ponto de retorno não remove o backup alvo pela retenção',str_contains($source,"gerarZipInterno('Ponto de retorno automático antes do restore ID '.\$id,false)")&&str_contains($source,'if($cleanup) self::limparAntigos(30)'));
hub_check($checks,'Backup modular contém manifesto e uma parte por conexão',str_contains($source,'buildBackupParts')&&str_contains($source,"'format'=>'hub-backup-v2'")&&str_contains($source,'Database::connection((string)$part'));

hub_finish($checks);
