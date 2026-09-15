<?php
class PedidoCicloVidaService {
  public static function ensureSchema(): void {
    SchemaRuntimePolicyService::requireTables([
      'pedidos_hub','pedidos_payloads','pedidos_status_historico','pedidos_nfe_xml'
    ], 'ciclo de vida Tiny → HUB → VSM → Tiny');
  }

  private static function pdo(): PDO { return Database::forTable('pedidos_hub'); }
  private static function json($v): string { return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
  private static function hashPayload($payload): string { return hash('sha256', is_string($payload) ? $payload : self::json($payload)); }

  public static function fromTinyPedido(array $payload, array $validacao, ?int $pedidoValidacaoId=null): int {
    self::ensureSchema();
    $v = $validacao['payload_vsm'] ?? PedidoTinyVsmValidationService::toVsmPayload($payload);
    $tinyId = (string)($v['pedido_id'] ?? PedidoTinyVsmValidationService::pedidoId($payload));
    $pdo = self::pdo();
    $st = TenantScopeService::run('pedidos_hub', "INSERT INTO pedidos_hub(trace_id,pedido_tiny_id,numero_pedido,status_hub,status_tiny,cliente_nome,cliente_documento,valor_total,data_recebido_tiny,criado_em,atualizado_em)
      VALUES(?,?,?,?,?,?,?,?,NOW(),NOW(),NOW())
      ON DUPLICATE KEY UPDATE numero_pedido=VALUES(numero_pedido),status_tiny=VALUES(status_tiny),cliente_nome=VALUES(cliente_nome),cliente_documento=VALUES(cliente_documento),valor_total=VALUES(valor_total),atualizado_em=NOW()", [RequestContext::id(),$tinyId,(string)($v['numero'] ?? $tinyId),'recebido_tiny',(string)($v['status'] ?? ''),$v['cliente']['nome'] ?? null,$v['cliente']['documento'] ?? null,(float)($v['valor_total'] ?? 0)]);
    $id = (int)$pdo->lastInsertId();
    if(!$id){ $s = TenantScopeService::run('pedidos_hub', 'SELECT id FROM pedidos_hub WHERE pedido_tiny_id=? LIMIT 1', [$tinyId]); $id=(int)$s->fetchColumn(); }
    self::snapshot($id,'tiny','hub','tiny_pedido_original',$payload);
    self::snapshot($id,'hub','vsm','hub_pedido_validado',$v);
    self::status($id,null,'recebido_tiny','tiny','Pedido recebido do Tiny e cópia original gravada no Hub.',null,['pedido_validacao_id'=>$pedidoValidacaoId,'validacao'=>$validacao]);
    if($pedidoValidacaoId){
      try { TenantScopeService::run('pedidos_validacao', "UPDATE pedidos_validacao SET pedido_hub_id=?, status_hub='recebido_tiny' WHERE id=?", [$id,$pedidoValidacaoId]); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    }
    Audit::event('pedido.ciclo.recebido_tiny','sucesso',['entidade'=>'pedidos_hub','entidade_id'=>$id,'mensagem'=>'Pedido Tiny recebido, copiado e vinculado ao ciclo de vida.','payload'=>$payload,'contexto'=>['pedido_validacao_id'=>$pedidoValidacaoId]]);
    return $id;
  }

  public static function snapshot(int $pedidoHubId, string $origem, ?string $destino, string $tipo, $payload): void {
    try {
      TenantScopeService::run('pedidos_payloads', 'INSERT INTO pedidos_payloads(pedido_hub_id,origem,destino,tipo_payload,payload_json,hash_payload,trace_id) VALUES(?,?,?,?,?,?,?)', [$pedidoHubId,$origem,$destino,$tipo,is_string($payload)?$payload:self::json($payload),self::hashPayload($payload),RequestContext::id()]);
    } catch(Throwable $e) { Audit::exception($e,'pedido.snapshot.erro',['pedido_hub_id'=>$pedidoHubId,'tipo'=>$tipo]); }
  }

  public static function status(int $pedidoHubId, ?string $statusAnterior, string $statusNovo, string $origem, string $mensagem, ?string $erro=null, array $ctx=[]): void {
    try {
      $pdo=self::pdo();
      if($statusAnterior===null){ $s = TenantScopeService::run('pedidos_hub', 'SELECT status_hub FROM pedidos_hub WHERE id=?', [$pedidoHubId]); $statusAnterior=(string)($s->fetchColumn() ?: ''); }
      $fields = ['status_hub=?','ultimo_erro=?','atualizado_em=NOW()']; $params=[$statusNovo,$erro,$pedidoHubId];
      if($statusNovo==='enviado_vsm') { $fields[]='data_enviado_vsm=NOW()'; }
      if($statusNovo==='recebido_vsm' || $statusNovo==='xml_validado') { $fields[]='data_recebido_vsm=COALESCE(data_recebido_vsm,NOW())'; }
      if($statusNovo==='enviado_tiny' || $statusNovo==='concluido') { $fields[]='data_enviado_tiny=COALESCE(data_enviado_tiny,NOW())'; }
      TenantScopeService::run('pedidos_hub', 'UPDATE pedidos_hub SET '.implode(',',$fields).' WHERE id=?', $params);
      TenantScopeService::run('pedidos_status_historico', 'INSERT INTO pedidos_status_historico(pedido_hub_id,status_anterior,status_novo,origem,mensagem,erro,usuario_id,trace_id,contexto_json) VALUES(?,?,?,?,?,?,?,?,?)', [$pedidoHubId,$statusAnterior ?: null,$statusNovo,$origem,$mensagem,$erro,Auth::user()['id'] ?? null,RequestContext::id(),self::json($ctx)]);
      Audit::event('pedido.status.'.$statusNovo, $erro?'erro':'sucesso', ['entidade'=>'pedidos_hub','entidade_id'=>$pedidoHubId,'mensagem'=>$mensagem,'codigo_erro'=>$erro ? 'PEDIDO_STATUS_ERRO' : null,'contexto'=>$ctx]);
    } catch(Throwable $e) { Audit::exception($e,'pedido.status_historico.erro',['pedido_hub_id'=>$pedidoHubId,'status'=>$statusNovo]); }
  }

  public static function marcarEnviadoVsmPorTinyId(string $tinyId, array $retorno, bool $erro=false): void {
    self::ensureSchema();
    $pdo=self::pdo(); $s = TenantScopeService::run('pedidos_hub', 'SELECT id FROM pedidos_hub WHERE pedido_tiny_id=? LIMIT 1', [$tinyId]); $id=(int)$s->fetchColumn();
    if(!$id) return;
    self::snapshot($id,'hub','vsm','hub_envio_vsm',$retorno);
    self::status($id,null,$erro?'erro_envio_vsm':'enviado_vsm','hub',$erro?'Erro ao enviar pedido para VSM.':'Pedido enviado para VSM.',$erro?self::json($retorno):null,['retorno'=>$retorno]);
    try { TenantScopeService::run('pedidos_validacao', "UPDATE pedidos_validacao SET status_hub=?, status_validacao=? WHERE pedido_hub_id=?", [$erro?'erro_envio_vsm':'enviado_vsm',$erro?'erro_envio_vsm':'enviado_vsm',$id]); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
  }

  private static function parseXmlInfo(?string $xml): array {
    $out=['ok'=>false,'chave'=>null,'numero'=>null,'serie'=>null,'erro'=>null];
    if(!$xml){ $out['erro']='XML ausente.'; return $out; }
    // Sugestão pós-análise da doc de Multi-WebServer do aaPanel: o lsphp (OpenLiteSpeed) é
    // um build de PHP separado do PHP padrão, então SimpleXML não vem garantido só porque
    // o restante do app funciona. Sem esta guarda, a falta da extensão virava um fatal
    // error cru no meio do processamento do ciclo de vida do pedido/NF-e.
    if (!function_exists('simplexml_load_string')) { $out['erro']='Extensão PHP SimpleXML não está habilitada neste servidor.'; return $out; }
    libxml_use_internal_errors(true);
    $sx=simplexml_load_string($xml);
    if(!$sx){ $out['erro']='XML mal formado.'; return $out; }
    $str=(string)$xml;
    if(preg_match('/Id=["\']NFe([0-9]{44})["\']/', $str, $m)) $out['chave']=$m[1];
    if(!$out['chave'] && preg_match('/<chNFe>([0-9]{44})<\/chNFe>/', $str, $m)) $out['chave']=$m[1];
    if(preg_match('/<nNF>([^<]+)<\/nNF>/', $str, $m)) $out['numero']=$m[1];
    if(preg_match('/<serie>([^<]+)<\/serie>/', $str, $m)) $out['serie']=$m[1];
    $out['ok']=true; return $out;
  }

  private static function findPedidoByRetorno(array $payload): ?array {
    self::ensureSchema();
    $tinyId=(string)($payload['pedido_tiny_id'] ?? $payload['id_pedido_tiny'] ?? $payload['pedidoTinyId'] ?? $payload['pedido_tiny'] ?? '');
    $vsmId=(string)($payload['pedido_vsm_id'] ?? $payload['id_pedido_vsm'] ?? $payload['pedidoVsmId'] ?? $payload['pedido_vsm'] ?? $payload['id'] ?? '');
    $numero=(string)($payload['numero_pedido'] ?? $payload['numero'] ?? $payload['pedido'] ?? '');
    $pdo=self::pdo();
    foreach([['pedido_tiny_id',$tinyId],['pedido_vsm_id',$vsmId],['numero_pedido',$numero]] as [$col,$val]){
      if($val==='') continue;
      $s = TenantScopeService::run('pedidos_hub', "SELECT * FROM pedidos_hub WHERE $col=? ORDER BY id DESC LIMIT 1", [$val]); $r=$s->fetch(PDO::FETCH_ASSOC); if($r) return $r;
    }
    return null;
  }

  public static function receberRetornoVsm(array $payload, ?string $xmlRaw=null): array {
    self::ensureSchema();
    $pedido=self::findPedidoByRetorno($payload);
    if(!$pedido) throw new RuntimeException('Pedido não encontrado no Hub para retorno VSM.');
    $pedidoId=(int)$pedido['id'];
    $xml = $xmlRaw ?: (string)($payload['xml'] ?? $payload['nfe_xml'] ?? $payload['xml_nfe'] ?? '');
    $chave=(string)($payload['chave_nfe'] ?? $payload['chaveNFe'] ?? $payload['chave'] ?? '');
    $numero=(string)($payload['numero_nfe'] ?? $payload['numeroNFe'] ?? $payload['nfe_numero'] ?? '');
    $serie=(string)($payload['serie'] ?? $payload['serie_nfe'] ?? '');
    $statusVsm=(string)($payload['status'] ?? $payload['status_vsm'] ?? 'recebido');
    $xmlInfo=self::parseXmlInfo($xml);
    if(!$chave && $xmlInfo['chave']) $chave=$xmlInfo['chave'];
    if(!$numero && $xmlInfo['numero']) $numero=$xmlInfo['numero'];
    if(!$serie && $xmlInfo['serie']) $serie=$xmlInfo['serie'];
    $erros=[];
    $cfg=IntegrationConfig::get();
    if(!empty($cfg['pedido_retorno_vsm_exigir_xml']) && !$xml) $erros[]='Retorno VSM sem XML.';
    if($xml && !$xmlInfo['ok']) $erros[]=$xmlInfo['erro'] ?: 'XML inválido.';
    if(!empty($cfg['pedido_retorno_vsm_exigir_chave_nfe']) && !$chave) $erros[]='Chave NF-e ausente.';
    $validado=empty($erros);
    $hash=$xml ? hash('sha256',$xml) : null;
    $xmlId=null;
    $pdo=Database::forTable('pedidos_nfe_xml');
    $st = TenantScopeService::run('pedidos_nfe_xml', 'INSERT INTO pedidos_nfe_xml(pedido_hub_id,chave_nfe,numero_nfe,serie,xml_original,xml_hash,status_xml,validado,erro_validacao) VALUES(?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE chave_nfe=VALUES(chave_nfe),numero_nfe=VALUES(numero_nfe),serie=VALUES(serie),status_xml=VALUES(status_xml),validado=VALUES(validado),erro_validacao=VALUES(erro_validacao),atualizado_em=NOW()', [$pedidoId,$chave ?: null,$numero ?: null,$serie ?: null,$xml ?: null,$hash,$validado?'xml_validado':'erro_xml',$validado?1:0,$erros?implode(' | ',$erros):null]);
    $xmlId=(int)$pdo->lastInsertId(); if(!$xmlId && $hash){ $s = TenantScopeService::run('pedidos_nfe_xml', 'SELECT id FROM pedidos_nfe_xml WHERE xml_hash=? LIMIT 1', [$hash]); $xmlId=(int)$s->fetchColumn(); }
    self::snapshot($pedidoId,'vsm','hub','vsm_retorno_nfe',$payload);
    if($xml) self::snapshot($pedidoId,'vsm','hub','vsm_xml',$xml);
    try { TenantScopeService::run('pedidos_hub', 'UPDATE pedidos_hub SET pedido_vsm_id=COALESCE(NULLIF(?,\'\'),pedido_vsm_id), status_vsm=?, data_recebido_vsm=NOW(), atualizado_em=NOW() WHERE id=?', [(string)($payload['pedido_vsm_id'] ?? $payload['id'] ?? ''),$statusVsm,$pedidoId]); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    self::status($pedidoId,null,'recebido_vsm','vsm','Retorno VSM recebido no Hub.',null,['payload'=>$payload,'xml_id'=>$xmlId]);
    self::status($pedidoId,null,$validado?'xml_validado':'erro_xml','hub',$validado?'NF-e/XML validado antes de enviar ao Tiny.':'NF-e/XML bloqueado por validação.',$validado?null:implode(' | ',$erros),['xml_id'=>$xmlId,'chave'=>$chave]);
    try { TenantScopeService::run('pedidos_validacao', "UPDATE pedidos_validacao SET status_hub=?, recebido_vsm_em=NOW(), xml_nfe_id=? WHERE pedido_hub_id=?", [$validado?'xml_validado':'erro_xml',$xmlId,$pedidoId]); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    return ['pedido_hub_id'=>$pedidoId,'xml_id'=>$xmlId,'validado'=>$validado,'erros'=>$erros,'chave_nfe'=>$chave,'status'=>'recebido_vsm'];
  }

  public static function enviarXmlParaTiny(int $pedidoHubId): array {
    self::ensureSchema();
    $p=self::pdo()->prepare('SELECT * FROM pedidos_hub WHERE id=? LIMIT 1'); $p->execute([$pedidoHubId]); $pedido=$p->fetch(PDO::FETCH_ASSOC); if(!$pedido) throw new RuntimeException('Pedido Hub não encontrado.');
    $x = TenantScopeService::run('pedidos_nfe_xml', "SELECT * FROM pedidos_nfe_xml WHERE pedido_hub_id=? ORDER BY id DESC LIMIT 1", [$pedidoHubId]); $xml=$x->fetch(PDO::FETCH_ASSOC); if(!$xml) throw new RuntimeException('XML/NF-e não encontrado para o pedido.');
    if((int)$xml['validado'] !== 1) throw new RuntimeException('XML/NF-e não está validado.');
    $fluxo = IntegrationOrchestratorService::podeExecutar('sync_vsm_enviar_nota_tiny', [
      'fluxo_id'=>'nfe_vsm_enviar_tiny',
      'nfe_autorizada'=>((int)$xml['validado'] === 1),
      'pedido_hub_id'=>$pedidoHubId,
      'chave_nfe'=>$xml['chave_nfe'] ?? null
    ]);
    if(empty($fluxo['ok'])) {
      throw new RuntimeException((string)($fluxo['mensagem'] ?? 'Fluxo VSM → Tiny para NF-e está bloqueado pela orquestração.'));
    }
    $tiny=TinyFactory::make();
    $payload=['pedido_id'=>$pedido['pedido_tiny_id'],'status'=>'faturado','chave_nfe'=>$xml['chave_nfe'],'numero_nfe'=>$xml['numero_nfe'],'serie'=>$xml['serie'],'xml'=>$xml['xml_original']];
    self::snapshot($pedidoHubId,'hub','tiny','hub_envio_tiny',$payload);
    if(method_exists($tiny,'enviarNfeXmlPedido')) $ret=$tiny->enviarNfeXmlPedido((string)$pedido['pedido_tiny_id'], $payload);
    else $ret=['ok'=>false,'erro'=>'Cliente Tiny não possui método enviarNfeXmlPedido configurado.','codigo_erro'=>'TINY_XML_METHOD_MISSING','trace_id'=>RequestContext::id()];
    $erro=isset($ret['erro']) || isset($ret['codigo_erro']) || (isset($ret['http_code']) && ((int)$ret['http_code']<200 || (int)$ret['http_code']>=300));
    TenantScopeService::run('pedidos_nfe_xml', 'UPDATE pedidos_nfe_xml SET retorno_tiny_json=?, enviado_tiny_em=?, status_xml=? WHERE id=?', [self::json($ret),$erro?null:date('Y-m-d H:i:s'),$erro?'erro_envio_tiny':'enviado_tiny',(int)$xml['id']]);
    self::status($pedidoHubId,null,$erro?'erro_envio_tiny':'enviado_tiny','tiny',$erro?'Erro ao enviar XML/NF-e para Tiny.':'XML/NF-e enviado ao Tiny e status do pedido atualizado.',$erro?self::json($ret):null,['retorno'=>$ret,'xml_id'=>(int)$xml['id']]);
    if(!$erro) self::status($pedidoHubId,'enviado_tiny','concluido','hub','Ciclo do pedido concluído.',null,['xml_id'=>(int)$xml['id']]);
    try { TenantScopeService::run('pedidos_validacao', "UPDATE pedidos_validacao SET status_hub=?, enviado_tiny_em=? WHERE pedido_hub_id=?", [$erro?'erro_envio_tiny':'enviado_tiny',$erro?null:date('Y-m-d H:i:s'),$pedidoHubId]); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    return ['ok'=>!$erro,'retorno'=>$ret,'pedido_hub_id'=>$pedidoHubId];
  }

  public static function listar(string $status='', string $busca=''): array {
    self::ensureSchema(); $sql='SELECT * FROM pedidos_hub WHERE 1=1'; $params=[];
    if($status!==''){ $sql.=' AND status_hub=?'; $params[]=$status; }
    if($busca!==''){ $sql.=' AND (pedido_tiny_id LIKE ? OR pedido_vsm_id LIKE ? OR numero_pedido LIKE ? OR cliente_nome LIKE ? OR trace_id LIKE ?)'; for($i=0;$i<5;$i++) $params[]='%'.$busca.'%'; }
    $sql.=' ORDER BY id DESC LIMIT 300'; $st=self::pdo()->prepare($sql); $st->execute($params); return $st->fetchAll(PDO::FETCH_ASSOC);
  }

  public static function detalhe(int $id): array {
    self::ensureSchema(); $pdo=self::pdo();
    $s = TenantScopeService::run('pedidos_hub', 'SELECT * FROM pedidos_hub WHERE id=? LIMIT 1', [$id]); $pedido=$s->fetch(PDO::FETCH_ASSOC); if(!$pedido) throw new RuntimeException('Pedido não encontrado.');
    $h = TenantScopeService::run('pedidos_status_historico', 'SELECT * FROM pedidos_status_historico WHERE pedido_hub_id=? ORDER BY id DESC LIMIT 200', [$id]);
    $pl = TenantScopeService::run('pedidos_payloads', 'SELECT id,origem,destino,tipo_payload,hash_payload,trace_id,criado_em FROM pedidos_payloads WHERE pedido_hub_id=? ORDER BY id DESC LIMIT 200', [$id]);
    $x = TenantScopeService::run('pedidos_nfe_xml', 'SELECT id,chave_nfe,numero_nfe,serie,xml_hash,status_xml,validado,erro_validacao,enviado_tiny_em,criado_em FROM pedidos_nfe_xml WHERE pedido_hub_id=? ORDER BY id DESC LIMIT 50', [$id]);
    return ['pedido'=>$pedido,'historico'=>$h->fetchAll(PDO::FETCH_ASSOC),'payloads'=>$pl->fetchAll(PDO::FETCH_ASSOC),'xmls'=>$x->fetchAll(PDO::FETCH_ASSOC)];
  }
}
