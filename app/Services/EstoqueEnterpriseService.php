<?php
class EstoqueEnterpriseService {
  public static function config(): array {
    $defaults = [
      'estoque_mestre'=>'vsm',
      'permitir_vsm_tiny'=>'1',
      'permitir_tiny_vsm'=>'1',
      'estrategia_estoque'=>'vsm_fonte_real',
      'ignorar_retorno_espelhado_minutos'=>'10',
      'bloquear_loop_bidirecional'=>'1',
      'reconciliacao_automatica'=>'1',
      'alertar_estoque_negativo'=>'1',
      'alertar_produto_sem_mapeamento'=>'1',
      'retencao_dias'=>'90',
      'retry_minutos'=>'5,15,30,60',
    ];
    try {
      $rows = Database::forTable('estoque_configuracoes')->query("SELECT chave, valor FROM estoque_configuracoes")->fetchAll();
      foreach ($rows as $r) $defaults[$r['chave']] = (string)$r['valor'];
    } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    return $defaults;
  }

  public static function salvarConfig(array $dados): void {
    $db = Database::forTable('estoque_configuracoes');
    $st = $db->prepare("INSERT INTO estoque_configuracoes(chave, valor, atualizado_em) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE valor=VALUES(valor), atualizado_em=NOW()");
    foreach ($dados as $k=>$v) $st->execute([$k, (string)$v]);
  }

  public static function registrarAlerta(string $sku, string $tipo, string $mensagem, string $severidade='alerta', array $ctx=[]): void {
    TenantScopeService::run('estoque_alertas', "INSERT INTO estoque_alertas(sku,tipo,severidade,mensagem,contexto_json,status,trace_id) VALUES(?,?,?,?,?,'aberto',?)", [$sku,$tipo,$severidade,$mensagem,json_encode($ctx,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),RequestContext::id()]);
    Audit::event('estoque.alerta.gerado',$severidade,['entidade'=>'estoque_alertas','mensagem'=>$mensagem,'contexto'=>['sku'=>$sku,'tipo'=>$tipo]+$ctx]);
  }

  public static function enfileirarEstoque(string $origem, string $destino, string $sku, float $quantidade, array $payload=[]): int {
    $config = self::config();
    if (($config['bloquear_loop_bidirecional'] ?? '1') === '1' && $origem === 'vsm' && $destino === 'tiny' && ($config['estoque_mestre'] ?? 'vsm') === 'tiny') {
      self::registrarAlerta($sku,'loop_bloqueado','Fluxo VSM → Tiny bloqueado porque Tiny está definido como estoque mestre.','alerta',['origem'=>$origem,'destino'=>$destino]);
      return 0;
    }
    $trace = RequestContext::id();
    $st = TenantScopeService::run('fila_estoque', "INSERT INTO fila_estoque(origem,destino,sku,quantidade,status,payload,trace_id) VALUES(?,?,?,?, 'pendente', ?, ?)", [$origem,$destino,$sku,$quantidade,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$trace]);
    $id = (int)Database::forTable('fila_estoque')->lastInsertId();
    Audit::event('estoque.fila.criada','info',['entidade'=>'fila_estoque','entidade_id'=>$id,'mensagem'=>'Movimento de estoque enviado para fila exclusiva.','contexto'=>compact('origem','destino','sku','quantidade')]);
    return $id;
  }



  public static function extrairEstoque(array $payload, string $origem): array {
    $dados = $payload['dados'] ?? $payload['data'] ?? $payload['produto'] ?? $payload;
    if (isset($dados['itens']) && is_array($dados['itens']) && isset($dados['itens'][0])) $dados = $dados['itens'][0];
    $sku = (string)($dados['sku'] ?? $dados['codigo'] ?? $dados['codigo_produto'] ?? $dados['codigoProduto'] ?? $dados['referencia'] ?? $payload['sku'] ?? '');
    $saldo = $dados['saldo'] ?? $dados['estoque'] ?? $dados['quantidade'] ?? $dados['qtd'] ?? $payload['saldo'] ?? $payload['estoque'] ?? null;
    $tipo = (string)($payload['tipo_movimento'] ?? $dados['tipo_movimento'] ?? ($origem === 'vsm' ? 'saldo_autoritativo' : 'baixa_venda_tiny'));
    $referencia = (string)($payload['referencia'] ?? $payload['id'] ?? $payload['pedido_id'] ?? $payload['numero'] ?? $sku.'-'.sha1(json_encode($payload)));
    return ['sku'=>trim($sku), 'quantidade'=>$saldo === null ? null : (float)$saldo, 'tipo_movimento'=>$tipo, 'referencia'=>$referencia, 'dados'=>$dados];
  }

