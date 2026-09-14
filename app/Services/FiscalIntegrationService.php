<?php
class FiscalIntegrationService {
  public static function resumo(): array {
    $tables = ['notas_fiscais','notas_fiscais_eventos','nfe_integracao','nfe_xml','nfe_status_historico'];
    $out = ['total'=>0,'pendentes'=>0,'erros'=>0,'autorizadas'=>0,'tabelas'=>[]];
    foreach ($tables as $table) {
      try {
        $pdo = Database::forTable($table);
        $pdo->query('SELECT 1 FROM '.$table.' LIMIT 1');
        $out['tabelas'][$table] = 'ok';
      } catch (Throwable $e) { $out['tabelas'][$table] = 'erro: '.$e->getMessage(); }
    }
    try { $out['total'] = (int)TenantScopeService::run('notas_fiscais', 'SELECT COUNT(*) c FROM notas_fiscais')->fetch()['c']; } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    try { $out['pendentes'] = (int)TenantScopeService::run('nfe_integracao', "SELECT COUNT(*) c FROM nfe_integracao WHERE status='pendente'")->fetch()['c']; } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    try { $out['erros'] = (int)TenantScopeService::run('nfe_integracao', "SELECT COUNT(*) c FROM nfe_integracao WHERE status='erro'")->fetch()['c']; } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    try { $out['autorizadas'] = (int)TenantScopeService::run('notas_fiscais', "SELECT COUNT(*) c FROM notas_fiscais WHERE status='autorizada'")->fetch()['c']; } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    return $out;
  }

  public static function registrarEvento(int $notaId, string $tipo, string $mensagem = '', array $payload = []): void {
    $pdo = Database::forTable('notas_fiscais_eventos');
    TenantScopeService::run('notas_fiscais_eventos', 'INSERT INTO notas_fiscais_eventos(nota_fiscal_id,tipo_evento,mensagem,payload,trace_id) VALUES(?,?,?,?,?)', [$notaId,$tipo,$mensagem,json_encode($payload, JSON_UNESCAPED_UNICODE), RequestContext::id()]);
  }

  public static function listar(string $status = '', int $limit = 100): array {
    $sql = 'SELECT * FROM notas_fiscais WHERE 1=1'; $params=[];
    if ($status !== '') { $sql .= ' AND status=?'; $params[]=$status; }
    $sql .= ' ORDER BY id DESC LIMIT '.max(1,min(500,$limit));
    $st = TenantScopeService::run('notas_fiscais', $sql, $params); return $st->fetchAll();
  }

  public static function pendenciarEnvioVsm(int $notaId, string $chave = ''): void {
    $trace=RequestContext::id();
    $pdo=Database::forTable('nfe_integracao');
    TenantScopeService::run('nfe_integracao', 'INSERT INTO nfe_integracao(nota_fiscal_id,destino,status,tentativas,ultimo_erro,trace_id) VALUES(?,?,?,?,?,?)', [$notaId,'vsm','pendente',0,'',$trace]);
    self::registrarEvento($notaId,'pendente_vsm','NF-e marcada para envio à VSM',['chave'=>$chave,'trace_id'=>$trace]);
  }

  public static function marcarReenvio(int $integracaoId): void {
    $pdo=Database::forTable('nfe_integracao');
    TenantScopeService::run('nfe_integracao', "UPDATE nfe_integracao SET status='pendente', ultimo_erro=NULL, atualizado_em=NOW() WHERE id=?", [$integracaoId]);
  }
}

