<?php
class FiscalEnterpriseService {
  public static function dashboard(): array {
    $out = [
      'recebidas'=>0,'pendentes'=>0,'xml_erro'=>0,'reenviadas'=>0,'concluidas'=>0,
      'status'=>'ok','alertas'=>[]
    ];
    try { $out['recebidas']=(int)(TenantScopeService::run('notas_fiscais', 'SELECT COUNT(*) c FROM notas_fiscais')->fetch()['c'] ?? 0); } catch(Throwable $e){ $out['status']='erro'; $out['alertas'][]=$e->getMessage(); }
    try { $out['pendentes']=(int)(TenantScopeService::run('nfe_integracao', "SELECT COUNT(*) c FROM nfe_integracao WHERE status IN ('pendente','processando')")->fetch()['c'] ?? 0); } catch(Throwable $e){ $out['status']='erro'; $out['alertas'][]=$e->getMessage(); }
    try { $out['xml_erro']=(int)(TenantScopeService::run('nfe_status_historico', "SELECT COUNT(*) c FROM nfe_status_historico WHERE status_novo IN ('erro_xml','erro_validacao','erro_envio_tiny','erro_envio_vsm')")->fetch()['c'] ?? 0); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    try { $out['reenviadas']=(int)(TenantScopeService::run('nfe_status_historico', "SELECT COUNT(*) c FROM nfe_status_historico WHERE status_novo='reenviado_tiny'")->fetch()['c'] ?? 0); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    try { $out['concluidas']=(int)(TenantScopeService::run('notas_fiscais', "SELECT COUNT(*) c FROM notas_fiscais WHERE status IN ('autorizada','enviada_vsm')")->fetch()['c'] ?? 0); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    if ($out['pendentes'] > 0 || $out['xml_erro'] > 0) $out['status']='atencao';
    if ($out['xml_erro'] > 0) $out['status']='erro';
    return $out;
  }

  public static function timeline(int $notaId): array {
    $eventos=[];
    try {
      $st = TenantScopeService::run('notas_fiscais_eventos', 'SELECT tipo_evento AS tipo, mensagem, trace_id, criado_em FROM notas_fiscais_eventos WHERE nota_fiscal_id=? ORDER BY id ASC', [$notaId]); $eventos=array_merge($eventos,$st->fetchAll());
    } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    try {
      $st = TenantScopeService::run('nfe_status_historico', 'SELECT status_novo AS tipo, motivo AS mensagem, trace_id, criado_em FROM nfe_status_historico WHERE nota_fiscal_id=? ORDER BY id ASC', [$notaId]); $eventos=array_merge($eventos,$st->fetchAll());
    } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    usort($eventos, fn($a,$b)=>strcmp((string)($a['criado_em']??''),(string)($b['criado_em']??'')));
    return $eventos;
  }

  public static function xmls(string $status=''): array {
    $sql='SELECT x.*, n.numero, n.serie, n.chave_acesso, n.status FROM nfe_xml x LEFT JOIN notas_fiscais n ON n.id=x.nota_fiscal_id WHERE 1=1';
    $params=[];
    if($status!=='') { $sql.=' AND n.status=?'; $params[]=$status; }
    $sql.=' ORDER BY x.id DESC LIMIT 100';
    try { $st = TenantScopeService::run('nfe_xml', $sql, $params); return $st->fetchAll(); } catch(Throwable $e){ return []; }
  }

  public static function reconciliacao(): array {
    $dados=['nf_sem_xml'=>0,'xml_sem_envio'=>0,'erro_integracao'=>0,'divergencias'=>[]];
    try { $dados['nf_sem_xml']=(int)(TenantScopeService::run('notas_fiscais', 'SELECT COUNT(*) c FROM notas_fiscais n LEFT JOIN nfe_xml x ON x.nota_fiscal_id=n.id WHERE x.id IS NULL', [], 'n')->fetch()['c'] ?? 0); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    try { $dados['xml_sem_envio']=(int)(TenantScopeService::run('nfe_xml', "SELECT COUNT(*) c FROM nfe_xml x LEFT JOIN nfe_integracao i ON i.nota_fiscal_id=x.nota_fiscal_id WHERE i.id IS NULL", [], 'x')->fetch()['c'] ?? 0); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    try { $dados['erro_integracao']=(int)(TenantScopeService::run('nfe_integracao', "SELECT COUNT(*) c FROM nfe_integracao WHERE status='erro'")->fetch()['c'] ?? 0); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    if($dados['nf_sem_xml']>0) $dados['divergencias'][]='NF-e registrada sem XML vinculado.';
    if($dados['xml_sem_envio']>0) $dados['divergencias'][]='XML salvo sem registro de envio/reenvio fiscal.';
    if($dados['erro_integracao']>0) $dados['divergencias'][]='Integrações fiscais em erro aguardando ação.';
    return $dados;
  }

  public static function health(): array {
    $checks=[];
    foreach(['notas_fiscais','nfe_xml','nfe_integracao','nfe_status_historico','notas_fiscais_eventos','fila_integracao','fila_fiscal','auditoria_eventos','fiscal_reconciliacao_snapshots'] as $table){
      try { Database::forTable($table)->query('SELECT 1 FROM '.$table.' LIMIT 1'); $checks[$table]=['ok'=>true,'msg'=>'ok']; }
      catch(Throwable $e){ $checks[$table]=['ok'=>false,'msg'=>$e->getMessage()]; }
    }
    return $checks;
  }



  public static function proximaTentativa(int $tentativa): ?string {
    $mins = [5,15,30,60];
    try {
      $row = Database::forTable('fiscal_configuracoes_cache')->prepare("SELECT valor FROM fiscal_configuracoes_cache WHERE chave='retry_minutos' LIMIT 1");
      $row->execute();
      $v = $row->fetchColumn();
      if ($v) $mins = array_values(array_filter(array_map('intval', explode(',', (string)$v))));
    } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    $min = $mins[min(max($tentativa-1,0), count($mins)-1)] ?? 60;
    // G-03: jitter aditivo; a escada de retry_minutos continua valendo integralmente.
    return RetryPolicyService::proximaTentativaEm((int)$min);
  }

  public static function registrarStatus(int $notaId, string $novo, ?string $anterior=null, string $motivo=''): void {
    try {
      TenantScopeService::run('nfe_status_historico', 'INSERT INTO nfe_status_historico(nota_fiscal_id,status_anterior,status_novo,motivo,origem,trace_id) VALUES(?,?,?,?,?,?)', [$notaId,$anterior,$novo,$motivo,'hub',RequestContext::id()]);
      Audit::event('fiscal.status.'.$novo, 'sucesso', ['entidade'=>'nota_fiscal','entidade_id'=>$notaId,'mensagem'=>$motivo ?: 'Status fiscal atualizado para '.$novo]);
    } catch(Throwable $e){ Audit::exception($e,'fiscal.status.erro',['entidade'=>'nota_fiscal','entidade_id'=>$notaId]); }
  }

  public static function reprocessar(int $integracaoId): void {
    FiscalIntegrationService::marcarReenvio($integracaoId);
    try { TenantScopeService::run('fila_fiscal', 'INSERT INTO fila_fiscal(nfe_integracao_id,acao,prioridade,status,trace_id) VALUES(?,?,?,?,?)', [$integracaoId,'reprocessar','alta','pendente',RequestContext::id()]); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    try {
      $st = TenantScopeService::run('nfe_integracao', 'SELECT nota_fiscal_id FROM nfe_integracao WHERE id=?', [$integracaoId]);
      $row=$st->fetch(); if($row && !empty($row['nota_fiscal_id'])) self::registrarStatus((int)$row['nota_fiscal_id'],'reprocessamento_solicitado',null,'Reprocessamento fiscal solicitado pelo painel.');
    } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
  }
}