  public static function eventoJaProcessado(string $origem, string $referencia, string $sku, string $tipo): bool {
    try {
      $hash = hash('sha256', $origem.'|'.$referencia.'|'.$sku.'|'.$tipo);
      $db = Database::forTable('estoque_eventos_sincronizacao');
      $st = $db->prepare('INSERT INTO estoque_eventos_sincronizacao(hash_evento,origem,referencia,sku,tipo_movimento,trace_id) VALUES(?,?,?,?,?,?)');
      $st->execute([$hash,$origem,$referencia,$sku,$tipo,RequestContext::id()]);
      return false;
    } catch (Throwable $e) {
      return str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'duplic');
    }
  }

  public static function receberAtualizacao(string $origem, array $payload, string $raw=''): array {
    $info = self::extrairEstoque($payload, $origem);
    $sku = $info['sku'];
    $qtd = $info['quantidade'];
    if ($sku === '') throw new RuntimeException('Atualização de estoque sem SKU/código.');
    if ($qtd === null) throw new RuntimeException('Atualização de estoque sem saldo/quantidade.');
    $cfg = self::config();
    $destino = $origem === 'vsm' ? 'tiny' : 'vsm';
    if ($origem === 'vsm' && ($cfg['permitir_vsm_tiny'] ?? '1') !== '1') throw new RuntimeException('Fluxo VSM → Tiny está desativado na política de estoque.');
    if ($origem === 'tiny' && ($cfg['permitir_tiny_vsm'] ?? '1') !== '1') throw new RuntimeException('Fluxo Tiny → VSM está desativado na política de estoque.');
    if (self::eventoJaProcessado($origem, $info['referencia'], $sku, $info['tipo_movimento'])) {
      return ['duplicado'=>true,'sku'=>$sku,'origem'=>$origem,'destino'=>$destino,'message'=>'Evento de estoque duplicado ignorado.'];
    }
    if (($cfg['alertar_estoque_negativo'] ?? '1') === '1' && $qtd < 0) self::registrarAlerta($sku,'estoque_negativo','Estoque negativo recebido de '.$origem.'.','erro',['quantidade'=>$qtd,'payload'=>$payload]);
    TenantScopeService::run('estoque_movimentos', 'INSERT IGNORE INTO estoque_movimentos(origem,referencia,sku,quantidade,tipo_movimento,status,payload_origem,trace_id) VALUES(?,?,?,?,?,?,?,?)', [$origem,$info['referencia'],$sku,$qtd,$info['tipo_movimento'],'recebido',json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),RequestContext::id()]);
    $filaId = self::enfileirarEstoque($origem, $destino, $sku, (float)$qtd, ['referencia'=>$info['referencia'],'tipo_movimento'=>$info['tipo_movimento'],'payload_original'=>$payload,'raw'=>$raw,'estoque_mestre'=>$cfg['estoque_mestre'] ?? 'vsm','estrategia'=>$cfg['estrategia_estoque'] ?? 'vsm_fonte_real']);
    self::auditarSku($sku,'estoque.recebido_'.$origem,null,'pendente_envio_'.$destino,null,(float)$qtd,$origem,'Estoque recebido de '.$origem.' e enfileirado para '.$destino.'.',['fila_id'=>$filaId,'referencia'=>$info['referencia']]);
    return ['ok'=>true,'sku'=>$sku,'quantidade'=>$qtd,'origem'=>$origem,'destino'=>$destino,'fila_id'=>$filaId,'referencia'=>$info['referencia']];
  }

  public static function auditarSku(string $sku, string $evento, ?string $statusAnterior, ?string $statusNovo, ?float $saldoAnterior, ?float $saldoNovo, string $origem, string $mensagem, array $ctx=[]): void {
    try {
      TenantScopeService::run('estoque_auditoria_sku', 'INSERT INTO estoque_auditoria_sku(sku,evento,status_anterior,status_novo,saldo_anterior,saldo_novo,origem,mensagem,contexto_json,trace_id,usuario_id) VALUES(?,?,?,?,?,?,?,?,?,?,?)', [$sku,$evento,$statusAnterior,$statusNovo,$saldoAnterior,$saldoNovo,$origem,$mensagem,json_encode($ctx,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),RequestContext::id(), Auth::check() ? (Auth::user()['id'] ?? null) : null]);
    } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
  }

  public static function criarReconciliacaoManual(): array {
    $trace = RequestContext::id();
    $db = Database::forTable('reconciliacao_execucoes');
    $db->prepare("INSERT INTO reconciliacao_execucoes(trace_id,tipo,status,mensagem,iniciado_em) VALUES(?,'estoque','pendente','Reconciliação manual solicitada pelo painel.',NOW())")->execute([$trace]);
    $id = (int)$db->lastInsertId();
    TenantScopeService::run('fila_estoque', "INSERT INTO fila_estoque(origem,destino,sku,quantidade,status,payload,trace_id) VALUES('hub','hub','__RECONCILIACAO__',0,'pendente',?,?)", [json_encode(['execucao_id'=>$id,'acao'=>'reconciliar_estoque'],JSON_UNESCAPED_UNICODE),$trace]);
    Audit::event('estoque.reconciliacao.solicitada','info',['entidade'=>'reconciliacao_execucoes','entidade_id'=>$id,'mensagem'=>'Reconciliação de estoque solicitada manualmente.']);
    return ['id'=>$id,'trace_id'=>$trace];
  }

  public static function proximaTentativa(int $tentativa): ?string {
    $cfg = self::config();
    $mins = array_values(array_filter(array_map('intval', explode(',', (string)($cfg['retry_minutos'] ?? '5,15,30,60')))));
    $min = $mins[min(max($tentativa-1,0), count($mins)-1)] ?? 60;
    // G-03: jitter aditivo; a escada configurada em retry_minutos continua valendo integralmente.
    return RetryPolicyService::proximaTentativaEm((int)$min);
  }
}
