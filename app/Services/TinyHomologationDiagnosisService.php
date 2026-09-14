<?php
class TinyHomologationDiagnosisService {
  public static function montar(array $checks, ?array $ultimo=null, string $versao='Tiny'): array {
    $passou=[]; $falhou=[]; $pendente=[]; $alerta=[]; $acoes=[];
    foreach ($checks as $c) {
      $label=(string)($c['label'] ?? $c['key'] ?? 'Etapa');
      $status=(string)($c['status'] ?? 'pendente');
      $det=(string)($c['detalhe'] ?? '');
      $acao=(string)($c['acao_recomendada'] ?? '');
      if ($status==='ok') $passou[]=$label;
      elseif ($status==='alerta') { $alerta[]=$label; $acoes[]=$acao ?: ($label.': revisar divergência antes da produção.'); }
      elseif ($status==='falha') { $falhou[]=$label; $acoes[]=$acao ?: ($label.': corrigir falha e executar novamente.'); }
      else { $pendente[]=$label; $acoes[]=$acao ?: ($label.': executar ou informar dados de teste.'); }
    }
    $total=count($checks); $ok=count($passou); $warn=count($alerta); $fail=count($falhou); $pend=count($pendente);
    $status='pendente';
    if ($total>0 && $ok===$total) $status='aprovada';
    elseif ($fail>0) $status='requer_ajuste';
    elseif ($warn>0) $status='parcial_com_alerta';
    elseif ($ok>0) $status='parcial';
    $modoRecomendado = ($pend>0 || $warn>0 || $fail>0) ? 'continuar' : 'completa';
    $resumo = $versao.' '; 
    if ($status==='aprovada') $resumo .= 'aprovada. Todas as etapas essenciais passaram.';
    elseif ($status==='requer_ajuste') $resumo .= 'com falhas. Corrija as etapas vermelhas antes de liberar produção.';
    elseif ($status==='parcial_com_alerta') $resumo .= 'parcial com alerta. Divergências podem ser aceitas só como homologação parcial.';
    elseif ($status==='parcial') $resumo .= 'parcial. Continue as etapas pendentes.';
    else $resumo .= 'pendente. Execute a primeira homologação.';
    return [
      'status'=>$status,
      'resumo'=>$resumo,
      'passou'=>$passou,
      'alerta'=>$alerta,
      'falhou'=>$falhou,
      'pendente'=>$pendente,
      'acoes_recomendadas'=>array_values(array_unique(array_filter($acoes))),
      'modo_recomendado'=>$modoRecomendado,
      'ultimo_trace'=>$ultimo['trace_id'] ?? null,
      'ultimo_teste'=>$ultimo['criado_em'] ?? null,
    ];
  }

  public static function statusEtapa(array $etapa, bool $pedidoOpcional=false): string {
    if (!$etapa) return 'pendente';
    if (!empty($etapa['ok'])) return 'ok';
    if (($etapa['status_homologacao'] ?? '') === 'alerta') return 'alerta';
    if (($etapa['status_homologacao'] ?? '') === 'pendente') return 'pendente';
    if ($pedidoOpcional && isset($etapa['codigo_erro']) && $etapa['codigo_erro']==='PEDIDO_OPCIONAL_NAO_INFORMADO') return 'pendente';
    return 'falha';
  }
}
