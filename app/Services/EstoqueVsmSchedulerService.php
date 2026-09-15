<?php
/**
 * V65 - Consulta programada de estoque VSM produção limpa.
 * Objetivo: consultar periodicamente o estoque real na VSM dos produtos cadastrados/mapeados no Hub
 * e enviar alterações para o Tiny, mantendo a VSM como fonte autoritativa.
 */
class EstoqueVsmSchedulerService {
  public static function config(): array {
    $cfg = EstoqueEnterpriseService::config();
    $defaults = [
      'consulta_vsm_ativa' => '1',
      'consulta_vsm_modo' => 'automatico',
      'consulta_vsm_intervalo_minutos' => '60',
      'consulta_vsm_quantidade_produtos' => '100',
      'consulta_vsm_enviar_tiny_se_alterou' => '1',
      'consulta_vsm_apenas_produtos_ativos' => '1',
      'consulta_vsm_ordem' => 'menos_recente',
      'consulta_vsm_variacao_minima' => '0',
      'consulta_vsm_metodo_http' => 'POST',
      'consulta_vsm_endpoint' => '/api/estoque/consulta',
      'consulta_vsm_payload_template' => '{"sku":"{{sku}}","trace_id":"{{trace_id}}"}',
      'consulta_vsm_timeout_segundos' => '30',
      'consulta_vsm_alerta_falhas_percentual' => '30',
    ];
    return array_merge($defaults, $cfg);
  }

  public static function deveExecutar(): array {
    $cfg = self::config();
    if (($cfg['consulta_vsm_ativa'] ?? '1') !== '1') {
      return ['ok'=>false,'motivo'=>'Consulta VSM desativada nas configurações de estoque.'];
    }
    if (($cfg['consulta_vsm_modo'] ?? 'automatico') === 'manual') {
      return ['ok'=>false,'motivo'=>'Consulta VSM em modo manual. Execute pelo botão ou worker com --force.'];
    }
    $intervalo = max(5, min(1440, (int)($cfg['consulta_vsm_intervalo_minutos'] ?? 60)));
    $db = Database::forTable('estoque_consulta_vsm_execucoes');
    try {
      $last = $db->query("SELECT finalizado_em, iniciado_em FROM estoque_consulta_vsm_execucoes WHERE status IN ('concluido','parcial','erro') ORDER BY id DESC LIMIT 1")->fetch();
      if ($last) {
        $base = $last['finalizado_em'] ?: $last['iniciado_em'];
        if ($base && strtotime($base) > time() - ($intervalo * 60)) {
          return ['ok'=>false,'motivo'=>'Intervalo mínimo ainda não atingido.','ultima_execucao'=>$base,'intervalo_minutos'=>$intervalo];
        }
      }
    } catch (Throwable $e) {
      if(class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e, ['operation'=>'last_execution']);
    }
    return ['ok'=>true,'intervalo_minutos'=>$intervalo];
  }

  public static function criarExecucao(string $modo='automatico'): int {
    $cfg = self::config();
    $db = Database::forTable('estoque_consulta_vsm_execucoes');
    $db->prepare("INSERT INTO estoque_consulta_vsm_execucoes(trace_id,modo,status,intervalo_minutos,limite_produtos,iniciado_em) VALUES(?,?,'executando',?,?,NOW())")
      ->execute([RequestContext::id(), $modo, max(5,(int)$cfg['consulta_vsm_intervalo_minutos']), max(1,(int)$cfg['consulta_vsm_quantidade_produtos'])]);
    return (int)$db->lastInsertId();
  }

