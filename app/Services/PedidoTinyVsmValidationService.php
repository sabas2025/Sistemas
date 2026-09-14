<?php
class PedidoTinyVsmValidationService {
  public static function ensureSchema(): void {
    SchemaRuntimePolicyService::requireTables(['pedidos_validacao','pedidos_validacao_historico'], 'validação de pedidos Tiny para VSM');
  }
  private static function digits($v): string { return preg_replace('/\D+/', '', (string)$v); }
  private static function data(array $payload): array { return $payload['dados'] ?? $payload['data'] ?? $payload['pedido'] ?? $payload; }
  public static function pedidoId(array $payload): string { $d=self::data($payload); return (string)($d['id'] ?? $d['idPedido'] ?? $d['numero'] ?? $d['numeroPedido'] ?? $payload['id'] ?? 'TINY-'.date('YmdHis')); }
  public static function toVsmPayload(array $payload): array {
    $d=self::data($payload); $cliente=$d['cliente'] ?? $d['comprador'] ?? [];
    $itens=$d['itens'] ?? $d['produtos'] ?? [];
    $outItens=[]; foreach((array)$itens as $it){ if(!is_array($it)) continue; $prod=$it['produto'] ?? $it; $outItens[]=[
      'sku'=>(string)($prod['sku'] ?? $prod['codigo'] ?? $it['sku'] ?? $it['codigo'] ?? ''),
      'ean'=>(string)($prod['ean'] ?? $prod['gtin'] ?? $it['ean'] ?? $it['gtin'] ?? ''),
      'descricao'=>(string)($prod['descricao'] ?? $prod['nome'] ?? $it['descricao'] ?? $it['nome'] ?? ''),
      'quantidade'=>(float)str_replace(',','.',(string)($it['quantidade'] ?? $it['qtd'] ?? 0)),
      'valor_unitario'=>(float)str_replace(',','.',(string)($it['valor_unitario'] ?? $it['preco'] ?? $it['valor'] ?? 0)),
      'ncm'=>(string)($prod['ncm'] ?? $it['ncm'] ?? ''),
    ]; }
    return [
      'origem'=>'tiny','pedido_id'=>self::pedidoId($payload),'numero'=>(string)($d['numero'] ?? $d['numeroPedido'] ?? self::pedidoId($payload)),
      'status'=>(string)($d['situacao'] ?? $d['status'] ?? $payload['situacao'] ?? ''),
      'cliente'=>['nome'=>(string)($cliente['nome'] ?? $cliente['razao_social'] ?? ''),'documento'=>self::digits($cliente['cpf_cnpj'] ?? $cliente['documento'] ?? $cliente['cpf'] ?? $cliente['cnpj'] ?? ''),'email'=>(string)($cliente['email'] ?? '')],
      'endereco'=>$cliente['endereco'] ?? $d['endereco_entrega'] ?? $d['endereco'] ?? [],
      'itens'=>$outItens,
      'forma_pagamento'=>(string)($d['forma_pagamento'] ?? ($d['pagamento']['forma'] ?? '')),
      'valor_total'=>(float)str_replace(',','.',(string)($d['valor_total'] ?? $d['total'] ?? 0)),
      'frete'=>(float)str_replace(',','.',(string)($d['frete'] ?? $d['valor_frete'] ?? 0)),
      'desconto'=>(float)str_replace(',','.',(string)($d['desconto'] ?? $d['valor_desconto'] ?? 0)),
      'payload_original'=>$payload,
      'trace_id'=>RequestContext::id()
    ];
  }
  public static function validar(array $payload, ?array $cfg=null): array {
    self::ensureSchema(); $cfg=$cfg ?: ProductApprovalPolicyService::config(); $vsm=self::toVsmPayload($payload); $erros=[]; $avisos=[];
    $status=strtolower(trim((string)($vsm['status'] ?? ''))); $permitidos=array_filter(array_map('trim', explode(',', strtolower((string)($cfg['pedido_tiny_vsm_status_permitidos'] ?? '')))));
    if($permitidos && $status!=='' && !in_array($status,$permitidos,true)) $erros[]='Status Tiny não permitido para envio à VSM: '.$status.'.';
    if(empty($vsm['pedido_id'])) $erros[]='Pedido Tiny sem ID/número.';
    if(empty($vsm['cliente']['nome'])) $erros[]='Cliente sem nome.';
    if(!empty($cfg['pedido_tiny_vsm_exigir_cliente_documento']) && empty($vsm['cliente']['documento'])) $erros[]='CPF/CNPJ do cliente obrigatório.';
    if(!empty($cfg['pedido_tiny_vsm_exigir_endereco'])){ $end=$vsm['endereco']; foreach(['cep','cidade','bairro'] as $k){ if(empty($end[$k])) $erros[]='Endereço de entrega sem '.$k.'.'; } }
    if(empty($vsm['itens'])) $erros[]='Pedido sem itens reconhecidos.';
    $soma=0; foreach($vsm['itens'] as $idx=>$it){ $n=$idx+1; if(empty($it['sku'])) $erros[]="Item #$n sem SKU."; if((float)$it['quantidade']<=0) $erros[]="Item #$n com quantidade inválida."; if((float)$it['valor_unitario']<0) $erros[]="Item #$n com valor negativo."; $soma += (float)$it['quantidade']*(float)$it['valor_unitario']; if(!empty($cfg['pedido_tiny_vsm_exigir_sku_mapeado']) && !ProdutoVsmGovernanceService::mappingForSku((string)$it['sku'])) $erros[]="Item #$n SKU {$it['sku']} sem mapeamento Hub de Integração."; }
    if($vsm['valor_total']>0 && abs(($soma + (float)$vsm['frete'] - (float)$vsm['desconto']) - (float)$vsm['valor_total']) > 1.00) $avisos[]='Total calculado difere do total informado em mais de R$ 1,00.';
    if(empty($vsm['forma_pagamento'])) $avisos[]='Forma de pagamento não informada.';
    return ['ok'=>empty($erros),'erros'=>$erros,'avisos'=>$avisos,'payload_vsm'=>$vsm,'status_validacao'=>empty($erros)?'aprovado_para_vsm':'pendente_correcao'];
  }
  public static function registrar(array $payload, array $validacao, string $origem='tiny'): int {
    self::ensureSchema(); $v=$validacao['payload_vsm']; $pdo=Database::forTable('pedidos_validacao');
    $st = TenantScopeService::run('pedidos_validacao', 'INSERT INTO pedidos_validacao(trace_id,origem,destino,pedido_origem_id,numero_pedido,cliente_nome,cliente_documento,valor_total,quantidade_itens,status_tiny,status_validacao,erros_json,avisos_json,payload_json,payload_vsm_json) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE status_validacao=VALUES(status_validacao), erros_json=VALUES(erros_json), avisos_json=VALUES(avisos_json), payload_json=VALUES(payload_json), payload_vsm_json=VALUES(payload_vsm_json), atualizado_em=NOW()', [RequestContext::id(),$origem,'vsm',$v['pedido_id'],$v['numero'],$v['cliente']['nome'] ?? null,$v['cliente']['documento'] ?? null,(float)$v['valor_total'],count($v['itens'] ?? []),$v['status'] ?? null,$validacao['status_validacao'],json_encode($validacao['erros'],JSON_UNESCAPED_UNICODE),json_encode($validacao['avisos'],JSON_UNESCAPED_UNICODE),json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
    $id=(int)$pdo->lastInsertId(); if(!$id){ $s = TenantScopeService::run('pedidos_validacao', 'SELECT id FROM pedidos_validacao WHERE origem=? AND pedido_origem_id=? LIMIT 1', [$origem,$v['pedido_id']]); $id=(int)$s->fetchColumn(); }
    self::historico($id,'validar',$validacao['ok']?'sucesso':'pendente',$validacao['ok']?'Pedido validado para VSM.':'Pedido bloqueado por validação.',$validacao);
    if(class_exists('PedidoCicloVidaService')){
      try { PedidoCicloVidaService::fromTinyPedido($payload, $validacao, $id); }
      catch(Throwable $e){ Audit::exception($e,'pedido.ciclo.from_tiny.erro',['pedido_validacao_id'=>$id]); }
    }
    return $id;
  }
  public static function historico(int $id,string $acao,string $resultado,string $mensagem,array $ctx=[]): void { try { TenantScopeService::run('pedidos_validacao_historico', 'INSERT INTO pedidos_validacao_historico(pedido_validacao_id,acao,resultado,mensagem,contexto_json,usuario_id) VALUES(?,?,?,?,?,?)', [$id,$acao,$resultado,$mensagem,json_encode($ctx,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),Auth::user()['id'] ?? null]); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); } }
  public static function enfileirar(int $id): int { $pdo=Database::forTable('pedidos_validacao'); $st = TenantScopeService::run('pedidos_validacao', 'SELECT * FROM pedidos_validacao WHERE id=? LIMIT 1', [$id]); $p=$st->fetch(PDO::FETCH_ASSOC); if(!$p) throw new RuntimeException('Pedido de validação não encontrado.'); if(!in_array($p['status_validacao'],['aprovado_para_vsm','enviado_vsm'],true)) throw new RuntimeException('Pedido não está aprovado para envio à VSM.'); $payload=json_decode((string)$p['payload_vsm_json'],true) ?: []; $q = TenantScopeService::run('fila_integracao', "INSERT INTO fila_integracao(tipo,referencia,payload,status,trace_id) VALUES('pedido_tiny_para_vsm',?,?, 'pendente', ?)", [$p['pedido_origem_id'],json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),RequestContext::id()]); $fila=(int)Database::forTable('fila_integracao')->lastInsertId(); TenantScopeService::run('pedidos_validacao', "UPDATE pedidos_validacao SET fila_id=?, status_validacao='enviado_vsm', aprovado_por=COALESCE(aprovado_por,?), aprovado_em=COALESCE(aprovado_em,NOW()), enviado_em=NOW() WHERE id=?", [$fila,Auth::user()['id'] ?? null,$id]); self::historico($id,'enfileirar_vsm','sucesso','Pedido aprovado/enfileirado para envio à VSM.',['fila_id'=>$fila]); return $fila; }
}
