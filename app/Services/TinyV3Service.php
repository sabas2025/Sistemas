<?php
class TinyV3Service implements TinyClientInterface {
  private string $url;
  private array $cfg;

  public function __construct(?array $cfg=null){
    $this->cfg=$cfg ?: IntegrationConfig::get();
    $this->url=rtrim((string)($this->cfg['tiny_v3_url'] ?? cfg('tiny.v3_url') ?: 'https://api.tiny.com.br/public-api/v3'),'/');
  }

  private function token(): string {
    return TinyV3TokenService::accessToken();
  }

  private function request(string $method, string $path, array $payload=[], array $query=[]): array {
    $trace=RequestContext::id();
    if(!CircuitBreakerService::permitir('tiny_v3')) return ['erro'=>'Circuit breaker Tiny V3 aberto.','codigo_erro'=>'TINY_V3_CIRCUIT_OPEN','trace_id'=>$trace];
    $token=$this->token();
    if(!$token){
      Audit::event('tiny.v3.request.bloqueado','erro',['codigo_erro'=>'TINY_V3_TOKEN_MISSING','mensagem'=>'Token Tiny V3 ausente. OAuth é obrigatório para Tiny V3.']);
      return ['erro'=>'Token Tiny V3 não configurado. OAuth obrigatório.','codigo_erro'=>'TINY_V3_TOKEN_MISSING','trace_id'=>$trace];
    }
    try {
      $baseUrl=TinyEndpointSecurityService::validateUrl($this->url);
      $safePath=TinyEndpointSecurityService::sanitizePath($path);
      $securityOptions=TinyEndpointSecurityService::curlSecurityOptions($baseUrl);
    } catch(Throwable $e) {
      Audit::exception($e,'tiny.v3.url.bloqueada',['codigo_erro'=>'TINY_V3_URL_BLOCKED']);
      return ['erro'=>'URL Tiny V3 bloqueada pela política de segurança.','codigo_erro'=>'TINY_V3_URL_BLOCKED','trace_id'=>$trace];
    }
    $url=$baseUrl.'/'.$safePath;
    if($query) $url.='?'.http_build_query($query);
    $safeHeaders=['Authorization'=>'Bearer ***mascarado***','Content-Type'=>'application/json','Accept'=>'application/json','X-Trace-ID'=>$trace];
    Audit::event('tiny.v3.request','info',['mensagem'=>'Enviando requisição para Tiny V3','payload'=>['method'=>$method,'url'=>$url,'headers'=>$safeHeaders,'body'=>SensitiveDataService::mask($payload)]]);

    $attempts=RetryPolicyService::attempts();
    $last=null;
    for($attempt=1; $attempt <= $attempts; $attempt++){
      RetryPolicyService::sleep($attempt);
      $ch=curl_init($url);
      $headers=['Authorization: Bearer '.$token,'Accept: application/json','Content-Type: application/json','X-Trace-ID: '.$trace];
      $capHeaders=[]; // T-05: observabilidade do limite (X-RateLimit-*), sem throttle
      $opts=$securityOptions+[CURLOPT_CUSTOMREQUEST=>strtoupper($method),CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADERFUNCTION=>TinyRateLimitObserverService::headerCapture($capHeaders),CURLOPT_TIMEOUT=>30,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2];
      if(in_array(strtoupper($method),['POST','PUT','PATCH'],true)) $opts[CURLOPT_POSTFIELDS]=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
      curl_setopt_array($ch,$opts);
      $start=microtime(true); $body=curl_exec($ch); $err=curl_error($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
      $ms=(int)round((microtime(true)-$start)*1000);
      $json = $body==='' ? [] : json_decode((string)$body,true);
      $last=['body'=>$body,'err'=>$err,'http'=>$http,'ms'=>$ms,'json'=>$json,'attempt'=>$attempt,'headers'=>$capHeaders];
      if (!RetryPolicyService::shouldRetry($http, $err ?: null, $json) || $attempt === $attempts) break;
      Audit::event('tiny.v3.retry','alerta',['mensagem'=>'Retry Tiny V3 com backoff exponencial','contexto'=>['path'=>$path,'tentativa'=>$attempt,'http'=>$http,'erro'=>$err,'trace_id'=>$trace]]);
    }

    $body=$last['body']; $err=$last['err']; $http=(int)$last['http']; $ms=(int)$last['ms']; $json=$last['json']; $attempt=(int)$last['attempt'];
    if($err){
      $this->logEndpoint($path, strtoupper($method), $http, false, $ms, $trace, $payload, (string)$body, $err);
      MetricsService::registrar('tiny_v3',$path,$http,$ms,false,'TINY_V3_CURL_ERROR'); CircuitBreakerService::falha('tiny_v3',$err);
      Audit::event('tiny.v3.response','erro',['codigo_erro'=>'TINY_V3_CURL_ERROR','mensagem'=>$err,'retorno'=>['http_code'=>$http,'body'=>SensitiveDataService::mask((string)$body),'tentativas'=>$attempt]]);
      return ['erro'=>$err,'codigo_erro'=>'TINY_V3_CURL_ERROR','http_code'=>$http,'trace_id'=>$trace,'tentativas'=>$attempt];
    }
    if($body!=='' && !is_array($json)){
      $this->logEndpoint($path, strtoupper($method), $http, false, $ms, $trace, $payload, (string)$body, 'TINY_V3_INVALID_JSON');
      MetricsService::registrar('tiny_v3',$path,$http,$ms,false,'TINY_V3_INVALID_JSON'); CircuitBreakerService::falha('tiny_v3','TINY_V3_INVALID_JSON');
      Audit::event('tiny.v3.response','erro',['codigo_erro'=>'TINY_V3_INVALID_JSON','mensagem'=>'Tiny V3 retornou JSON inválido','retorno'=>['http_code'=>$http,'body'=>SensitiveDataService::mask((string)$body),'tentativas'=>$attempt]]);
      return ['erro'=>'Resposta inválida Tiny V3','codigo_erro'=>'TINY_V3_INVALID_JSON','http_code'=>$http,'raw'=>SensitiveDataService::mask((string)$body),'trace_id'=>$trace,'tentativas'=>$attempt];
    }
    $ok=$http>=200 && $http<300;
    $this->logEndpoint($path, strtoupper($method), $http, $ok, $ms, $trace, $payload, (string)$body, $ok ? null : 'HTTP '.$http);
    MetricsService::registrar('tiny_v3',$path,$http,$ms,$ok,$ok?null:'TINY_V3_HTTP_ERROR');
    if($ok) CircuitBreakerService::sucesso('tiny_v3'); else CircuitBreakerService::falha('tiny_v3','HTTP '.$http);
    // T-05 (observabilidade, sem throttle): registra o orçamento de limite que o Tiny informou nos headers.
    $rateLimit = TinyRateLimitObserverService::v3($last['headers'] ?? []);
    Audit::event('tiny.v3.response',$ok?'sucesso':'erro',['mensagem'=>'Resposta recebida do Tiny V3','retorno'=>['http_code'=>$http,'tempo_ms'=>$ms,'json'=>SensitiveDataService::mask($json),'tentativas'=>$attempt,'rate_limit'=>$rateLimit]]);
    $ret=['http_code'=>$http,'ok'=>$ok,'data'=>$json,'trace_id'=>$trace,'tentativas'=>$attempt];
    if($rateLimit!==null) $ret['rate_limit']=$rateLimit;
    return $ret;
  }

  private function logEndpoint(string $endpoint, string $metodo, int $httpCode, bool $sucesso, int $tempoMs, string $trace, array $requestBody=[], ?string $responseBody=null, ?string $erro=null): void {
    try {
      $pdo = Database::forTable('tiny_v3_endpoint_logs');
      $safeRequest = SensitiveDataService::mask($requestBody);
      $req = $requestBody ? json_encode($safeRequest, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null;
      $resp = $responseBody !== null ? mb_substr(SensitiveDataService::mask((string)$responseBody), 0, 200000) : null;
      $safeErro = $erro !== null ? SensitiveDataService::mask((string)$erro) : null;
      $st = $pdo->prepare('INSERT INTO tiny_v3_endpoint_logs(endpoint, metodo, http_code, sucesso, tempo_ms, trace_id, request_body, response_body, erro, criado_em) VALUES(?,?,?,?,?,?,?,?,?,NOW())');
      $st->execute([$endpoint, $metodo, $httpCode ?: null, $sucesso ? 1 : 0, $tempoMs, $trace, $req, $resp, $safeErro]);
    } catch (Throwable $e) {
      if(class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e, ['provider'=>'tiny_v3','endpoint'=>$endpoint]);
    }
  }

  private function normalizeList(array $ret): array {
    $data=$ret['data'] ?? [];
    if(isset($data['itens']) && is_array($data['itens'])) return $data['itens'];
    if(isset($data['items']) && is_array($data['items'])) return $data['items'];
    if(isset($data['produtos']) && is_array($data['produtos'])) return $data['produtos'];
    if(array_is_list($data)) return $data;
    return $data ? [$data] : [];
  }

  public function consultarProduto(string $sku): array {
    $endpoint=TinyV3EndpointCatalog::get('produtos_listar');
    $ret=$this->request('GET',$endpoint,[],['sku'=>$sku,'codigo'=>$sku,'pesquisa'=>$sku]);
    $items=$this->normalizeList($ret);
    $match=null;
    foreach($items as $item){
      $codigo=(string)($item['sku'] ?? $item['codigo'] ?? $item['produto']['codigo'] ?? $item['produto']['sku'] ?? '');
      if($codigo === $sku){ $match=$item; break; }
    }
    $ret['produto_encontrado']=$match;
    $ret['sku_exato']=$match !== null;
    if($match===null && !empty($items)) $ret['aviso']='Tiny V3 retornou produtos, mas nenhum SKU exatamente igual ao solicitado.';
    return $ret;
  }

  public function criarProduto(array $produto): array { return $this->request('POST', TinyV3EndpointCatalog::get('produtos_criar'), $produto); }

  public function atualizarProduto(array $produto): array {
    $sku=(string)($produto['sku'] ?? $produto['codigo'] ?? $produto['produto']['codigo'] ?? '');
    $id=(string)($produto['id'] ?? $produto['produto']['id'] ?? '');
    if(!$id && $sku){ $pre=$this->consultarProduto($sku); $found=$pre['produto_encontrado'] ?? null; $id=(string)($found['id'] ?? $found['produto']['id'] ?? ''); }
    if(!$id) return ['erro'=>'Produto Tiny V3 não encontrado para atualização.','codigo_erro'=>'TINY_V3_PRODUCT_NOT_FOUND','trace_id'=>RequestContext::id()];
    return $this->request('PUT', TinyV3EndpointCatalog::fill(TinyV3EndpointCatalog::get('produtos_alterar'), ['id'=>$id]), $produto);
  }

  public function atualizarStatusProduto(string $sku, string $situacao): array {
    $pre=$this->consultarProduto($sku); $found=$pre['produto_encontrado'] ?? null; $id=(string)($found['id'] ?? $found['produto']['id'] ?? '');
    if(!$id) return ['erro'=>'SKU não encontrado no Tiny V3 para alteração de status.','codigo_erro'=>'TINY_V3_SKU_NOT_FOUND','trace_id'=>RequestContext::id(),'preflight'=>$pre];
    $payload=['situacao'=>$situacao,'status'=>$situacao,'ativo'=>in_array(strtolower($situacao),['ativo','a','s','sim','1'],true)];
    return $this->request('PATCH', TinyV3EndpointCatalog::fill(TinyV3EndpointCatalog::get('produtos_alterar'), ['id'=>$id]), $payload);
  }

  public function atualizarEstoque(string $sku, float $qtd): array {
    $pre=$this->consultarProduto($sku); $found=$pre['produto_encontrado'] ?? null; $id=(string)($found['id'] ?? $found['produto']['id'] ?? '');
    if(!$id) return ['erro'=>'SKU não encontrado no Tiny V3 para atualização de estoque.','codigo_erro'=>'TINY_V3_SKU_NOT_FOUND','trace_id'=>RequestContext::id(),'preflight'=>$pre];
    return $this->request('POST', TinyV3EndpointCatalog::fill(TinyV3EndpointCatalog::get('estoque_atualizar'), ['id'=>$id]), ['saldo'=>$qtd,'estoque'=>$qtd,'quantidade'=>$qtd]);
  }

  public function consultarPedido(string $id): array { return $this->request('GET', TinyV3EndpointCatalog::fill(TinyV3EndpointCatalog::get('pedidos_obter'), ['id'=>$id])); }
  public function criarPedido(array $pedido): array { return $this->request('POST', '/pedidos', $pedido); }
  public function consultarNotaFiscal(string $id): array { return $this->request('GET', TinyV3EndpointCatalog::fill(TinyV3EndpointCatalog::get('notas_obter'), ['id'=>$id])); }
  public function lancarEstoquePedido(string $idPedido): array { return $this->request('POST', TinyV3EndpointCatalog::fill(TinyV3EndpointCatalog::get('pedidos_lancar_estoque'), ['idPedido'=>$idPedido])); }

  public function testarModulo(string $modulo, array $params=[]): array {
    return match($modulo) {
      'token' => $this->request('GET', TinyV3EndpointCatalog::get('produtos_listar'), [], ['limit'=>1]),
      'listar_produtos' => $this->request('GET', TinyV3EndpointCatalog::get('produtos_listar'), [], ['limit'=>5]),
      'produto' => $this->consultarProduto((string)($params['sku'] ?? 'TESTE')),
      'estoque' => $this->consultarEstoquePorSku((string)($params['sku'] ?? 'TESTE')),
      'pedido' => $this->consultarPedido((string)($params['id_pedido'] ?? '0')),
      'nota_fiscal' => $this->consultarNotaFiscal((string)($params['id_nota'] ?? '0')),
      default => ['erro'=>'Módulo Tiny V3 desconhecido.','codigo_erro'=>'TINY_V3_TEST_MODULE_UNKNOWN','trace_id'=>RequestContext::id()]
    };
  }

  public function consultarEstoquePorSku(string $sku): array {
    $pre = $this->consultarProduto($sku);
    $found = $pre['produto_encontrado'] ?? null;
    $id = (string)($found['id'] ?? $found['produto']['id'] ?? '');
    if(!$id) return ['erro'=>'SKU não encontrado no Tiny V3 para consulta de estoque.','codigo_erro'=>'TINY_V3_SKU_NOT_FOUND','trace_id'=>RequestContext::id(),'preflight'=>$pre];
    return $this->request('GET', TinyV3EndpointCatalog::fill(TinyV3EndpointCatalog::get('estoque_consultar'), ['id'=>$id]));
  }

  public function enviarNfeXmlPedido(string $pedidoId, array $dados): array {
    $payload = [
      'pedidoId'=>$pedidoId,
      'idPedido'=>$pedidoId,
      'chaveNFe'=>$dados['chave_nfe'] ?? null,
      'numeroNFe'=>$dados['numero_nfe'] ?? null,
      'serie'=>$dados['serie'] ?? null,
      'xml'=>$dados['xml'] ?? null,
      'status'=>$dados['status'] ?? 'faturado'
    ];
    $retXml = $this->request('POST', '/notas/xml', $payload);
    $retStatus = $this->request('PATCH', TinyV3EndpointCatalog::fill(TinyV3EndpointCatalog::get('pedidos_obter'), ['id'=>$pedidoId]), ['situacao'=>$dados['status'] ?? 'faturado','status'=>$dados['status'] ?? 'faturado']);
    return ['xml'=>$retXml,'status_pedido'=>$retStatus,'trace_id'=>RequestContext::id()];
  }
}