  public static function produtosParaConsulta(int $limite): array {
    $limite = max(1, min(1000, $limite));
    $cfg = self::config();
    $ativos = ($cfg['consulta_vsm_apenas_produtos_ativos'] ?? '1') === '1';
    $ordem = $cfg['consulta_vsm_ordem'] ?? 'menos_recente';
    $orderSql = $ordem === 'sku' ? 'sku ASC' : 'COALESCE(atualizado_em, criado_em) ASC, id ASC';

    // Preferência: cadastro local de produtos Tiny, consultando o saldo real na VSM pelo SKU mapeado.
    try {
      $sql = "SELECT DISTINCT COALESCE(NULLIF(sku,''), NULLIF(codigo,'')) sku, produto_tiny_id, nome, status_tiny FROM produtos_tiny WHERE COALESCE(NULLIF(sku,''), NULLIF(codigo,'')) IS NOT NULL";
      if ($ativos) $sql .= " AND (status_tiny IS NULL OR status_tiny NOT IN ('inativo','I','0','desativado'))";
      $sql .= " ORDER BY $orderSql LIMIT ".(int)$limite;
      $rows = TenantScopeService::run('produtos_tiny', $sql)->fetchAll();
      $rows = array_values(array_filter($rows, fn($r)=>trim((string)($r['sku'] ?? '')) !== ''));
      if ($rows) return $rows;
    } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }

