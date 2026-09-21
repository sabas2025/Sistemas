<?php
class TinyV2Service implements TinyClientInterface {
  private string $url; private string $token;
  public function __construct(?array $cfg=null){ $cfg=$cfg ?: IntegrationConfig::get(); $this->url=rtrim((string)($cfg['tiny_v2_url'] ?? cfg('tiny.v2_url')),'/'); $this->token=(string)($cfg['tiny_v2_token'] ?? ''); }

  private function post(string $endpoint, array $data): array {
    $trace = RequestContext::id();
    $data = array_merge(['token'=>$this->token,'formato'=>'json'], $data);
    $safeData = $data; if(isset($safeData['token'])) $safeData['token'] = '***mascarado***';
    if(!CircuitBreakerService::permitir('tiny_v2')) {
      return ['erro'=>'Circuit breaker Tiny V2 aberto. Aguarde nova tentativa automática.','codigo_erro'=>'TINY_V2_CIRCUIT_OPEN','trace_id'=>$trace];
    }
    if(!$this->token) {
      Audit::event('tiny.v2.request.bloqueado','erro',['codigo_erro'=>'TINY_TOKEN_MISSING','mensagem'=>'Token Tiny V2 não configurado','payload'=>['endpoint'=>$endpoint,'data'=>$safeData]]);
      return ['erro'=>'Token Tiny V2 não configurado','codigo_erro'=>'TINY_TOKEN_MISSING','trace_id'=>$trace];
    }
    try {
      $baseUrl = TinyEndpointSecurityService::validateUrl($this->url);
      $safeEndpoint = TinyEndpointSecurityService::sanitizePath($endpoint);
      $requestUrl = $baseUrl.'/'.$safeEndpoint;
      $securityOptions = TinyEndpointSecurityService::curlSecurityOptions($baseUrl);
    } catch (Throwable $e) {
      Audit::exception($e,'tiny.v2.url.bloqueada',['codigo_erro'=>'TINY_V2_URL_BLOCKED']);
      return ['erro'=>'URL Tiny V2 bloqueada pela política de segurança.','codigo_erro'=>'TINY_V2_URL_BLOCKED','trace_id'=>$trace];
    }
    // Auditoria final 2026-09-14 (achado G-02): $safeData mascara apenas a chave `token`. O corpo
    // enviado ao Tiny V2 vai em chaves como `pedido`, que carregam JSON com cpf_cnpj, endereço,
    // e-mail e telefone do cliente. SensitiveDataService::mask() entra também no JSON embutido.
    // TinyV3Service e VsmService já mascaravam aqui; o V2 era o único que não.
    Audit::event('tiny.v2.request','info',['mensagem'=>'Enviando requisição para Tiny V2','payload'=>['url'=>$requestUrl,'data'=>SensitiveDataService::mask($safeData),'trace_id'=>$trace]]);

    $attempts = RetryPolicyService::attempts();
    $last = null;
    for($attempt=1; $attempt <= $attempts; $attempt++){
      RetryPolicyService::sleep($attempt);
      $ch=curl_init($requestUrl);
      $capHeaders=[]; // T-02: observabilidade do limite (x-limit-api), sem throttle
      curl_setopt_array($ch,$securityOptions+[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($data),CURLOPT_HTTPHEADER=>IntegrationSecurityService::traceHeaders(),CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADERFUNCTION=>TinyRateLimitObserverService::headerCapture($capHeaders),CURLOPT_TIMEOUT=>30,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
      $started=microtime(true); $body=curl_exec($ch); $err=curl_error($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); $tempoMs=(int)round((microtime(true)-$started)*1000);
      $json=json_decode((string)$body,true);
      $last=['body'=>$body,'err'=>$err,'http'=>$http,'ms'=>$tempoMs,'json'=>$json,'attempt'=>$attempt,'headers'=>$capHeaders];
      if (!RetryPolicyService::shouldRetry($http, $err ?: null, $json) || $attempt === $attempts) break;
      Audit::event('tiny.v2.retry','alerta',['mensagem'=>'Retry Tiny V2 com backoff exponencial','contexto'=>['endpoint'=>$endpoint,'tentativa'=>$attempt,'http'=>$http,'erro'=>$err,'trace_id'=>$trace]]);
    }

    $body=$last['body']; $err=$last['err']; $http=(int)$last['http']; $tempoMs=(int)$last['ms']; $json=$last['json']; $attempt=(int)$last['attempt'];
    if($err) {
      MetricsService::registrar('tiny',$endpoint,$http,$tempoMs,false,'TINY_CURL_ERROR');
      self::logEndpoint($endpoint,'POST',$http,false,$tempoMs,$safeData,$body,$err);
      CircuitBreakerService::falha('tiny_v2',$err);
      Audit::event('tiny.v2.response','erro',['codigo_erro'=>'TINY_CURL_ERROR','mensagem'=>$err,'retorno'=>['http_code'=>$http,'body'=>SensitiveDataService::mask((string)$body),'tentativas'=>$attempt]]);
      return ['erro'=>$err,'codigo_erro'=>'TINY_CURL_ERROR','http_code'=>$http,'trace_id'=>$trace,'tentativas'=>$attempt];
    }
    if(!is_array($json)) {
      MetricsService::registrar('tiny',$endpoint,$http,$tempoMs,false,'TINY_INVALID_JSON');
      self::logEndpoint($endpoint,'POST',$http,false,$tempoMs,$safeData,$body,'TINY_INVALID_JSON');
      CircuitBreakerService::falha('tiny_v2','TINY_INVALID_JSON');
      Audit::event('tiny.v2.response','erro',['codigo_erro'=>'TINY_INVALID_JSON','mensagem'=>'Resposta inválida do Tiny','retorno'=>['http_code'=>$http,'body'=>SensitiveDataService::mask((string)$body),'tentativas'=>$attempt]]);
      return ['erro'=>'Resposta inválida do Tiny','codigo_erro'=>'TINY_INVALID_JSON','http_code'=>$http,'raw'=>substr(SensitiveDataService::maskJson((string)$body),0,2000),'trace_id'=>$trace,'tentativas'=>$attempt];
    }
    $tinyFalhou = (isset($json['retorno']['status_processamento']) && (int)$json['retorno']['status_processamento'] !== 3 && isset($json['retorno']['erros']));
    MetricsService::registrar('tiny',$endpoint,$http,$tempoMs,!$tinyFalhou,$tinyFalhou?'TINY_BUSINESS_ERROR':null);
    self::logEndpoint($endpoint,'POST',$http,!$tinyFalhou,$tempoMs,$safeData,$json,$tinyFalhou?'TINY_BUSINESS_ERROR':null);
    if($tinyFalhou) CircuitBreakerService::falha('tiny_v2','TINY_BUSINESS_ERROR'); else CircuitBreakerService::sucesso('tiny_v2');
    // Auditoria final 2026-09-14 (achado G-02): este era o único caminho do arquivo que gravava a
    // resposta do provedor sem máscara - os dois caminhos de erro acima já usavam
    // SensitiveDataService. pedido.obter.php e nota.fiscal.incluir.xml.php devolvem dado pessoal
    // do cliente e XML de NF-e, que iam em claro para auditoria_eventos.retorno.
    // T-02 (observabilidade, sem throttle): registra o teto por minuto que o Tiny informou no header.
    $limiteApi = TinyRateLimitObserverService::v2($last['headers'] ?? []);
    Audit::event('tiny.v2.response',$tinyFalhou?'erro':'sucesso',['mensagem'=>'Resposta recebida do Tiny V2','retorno'=>['http_code'=>$http,'json'=>SensitiveDataService::mask($json),'tentativas'=>$attempt,'limite_api'=>$limiteApi]]);
    $json['trace_id']=$trace; $json['tentativas']=$attempt;
    if($limiteApi!==null) $json['limite_api']=$limiteApi;
    return $json;
  }

  private static function logEndpoint(string $endpoint, string $metodo, int $http, bool $sucesso, int $tempoMs, array $request, $response, ?string $erro=null): void {
    try {
      $pdo=Database::forTable('tiny_v2_endpoint_logs');
      $st=$pdo->prepare("SHOW TABLES LIKE 'tiny_v2_endpoint_logs'"); $st->execute(); if(!$st->fetch()) return;
      // Auditoria final 2026-09-14 (achado G-01): estas duas colunas eram preenchidas com
      // SensitiveDataService::maskJson(), que declara `string $body`. Mas $request é `array` por
      // assinatura e $response chega como array no caminho de sucesso - array não é coercível
      // para string em PHP 8, então TODA chamada lançava TypeError, o catch abaixo engolia, e a
      // tabela tiny_v2_endpoint_logs nunca recebia um registro sequer. Consequência: a tela de
      // observabilidade do Tiny V2 ficava vazia para sempre e o painel de saúde mostrava
      // "sem chamada recente" mesmo sob tráfego real - indicador verde que mente.
      // sanitizeForStorage() é o método próprio para isto: aceita mixed, mascara pelas chaves
      // sensíveis, serializa e trunca com hash de integridade. Mesmo desenho já usado no
      // TinyV3Service::logEndpoint(), que nunca teve o defeito.
      $req  = $request ? SensitiveDataService::sanitizeForStorage($request) : null;
      $resp = ($response === null || $response === '') ? null : SensitiveDataService::sanitizeForStorage($response);
      $ins=$pdo->prepare('INSERT INTO tiny_v2_endpoint_logs(trace_id,endpoint,metodo,http_code,sucesso,tempo_ms,request_body,response_body,erro) VALUES(?,?,?,?,?,?,?,?,?)');
      $ins->execute([
        RequestContext::id(), $endpoint, $metodo, $http, $sucesso?1:0, $tempoMs,
        $req, $resp, $erro
      ]);
    } catch(Throwable $e) { if(class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e, ['provider'=>'tiny_v2']); }
  }

  public function criarPedido(array $pedido): array { return $this->post('pedido.incluir.php',['pedido'=>json_encode($pedido,JSON_UNESCAPED_UNICODE)]); }
  public function criarProduto(array $produto): array { return $this->post('produto.incluir.php',['produto'=>json_encode($produto,JSON_UNESCAPED_UNICODE)]); }
  public function atualizarProduto(array $produto): array { return $this->post('produto.alterar.php',['produto'=>json_encode($produto,JSON_UNESCAPED_UNICODE)]); }
  public function atualizarStatusProduto(string $sku, string $situacao): array {
    $consulta = $this->consultarProduto($sku);
    $info = class_exists('ProdutoTinyPreflightService') ? ProdutoTinyPreflightService::analisarRetornoPesquisa($consulta) : ['existe'=>false];
    $produto = ['codigo'=>$sku, 'situacao'=>$situacao];
    if (!empty($info['id'])) $produto['id'] = $info['id'];
    $payload = ['produtos' => [[ 'produto' => $produto ]]];
    return $this->atualizarProduto($payload);
  }
  public function consultarPedido(string $id): array { return $this->post('pedido.obter.php',['id'=>$id]); }
  public function consultarProduto(string $sku): array { return $this->post('produtos.pesquisa.php',['pesquisa'=>$sku]); }

  public function consultarEstoquePorSku(string $sku): array {
    $consulta = $this->consultarProduto($sku);
    $info = class_exists('ProdutoTinyPreflightService') ? ProdutoTinyPreflightService::analisarRetornoPesquisa($consulta) : [];
    $id = (string)($info['id'] ?? '');
    if ($id !== '') {
      $ret = $this->post('produto.obter.estoque.php', ['id'=>$id]);
      $ret['consulta_produto'] = $consulta;
      return $ret;
    }
    $consulta['aviso'] = 'Endpoint de estoque Tiny V2 exige ID do produto; usando retorno de produtos.pesquisa.php como fallback.';
    return $consulta;
  }
  public function atualizarEstoque(string $sku, float $qtd): array { return $this->post('produto.atualizar.estoque.php',['codigo'=>$sku,'estoque'=>$qtd]); }

  public function enviarNfeXmlPedido(string $pedidoId, array $dados): array {
    $xml = (string)($dados['xml'] ?? '');
    $retXml = $xml ? $this->post('nota.fiscal.incluir.xml.php', ['xml'=>$xml]) : ['erro'=>'XML ausente para envio ao Tiny V2.','codigo_erro'=>'TINY_V2_XML_MISSING','trace_id'=>RequestContext::id()];
    $retStatus = $this->post('pedido.alterar.situacao.php', ['id'=>$pedidoId, 'situacao'=>(string)($dados['status'] ?? 'faturado')]);
    return ['xml'=>$retXml,'status_pedido'=>$retStatus,'trace_id'=>RequestContext::id()];
  }
}
