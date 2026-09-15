<?php
class MultiDbMigrationService {
  public static function moduleSqlFiles(): array {
    $dir = dirname(__DIR__,2).'/database/modules';
    return [
      'core'=>$dir.'/core.sql', 'pedidos'=>$dir.'/pedidos.sql', 'produtos'=>$dir.'/produtos.sql', 'estoque'=>$dir.'/estoque.sql',
      'fiscal'=>$dir.'/fiscal.sql', 'fila'=>$dir.'/fila.sql', 'observabilidade'=>$dir.'/observabilidade.sql', 'backups'=>$dir.'/backups.sql'
    ];
  }
  public static function instalarModulo(string $module): array {
    $files = self::moduleSqlFiles();
    if (empty($files[$module]) || !is_file($files[$module])) return ['ok'=>false,'mensagem'=>'Arquivo SQL do módulo não encontrado.'];
    try { $pdo = Database::connection($module); self::runSqlFile($pdo, $files[$module]); return ['ok'=>true,'mensagem'=>'Módulo '.$module.' instalado/atualizado com sucesso.']; }
    catch(Throwable $e) { return ['ok'=>false,'mensagem'=>$e->getMessage()]; }
  }
  public static function instalarTodos(): array { $out=[]; foreach(array_keys(self::moduleSqlFiles()) as $module){ $out[$module]=self::instalarModulo($module); } return $out; }
  public static function runSqlFile(PDO $pdo, string $file): void {
    $sql = file_get_contents($file); if ($sql === false) throw new RuntimeException('Não foi possível ler '.$file);
    $sql = preg_replace('/^\s*CREATE DATABASE .*?;\s*$/mi','',$sql);
    $sql = preg_replace('/^\s*USE .*?;\s*$/mi','',$sql);
    foreach(self::splitSql($sql) as $stmt){ if(trim($stmt)!=='') $pdo->exec($stmt); }
  }
  private static function splitSql(string $sql): array {
    $parts=[]; $buff=''; $inStr=false; $quote=''; $len=strlen($sql);
    for($i=0;$i<$len;$i++){ $c=$sql[$i]; $buff.=$c; if(($c==='"'||$c==="'") && ($i===0 || $sql[$i-1] !== '\\')){ if(!$inStr){$inStr=true;$quote=$c;} elseif($quote===$c){$inStr=false;} } if($c===';' && !$inStr){ $parts[]=$buff; $buff=''; } }
    if(trim($buff)!=='') $parts[]=$buff; return $parts;
  }
}
