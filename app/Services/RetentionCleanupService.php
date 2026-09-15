<?php
class RetentionCleanupService {
  public static function executar(int $dias=90, bool $dryRun=true): array {
    if (!$dryRun && class_exists('PermissionService')) PermissionService::require('logs','purgar');
    $dias=max(7,min(365,$dias));
    $targets=[
      ['auditoria_eventos','criado_em'],['logs_integracao','criado_em'],['vsm_endpoint_logs','criado_em'],
      ['tiny_v3_endpoint_logs','criado_em'],['tiny_v2_endpoint_logs','criado_em'],['payload_snapshots','criado_em'],
      ['fila_integracao','criado_em'],['fila_morta','criado_em'],['dashboard_testes_execucoes','criado_em']
    ];
    $res=[]; $limite=date('Y-m-d H:i:s', time()-($dias*86400));
    foreach($targets as [$table,$col]){
      try{
        if(!Database::tableExists($table)){ $res[]=['tabela'=>$table,'status'=>'ignorada','detalhe'=>'Tabela ausente']; continue; }
        $pdo=Database::forTable($table);
        $sql="SELECT COUNT(*) c FROM `$table` WHERE `$col` < ?";
        if($table==='fila_integracao') $sql .= " AND status IN ('processado','falha_definitiva','cancelado')";
        $st=$pdo->prepare($sql); $st->execute([$limite]); $count=(int)($st->fetch()['c'] ?? 0);
        if(!$dryRun && $count>0){ $del=str_replace('SELECT COUNT(*) c','DELETE',$sql); $d=$pdo->prepare($del); $d->execute([$limite]); }
        $res[]=['tabela'=>$table,'status'=>$dryRun?'simulado':'limpo','registros'=>$count,'limite'=>$limite];
      }catch(Throwable $e){ $res[]=['tabela'=>$table,'status'=>'erro','detalhe'=>$e->getMessage()]; }
    }
    SimpleCache::forget();
    Audit::event('retencao.v50.executada','info',['mensagem'=>'Limpeza/retencao V50 executada.','contexto'=>['dias'=>$dias,'dry_run'=>$dryRun,'resultado'=>$res]]);
    return ['dias'=>$dias,'dry_run'=>$dryRun,'resultado'=>$res,'executado_em'=>date('Y-m-d H:i:s')];
  }
}