    // Fallback: produtos mapeados.
    try {
      $sql = "SELECT DISTINCT COALESCE(NULLIF(sku_vsm,''), NULLIF(sku_hub,''), NULLIF(sku_tiny,'')) sku, id_produto_tiny produto_tiny_id, nome_produto nome, 'ativo' status_tiny FROM produtos_mapeamento WHERE COALESCE(NULLIF(sku_vsm,''), NULLIF(sku_hub,''), NULLIF(sku_tiny,'')) IS NOT NULL ORDER BY atualizado_em ASC LIMIT ".(int)$limite;
      return TenantScopeService::run('produtos_mapeamento', $sql)->fetchAll();
    } catch (Throwable $e) {
      if(class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e, ['fallback_table'=>'produtos_mapeamento']);
      return [];
    }
  }

  public static function executarConsultaProgramada(bool $forcar=false, ?int $limiteOverride=null, string $modo='automatico'): array {
    if (!self::adquirirLock()) {
      return ['executado'=>false,'motivo'=>'Worker de consulta VSM já está em execução.'];
    }
    try {
    if (!$forcar) {
      $check = self::deveExecutar();
      if (!$check['ok']) return ['executado'=>false] + $check;
    }
    $cfg = self::config();
    $limite = $limiteOverride !== null ? $limiteOverride : (int)($cfg['consulta_vsm_quantidade_produtos'] ?? 100);
    $limite = max(1, min(1000, $limite));
    $execId = self::criarExecucao($modo);
    $produtos = self::produtosParaConsulta($limite);
    $client = new VsmService();
    $total = count($produtos); $sucesso=0; $erro=0; $alterados=0; $enfileirados=0;

    foreach ($produtos as $p) {
      $sku = trim((string)($p['sku'] ?? ''));
      if ($sku === '') continue;
      try {
        $ret = self::consultarEstoqueVSM($client, $sku);
        $saldo = self::extrairSaldo($ret);
        if ($saldo === null) throw new RuntimeException('Não foi possível extrair saldo da resposta VSM para o SKU '.$sku.'.');
        $cache = self::saldoCache($sku);
        $saldoAnterior = $cache['saldo_vsm'] ?? null;
        $variacaoMinima = (float)($cfg['consulta_vsm_variacao_minima'] ?? 0);
        $alterou = $saldoAnterior === null || abs(((float)$saldoAnterior) - (float)$saldo) > $variacaoMinima;
        self::atualizarCacheVSM($sku, (float)$saldo, $ret);
        self::registrarResultado($execId,$sku,'sucesso',(float)$saldo,$saldoAnterior,$alterou,$ret,null);
        $sucesso++;
        if ($alterou) {
          $alterados++;
          EstoqueEnterpriseService::auditarSku($sku,'estoque.vsm.consulta_alteracao','saldo_vsm_anterior','saldo_vsm_atualizado',$saldoAnterior === null ? null : (float)$saldoAnterior,(float)$saldo,'vsm_scheduler','Consulta programada identificou saldo VSM novo/alterado.',['execucao_id'=>$execId]);
          if (($cfg['consulta_vsm_enviar_tiny_se_alterou'] ?? '1') === '1' && ($cfg['permitir_vsm_tiny'] ?? '1') === '1') {
            $filaId = EstoqueEnterpriseService::enfileirarEstoque('vsm','tiny',$sku,(float)$saldo,[
              'origem_consulta'=>'vsm_scheduler',
              'execucao_id'=>$execId,
              'saldo_anterior'=>$saldoAnterior,
              'saldo_vsm'=>$saldo,
              'politica'=>'vsm_estoque_real_com_consulta_vsm_programada',
              'retorno_vsm'=>$ret
            ]);
            if ($filaId) $enfileirados++;
          }
        }
      } catch (Throwable $e) {
        $erro++;
        self::registrarResultado($execId,$sku,'erro',null,null,false,[], $e->getMessage());
        EstoqueEnterpriseService::registrarAlerta($sku,'consulta_vsm_erro',$e->getMessage(),'erro',['execucao_id'=>$execId]);
      }
    }

    $status = $erro > 0 && $sucesso > 0 ? 'parcial' : ($erro > 0 ? 'erro' : 'concluido');
    Database::forTable('estoque_consulta_vsm_execucoes')->prepare("UPDATE estoque_consulta_vsm_execucoes SET status=?, total_produtos=?, total_sucesso=?, total_erro=?, total_alterados=?, total_enfileirados_tiny=?, finalizado_em=NOW(), mensagem=? WHERE id=?")
      ->execute([$status,$total,$sucesso,$erro,$alterados,$enfileirados,'Consulta programada VSM finalizada.',$execId]);
    Audit::event('estoque.vsm.consulta_programada.finalizada',$status==='concluido'?'sucesso':($status==='parcial'?'alerta':'erro'),['entidade'=>'estoque_consulta_vsm_execucoes','entidade_id'=>$execId,'mensagem'=>'Consulta programada de estoque VSM finalizada.','contexto'=>compact('total','sucesso','erro','alterados','enfileirados')]);
    $percentualErro = $total > 0 ? round(($erro / $total) * 100, 2) : 0;
    $limiteAlerta = (float)($cfg['consulta_vsm_alerta_falhas_percentual'] ?? 30);
    if ($total > 0 && $percentualErro >= $limiteAlerta) {
      EstoqueEnterpriseService::registrarAlerta(null,'consulta_vsm_falhas_altas','Muitas falhas na consulta VSM: '.$percentualErro.'%','erro',['execucao_id'=>$execId,'total'=>$total,'erro'=>$erro]);
    }
    return compact('execId','total','sucesso','erro','alterados','enfileirados','status','percentualErro');
    } finally {
      self::liberarLock();
    }
  }

  private static function adquirirLock(): bool {
    $file = __DIR__.'/../../storage/cache/worker_consulta_estoque_vsm.lock';
    if (!is_dir(dirname($file))) @mkdir(dirname($file), 0775, true);
    if (file_exists($file) && (time() - filemtime($file)) < 1800) return false;
    file_put_contents($file, getmypid().'|'.date('c'));
    return true;
  }

  private static function liberarLock(): void {
    $file = __DIR__.'/../../storage/cache/worker_consulta_estoque_vsm.lock';
    if (file_exists($file)) @unlink($file);
  }

  private static function consultarEstoqueVSM($client, string $sku): array {
    if (method_exists($client, 'consultarEstoque')) return $client->consultarEstoque($sku);
    throw new RuntimeException('Cliente VSM não possui método consultarEstoque.');
  }

  public static function extrairSaldo(array $ret): ?float {
    $paths = [
      ['saldo'], ['estoque'], ['quantidade'],
      ['data','saldo'], ['data','estoque'], ['data','quantidade'],
      ['retorno','produto','estoque'], ['retorno','produtos',0,'produto','estoque'],
      ['produto_encontrado','estoque'], ['produto_encontrado','saldo'], ['produto_encontrado','produto','estoque'],
    ];
    foreach ($paths as $path) {
      $v = self::getPath($ret, $path);
      if ($v !== null && $v !== '' && is_numeric(str_replace(',','.',(string)$v))) return (float)str_replace(',','.',(string)$v);
    }
    // Busca recursiva por chaves comuns.
    $found = self::findNumericByKeys($ret, ['saldo','estoque','quantidadeEstoque','quantidade_estoque','saldoEstoque']);
    return $found;
  }

  private static function getPath($arr, array $path) {
    $cur=$arr;
    foreach($path as $k){ if(!is_array($cur) || !array_key_exists($k,$cur)) return null; $cur=$cur[$k]; }
    return $cur;
  }

  private static function findNumericByKeys($data, array $keys): ?float {
    if (!is_array($data)) return null;
    foreach ($data as $k=>$v) {
      if (in_array((string)$k, $keys, true) && is_numeric(str_replace(',','.',(string)$v))) return (float)str_replace(',','.',(string)$v);
      if (is_array($v)) { $r=self::findNumericByKeys($v,$keys); if($r!==null) return $r; }
    }
    return null;
  }

  private static function saldoCache(string $sku): ?array {
    $st = TenantScopeService::run('estoque_saldos_cache', 'SELECT * FROM estoque_saldos_cache WHERE sku=?', [$sku]); $r=$st->fetch(); return $r ?: null;
  }

  private static function atualizarCacheVSM(string $sku, float $saldo, array $ret): void {
    // Roda pelo agendador, fora de sessão: sem o escopo aqui a linha de cache nascia com
    // empresa_id NULL. A gravação crua passava pelo portão por acidente — ver o achado I-02.
    TenantScopeService::run('estoque_saldos_cache', "INSERT INTO estoque_saldos_cache(sku,saldo_vsm,diferenca,trace_id) VALUES(?,?,0,?) ON DUPLICATE KEY UPDATE saldo_vsm=VALUES(saldo_vsm), diferenca=saldo_tiny-saldo_vsm, trace_id=VALUES(trace_id), atualizado_em=NOW()", [$sku,$saldo,RequestContext::id()]);
    try {
      // P1-08 (reauditoria 2026-08-23): a resposta integral da VSM era gravada sem
      // sanitização nem limite de tamanho. Agora usa o mascaramento/truncamento que
      // já existe no projeto (SensitiveDataService::sanitizeForStorage).
      TenantScopeService::run('produtos_tiny', "UPDATE produtos_tiny SET estoque_atual=?, payload_json=?, atualizado_em=NOW() WHERE sku=? OR codigo=?", [$saldo,SensitiveDataService::sanitizeForStorage($ret),$sku,$sku]);
    } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
  }

  private static function registrarResultado(int $execId, string $sku, string $status, ?float $saldo, $saldoAnterior, bool $alterou, array $ret, ?string $erro): void {
    // P1-08: idem - retorno_json passa a ser sanitizado/truncado antes de persistir.
    Database::forTable('estoque_consulta_vsm_resultados')->prepare("INSERT INTO estoque_consulta_vsm_resultados(execucao_id,sku,status,saldo_vsm,saldo_anterior,alterou,retorno_json,erro,trace_id) VALUES(?,?,?,?,?,?,?,?,?)")
      ->execute([$execId,$sku,$status,$saldo,$saldoAnterior,$alterou?1:0,SensitiveDataService::sanitizeForStorage($ret),$erro,RequestContext::id()]);
  }
}

// V65: consulta programada oficial é somente VSM. Worker/relatório Tiny removidos para evitar uso errado em produção.
