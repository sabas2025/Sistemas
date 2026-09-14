<?php
class TinyWebhookService {
  public static function rawPayload(): array {
    $raw = file_get_contents('php://input') ?: '';
    $payload = json_decode($raw, true);
    if(!is_array($payload)) $payload = $_POST ?: [];
    return [$raw, $payload];
  }

  public static function normalizarHeaders(): array {
    $headers=[];
    foreach($_SERVER as $k=>$v){
      if(str_starts_with($k,'HTTP_') || in_array($k,['CONTENT_TYPE','CONTENT_LENGTH'], true)){
        $name=strtolower(str_replace('_','-',preg_replace('/^HTTP_/','',$k)));
        $headers[$name]=$v;
      }
    }
    if (class_exists('RequestContext') && RequestContext::ip()) $headers['remote-addr'] = RequestContext::ip();
    return $headers;
  }

  public static function validarBase(array $payload, string $tipoEsperado): array {
    $erros=[];
    if(!$payload) $erros[]='Payload vazio ou JSON inválido.';
    $tipo = self::tipo($payload);
    if($tipoEsperado !== 'generico' && $tipo !== '' && $tipo !== $tipoEsperado){
      // Não bloqueia automaticamente porque alguns ambientes Tiny usam nomes internos variados.
      Audit::event('tiny.webhook.tipo_diferente','alerta',[
        'codigo_erro'=>'TINY_WEBHOOK_TYPE_DIFFERENT',
        'mensagem'=>'Tipo informado no payload Tiny difere do endpoint chamado.',
        'contexto'=>['endpoint'=>$tipoEsperado,'payload_tipo'=>$tipo],
        'acao_recomendada'=>'Conferir no Tiny se o webhook foi apontado para o endpoint correto.'
      ]);
    }
    if(empty($payload['dados']) && empty($payload['data']) && empty($payload['pedido']) && empty($payload['nota']) && empty($payload['itens']) && empty($payload['produtos'])){
      $erros[]='Payload sem dados úteis. Esperado dados, data, pedido, nota, itens ou produtos.';
    }
    return $erros;
  }

  public static function tipo(array $payload): string {
    return strtolower((string)($payload['tipo'] ?? $payload['evento'] ?? $payload['event'] ?? $payload['webhook'] ?? ''));
  }

  public static function referencia(array $payload, string $tipo): string {
    $dados = self::dados($payload);
    $candidatos = [
      $payload['idEcommerce'] ?? null,
      $payload['id_ecommerce'] ?? null,
      $payload['id'] ?? null,
      $payload['numero'] ?? null,
      $payload['numeroPedido'] ?? null,
      $payload['idVendaTiny'] ?? null,
      $payload['idNotaFiscal'] ?? null,
      $dados['idProduto'] ?? null,
      $dados['sku'] ?? null,
      $dados['skuMapeamento'] ?? null,
      $dados['idPedidoEcommerce'] ?? null,
      $dados['idVendaTiny'] ?? null,
      $dados['idNotaFiscal'] ?? null,
      $dados['chaveAcesso'] ?? null,
    ];
    foreach($candidatos as $v){ if($v !== null && $v !== '') return (string)$v; }
    return strtoupper($tipo).'-'.date('YmdHis').'-'.substr(hash('sha256', json_encode($payload)),0,8);
  }

  public static function dados(array $payload): array {
    $dados = $payload['dados'] ?? $payload['data'] ?? $payload['payload'] ?? $payload;
    return is_array($dados) ? $dados : [];
  }

