<?php
class ModuleHealthService {
  public static function checks(): array {
    $out=[];
    foreach (Database::modules() as $module) {
      try { $pdo=Database::connection($module); $pdo->query('SELECT 1'); $out[]=['modulo'=>$module,'status'=>'ok','mensagem'=>'Conexão OK']; }
      catch(Throwable $e){ $out[]=['modulo'=>$module,'status'=>'erro','mensagem'=>$e->getMessage()]; }
    }
    $tablesByModule=[];
    foreach(['usuarios','pedidos_integracao','produtos_mapeamento','estoque_movimentos','notas_fiscais','fila_integracao','logs_integracao','backups'] as $t){ $tablesByModule[]=[$t,Database::tableModule($t)]; }
    foreach($tablesByModule as [$table,$module]){
      try { Database::forTable($table)->query('SELECT 1 FROM '.$table.' LIMIT 1'); $out[]=['modulo'=>$module,'status'=>'ok','mensagem'=>'Tabela '.$table.' acessível']; }
      catch(Throwable $e){ $out[]=['modulo'=>$module,'status'=>'erro','mensagem'=>'Tabela '.$table.': '.$e->getMessage()]; }
    }
    return $out;
  }
}
