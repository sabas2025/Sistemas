<?php
class DatabaseInventoryService {
  private const OPERACIONAIS = ['pedidos','produtos','produto_pendencias','produtos_vsm_eventos','fila_integracao','fila_morta','webhook_requisicoes','tiny_webhooks','auditoria_eventos','eventos_processados','estoque_movimentos','estoque_divergencias','notas_fiscais','nfe_xml','nfe_integracao','fila_fiscal'];
  private const TECNICAS = ['circuit_breakers','security_events','ips_bloqueados','rate_limit_hits','login_tentativas','auditoria_hash_chain','auditoria_assinaturas','audit_daily_signatures','integration_replay_guard','token_vault','backups_banco','schema_migrations','module_health_snapshots'];
  private const HOMOLOGACAO = ['tiny_v2_homologacao_testes','tiny_v3_homologacao_testes','tiny_validacoes_execucoes','tiny_testes_reais','homologacao_checklist','homologacao_automatica_relatorios'];

  public static function classify(): array {
    $tables = self::tables();
    $out=[];
    foreach ($tables as $t) {
      $grupo='Legado'; $status='LEGADO'; $acao='Revisar uso real antes de remover.';
      if (in_array($t, self::OPERACIONAIS, true)) { $grupo='Operacionais'; $status='ATIVA'; $acao='Manter, auditar e aplicar retenção.'; }
      elseif (in_array($t, self::TECNICAS, true)) { $grupo='Técnicas'; $status='ATIVA'; $acao='Manter para segurança, rastreabilidade e operação.'; }
      elseif (in_array($t, self::HOMOLOGACAO, true) || str_contains($t,'homologacao')) { $grupo='Homologação'; $status='ATIVA'; $acao='Manter enquanto Tiny/VSM estiverem em homologação.'; }
      elseif (preg_match('/(^tmp_|_old$|_bak$|v\d{2}|legacy|historico)/i', $t)) { $grupo='Legado'; $status='LEGADO'; $acao='Candidato a arquivamento depois de backup assinado.'; }
      elseif (preg_match('/roadmap|future|pendente/i', $t)) { $grupo='Futuras'; $status='FUTURA'; $acao='Não usar em produção até existir rota/processo homologado.'; }
      $out[]=['tabela'=>$t,'grupo'=>$grupo,'status'=>$status,'acao'=>$acao,'modulo'=>self::moduleOf($t)];
    }
    usort($out, fn($a,$b)=>strcmp($a['grupo'].$a['tabela'],$b['grupo'].$b['tabela']));
    return ['items'=>$out,'resumo'=>self::resumo($out),'total'=>count($out),'gerado_em'=>date('Y-m-d H:i:s')];
  }

  private static function tables(): array {
    $all=[];
    foreach (array_keys(App::config()['db_modules'] ?? ['core'=>[]]) as $module) {
      try {
        $pdo=Database::connection($module);
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) $all[(string)$t]=true;
      } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    }
    if (!$all) {
      try { foreach (Database::getConnection()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) $all[(string)$t]=true; } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    }
    $tables=array_keys($all); sort($tables); return $tables;
  }
  private static function moduleOf(string $table): string { try { return Database::tableModule($table); } catch(Throwable $e){ return 'core'; } }
  private static function resumo(array $items): array { $r=[]; foreach($items as $i){ $r[$i['grupo']][$i['status']] = ($r[$i['grupo']][$i['status']] ?? 0)+1; } return $r; }
}