  public static function registrar(string $tipo, string $raw, array $payload, string $status='recebido', ?string $mensagem=null): int {
    $pdo=Database::forTable('tiny_webhooks');
    $dados=self::dados($payload);
    $cnpj=(string)($payload['cnpj'] ?? $dados['cnpj'] ?? '');
    $idEcommerce=(string)($payload['idEcommerce'] ?? $payload['id_ecommerce'] ?? $dados['idEcommerce'] ?? '');
    $ref=self::referencia($payload,$tipo);
    $hash=hash('sha256', $raw !== '' ? $raw : json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    // P0-06: nunca persistir headers crus (podem conter Authorization/X-TINY-HUB-SECRET etc.).
    $headersBrutos = self::normalizarHeaders();
    $headers=json_encode(class_exists('SensitiveHeaderRedactor') ? SensitiveHeaderRedactor::redactForStorage($headersBrutos) : [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    try{
      $st=$pdo->prepare('INSERT INTO tiny_webhooks(tipo,cnpj,id_ecommerce,referencia,hash_payload,status,payload,headers,trace_id,mensagem) VALUES(?,?,?,?,?,?,?,?,?,?)');
      $st->execute([$tipo,$cnpj,$idEcommerce,$ref,$hash,$status,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$headers,RequestContext::id(),$mensagem]);
      return (int)$pdo->lastInsertId();
    }catch(PDOException $e){
      if((string)$e->getCode()==='23000'){
        $pdo->prepare('UPDATE tiny_webhooks SET recebido_repetido=recebido_repetido+1, ultima_repeticao=NOW(), mensagem=? WHERE tipo=? AND referencia=? AND hash_payload=?')
          ->execute([$mensagem ?: 'Webhook duplicado recebido novamente pelo Tiny/Olist.',$tipo,$ref,$hash]);
        $stDup = $pdo->prepare('SELECT recebido_repetido FROM tiny_webhooks WHERE tipo=? AND referencia=? AND hash_payload=? LIMIT 1');
        $stDup->execute([$tipo,$ref,$hash]);
        $repeticoes = (int)($stDup->fetch()['recebido_repetido'] ?? 1);
        if ($repeticoes >= 3) {
          NotificationService::criar('webhook_repetido','Webhook Tiny reenviado várias vezes','O mesmo webhook '.$tipo.' / '.$ref.' já foi recebido '.$repeticoes.' vez(es). Verifique se o Tiny recebeu HTTP 200 ou se há falha no endpoint.','alerta',['trace_id'=>RequestContext::id(),'link'=>'index.php?page=tiny-webhooks&status=duplicado']);
        }
        Audit::event('tiny.webhook.duplicado','alerta',[
          'codigo_erro'=>'TINY_WEBHOOK_DUPLICATE',
          'mensagem'=>'Webhook Tiny/Olist duplicado ignorado para evitar baixa ou cadastro repetido.',
          'entidade'=>'tiny_webhooks',
          'entidade_id'=>$ref,
          'contexto'=>['tipo'=>$tipo,'hash'=>$hash,'repeticoes'=>$repeticoes],
          'acao_recomendada'=>'Normal se o Tiny reenviou por não ter recebido HTTP 200 anteriormente. Verifique se houve falha no retorno anterior.'
        ]);
        return 0;
      }
      throw $e;
    }
  }

  public static function atualizarStatus(int $id, string $status, array $retorno=[]): void {
    if($id <= 0) return;
    Database::forTable('tiny_webhooks')->prepare('UPDATE tiny_webhooks SET status=?, retorno=?, processado_em=NOW() WHERE id=?')
      ->execute([$status,json_encode($retorno,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$id]);
  }

  public static function baixaDeEstoqueTiny(array $payload): array {
    $dados=self::dados($payload);
    $sku=(string)($dados['sku'] ?? $dados['skuMapeamento'] ?? $dados['codigo'] ?? '');
    $saldo = $dados['saldo'] ?? $dados['estoque'] ?? null;
    $qtd = $dados['quantidade'] ?? $dados['quantidadeBaixada'] ?? $dados['qtd'] ?? null;
    $ref=self::referencia($payload,'estoque');

    if($sku !== '' && ($qtd !== null || $saldo !== null)){
      return [
        'origem'=>'tiny',
        'tipo'=>'baixa_estoque',
        'referencia'=>$ref,
        'data'=>date('c'),
        'trace_id'=>RequestContext::id(),
        'tipo_estoque'=>(string)($dados['tipoEstoque'] ?? ''),
        'saldo_atual'=>$saldo,
        'itens'=>[[
          'sku'=>$sku,
          'quantidade'=>(float)str_replace(',','.',(string)($qtd ?? 0)),
          'saldo'=>(float)str_replace(',','.',(string)($saldo ?? 0)),
          'descricao'=>(string)($dados['descricao'] ?? $dados['nome'] ?? ''),
          'id_produto_tiny'=>(string)($dados['idProduto'] ?? ''),
          'sku_mapeamento'=>(string)($dados['skuMapeamento'] ?? ''),
        ]],
        'payload_origem'=>$payload
      ];
    }

    return EstoqueMapper::tinyEventoParaVsmBaixa($payload);
  }

  public static function retornoMapeamentoProduto(array $payload, bool $ok=true, ?string $erro=null): array {
    $dados=self::dados($payload);
    $produtos=[];
    if(isset($dados['produtos']) && is_array($dados['produtos'])) $produtos=$dados['produtos'];
    elseif(isset($payload['produtos']) && is_array($payload['produtos'])) $produtos=$payload['produtos'];
    else $produtos=[$dados ?: $payload];
    $mapeamentos=[];
    foreach($produtos as $p){
      $produto=is_array($p) ? ($p['produto'] ?? $p) : [];
      $id=(string)($produto['idProduto'] ?? $produto['id'] ?? $produto['codigo'] ?? $produto['sku'] ?? uniqid('tiny_', true));
      $sku=(string)($produto['skuMapeamento'] ?? $produto['sku'] ?? $produto['codigo'] ?? $id);
      $mapeamentos[]=[
        'idMapeamento'=>'VSM-'.$sku,
        'skuMapeamento'=>$sku,
        'urlProduto'=>'',
        'urlImagem'=>'',
        'error'=>$ok ? '' : ($erro ?: 'Produto não processado pelo Hub')
      ];
    }
    return ['mapeamentos'=>$mapeamentos];
  }
}
