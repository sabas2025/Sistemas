<?php
class SelfTestService {
  public static function executar(): array {
    $itens=[]; $statusGeral='ok';
    $add=function($nome,$ok,$mensagem,$detalhes=[]) use (&$itens,&$statusGeral){
      $itens[]=['nome'=>$nome,'status'=>$ok?'ok':'erro','mensagem'=>$mensagem,'detalhes'=>$detalhes];
      if(!$ok) $statusGeral='erro';
    };
    try { Database::connection('core')->query('SELECT 1'); $add('Banco MySQL',true,'Conexão OK'); } catch(Throwable $e){ $add('Banco MySQL',false,$e->getMessage()); }
    try { $cfg=IntegrationConfig::get(); $add('Configuração Tiny',!empty($cfg['tiny_v2_url']),'URL Tiny: '.($cfg['tiny_v2_url'] ?? 'não configurada')); $add('Configuração VSM',!empty($cfg['vsm_url']),'URL VSM: '.($cfg['vsm_url'] ?? 'não configurada')); $prod=(($cfg['ambiente'] ?? '')==='producao'); $add('Secret Tiny Webhook', !$prod || !empty($cfg['tiny_webhook_exigir_secret']), $prod ? 'Em produção, o secret Tiny deve estar obrigatório.' : 'Homologação/local permite flexibilização controlada.'); } catch(Throwable $e){ $add('Configurações',false,$e->getMessage()); }
    foreach(['../storage','../storage/logs','../storage/backups'] as $dir){ $path=__DIR__.'/../../public/'.$dir; $add('Permissão '.$dir, is_dir($path) && is_writable($path), is_dir($path)?(is_writable($path)?'Gravável':'Sem permissão de escrita'):'Pasta ausente'); }
    $pdo=Database::forTable('fila_integracao');
    $pend=(int)$pdo->query("SELECT COUNT(*) c FROM fila_integracao WHERE status='pendente'")->fetch()['c'];
    $err=(int)$pdo->query("SELECT COUNT(*) c FROM fila_integracao WHERE status IN ('erro','falha_definitiva')")->fetch()['c'];
    $add('Fila', $err===0, $pend.' pendente(s), '.$err.' erro(s)');
    $cb=Database::forTable('circuit_breakers')->query('SELECT id, sistema, status, falhas_consecutivas, aberto_ate, atualizado_em FROM circuit_breakers')->fetchAll(); $add('Circuit Breaker', true, count($cb).' circuito(s) monitorado(s)', $cb);
    $add('Retenção operacional', class_exists('RetentionService'), class_exists('RetentionService') ? 'Serviço de retenção disponível.' : 'RetentionService ausente.');
    $res=['status'=>$statusGeral,'resumo'=>$statusGeral==='ok'?'Self-test concluído sem erro crítico.':'Self-test encontrou erro(s).','itens'=>$itens,'trace_id'=>RequestContext::id()];
    Database::forTable('selftest_relatorios')->prepare('INSERT INTO selftest_relatorios(status,resumo,detalhes,trace_id) VALUES(?,?,?,?)')->execute([$res['status'],$res['resumo'],json_encode($res,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),RequestContext::id()]);
    return $res;
  }
}
