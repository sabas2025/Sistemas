<?php
class ApiController {
  private function configIntegracao(): array {
    $pdo = Database::forTable('configuracoes_integracao');
    $row = $pdo->query('SELECT * FROM configuracoes_integracao WHERE id=1 LIMIT 1')->fetch() ?: [];
    foreach(['tiny_v2_token','tiny_v3_token','vsm_token','webhook_secret','tiny_webhook_secret'] as $k){ if(isset($row[$k])) $row[$k]=CryptoService::decrypt($row[$k]); }
    return $row;
  }


  private function validarSegurancaTiny(string $raw, array $payload): void {
    TinyWebhookSecurityService::validar($this->configIntegracao(), $raw, $payload);
  }


  private function registrarEventoIdempotente(string $origem, string $referencia, string $tipoEvento, string $raw, array $payload): bool {
    $pdo = Database::forTable('eventos_processados');
    $hash = hash('sha256', $raw !== '' ? $raw : json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    try {
      $st = Database::forTable('eventos_processados')->prepare('INSERT INTO eventos_processados(origem,referencia,tipo_evento,hash_payload,trace_id,payload) VALUES(?,?,?,?,?,?)');
      $st->execute([$origem,$referencia,$tipoEvento,$hash,RequestContext::id(),json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
      return true;
    } catch (PDOException $e) {
      if ((string)$e->getCode() === '23000') {
        Audit::event('idempotencia.evento_duplicado','alerta',[
          'codigo_erro'=>'DUPLICATE_EVENT_IGNORED',
          'mensagem'=>'Evento duplicado recebido e ignorado para evitar processamento repetido.',
          'entidade'=>'eventos_processados',
          'entidade_id'=>$referencia,
          'contexto'=>['origem'=>$origem,'tipo_evento'=>$tipoEvento,'hash'=>$hash],
          'acao_recomendada'=>'Se for um reenvio intencional, reprocessar manualmente pela tela Fila ou alterar a referência do teste.'
        ]);
        return false;
      }
      throw $e;
    }
  }

  public function webhookVsmPedido(){
    header('Content-Type: application/json; charset=utf-8');
    $trace = RequestContext::id();
    // Reauditoria 2026-09-14 (achado A-08): esta mutação aceitava qualquer método HTTP,
    // ao contrário de estoque/retorno. Exigir POST é pré-requisito para a assinatura v2,
    // que passa a cobrir método e rota.
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
      http_response_code(405);
      header('Allow: POST');
      echo json_encode(['success'=>false,'trace_id'=>$trace,'message'=>'Método não permitido. Use POST.']);
      return;
    }
    $raw=file_get_contents('php://input');
    $vsm=json_decode($raw,true);
    if(!is_array($vsm)) $vsm = $_POST ?: [];
    try { $config = $this->configIntegracao(); WebhookSecurityService::validar($config, $raw); }
    catch(Throwable $e){
      Audit::exception($e,'webhook.vsm.validacao_secret_erro',[
        'codigo_erro'=>'WEBHOOK_SECURITY_BLOCKED',
        'causa_provavel'=>'Assinatura HMAC, secret, IP ou tamanho do payload não atende à política configurada.',
        'acao_recomendada'=>'Validar headers X-HUB-TIMESTAMP, X-HUB-NONCE e X-HUB-SIGNATURE=sha256=hash_hmac(timestamp.nonce.payload, webhook_secret).'
      ]);
      http_response_code(401);
      echo json_encode(['success'=>false,'trace_id'=>$trace,'message'=>'Webhook bloqueado por segurança. Consulte Auditoria pelo Trace ID.']); return;
    }

    if (empty($config['fluxo_vsm_tiny_pedido'])) {
      Audit::event('webhook.vsm.pedido.fluxo_desativado','alerta',[
        'codigo_erro'=>'FLOW_DISABLED',
        'mensagem'=>'Webhook de pedido VSM recebido, mas este fluxo está desativado. O fluxo correto configurado é Tiny cria pedido/NF-e e VSM recebe baixa de estoque.',
        'payload'=>$vsm ?: $raw,
        'acao_recomendada'=>'Ative o fluxo VSM → Tiny Pedido apenas se a operação realmente passar a criar pedidos na VSM.'
      ]);
      http_response_code(409);
      echo json_encode(['success'=>false,'trace_id'=>$trace,'message'=>'Fluxo VSM → Tiny Pedido desativado. O fluxo correto é Tiny → VSM baixa de estoque.']); return;
    }

    if (!$vsm || !is_array($vsm)) {
      Audit::event('webhook.vsm.pedido.recebido','erro',['codigo_erro'=>'VSM_PAYLOAD_INVALID','mensagem'=>'Payload da VSM vazio ou inválido','payload'=>$raw]);
      echo json_encode(['success'=>false,'trace_id'=>$trace,'message'=>'Payload inválido. Consulte Auditoria pelo Trace ID.']); return;
    }
    $erros = PedidoValidator::validarVsm($vsm);
    if ($erros) {
      Audit::event('webhook.vsm.pedido.validacao','erro',[
        'codigo_erro'=>'VSM_ORDER_VALIDATION_ERROR',
        'mensagem'=>'Pedido recebido da VSM não passou na validação mínima.',
        'causa_provavel'=>'Campos obrigatórios ausentes ou valores inválidos no payload.',
        'acao_recomendada'=>'Conferir os campos id/numeroPedido, cliente.nome e itens com sku, quantidade e valor.',
        'payload'=>$vsm,
        'contexto'=>['erros'=>$erros]
      ]);
      NotificationService::erroIntegracao('Pedido VSM inválido', implode(' | ', $erros), ['trace_id'=>$trace,'payload'=>$vsm]);
      echo json_encode(['success'=>false,'trace_id'=>$trace,'message'=>'Pedido inválido','erros'=>$erros], JSON_UNESCAPED_UNICODE); return;
    }
    try {
      $pdo=Database::getConnection();
      $pedidoOrigem=$vsm['id']??$vsm['pedido']??$vsm['numero']??$vsm['numeroPedido'];
      $payloadJson=json_encode($vsm,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
      $st = TenantScopeService::run('fila_integracao', 'INSERT INTO fila_integracao(tipo,referencia,payload,status,trace_id) VALUES(?,?,?,?,?)', ['pedido_vsm',$pedidoOrigem,$payloadJson,'pendente',$trace]);
      Logger::log('webhook_vsm','Pedido recebido da VSM e enviado para fila',['pedido_origem'=>$pedidoOrigem,'trace_id'=>$trace],'info');
      Audit::event('webhook.vsm.pedido.enfileirado','sucesso',['entidade'=>'fila_integracao','entidade_id'=>$pedidoOrigem,'mensagem'=>'Pedido recebido da VSM e enfileirado','payload'=>$vsm]);
      NotificationService::novoPedido((string)$pedidoOrigem, $vsm);
      echo json_encode(['success'=>true,'trace_id'=>$trace,'message'=>'Pedido recebido e enviado para fila','pedido_origem'=>$pedidoOrigem], JSON_UNESCAPED_UNICODE);
    } catch(Throwable $e){
      Audit::exception($e,'webhook.vsm.pedido.erro',['codigo_erro'=>'DB_ERROR','payload'=>$vsm]);
      NotificationService::erroIntegracao('Erro no webhook VSM', 'Falha ao gravar pedido recebido da VSM. Consulte a auditoria pelo Trace ID.', ['trace_id'=>$trace, 'payload'=>$vsm]);
      echo json_encode(['success'=>false,'trace_id'=>$trace,'message'=>'Erro ao gravar pedido. Consulte Auditoria pelo Trace ID.']);
    }
  }


  public function webhookVsmProduto(){
    header('Content-Type: application/json; charset=utf-8');
    $trace = RequestContext::id();
    // Reauditoria 2026-09-14 (achado A-08): mutação passa a exigir POST, como estoque/retorno.
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
      http_response_code(405);
      header('Allow: POST');
      echo json_encode(['success'=>false,'trace_id'=>$trace,'message'=>'Método não permitido. Use POST.']);
      return;
    }
    $raw = file_get_contents('php://input');
    $produto = json_decode($raw, true);
    if(!is_array($produto)) $produto = $_POST ?: [];
    try { $config = $this->configIntegracao(); WebhookSecurityService::validar($config, $raw); }
    catch(Throwable $e){
      Audit::exception($e,'webhook.vsm.produto.validacao_erro',[
        'codigo_erro'=>'WEBHOOK_SECURITY_BLOCKED',
        'causa_provavel'=>'Produto enviado pela VSM sem assinatura/secret válido.',
        'acao_recomendada'=>'Configurar o webhook de produto da VSM com X-HUB-SIGNATURE ou X-HUB-SECRET.'
      ]);
      http_response_code(401);
      echo json_encode(['success'=>false,'trace_id'=>$trace,'message'=>'Webhook de produto bloqueado por segurança.']); return;
    }
    $erros = ProdutoMapper::validarVsmProduto($produto);
    if($erros){
      Audit::event('webhook.vsm.produto.validacao','erro',[
        'codigo_erro'=>'VSM_PRODUCT_VALIDATION_ERROR',
        'mensagem'=>'Produto recebido da VSM não passou na validação mínima.',
        'causa_provavel'=>'SKU/código ou nome/descrição ausente no payload.',
        'acao_recomendada'=>'Conferir payload de produto novo da VSM.',
        'payload'=>$produto,
        'contexto'=>['erros'=>$erros]
      ]);
      echo json_encode(['success'=>false,'trace_id'=>$trace,'message'=>'Produto inválido','erros'=>$erros], JSON_UNESCAPED_UNICODE); return;
    }
    if (empty($config['fluxo_vsm_tiny_produto'])) {
      Audit::event('webhook.vsm.produto.fluxo_desativado','alerta',[
        'codigo_erro'=>'FLOW_DISABLED',
        'mensagem'=>'Produto VSM recebido, mas o fluxo VSM → Tiny Produto está desativado.',
        'payload'=>$produto,
        'acao_recomendada'=>'Ative o fluxo em Configurações somente se a VSM for a origem de novos produtos.'
      ]);
      http_response_code(409);
      echo json_encode(['success'=>false,'trace_id'=>$trace,'message'=>'Fluxo VSM → Tiny Produto desativado.']); return;
    }
    $sku = ProdutoMapper::sku($produto) ?: ('PROD-'.date('YmdHis'));
    $tipoFila = ProdutoMapper::determinarEvento($produto);
    $governanca = ProdutoVsmGovernanceService::evaluate($produto, $sku, $tipoFila, $config);
    if (($governanca['action'] ?? '') === 'pending') {
      $pendenciaId = ProdutoVsmGovernanceService::createPending(
        $produto,
        $sku,
        $tipoFila,
        (string)($governanca['reason'] ?? 'PENDING_GOVERNANCE'),
        (string)($governanca['message'] ?? 'Produto VSM enviado para aprovação manual.'),
        null,
        $trace,
        ['acao_recomendada'=>'Conferir SKU, EAN e categoria; vincular a produto Tiny existente ou aprovar criação manual.']
      );
      NotificationService::criar('produto_novo','Produto VSM pendente de aprovação','Produto '.$sku.' foi bloqueado pela governança e enviado para aprovação manual.','alerta',['trace_id'=>$trace,'link'=>'index.php?page=produtos-pendentes-integracao']);
      http_response_code(202);
      echo json_encode(['success'=>true,'pending'=>true,'trace_id'=>$trace,'message'=>$governanca['message'],'sku'=>$sku,'pendencia_id'=>$pendenciaId], JSON_UNESCAPED_UNICODE); return;
    }
    $tipoEvento = match($tipoFila) {
      'produto_vsm_estoque_para_tiny' => 'produto_estoque_atualizado',
      'produto_vsm_status_para_tiny' => 'produto_status_atualizado',
      'produto_vsm_atualizar_tiny' => 'produto_cadastro_atualizado',
      default => 'produto_novo'
    };
    $referenciaEvento = ProdutoMapper::referenciaEvento($produto, $tipoEvento);
    if (ProdutoMapper::situacaoTiny($produto) === 'I' && (ProdutoMapper::estoque($produto) ?? 0) > 0) {
      Audit::event('webhook.vsm.produto.inativo_com_estoque','alerta',[
        'codigo_erro'=>'PRODUCT_INACTIVE_WITH_STOCK',
        'mensagem'=>'VSM enviou produto inativo com estoque maior que zero.',
        'payload'=>$produto,
        'contexto'=>['sku'=>$sku,'estoque'=>ProdutoMapper::estoque($produto)],
        'acao_recomendada'=>'Conferir se o produto deve ser inativado no Tiny ou se o estoque deve ser zerado antes.'
      ]);
      NotificationService::criar('estoque','Produto inativo com estoque','VSM enviou '.$sku.' como inativo, mas com estoque '.ProdutoMapper::estoque($produto).'.','alerta',['trace_id'=>$trace,'link'=>'index.php?page=produtos-vsm']);
    }
    if (!$this->registrarEventoIdempotente('vsm',(string)$referenciaEvento,$tipoEvento,$referenciaEvento,$produto)) {
      echo json_encode(['success'=>true,'duplicado'=>true,'trace_id'=>$trace,'message'=>'Evento de produto duplicado ignorado pela idempotência.','sku'=>$sku,'tipo'=>$tipoEvento], JSON_UNESCAPED_UNICODE); return;
    }
    $pdo=Database::getConnection();
    TenantScopeService::run('fila_integracao', 'INSERT INTO fila_integracao(tipo,referencia,payload,status,trace_id) VALUES(?,?,?,?,?)', [$tipoFila,$sku,json_encode($produto,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'pendente',$trace]);
    $filaId = (int)Database::forTable('fila_integracao')->lastInsertId();
    TenantScopeService::run('produtos_vsm_eventos', 'INSERT INTO produtos_vsm_eventos(tipo_evento,sku,produto_vsm_id,status_vsm,estoque_vsm,status_processamento,payload,trace_id,fila_id) VALUES(?,?,?,?,?,?,?,?,?)', [$tipoEvento,$sku,$produto['id'] ?? $produto['produto_id'] ?? null, ProdutoMapper::situacaoTiny($produto), ProdutoMapper::estoque($produto), 'enfileirado', json_encode($produto,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), $trace, $filaId]);
    Audit::event('webhook.vsm.produto.enfileirado','sucesso',[
      'entidade'=>'fila_integracao','entidade_id'=>$filaId,
      'mensagem'=>'Evento de produto recebido da VSM e enviado para fila: '.$tipoEvento.'.',
      'payload'=>$produto,
      'contexto'=>['tipo_fila'=>$tipoFila,'tipo_evento'=>$tipoEvento,'sku'=>$sku]
    ]);
    $titulo = match($tipoFila) {
      'produto_vsm_estoque_para_tiny' => 'Estoque de produto recebido da VSM',
      'produto_vsm_status_para_tiny' => 'Status de produto recebido da VSM',
      'produto_vsm_atualizar_tiny' => 'Atualização de produto recebida da VSM',
      default => 'Produto novo recebido da VSM'
    };
    NotificationService::criar('produto_novo',$titulo,'Produto '.$sku.' foi colocado na fila para sincronizar no Tiny.','info',['trace_id'=>$trace,'link'=>'index.php?page=produtos-vsm']);
    echo json_encode(['success'=>true,'trace_id'=>$trace,'message'=>'Evento de produto recebido e enviado para fila','sku'=>$sku,'tipo'=>$tipoEvento,'fila'=>$tipoFila], JSON_UNESCAPED_UNICODE);
  }

  public function webhookTinyEvento(){
    header('Content-Type: application/json; charset=utf-8');
    $trace = RequestContext::id();
    $raw = file_get_contents('php://input');
    $evento = json_decode($raw, true);
    if(!is_array($evento)) $evento = $_POST ?: [];
    try {
      $this->validarSegurancaTiny($raw, is_array($evento) ? $evento : []);
    } catch(Throwable $e) {
      Audit::exception($e,'webhook.tiny.evento.seguranca_bloqueado',[
        'codigo_erro'=>'TINY_WEBHOOK_SECURITY_BLOCKED',
        'causa_provavel'=>'Webhook Tiny/Olist sem secret, CNPJ autorizado, IP permitido, ou acima dos limites configurados.',
        'acao_recomendada'=>'Configurar tiny_webhook_secret, tiny_webhook_exigir_secret, CNPJs autorizados e limites na tela de Configurações/Segurança dos Webhooks Tiny.'
      ]);
      http_response_code(401);
      echo json_encode(['success'=>false,'trace_id'=>$trace,'message'=>'Webhook Tiny bloqueado por segurança. Consulte Auditoria pelo Trace ID.'], JSON_UNESCAPED_UNICODE); return;
    }
    if(!$evento){
      Audit::event('webhook.tiny.evento.validacao','erro',[
        'codigo_erro'=>'TINY_EVENT_PAYLOAD_INVALID',
        'mensagem'=>'Evento Tiny vazio ou inválido.',
        'payload'=>$raw,
        'acao_recomendada'=>'Enviar JSON do evento de pedido/NF-e do Tiny para o endpoint do Hub.'
      ]);
      echo json_encode(['success'=>false,'trace_id'=>$trace,'message'=>'Payload Tiny inválido']); return;
    }
    $baixa = EstoqueMapper::tinyEventoParaVsmBaixa($evento);
    if(empty($baixa['itens'])){
      Audit::event('webhook.tiny.evento.sem_itens','alerta',[
        'codigo_erro'=>'TINY_EVENT_NO_STOCK_ITEMS',
        'mensagem'=>'Evento Tiny recebido, mas sem itens para baixa de estoque.',
        'causa_provavel'=>'O payload do Tiny não contém itens, produtos, pedido.itens ou nota.itens com SKU e quantidade.',
        'acao_recomendada'=>'Conferir o JSON real do webhook Tiny ou ajustar EstoqueMapper.',
        'payload'=>$evento
      ]);
      echo json_encode(['success'=>false,'trace_id'=>$trace,'message'=>'Evento Tiny recebido, mas sem itens para baixa de estoque.']); return;
    }
    $config = $this->configIntegracao();
    if (empty($config['fluxo_tiny_vsm_estoque'])) {
      Audit::event('webhook.tiny.baixa_estoque.fluxo_desativado','alerta',[
        'codigo_erro'=>'FLOW_DISABLED',
        'mensagem'=>'Evento Tiny recebido, mas o fluxo Tiny → VSM Baixa Estoque está desativado.',
        'payload'=>$evento,
        'acao_recomendada'=>'Ative o fluxo em Configurações para enviar baixa de estoque para a VSM.'
      ]);
      http_response_code(409);
      echo json_encode(['success'=>false,'trace_id'=>$trace,'message'=>'Fluxo Tiny → VSM Baixa Estoque desativado.']); return;
    }
    $ref = $baixa['referencia'] ?: ('TINY-'.date('YmdHis'));
    if (!$this->registrarEventoIdempotente('tiny',(string)$ref,'baixa_estoque',$raw,$baixa)) {
      echo json_encode(['success'=>true,'duplicado'=>true,'trace_id'=>$trace,'message'=>'Evento Tiny duplicado ignorado pela idempotência.','referencia'=>$ref], JSON_UNESCAPED_UNICODE); return;
    }
    $pdo=Database::getConnection();
    TenantScopeService::run('fila_integracao', 'INSERT INTO fila_integracao(tipo,referencia,payload,status,trace_id) VALUES(?,?,?,?,?)', ['baixa_estoque_vsm',$ref,json_encode($baixa,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'pendente',$trace]);
    Audit::event('webhook.tiny.baixa_estoque.enfileirada','sucesso',[
      'entidade'=>'fila_integracao','entidade_id'=>Database::forTable('fila_integracao')->lastInsertId(),
      'mensagem'=>'Evento Tiny recebido e enviado para fila de baixa de estoque na VSM.',
      'payload'=>$baixa
    ]);
    NotificationService::criar('baixa_estoque','Baixa de estoque recebida do Tiny','Evento Tiny '.$ref.' foi colocado na fila para baixa na VSM.','info',['trace_id'=>$trace,'link'=>'index.php?page=fila']);
    echo json_encode(['success'=>true,'trace_id'=>$trace,'message'=>'Evento Tiny recebido e enviado para fila de baixa VSM','referencia'=>$ref], JSON_UNESCAPED_UNICODE);
  }


  public function webhookTinyPedidoVsm(){
    header('Content-Type: application/json; charset=utf-8');
    $trace=RequestContext::id();
    [$raw,$payload]=TinyWebhookService::rawPayload();
    try { $this->validarSegurancaTiny($raw, $payload); }
    catch(Throwable $e) { $this->responderTiny(false, ['message'=>'Webhook Tiny/Olist bloqueado por segurança.', 'trace_id'=>$trace], 401); return; }
    if(!$payload || !is_array($payload)) { $this->responderTiny(false,['message'=>'Payload de pedido Tiny vazio ou inválido.'],400); return; }
    $cfg = ProductApprovalPolicyService::config();
    $validacao = PedidoTinyVsmValidationService::validar($payload, $cfg);
    $id = PedidoTinyVsmValidationService::registrar($payload, $validacao, 'tiny');
    Audit::event('tiny.webhook.pedido.validado', $validacao['ok']?'sucesso':'alerta', [
      'entidade'=>'pedidos_validacao','entidade_id'=>$id,
      'mensagem'=>$validacao['ok']?'Pedido Tiny validado antes da VSM.':'Pedido Tiny bloqueado antes da VSM.',
      'payload'=>$payload,
      'contexto'=>['erros'=>$validacao['erros'],'avisos'=>$validacao['avisos']]
    ]);
    if($validacao['ok'] && !empty($cfg['pedido_tiny_vsm_auto_enviar_validos']) && empty($cfg['pedido_tiny_vsm_aprovacao_manual'])){
      try { $fila=PedidoTinyVsmValidationService::enfileirar($id); $this->responderTiny(true,['message'=>'Pedido Tiny validado e enfileirado para VSM.','pedido_validacao_id'=>$id,'fila_id'=>$fila]); return; }
      catch(Throwable $e){ Audit::exception($e,'tiny.webhook.pedido.enfileirar_erro',['pedido_validacao_id'=>$id]); $this->responderTiny(false,['message'=>'Pedido validado, mas falhou ao enfileirar para VSM.','erro'=>$e->getMessage(),'pedido_validacao_id'=>$id],500); return; }
    }
    // Achado I-14 + DECISÃO DE PRODUTO (2026-09-22): o primeiro argumento `success` era `true` FIXO
    // enquanto o código HTTP variava — um pedido bloqueado respondia `HTTP 422` com `"success": true`.
    // Corrigido para o corpo seguir o status. E o CÓDIGO HTTP foi decidido pelo responsável à luz da
    // doc oficial da Olist (o webhook deve retornar 200 para confirmar; senão reenvia até 10×, +5 min):
    //   - pedido validado AGUARDANDO APROVAÇÃO -> 200 (ack de recebimento; a Olist não reenvia).
    //     Era 202; a doc pede 200 e o pedido já está gravado, então 200 é o "recebi".
    //   - pedido BLOQUEADO por validação        -> 422 MANTIDO (a Olist reenvia; idempotência por
    //     `pedido_origem_id` impede duplicar — decisão de manter o sinal de rejeição).
    // O caminho de auto-envio logo acima já responde 200 (responderTiny default).
    $this->responderTiny($validacao['ok'],[
      'message'=>$validacao['ok']?'Pedido Tiny validado e aguardando aprovação/envio.':'Pedido Tiny recebido, mas bloqueado por validação.',
      'pedido_validacao_id'=>$id,
      'status_validacao'=>$validacao['status_validacao'],
      'erros'=>$validacao['erros'],
      'avisos'=>$validacao['avisos'],
      'link'=>'index.php?page=pedido-validacao-detalhe&id='.$id
    ], $validacao['ok']?200:422);
  }



  public function webhookVsmRetornoPedido(){
    header('Content-Type: application/json; charset=utf-8');
    $trace=RequestContext::id();
    // P0-10 (reauditoria 2026-08-23): o endpoint aceitava qualquer método HTTP; um
    // envelope assinado só devia valer para o método/rota que o HMAC realmente cobre.
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
      http_response_code(405);
      echo json_encode(['success'=>false,'trace_id'=>$trace,'message'=>'Método não permitido. Use POST.']);
      return;
    }
    $raw=file_get_contents('php://input') ?: '';
    try { $config = $this->configIntegracao(); WebhookSecurityService::validar($config, $raw); }
    catch(Throwable $e){
      Audit::exception($e,'webhook.vsm.retorno_pedido.validacao_erro',[
        'codigo_erro'=>'WEBHOOK_SECURITY_BLOCKED',
        'causa_provavel'=>'Retorno VSM sem HMAC/secret/IP permitido ou tentativa de replay.',
        'acao_recomendada'=>'Configurar X-HUB-SIGNATURE ou X-HUB-SECRET e evitar reenvio do mesmo payload dentro da janela anti-replay.'
      ]);
      http_response_code(401);
      echo json_encode(['success'=>false,'trace_id'=>$trace,'message'=>'Retorno VSM bloqueado por segurança.']); return;
    }
    $payload=json_decode($raw,true);
    if(!is_array($payload)) {
      // Aceita XML puro com identificadores pela query string.
      // P0-10: estes identificadores (pedido_tiny_id/pedido_vsm_id/numero_pedido) vêm da
      // query string e NÃO são cobertos pelo HMAC (que só assina o corpo bruto). Só o
      // secret/HMAC do transporte foi validado acima - os IDs em si continuam não
      // assinados, então tratamos e auditamos como canal de confiança mais fraca.
      Audit::event('webhook.vsm.retorno_pedido.identificadores_nao_assinados','alerta',[
        'mensagem'=>'Retorno VSM em XML identificou o pedido por parâmetros de query fora do escopo do HMAC.',
        'codigo_erro'=>'WEBHOOK_UNSIGNED_QUERY_IDENTIFIERS',
        'contexto'=>['pedido_tiny_id'=>$_GET['pedido_tiny_id'] ?? $_GET['id_tiny'] ?? '','pedido_vsm_id'=>$_GET['pedido_vsm_id'] ?? $_GET['id_vsm'] ?? '','numero_pedido'=>$_GET['numero_pedido'] ?? $_GET['numero'] ?? ''],
        'acao_recomendada'=>'Migrar o retorno VSM para JSON assinado com os identificadores dentro do corpo, evitando depender de query string fora do HMAC.'
      ]);
      // Reauditoria 2026-09-14 (achado A-08): antes o fluxo apenas alertava e seguia usando
      // identificadores não assinados. Com a assinatura v2 exigida, esse canal mais fraco
      // deixa de ser aceito - os identificadores precisam vir dentro do corpo assinado.
      if (!empty(App::config()['security']['webhook_signature_require_v2'])) {
        http_response_code(422);
        echo json_encode(['success'=>false,'trace_id'=>$trace,'message'=>'Identificadores do pedido devem vir no corpo assinado (assinatura v2 exigida), não na query string.'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        return;
      }
      $payload = [
        'pedido_tiny_id'=>$_GET['pedido_tiny_id'] ?? $_GET['id_tiny'] ?? '',
        'pedido_vsm_id'=>$_GET['pedido_vsm_id'] ?? $_GET['id_vsm'] ?? '',
        'numero_pedido'=>$_GET['numero_pedido'] ?? $_GET['numero'] ?? '',
        'xml'=>$raw,
        'status'=>'recebido_xml'
      ];
    }
    try {
      $ret=PedidoCicloVidaService::receberRetornoVsm($payload, (string)($payload['xml'] ?? ''));
      $cfg=IntegrationConfig::get();
      if(!empty($cfg['pedido_xml_auto_enviar_tiny']) && !empty($ret['validado'])){
        $env=PedidoCicloVidaService::enviarXmlParaTiny((int)$ret['pedido_hub_id']);
        $ret['envio_tiny']=$env;
      }
      echo json_encode(['success'=>true,'trace_id'=>$trace,'message'=>'Retorno VSM recebido e registrado no ciclo do pedido.']+$ret, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    } catch(Throwable $e) {
      Audit::exception($e,'webhook.vsm.retorno_pedido.erro',['payload'=>$payload]);
      http_response_code(422);
      echo json_encode(['success'=>false,'trace_id'=>$trace,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }
  }



  public function webhookVsmEstoque(){
    header('Content-Type: application/json; charset=utf-8');
    $trace=RequestContext::id();
    // P0-10: mesmo motivo do retorno de pedido - exigir POST explicitamente.
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
      http_response_code(405);
      echo json_encode(['success'=>false,'trace_id'=>$trace,'message'=>'Método não permitido. Use POST.']);
      return;
    }
    $raw=file_get_contents('php://input') ?: '';
    $payload=json_decode($raw,true);
    if(!is_array($payload)) $payload=$_POST ?: [];
    try { $config = $this->configIntegracao(); WebhookSecurityService::validar($config, $raw); }
    catch(Throwable $e){
      Audit::exception($e,'webhook.vsm.estoque.validacao_erro',[
        'codigo_erro'=>'WEBHOOK_SECURITY_BLOCKED',
        'causa_provavel'=>'Estoque VSM sem HMAC/secret/IP permitido ou tentativa de replay.',
        'acao_recomendada'=>'Configurar X-HUB-SIGNATURE ou X-HUB-SECRET e evitar reenvio do mesmo payload dentro da janela anti-replay.'
      ]);
      http_response_code(401);
      echo json_encode(['success'=>false,'trace_id'=>$trace,'message'=>'Webhook de estoque VSM bloqueado por segurança.']); return;
    }
    try {
      $cfg=EstoqueEnterpriseService::config();
      if(($cfg['estoque_mestre'] ?? 'vsm') !== 'vsm') {
        Audit::event('vsm.webhook.estoque.mestre_divergente','alerta',['mensagem'=>'VSM enviou estoque, mas a política atual não está com VSM como estoque mestre.','contexto'=>$cfg]);
      }
      $res=EstoqueEnterpriseService::receberAtualizacao('vsm',$payload,$raw);
      Audit::event('vsm.webhook.estoque.recebido','sucesso',['entidade'=>'fila_estoque','entidade_id'=>$res['fila_id'] ?? null,'mensagem'=>'Estoque VSM recebido e enfileirado para atualização no Tiny.','payload'=>$payload,'contexto'=>$res]);
      echo json_encode(['success'=>true,'trace_id'=>$trace,'message'=>'Estoque VSM recebido pelo HUB e enfileirado para o Tiny.']+$res, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    } catch(Throwable $e) {
      Audit::exception($e,'vsm.webhook.estoque.erro',['payload'=>$payload]);
      http_response_code(422);
      echo json_encode(['success'=>false,'trace_id'=>$trace,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }
  }

  private function responderTiny(bool $success, array $data=[], int $httpCode=200): void {
    http_response_code($httpCode);
    echo json_encode(array_merge(['success'=>$success,'trace_id'=>RequestContext::id()], $data), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  }

  public function webhookTinyEstoque(){
    header('Content-Type: application/json; charset=utf-8');
    $trace=RequestContext::id();
    [$raw,$payload]=TinyWebhookService::rawPayload();
    try { $this->validarSegurancaTiny($raw, $payload); }
    catch(Throwable $e) { $this->responderTiny(false, ['message'=>'Webhook Tiny/Olist bloqueado por segurança.', 'trace_id'=>RequestContext::id()], 401); return; }
    $erros=TinyWebhookService::validarBase($payload,'estoque');
    $webhookId=TinyWebhookService::registrar('estoque',$raw,$payload,$erros?'erro':'recebido',$erros?implode(' | ',$erros):'Webhook de estoque Tiny recebido.');
    if($erros){
      Audit::event('tiny.webhook.estoque.validacao','erro',[
        'codigo_erro'=>'TINY_STOCK_WEBHOOK_INVALID',
        'mensagem'=>'Webhook de estoque do Tiny inválido.',
        'payload'=>$payload ?: $raw,
        'contexto'=>['erros'=>$erros],
        'acao_recomendada'=>'Conferir se o webhook de atualização de estoque do Tiny está apontando para /api/tiny/webhook/estoque.'
      ]);
      $this->responderTiny(false,['message'=>'Webhook de estoque inválido','erros'=>$erros],400); return;
    }
    $config=$this->configIntegracao();
    if(empty($config['fluxo_tiny_vsm_estoque'])){
      TinyWebhookService::atualizarStatus($webhookId,'ignorado',['motivo'=>'Fluxo Tiny → VSM Estoque desativado.']);
      $this->responderTiny(true,['message'=>'Fluxo de estoque desativado; webhook registrado e ignorado.']); return;
    }
    $baixa=TinyWebhookService::baixaDeEstoqueTiny($payload);
    if(empty($baixa['itens'])){
      TinyWebhookService::atualizarStatus($webhookId,'erro',['erro'=>'Sem itens para baixa.']);
      Audit::event('tiny.webhook.estoque.sem_itens','alerta',[
        'codigo_erro'=>'TINY_STOCK_NO_ITEMS',
        'mensagem'=>'Webhook de estoque recebido sem SKU/quantidade reconhecível.',
        'payload'=>$payload,
        'acao_recomendada'=>'Validar campos dados.sku, dados.skuMapeamento, dados.saldo ou ajustar TinyWebhookService::baixaDeEstoqueTiny().'
      ]);
      $this->responderTiny(false,['message'=>'Webhook recebido, mas sem itens para baixa.'],422); return;
    }
    $ref=$baixa['referencia'] ?: TinyWebhookService::referencia($payload,'estoque');
    if(!$this->registrarEventoIdempotente('tiny',(string)$ref,'webhook_estoque',$raw,$payload)){
      TinyWebhookService::atualizarStatus($webhookId,'duplicado',['referencia'=>$ref]);
      $this->responderTiny(true,['duplicado'=>true,'message'=>'Webhook de estoque duplicado ignorado.','referencia'=>$ref]); return;
    }
    $resEstoque = EstoqueEnterpriseService::receberAtualizacao('tiny', $payload, $raw);
    $filaId=(int)($resEstoque['fila_id'] ?? 0);
    TinyWebhookService::atualizarStatus($webhookId,'enfileirado',['fila_estoque_id'=>$filaId,'referencia'=>$ref,'politica'=>'vsm_fonte_real']);
    Audit::event('tiny.webhook.estoque.enfileirado','sucesso',[
      'entidade'=>'fila_estoque','entidade_id'=>$filaId,
      'mensagem'=>'Webhook Tiny de estoque recebido pelo HUB e enfileirado para atualização na VSM, mantendo VSM como estoque real.',
      'payload'=>$baixa,
      'contexto'=>$resEstoque
    ]);
    NotificationService::criar('baixa_estoque','Webhook Tiny estoque recebido','Estoque '.$ref.' foi enfileirado para sincronizar com a VSM.','info',['trace_id'=>$trace,'link'=>'index.php?page=estoque-dashboard']);
    $this->responderTiny(true,['message'=>'Webhook de estoque recebido e enfileirado na fila exclusiva de estoque.','referencia'=>$ref,'fila_estoque_id'=>$filaId,'politica'=>'vsm_fonte_real']);
  }

  public function webhookTinyProduto(){
    header('Content-Type: application/json; charset=utf-8');
    [$raw,$payload]=TinyWebhookService::rawPayload();
    try { $this->validarSegurancaTiny($raw, $payload); }
    catch(Throwable $e) { $this->responderTiny(false, ['message'=>'Webhook Tiny/Olist bloqueado por segurança.', 'trace_id'=>RequestContext::id()], 401); return; }
    $erros=TinyWebhookService::validarBase($payload,'produto');
    $webhookId=TinyWebhookService::registrar('produto',$raw,$payload,$erros?'erro':'recebido',$erros?implode(' | ',$erros):'Webhook de produto Tiny recebido.');
    $retorno=TinyWebhookService::retornoMapeamentoProduto($payload, !$erros, $erros?implode(' | ',$erros):null);
    TinyWebhookService::atualizarStatus($webhookId,$erros?'erro':'respondido',$retorno);
    Audit::event('tiny.webhook.produto.respondido',$erros?'erro':'sucesso',[
      'codigo_erro'=>$erros?'TINY_PRODUCT_WEBHOOK_INVALID':null,
      'mensagem'=>$erros?'Webhook de produto Tiny inválido.':'Webhook de produto Tiny registrado e respondido com mapeamentos.',
      'payload'=>$payload ?: $raw,
      'retorno'=>$retorno,
      'contexto'=>['erros'=>$erros]
    ]);
    // Importante: a Olist/Tiny espera HTTP 200 com mapeamentos para não ficar reenviando quando a requisição chegou ao Hub.
    echo json_encode($retorno, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  }

  public function webhookTinyNotaFiscal(){
    header('Content-Type: application/json; charset=utf-8');
    [$raw,$payload]=TinyWebhookService::rawPayload();
    try { $this->validarSegurancaTiny($raw, $payload); }
    catch(Throwable $e) { $this->responderTiny(false, ['message'=>'Webhook Tiny/Olist bloqueado por segurança.', 'trace_id'=>RequestContext::id()], 401); return; }
    $erros=TinyWebhookService::validarBase($payload,'nota_fiscal');
    $webhookId=TinyWebhookService::registrar('nota_fiscal',$raw,$payload,$erros?'erro':'recebido',$erros?implode(' | ',$erros):'Webhook de nota fiscal Tiny recebido.');
    if($erros){ $this->responderTiny(false,['message'=>'Webhook de NF-e inválido','erros'=>$erros],400); return; }
    $dados=TinyWebhookService::dados($payload);
    $ref=TinyWebhookService::referencia($payload,'nota_fiscal');
    TinyWebhookService::atualizarStatus($webhookId,'registrado',['referencia'=>$ref]);
    Audit::event('tiny.webhook.nota_fiscal.registrado','sucesso',[
      'entidade'=>'tiny_webhooks','entidade_id'=>$webhookId,
      'mensagem'=>'Webhook de NF-e recebido e guardado para rastreabilidade.',
      'payload'=>$payload,
      'contexto'=>['chave_acesso'=>$dados['chaveAcesso'] ?? $dados['chave_acesso'] ?? null,'numero'=>$dados['numero'] ?? null]
    ]);
    NotificationService::criar('nota_fiscal','NF-e Tiny recebida','Webhook de NF-e '.$ref.' foi registrado no Hub.','info',['trace_id'=>RequestContext::id(),'link'=>'index.php?page=tiny-webhooks']);
    $this->responderTiny(true,['message'=>'Webhook de NF-e registrado.','referencia'=>$ref]);
  }

  public function webhookTinySituacaoPedido(){
    header('Content-Type: application/json; charset=utf-8');
    [$raw,$payload]=TinyWebhookService::rawPayload();
    try { $this->validarSegurancaTiny($raw, $payload); }
    catch(Throwable $e) { $this->responderTiny(false, ['message'=>'Webhook Tiny/Olist bloqueado por segurança.', 'trace_id'=>RequestContext::id()], 401); return; }
    $erros=TinyWebhookService::validarBase($payload,'situacao_pedido');
    $webhookId=TinyWebhookService::registrar('situacao_pedido',$raw,$payload,$erros?'erro':'recebido',$erros?implode(' | ',$erros):'Webhook de situação de pedido Tiny recebido.');
    if($erros){ $this->responderTiny(false,['message'=>'Webhook de situação inválido','erros'=>$erros],400); return; }
    $dados=TinyWebhookService::dados($payload);
    $situacao=strtolower((string)($dados['situacao'] ?? $payload['situacao'] ?? $dados['descricaoSituacao'] ?? $payload['descricaoSituacao'] ?? ''));
    $ref=TinyWebhookService::referencia($payload,'situacao_pedido');
    TinyWebhookService::atualizarStatus($webhookId,'registrado',['referencia'=>$ref,'situacao'=>$situacao]);
    Audit::event('tiny.webhook.situacao_pedido.registrado','sucesso',[
      'entidade'=>'tiny_webhooks','entidade_id'=>$webhookId,
      'mensagem'=>'Webhook de situação de pedido recebido.',
      'payload'=>$payload,
      'contexto'=>['situacao'=>$situacao,'referencia'=>$ref],
      'acao_recomendada'=>'Use esse evento para acionar baixa de estoque apenas quando a situação configurada indicar venda confirmada/faturada.'
    ]);
    $this->responderTiny(true,['message'=>'Webhook de situação de pedido registrado.','referencia'=>$ref,'situacao'=>$situacao]);
  }


  public function processarFila(){
    header('Content-Type: application/json; charset=utf-8');
    if (PHP_SAPI !== 'cli') {
      Auth::requireLogin();
      PermissionService::require('fila','reprocessar');
      if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        echo json_encode(['success'=>false,'message'=>'Use POST com CSRF para processar a fila pelo painel. Para automação, use public/worker_fila.php via CLI.']);
        return;
      }
      Csrf::validate();
    }
    $trace = RequestContext::id();
    $start = microtime(true);
    try {
      QueueService::liberarTravados();
      $pdo=Database::getConnection();
      $item=QueueService::pegarProximo();
      if(!$item){
        Audit::event('fila.processar.sem_pendencia','info',[
          'codigo_erro'=>'QUEUE_EMPTY',
          'mensagem'=>'Nenhum item pendente para processar.',
          'causa_provavel'=>'A fila está vazia ou todos os itens aguardam a próxima tentativa.',
          'acao_recomendada'=>'Crie uma baixa Tiny teste, simule produto VSM ou aguarde a próxima tentativa agendada.'
        ]);
        echo json_encode([
          'success'=>true,
          'status'=>'fila_vazia',
          'trace_id'=>$trace,
          'message'=>'Nenhum item pendente para processar.',
          'action'=>'Use o botão Criar baixa Tiny teste na tela Fila ou simule produto VSM para validar o fluxo correto.'
        ], JSON_UNESCAPED_UNICODE);
        return;
      }
      QueueService::heartbeat((int)$item['id'], (string)($item['locked_by'] ?? ''), (string)($item['tipo'] ?? ''));
      $traceOriginalFila = !empty($item['trace_id']) ? (string)$item['trace_id'] : null; // Não sobrescreve o Trace ID interno da requisição.
      $payload=json_decode($item['payload'],true) ?: [];
      $tipo = (string)($item['tipo'] ?? '');
      $origemExec = match($tipo) {
        'baixa_estoque_vsm' => 'tiny',
        'produto_vsm_para_tiny' => 'vsm',
        'produto_vsm_atualizar_tiny' => 'vsm',
        'produto_vsm_estoque_para_tiny' => 'vsm',
        'produto_vsm_status_para_tiny' => 'vsm',
        'pedido_vsm' => 'vsm_legado',
        'pedido_tiny_para_vsm' => 'tiny',
        default => 'desconhecido'
      };
      $destinoExec = match($tipo) {
        'baixa_estoque_vsm' => 'vsm',
        'produto_vsm_para_tiny' => 'tiny',
        'produto_vsm_atualizar_tiny' => 'tiny',
        'produto_vsm_estoque_para_tiny' => 'tiny',
        'produto_vsm_status_para_tiny' => 'tiny',
        'pedido_vsm' => 'tiny_legado',
        'pedido_tiny_para_vsm' => 'vsm',
        default => 'desconhecido'
      };
      $tipoExec = match($tipo) {
        'baixa_estoque_vsm' => 'baixa_estoque',
        'produto_vsm_para_tiny' => 'produto_novo',
        'produto_vsm_atualizar_tiny' => 'produto_atualizar',
        'produto_vsm_estoque_para_tiny' => 'produto_estoque',
        'produto_vsm_status_para_tiny' => 'produto_status',
        'pedido_vsm' => 'pedido_legado',
        'pedido_tiny_para_vsm' => 'pedido_tiny_vsm',
        default => $tipo ?: 'desconhecido'
      };
      Database::forTable('integracao_execucoes')->prepare("INSERT INTO integracao_execucoes(trace_id,tipo,origem,destino,referencia,status,iniciado_em) VALUES(?,?,?,?,?,'iniciado',NOW())")->execute([$trace,$tipoExec,$origemExec,$destinoExec,$item['referencia']]);
      $execId=(int)Database::forTable('integracao_execucoes')->lastInsertId();
      Audit::event('fila.processar.inicio','info',['entidade'=>'fila_integracao','entidade_id'=>$item['id'],'mensagem'=>'Iniciando processamento da fila','payload'=>$item,'contexto'=>['origem'=>$origemExec,'destino'=>$destinoExec,'tipo_execucao'=>$tipoExec]]);
      PayloadSnapshotService::registrar((int)$item['id'], 'original', $payload, ['referencia'=>$item['referencia'] ?? null, 'origem'=>$origemExec, 'destino'=>$destinoExec, 'trace_id'=>$trace]);
      $ret = [];
      $isErro = false;
      $codigo = null;
      $destino = $destinoExec;
      $status = 'sucesso';
      $pedidoTinyId = null;

      if (in_array($tipo, ['produto_vsm_para_tiny','produto_vsm_atualizar_tiny','produto_vsm_estoque_para_tiny','produto_vsm_status_para_tiny'], true)) {
        $erros = ProdutoMapper::validarVsmProduto($payload);
        if ($erros) throw new RuntimeException('Produto VSM inválido na fila: '.implode(' | ', $erros));
        $tiny=TinyFactory::make();
        $sku = ProdutoMapper::sku($payload) ?: (string)($item['referencia'] ?? '');
        $configAtual = IntegrationConfig::get();
        $governanca = ProdutoVsmGovernanceService::evaluate($payload, $sku, $tipo, $configAtual);
        if (($governanca['action'] ?? '') === 'pending') {
          ProdutoVsmGovernanceService::createPending($payload, $sku, $tipo, (string)$governanca['reason'], (string)$governanca['message'], (int)$item['id'], $trace);
          $ret = ['skipped'=>true,'codigo_erro'=>'PRODUCT_GOVERNANCE_PENDING','message'=>$governanca['message']];
          $isErro = false;
          $status = 'sucesso';
          QueueService::marcarResultado((int)$item['id'], true, $ret, null, (string)($item['locked_by'] ?? ''));
          Database::forTable('integracao_execucoes')->prepare("UPDATE integracao_execucoes SET status='sucesso', retorno=?, finalizado_em=NOW() WHERE id=?")->execute([json_encode($ret,JSON_UNESCAPED_UNICODE), $execId]);
          Audit::event('fila.produto_vsm.governanca_pendente','alerta',['entidade'=>'fila_integracao','entidade_id'=>$item['id'],'mensagem'=>'Item de produto VSM removido do processamento automático e enviado para aprovação manual.','contexto'=>$governanca]);
          echo json_encode(['success'=>true,'trace_id'=>$trace,'status'=>'governanca_pendente','message'=>$governanca['message'],'sku'=>$sku], JSON_UNESCAPED_UNICODE);
          return;
        }
        $produtoTinyId = null;

        if ($tipo === 'produto_vsm_estoque_para_tiny') {
          $saldo = ProdutoMapper::estoque($payload);
          if ($saldo === null) throw new RuntimeException('Atualização de estoque VSM sem saldo/estoque/quantidade.');
          $produtoTiny = ['codigo'=>$sku, 'estoque'=>$saldo];
          PayloadSnapshotService::registrar((int)$item['id'], 'transformado', $produtoTiny, ['referencia'=>$item['referencia'] ?? null, 'origem'=>'vsm', 'destino'=>'tiny', 'trace_id'=>$trace]);
          Audit::event('mapper.produto_estoque_vsm_para_tiny','sucesso',['mensagem'=>'Estoque VSM convertido para atualização de estoque no Tiny','payload'=>$payload,'retorno'=>$produtoTiny]);
          if (!SyncRulesService::enabled('sync_atualizar_estoque_tiny')) {
            $preflight = ['ok'=>true,'info'=>[]];
            $ret = SyncRulesService::skipped('sync_atualizar_estoque_tiny', $payload);
          } elseif (SyncRulesService::enabled('sync_bloquear_estoque_negativo') && (float)$saldo < 0) {
            $preflight = ['ok'=>false,'info'=>[]];
            $ret = ['erro'=>'Estoque negativo bloqueado por regra de sincronização.','codigo_erro'=>'NEGATIVE_STOCK_BLOCKED'];
            $isErro = true; $codigo = 'NEGATIVE_STOCK_BLOCKED';
          } else {
            $preflight = ProdutoTinyPreflightService::consultarOuPendenciar($tiny, $sku, 'produto_estoque_atualizado', $payload, (int)$item['id'], $trace);
            if (!$preflight['ok']) { $ret = ['erro'=>$preflight['mensagem'],'codigo_erro'=>$preflight['codigo'],'preflight'=>$preflight]; $isErro = true; $codigo = $preflight['codigo']; }
            else { $ret=$tiny->atualizarEstoque($sku, (float)$saldo); }
          }
          $statusProcessamentoProduto = (isset($ret['erro']) || isset($ret['codigo_erro'])) ? 'erro' : 'processado';
          $estoqueAnteriorTiny = $preflight['info']['estoque'] ?? null;
          TenantScopeService::run('produtos_vsm_eventos', 'INSERT INTO produtos_vsm_eventos(tipo_evento,sku,produto_vsm_id,status_vsm,estoque_vsm,status_processamento,payload,retorno,trace_id,fila_id, estoque_tiny_anterior, estoque_tiny_novo) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)', ['produto_estoque_atualizado',$sku,$payload['id'] ?? $payload['produto_id'] ?? null, ProdutoMapper::situacaoTiny($payload), $saldo, $statusProcessamentoProduto, json_encode($payload,JSON_UNESCAPED_UNICODE), json_encode($ret,JSON_UNESCAPED_UNICODE), $trace, (int)$item['id'], $estoqueAnteriorTiny, $saldo]);
        } elseif ($tipo === 'produto_vsm_status_para_tiny') {
          $situacao = ProdutoMapper::situacaoTiny($payload);
          $produtoTiny=ProdutoMapper::vsmStatusParaTiny($payload);
          PayloadSnapshotService::registrar((int)$item['id'], 'transformado', $produtoTiny, ['referencia'=>$item['referencia'] ?? null, 'origem'=>'vsm', 'destino'=>'tiny', 'trace_id'=>$trace]);
          Audit::event('mapper.produto_status_vsm_para_tiny','sucesso',['mensagem'=>'Status VSM convertido para atualização de produto no Tiny','payload'=>$payload,'retorno'=>$produtoTiny]);
          $bloqueadoInativoEstoque = ($situacao === 'I') ? ProdutoTinyPreflightService::registrarAlertaInativoComEstoque($sku, (float)(ProdutoMapper::estoque($payload) ?? 0), $payload, (int)$item['id'], $trace) : false;
          if ($bloqueadoInativoEstoque) {
            $ret = ['erro'=>'Produto inativo com estoque bloqueado para conferência manual.','codigo_erro'=>'PRODUCT_INACTIVE_WITH_STOCK_BLOCKED'];
            $isErro = true; $codigo = 'PRODUCT_INACTIVE_WITH_STOCK_BLOCKED';
            $preflight = ['ok'=>false,'info'=>[]];
          } elseif (!SyncRulesService::enabled('sync_atualizar_status_tiny')) {
            $preflight = ['ok'=>true,'info'=>[]];
            $ret = SyncRulesService::skipped('sync_atualizar_status_tiny', $payload);
          } else {
            $preflight = ProdutoTinyPreflightService::consultarOuPendenciar($tiny, $sku, 'produto_status_atualizado', $payload, (int)$item['id'], $trace);
            if (!$preflight['ok']) { $ret = ['erro'=>$preflight['mensagem'],'codigo_erro'=>$preflight['codigo'],'preflight'=>$preflight]; $isErro = true; $codigo = $preflight['codigo']; }
            else { $ret=$tiny->atualizarStatusProduto($sku, $situacao); }
          }
          $statusProcessamentoProduto = (isset($ret['erro']) || isset($ret['codigo_erro'])) ? 'erro' : 'processado';
          $statusAnteriorTiny = (string)($preflight['info']['situacao'] ?? '');
          TenantScopeService::run('produtos_vsm_eventos', 'INSERT INTO produtos_vsm_eventos(tipo_evento,sku,produto_vsm_id,status_vsm,estoque_vsm,status_processamento,payload,retorno,trace_id,fila_id, status_tiny_anterior, status_tiny_novo) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)', ['produto_status_atualizado',$sku,$payload['id'] ?? $payload['produto_id'] ?? null, $situacao, ProdutoMapper::estoque($payload), $statusProcessamentoProduto, json_encode($payload,JSON_UNESCAPED_UNICODE), json_encode($ret,JSON_UNESCAPED_UNICODE), $trace, (int)$item['id'], $statusAnteriorTiny, $situacao]);
        } else {
          $produtoTiny=ProdutoMapper::vsmParaTiny($payload);
          PayloadSnapshotService::registrar((int)$item['id'], 'transformado', $produtoTiny, ['referencia'=>$item['referencia'] ?? null, 'origem'=>'vsm', 'destino'=>'tiny', 'trace_id'=>$trace]);
          Audit::event('mapper.produto_vsm_para_tiny','sucesso',['mensagem'=>'Produto VSM convertido para formato Tiny','payload'=>$payload,'retorno'=>$produtoTiny]);
          PayloadSnapshotService::registrar((int)$item['id'], 'enviado', $produtoTiny, ['referencia'=>$item['referencia'] ?? null, 'origem'=>'vsm', 'destino'=>'tiny', 'trace_id'=>$trace]);
          $regraProduto = ($tipo === 'produto_vsm_atualizar_tiny') ? 'sync_atualizar_produto_tiny' : 'sync_criar_produto_tiny';
          if ($tipo === 'produto_vsm_para_tiny' && empty($payload['hub_aprovado_manual']) && !ProdutoVsmGovernanceService::isAutoCreateAllowed(IntegrationConfig::get())) {
            $ret = SyncRulesService::skipped('sync_bloquear_produto_novo_vsm', $payload);
            $ret['message'] = 'Criação automática de produto novo VSM no Tiny bloqueada. Use aprovação manual no Hub.';
          } elseif (!SyncRulesService::enabled($regraProduto)) {
            $ret = SyncRulesService::skipped($regraProduto, $payload);
          } else {
            $ret = ($tipo === 'produto_vsm_atualizar_tiny') ? $tiny->atualizarProduto($produtoTiny) : $tiny->criarProduto($produtoTiny);
          }
          $produtoTinyId = ProdutoMapper::extrairProdutoTinyId($ret);
          TenantScopeService::run('produtos_vsm_eventos', 'INSERT INTO produtos_vsm_eventos(tipo_evento,sku,produto_vsm_id,status_vsm,estoque_vsm,status_processamento,payload,retorno,trace_id,fila_id) VALUES(?,?,?,?,?,?,?,?,?,?)', [$tipo === 'produto_vsm_atualizar_tiny' ? 'produto_cadastro_atualizado' : 'produto_novo', $sku, $payload['id'] ?? $payload['produto_id'] ?? null, ProdutoMapper::situacaoTiny($payload), ProdutoMapper::estoque($payload), 'processado', json_encode($payload,JSON_UNESCAPED_UNICODE), json_encode($ret,JSON_UNESCAPED_UNICODE), $trace, (int)$item['id']]);
        }

        $isErro = $isErro || isset($ret['erro']) || isset($ret['codigo_erro']) || (isset($ret['retorno']['status_processamento']) && (int)$ret['retorno']['status_processamento'] !== 3 && isset($ret['retorno']['erros']));
        $tinyErro = $isErro ? TinyErrorCatalog::detectar($ret) : null;
        $codigo=$isErro ? ($ret['codigo_erro'] ?? $tinyErro['codigo'] ?? 'TINY_PRODUCT_SYNC_ERROR') : null;
        $ativo = ProdutoMapper::situacaoTiny($payload) === 'A' ? 1 : 0;
        TenantScopeService::run('produtos_mapeamento', 'INSERT INTO produtos_mapeamento(sku_tiny,sku_vsm,produto_tiny_id,produto_vsm_id,descricao,ativo,estoque_atual,status_tiny,ultima_sincronizacao) VALUES(?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE produto_tiny_id=COALESCE(VALUES(produto_tiny_id), produto_tiny_id), descricao=COALESCE(VALUES(descricao), descricao), ativo=VALUES(ativo), estoque_atual=COALESCE(VALUES(estoque_atual), estoque_atual), status_tiny=VALUES(status_tiny), ultima_sincronizacao=NOW()', [$sku,$sku,$produtoTinyId,$payload['id'] ?? $payload['produto_id'] ?? null,$payload['nome'] ?? $payload['descricao'] ?? null,$ativo,ProdutoMapper::estoque($payload),ProdutoMapper::situacaoTiny($payload)]);
        $pedidoTinyId = $produtoTinyId ?: $sku;
        $destino = 'tiny';
      } elseif ($tipo === 'baixa_estoque_vsm') {
        $vsm = new VsmService();
        $baixa = $payload;
        PayloadSnapshotService::registrar((int)$item['id'], 'enviado', $baixa, ['referencia'=>$item['referencia'] ?? null, 'origem'=>'tiny', 'destino'=>'vsm', 'trace_id'=>$trace]);
        $ret = $vsm->enviarBaixaEstoque($baixa, 'fila_estoque_pedidos:'.$item['id']);
        $isErro = isset($ret['erro']) || isset($ret['codigo_erro']) || ((int)($ret['http_code'] ?? 200) < 200 || (int)($ret['http_code'] ?? 200) >= 300);
        $codigo = $isErro ? ($ret['codigo_erro'] ?? 'VSM_STOCK_DECREASE_ERROR') : null;
        $destino = 'vsm';
        foreach (($baixa['itens'] ?? []) as $it) {
          TenantScopeService::run('estoque_movimentos', 'INSERT IGNORE INTO estoque_movimentos(origem,referencia,sku,quantidade,tipo_movimento,status,payload_origem,retorno_vsm,trace_id) VALUES(?,?,?,?,?,?,?,?,?)', ['tiny',$baixa['referencia'] ?? $item['referencia'],$it['sku'] ?? '',(float)($it['quantidade'] ?? 0),'baixa',$isErro?'erro':'sucesso',json_encode($baixa,JSON_UNESCAPED_UNICODE),json_encode($ret,JSON_UNESCAPED_UNICODE),$trace]);
        }
      } elseif ($tipo === 'pedido_tiny_para_vsm') {
        $vsm = new VsmService();
        PayloadSnapshotService::registrar((int)$item['id'], 'enviado', $payload, ['referencia'=>$item['referencia'] ?? null, 'origem'=>'tiny', 'destino'=>'vsm', 'trace_id'=>$trace]);
        $ret = $vsm->enviarPedido($payload, 'fila_pedidos:'.$item['id']);
        $isErro = isset($ret['erro']) || isset($ret['codigo_erro']) || ((int)($ret['http_code'] ?? 200) < 200 || (int)($ret['http_code'] ?? 200) >= 300);
        $codigo = $isErro ? ($ret['codigo_erro'] ?? 'VSM_ORDER_SEND_ERROR') : null;
        $destino = 'vsm';
        try { TenantScopeService::run('pedidos_validacao', "UPDATE pedidos_validacao SET status_validacao=?, retorno_vsm_json=?, atualizado_em=NOW() WHERE pedido_origem_id=?", [$isErro?'erro_envio_vsm':'enviado_vsm', json_encode($ret,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), $item['referencia']]); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
        try { if(class_exists('PedidoCicloVidaService')) PedidoCicloVidaService::marcarEnviadoVsmPorTinyId((string)$item['referencia'], $ret, $isErro); } catch(Throwable $e) { Audit::exception($e,'pedido.ciclo.marcar_enviado_vsm.erro',['referencia'=>$item['referencia'] ?? null]); }
      } else {
        // Compatibilidade com versões anteriores: fluxo VSM → Tiny Pedido fica desativado por padrão.
        $cfgFluxo = IntegrationConfig::get();
        if ($tipo === 'pedido_vsm' && empty($cfgFluxo['fluxo_vsm_tiny_pedido'])) {
          throw new RuntimeException('Fluxo pedido_vsm desativado. O fluxo operacional correto é Tiny → VSM baixa de estoque e VSM → Tiny produto novo.');
        }
        $erros = PedidoValidator::validarVsm($payload);
        if ($erros) throw new RuntimeException('Pedido inválido na fila: '.implode(' | ', $erros));
        $tiny=TinyFactory::make();
        $pedidoTiny=PedidoMapper::vsmParaTiny($payload);
        Audit::event('mapper.vsm_para_tiny','sucesso',['mensagem'=>'Payload VSM convertido para formato Tiny','payload'=>$payload,'retorno'=>$pedidoTiny]);
        $ret=$tiny->criarPedido($pedidoTiny);
        $isErro = isset($ret['erro']) || isset($ret['codigo_erro']) || (isset($ret['retorno']['status_processamento']) && (int)$ret['retorno']['status_processamento'] !== 3 && isset($ret['retorno']['erros']));
        $tinyErro = $isErro ? TinyErrorCatalog::detectar($ret) : null;
        $codigo=$isErro ? ($ret['codigo_erro'] ?? $tinyErro['codigo'] ?? 'TINY_ORDER_CREATE_ERROR') : null;
        $pedidoTinyId = PedidoMapper::extrairPedidoTinyId($ret);
        $origemId=$payload['id']??$payload['pedido']??$payload['numero']??$payload['numeroPedido']??$item['referencia'];
        $cliente=$payload['cliente']??$payload['comprador']??[];
        $valor=PedidoMapper::valorTotal($payload);
        TenantScopeService::run('pedidos_integracao', 'INSERT INTO pedidos_integracao(origem,pedido_origem_id,pedido_tiny_id,cliente_nome,cliente_documento,valor_total,status,payload_origem,payload_tiny,retorno_tiny,erro,tentativas,trace_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE pedido_tiny_id=VALUES(pedido_tiny_id), cliente_nome=VALUES(cliente_nome), cliente_documento=VALUES(cliente_documento), valor_total=VALUES(valor_total), status=VALUES(status), retorno_tiny=VALUES(retorno_tiny), erro=VALUES(erro), tentativas=tentativas+1, trace_id=VALUES(trace_id), atualizado_em=NOW()', ['vsm',$origemId,$pedidoTinyId,$cliente['nome']??$cliente['razao_social']??null,$cliente['documento']??$cliente['cpf_cnpj']??null,$valor,$isErro?'erro':'sucesso',json_encode($payload,JSON_UNESCAPED_UNICODE),json_encode($pedidoTiny,JSON_UNESCAPED_UNICODE),json_encode($ret,JSON_UNESCAPED_UNICODE),$isErro?json_encode($ret,JSON_UNESCAPED_UNICODE):null,(int)$item['tentativas'],$trace]);
      }

      QueueService::marcarResultado((int)$item['id'], !$isErro, $ret, $codigo, (string)($item['locked_by'] ?? ''));
      $status=$isErro?'erro':'sucesso';
      $dur=(int)((microtime(true)-$start)*1000);
      Database::forTable('integracao_execucoes')->prepare('UPDATE integracao_execucoes SET destino=?, status=?, finalizado_em=NOW(), duracao_ms=?, erro_codigo=?, erro_mensagem=? WHERE id=?')->execute([$destino,$status,$dur,$codigo,$isErro?json_encode($ret,JSON_UNESCAPED_UNICODE):null,$execId]);
      Audit::event('fila.processar.fim',$isErro?'erro':'sucesso',[
        'codigo_erro'=>$codigo,
        'entidade'=>'fila_integracao',
        'entidade_id'=>$item['id'],
        'mensagem'=>$isErro?'Fila processada com erro':'Fila processada com sucesso',
        'causa_provavel'=>$isErro ? 'A API de destino recusou a requisição, retornou erro ou não respondeu como esperado.' : null,
        'acao_recomendada'=>$isErro ? 'Abrir o detalhe da auditoria pelo Trace ID e conferir payload, retorno da API, tokens e endpoint configurado.' : null,
        'retorno'=>$ret,
        'contexto'=>['duracao_ms'=>$dur,'tipo'=>$tipo,'destino'=>$destino,'id_destino'=>$pedidoTinyId]
      ]);
      if($isErro){ NotificationService::erroIntegracao('Erro ao processar integração', 'O item da fila #'.$item['id'].' falhou no fluxo '.$tipo.'.', ['trace_id'=>$trace, 'entidade'=>'fila_integracao', 'entidade_id'=>$item['id'], 'payload'=>$ret]); }
      else { NotificationService::criar('integracao_sucesso','Integração processada com sucesso','Item da fila #'.$item['id'].' processado no fluxo '.$tipo.'.','sucesso',['trace_id'=>$trace,'entidade'=>'fila_integracao','entidade_id'=>$item['id'],'link'=>'index.php?page=fila']); }
      MetricsService::registrar($destino, $tipo, null, $dur, !$isErro, $codigo);
      echo json_encode(['success'=>!$isErro,'trace_id'=>$trace,'status'=>$status,'tipo'=>$tipo,'destino'=>$destino,'id_destino'=>$pedidoTinyId,'retorno'=>$ret], JSON_UNESCAPED_UNICODE);
    } catch(Throwable $e){
      Audit::exception($e,'fila.processar.erro',['codigo_erro'=>'QUEUE_PROCESS_ERROR']);
      NotificationService::erroIntegracao('Erro crítico ao processar fila', $e->getMessage(), ['trace_id'=>$trace]);
      if(isset($item['id'])) QueueService::marcarResultado((int)$item['id'], false, ['erro'=>$e->getMessage(),'trace_id'=>$trace], 'QUEUE_PROCESS_ERROR', (string)($item['locked_by'] ?? ''));
      echo json_encode(['success'=>false,'trace_id'=>$trace,'message'=>'Erro ao processar fila. Consulte Auditoria pelo Trace ID.']);
    }
  }

  public function notificacoesRecentes(){
    header('Content-Type: application/json');
    Auth::requireLogin();
    $ultimoId = (int)($_GET['ultimo_id'] ?? 0);
    echo json_encode(['success'=>true,'nao_lidas'=>NotificationService::totalNaoLidas(),'notificacoes'=>NotificationService::recentes($ultimoId, 20)], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  }

  public function marcarNotificacaoLidaApi(){
    header('Content-Type: application/json');
    Auth::requireLogin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Método não permitido. Use POST com CSRF.']); return; }
    Csrf::validate();
    $id = (int)($_POST['id'] ?? 0);
    if($id > 0) NotificationService::marcarLida($id);
    echo json_encode(['success'=>true,'nao_lidas'=>NotificationService::totalNaoLidas()]);
  }

  public function statusJson(){
    header('Content-Type: application/json; charset=utf-8');
    $cfgApp = class_exists('App') ? App::config() : [];
    $mode = (string)($cfgApp['security']['api_status_public_mode'] ?? 'minimal');
    $logged = class_exists('Auth') && Auth::check();

    // V104.26: não expõe fila, DLQ, Tiny/VSM, circuit breaker ou erro interno para usuário anônimo.
    if (!$logged) {
      if ($mode === 'private') { http_response_code(401); }
      echo json_encode([
        'success'=>($mode !== 'private'),
        'status'=>($mode === 'private') ? 'authentication_required' : 'ok',
        'trace_id'=>RequestContext::id(),
        'timestamp'=>date('c')
      ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
      return;
    }

    try {
      PermissionService::require('dashboard','visualizar');
      $cfg=IntegrationConfig::get();
      $pdoFila=Database::forTable('fila_integracao');
      $filaPendente=(int)$pdoFila->query("SELECT COUNT(*) c FROM fila_integracao WHERE status='pendente'")->fetch()['c'];
      $filaErro=(int)$pdoFila->query("SELECT COUNT(*) c FROM fila_integracao WHERE status IN ('erro','falha_definitiva')")->fetch()['c'];
      $dlq=(int)TenantScopeService::run('fila_morta', "SELECT COUNT(*) c FROM fila_morta WHERE status='aberto'")->fetch()['c'];
      $cb=Database::forTable('circuit_breakers')->query("SELECT sistema,status,falhas_consecutivas,aberto_ate FROM circuit_breakers")->fetchAll();
      // Melhoria 6 da seção 8 (relatório V104.49.3-R6): o estado de degradação dos controles de
      // segurança morria em $GLOBALS no fim da requisição. Agora é consultável aqui, para o
      // operador ver que uma proteção está degradada antes do incidente, e não depois.
      $seguranca = class_exists('SecurityHealthService') ? SecurityHealthService::summary() : ['degradado'=>false,'controles'=>[]];
      echo json_encode(['success'=>true,'trace_id'=>RequestContext::id(),'database'=>'ok','tiny'=>!empty($cfg['tiny_v2_url'])?'configured':'missing','vsm'=>!empty($cfg['vsm_url'])?'configured':'missing','fila'=>['pendente'=>$filaPendente,'erro'=>$filaErro,'fila_morta'=>$dlq],'circuit_breakers'=>$cb,'seguranca'=>$seguranca,'timestamp'=>date('c')], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    } catch(Throwable $e){ http_response_code(500); echo json_encode(['success'=>false,'trace_id'=>RequestContext::id(),'message'=>'Erro ao consultar status autenticado. Consulte Auditoria pelo Trace ID.']); }
  }

}
