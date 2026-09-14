<?php
class VsmService {
  private string $url; private string $token; private string $endpointBaixa; private string $endpointPedido;
  public function __construct(?array $cfg=null){
    $cfg=$cfg ?: IntegrationConfig::get();
    $this->url = rtrim((string)($cfg['vsm_url'] ?? cfg('vsm.url')), '/');
    if ($this->url !== '') {
      $this->url = VsmEndpointSecurityService::validateBaseUrl($this->url);
    }
    $this->token = (string)($cfg['vsm_token'] ?? '');
    $this->endpointBaixa = (string)($cfg['vsm_endpoint_baixa_estoque'] ?? '/api/estoque/baixa');
    $this->endpointPedido = (string)($cfg['vsm_endpoint_pedido'] ?? '/api/pedidos');
  }
  private function request(string $method, string $endpoint, array $payload=[], int $timeout=30, ?string $idempotencyKey=null): array {
    $trace = RequestContext::id();
    if(!$this->url) return ['erro'=>'URL da VSM não configurada','codigo_erro'=>'VSM_URL_MISSING','trace_id'=>$trace];
    if(!CircuitBreakerService::permitir('vsm')) return ['erro'=>'Circuit breaker VSM aberto. Aguarde nova tentativa automática.','codigo_erro'=>'VSM_CIRCUIT_OPEN','trace_id'=>$trace];
    $endpoint = VsmEndpointSecurityService::sanitizePath($endpoint);
    $method = strtoupper($method);
    if (!in_array($method, ['GET','POST','PUT','PATCH'], true)) $method = 'POST';
    $url = $this->url.'/'.ltrim($endpoint,'/');
    if ($method === 'GET' && $payload) $url .= (str_contains($url,'?')?'&':'?').http_build_query($payload);
    $rawBody = $method === 'GET' ? '' : json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $headers = ['Content-Type: application/json', 'X-Trace-ID: '.$trace];
    if($this->token) $headers[] = 'Authorization: Bearer '.$this->token;
    // P0-09 (reauditoria 2026-08-23): antes o retry de POST/PUT/PATCH em timeout/408/409/425/
    // 429/5xx era feito sem nenhuma chave de idempotência - se a mutação já tivesse sido
    // aplicada na VSM e só a resposta se perdeu, o retry duplicava pedido/baixa de estoque.
    // Quando o chamador informa um idempotencyKey estável (ex.: id da fila, que se mantém
    // igual entre reprocessamentos do mesmo job), ele é enviado em um header dedicado -
    // se a VSM já suportar/adotar deduplicação por esse header, o retry passa a ser seguro;
    // se a VSM ainda ignorar o header, o comportamento é idêntico ao atual (sem regressão).
    if ($idempotencyKey !== null && $idempotencyKey !== '' && in_array($method, ['POST','PUT','PATCH'], true)) {
      $headers[] = 'Idempotency-Key: '.hash('sha256', $idempotencyKey);
    }
    $headers = array_merge($headers, IntegrationSecurityService::vsmHmacHeaders((string)$rawBody));
    $safeHeaders = IntegrationSecurityService::maskHeaders($headers);
    Audit::event('vsm.request','info',['mensagem'=>'Enviando requisição para VSM','payload'=>['method'=>$method,'url'=>$url,'headers'=>$safeHeaders,'body'=>SensitiveDataService::mask($payload),'hmac_ativo'=>!empty(App::config()['security']['vsm_hmac_enabled'])]]);
    JsonLogger::write('vsm','info','Requisição VSM iniciada',['method'=>$method,'url'=>$url,'trace_id'=>$trace]);

    $attempts=RetryPolicyService::attempts();
    $last=null;
    for($attempt=1; $attempt <= $attempts; $attempt++){
      RetryPolicyService::sleep($attempt);
      $inicio = microtime(true);
      $ch=curl_init($url);
      $opts=[CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>max(5,min(120,$timeout)),CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS|CURLPROTO_HTTP,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS];
      $resolveEntry=VsmEndpointSecurityService::curlResolveEntry($this->url); if($resolveEntry!==null)$opts[CURLOPT_RESOLVE]=[$resolveEntry];
      if ($method !== 'GET') { $opts[CURLOPT_CUSTOMREQUEST]=$method; $opts[CURLOPT_POSTFIELDS]=$rawBody; }
      curl_setopt_array($ch,$opts);
      $body=curl_exec($ch); $err=curl_error($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
      $tempoMs=(int)((microtime(true)-$inicio)*1000);
      $json=json_decode((string)$body,true);
      $last=['body'=>$body,'err'=>$err,'http'=>$http,'ms'=>$tempoMs,'json'=>$json,'attempt'=>$attempt];
      if (!RetryPolicyService::shouldRetry($http, $err ?: null, $json) || $attempt === $attempts) break;
      Audit::event('vsm.retry','alerta',['mensagem'=>'Retry VSM com backoff exponencial','contexto'=>['endpoint'=>$endpoint,'tentativa'=>$attempt,'http'=>$http,'erro'=>$err,'trace_id'=>$trace]]);
    }

    $body=$last['body']; $err=$last['err']; $http=(int)$last['http']; $tempoMs=(int)$last['ms']; $json=$last['json']; $attempt=(int)$last['attempt'];
    if($err) {
      MetricsService::registrar('vsm',$url,$http,$tempoMs,false,'VSM_CURL_ERROR'); CircuitBreakerService::falha('vsm',$err); JsonLogger::write('vsm','erro','Erro cURL VSM',['erro'=>$err,'http'=>$http,'tempo_ms'=>$tempoMs,'tentativas'=>$attempt]);
      Audit::event('vsm.response','erro',['codigo_erro'=>'VSM_CURL_ERROR','mensagem'=>$err,'retorno'=>['http_code'=>$http,'tentativas'=>$attempt]]);
      return ['erro'=>$err,'codigo_erro'=>'VSM_CURL_ERROR','http_code'=>$http,'trace_id'=>$trace,'tempo_ms'=>$tempoMs,'tentativas'=>$attempt];
    }
    $ret = is_array($json) ? $json : ['raw'=>substr(SensitiveDataService::maskJson((string)$body),0,2000)];
    $ret['http_code']=$http; $ret['trace_id']=$trace; $ret['tempo_ms']=$tempoMs; $ret['tentativas']=$attempt;
    if($http < 200 || $http >= 300) $ret['codigo_erro']='VSM_HTTP_'.$http;
    MetricsService::registrar('vsm',$url,$http,$tempoMs, !isset($ret['codigo_erro']), $ret['codigo_erro'] ?? null);
    if(isset($ret['codigo_erro'])) CircuitBreakerService::falha('vsm', $ret['codigo_erro']); else CircuitBreakerService::sucesso('vsm');
    JsonLogger::write('vsm', isset($ret['codigo_erro'])?'erro':'info','Resposta VSM recebida',['http'=>$http,'tempo_ms'=>$tempoMs,'codigo_erro'=>$ret['codigo_erro'] ?? null,'tentativas'=>$attempt]);
    Audit::event('vsm.response', isset($ret['codigo_erro'])?'erro':'sucesso',['mensagem'=>'Resposta recebida da VSM','retorno'=>SensitiveDataService::mask($ret)]);
    return $ret;
  }
  private function post(string $endpoint, array $payload, ?string $idempotencyKey=null): array { return $this->request('POST', $endpoint, $payload, 30, $idempotencyKey); }
  public function enviarBaixaEstoque(array $baixa, ?string $idempotencyKey=null): array { return $this->post($this->endpointBaixa, $baixa, $idempotencyKey); }
  public function enviarPedido(array $pedido, ?string $idempotencyKey=null): array { return $this->post($this->endpointPedido, $pedido, $idempotencyKey); }

  public function consultarEstoque(string $sku): array {
    $cfg = EstoqueVsmSchedulerService::config();
    $endpoint = (string)($cfg['consulta_vsm_endpoint'] ?? ($cfg['vsm_endpoint_consulta_estoque'] ?? '/api/estoque/consulta'));
    $method = strtoupper((string)($cfg['consulta_vsm_metodo_http'] ?? 'POST'));
    $template = (string)($cfg['consulta_vsm_payload_template'] ?? '{"sku":"{{sku}}","trace_id":"{{trace_id}}"}');
    $json = str_replace(['{{sku}}','{{trace_id}}'], [$sku, RequestContext::id()], $template);
    $payload = json_decode($json, true);
    if (!is_array($payload)) $payload = ['sku'=>$sku, 'trace_id'=>RequestContext::id()];
    $timeout = (int)($cfg['consulta_vsm_timeout_segundos'] ?? 30);
    return $this->request($method, $endpoint, $payload, $timeout);
  }
}
