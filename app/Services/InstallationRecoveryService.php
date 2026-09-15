<?php
class InstallationRecoveryService {
  public static function status(): array {
    $root = __DIR__.'/../..';
    $lock = $root.'/storage/install.lock';
    $cfg = $root.'/config/config.php';
    $items = [
      ['item'=>'Arquivo config.php','ok'=>file_exists($cfg),'acao'=>'Execute install.php se não existir.'],
      ['item'=>'Install lock','ok'=>file_exists($lock),'acao'=>'Se já instalou, mantenha. Para reinstalar, remova manualmente.'],
      ['item'=>'Storage gravável','ok'=>is_writable($root.'/storage'),'acao'=>'Ajuste permissão da pasta storage.'],
      ['item'=>'Logs gravável','ok'=>is_writable($root.'/storage/logs'),'acao'=>'Ajuste permissão de storage/logs.'],
      ['item'=>'Backups gravável','ok'=>is_writable($root.'/storage/backups'),'acao'=>'Ajuste permissão de storage/backups.'],
    ];
    try {
      $pdo = Database::getConnection();
      $tables = ['usuarios','configuracoes_integracao','fila_integracao','auditoria_eventos','logs_integracao'];
      foreach($tables as $t){
        try { $pdo->query("SELECT 1 FROM {$t} LIMIT 1"); $items[]=['item'=>'Tabela '.$t,'ok'=>true,'acao'=>'OK']; }
        catch(Throwable $e){ $items[]=['item'=>'Tabela '.$t,'ok'=>false,'acao'=>'Rodar atualização/instalação.']; }
      }
    } catch(Throwable $e) {
      $items[]=['item'=>'Banco de dados','ok'=>false,'acao'=>$e->getMessage()];
    }
    $ok = count(array_filter($items, fn($i)=>$i['ok']));
    return ['score'=>round(($ok/count($items))*100),'items'=>$items];
  }
}
