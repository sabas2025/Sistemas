<?php
class RetentionService {
  public static function limparOperacional(int $diasLogs=90, int $diasAuditoria=180, int $diasWebhooks=180): array {
    $result = ['logs'=>0,'auditoria'=>0,'webhooks'=>0,'metricas'=>0,'diagnostico'=>0,'selftests'=>0];
    $map = [
      'logs' => ["DELETE FROM logs_integracao WHERE criado_em < DATE_SUB(NOW(), INTERVAL ? DAY)", $diasLogs],
      'auditoria' => ["DELETE FROM auditoria_eventos WHERE criado_em < DATE_SUB(NOW(), INTERVAL ? DAY)", $diasAuditoria],
      'webhooks' => ["DELETE FROM tiny_webhooks WHERE criado_em < DATE_SUB(NOW(), INTERVAL ? DAY)", $diasWebhooks],
      'metricas' => ["DELETE FROM metricas_api WHERE criado_em < DATE_SUB(NOW(), INTERVAL ? DAY)", $diasLogs],
      'diagnostico' => ["DELETE FROM diagnostico_api WHERE criado_em < DATE_SUB(NOW(), INTERVAL ? DAY)", $diasLogs],
      'selftests' => ["DELETE FROM selftest_relatorios WHERE criado_em < DATE_SUB(NOW(), INTERVAL ? DAY)", $diasLogs],
    ];
    foreach($map as $key=>$cfg){
      try { $st=$pdo->prepare($cfg[0]); $st->execute([(int)$cfg[1]]); $result[$key]=$st->rowCount(); }
      catch(Throwable $e){ $result[$key]='erro: '.$e->getMessage(); }
    }
    $backups = BackupService::limparAntigos(30);
    Audit::event('retencao.limpeza','sucesso',[
      'mensagem'=>'Retenção operacional executada.',
      'contexto'=>array_merge($result, ['backups_removidos'=>$backups]),
      'acao_recomendada'=>'Mantenha backup externo antes de reduzir os prazos de retenção.'
    ]);
    return array_merge($result, ['backups_removidos'=>$backups]);
  }
}
