<?php
class PostInstallTestService {
  public static function run(): array {
    $tests=[]; $add=function($nome,$ok,$msg='') use (&$tests){ $tests[]=['nome'=>$nome,'ok'=>(bool)$ok,'mensagem'=>$msg]; };
    try { Database::connection('core')->query('SELECT 1'); $add('Banco MySQL',true,'Conexão PDO OK.'); } catch(Throwable $e){ $add('Banco MySQL',false,$e->getMessage()); }
    foreach(['usuarios','configuracoes_integracao','fila_integracao','auditoria_eventos','fila_morta'] as $t){ try { $st=Database::forTable($t)->prepare('SHOW TABLES LIKE ?'); $st->execute([$t]); $add('Tabela '.$t,(bool)$st->fetch()); } catch(Throwable $e){ $add('Tabela '.$t,false,$e->getMessage()); } }
    foreach(['storage','storage/logs','storage/backups'] as $dir){ $path=dirname(__DIR__,2).'/'.$dir; $add('Permissão '.$dir, is_dir($path) && is_writable($path), $path); }
    try { Audit::event('selftest.post_install','sucesso',['mensagem'=>'Teste automático pós-instalação executado.']); $add('Auditoria',true,'Evento registrado.'); } catch(Throwable $e){ $add('Auditoria',false,$e->getMessage()); }
    try { QueueV24AnalyticsService::snapshot(); $add('Fila/analytics',true,'Snapshot de fila executado.'); } catch(Throwable $e){ $add('Fila/analytics',false,$e->getMessage()); }
    return $tests;
  }
  public static function score(array $tests): int { if(!$tests) return 0; return (int)round(count(array_filter($tests,fn($t)=>!empty($t['ok'])))*100/count($tests)); }
}
