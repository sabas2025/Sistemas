<?php
class VsmService {
  // Duas APIs VSM ativas, sem principal/secundária:
  //  - $url          = API de GRAVAÇÃO (vsm_url): enviarPedido() e enviarBaixaEstoque().
  //  - $urlConsulta  = API de CONSULTA (vsm_url_consulta): consultarEstoque().
  // Quando vsm_url_consulta está vazia, a consulta reaproveita a base de gravação
  // (retrocompatível: instalação que não separa as duas continua idêntica ao anterior).
  private string $url; private string $urlConsulta; private string $token; private string $endpointBaixa; private string $endpointPedido;
  private string $clientTokenLoja=''; private string $clientTokenIntegradora='';
  public function __construct(?array $cfg=null){
    $cfg=$cfg ?: IntegrationConfig::get();
    $this->url = rtrim((string)($cfg['vsm_url'] ?? cfg('vsm.url')), '/');
    if ($this->url !== '') {
      $this->url = VsmEndpointSecurityService::validateBaseUrl($this->url);
    }
    $this->urlConsulta = self::baseConsulta($this->url, (string)($cfg['vsm_url_consulta'] ?? ''));
    if ($this->urlConsulta !== '' && $this->urlConsulta !== $this->url) {
      $this->urlConsulta = VsmEndpointSecurityService::validateBaseUrl($this->urlConsulta);
    }
    $this->token = (string)($cfg['vsm_token'] ?? '');
    // F6-07: credenciais reais da VSM Conecta Venda. clientTokenIntegradora É o clientToken da
    // integradora (mesmo valor do /v1/auth/token); clientTokenLoja é da loja. Vão na QUERY do pedido.
    $this->clientTokenIntegradora = (string)($cfg['vsm_client_token'] ?? '');
    $this->clientTokenLoja = (string)($cfg['vsm_client_token_loja'] ?? '');
    $this->endpointBaixa = (string)($cfg['vsm_endpoint_baixa_estoque'] ?? '/api/estoque/baixa');
    // Endpoint real de cadastro de pedido (contrato pedidos-integradora). Configurável; default correto.
    $this->endpointPedido = (string)($cfg['vsm_endpoint_pedido'] ?? '/v1/pedido/integradora');
  }

  /**
   * Bearer da requisição: quando as credenciais VSM estão configuradas, usa o JWT emitido por
   * VsmTokenService (troca /v1/auth/token); senão cai no vsm_token legado (Bearer estático).
   */
  private function resolveBearer(): string {
    if ($this->clientTokenIntegradora !== '' && class_exists('VsmTokenService')) {
      $jwt = VsmTokenService::accessToken();
      if ($jwt !== '') return $jwt;
    }
    return $this->token;
  }

  /** Resolve a base da API de CONSULTA: usa vsm_url_consulta se informada, senão cai na de gravação. Puro. */
  public static function baseConsulta(string $urlGravacao, string $urlConsultaRaw): string {
    $c = rtrim(trim($urlConsultaRaw), '/');
    return $c !== '' ? $c : rtrim(trim($urlGravacao), '/');
  }
  private function request(string $method, string $endpoint, array $payload=[], int $timeout=30, ?string $idempotencyKey=null, ?string $baseUrl=null, array $query=[]): array {
    $trace = RequestContext::id();
    // Base da operação: gravação usa $this->url (default); consulta injeta $this->urlConsulta.
    $base = ($baseUrl !== null && $baseUrl !== '') ? $baseUrl : $this->url;
    if(!$base) return ['erro'=>'URL da VSM não configurada','codigo_erro'=>'VSM_URL_MISSING','trace_id'=>$trace];
    if(!CircuitBreakerService::permitir('vsm')) return ['erro'=>'Circuit breaker VSM aberto. Aguarde nova tentativa automática.','codigo_erro'=>'VSM_CIRCUIT_OPEN','trace_id'=>$trace];
    $endpoint = VsmEndpointSecurityService::sanitizePath($endpoint);
    $method = strtoupper($method);
    if (!in_array($method, ['GET','POST','PUT','PATCH'], true)) $method = 'POST';
    $url = $base.'/'.ltrim($endpoint,'/');
    // F6-07: parâmetros de query (ex.: clientTokenLoja/clientTokenIntegradora do pedido) —
    // codificados com http_build_query para não depender do sanitizePath do endpoint.
    if ($query) $url .= (str_contains($url,'?')?'&':'?').http_build_query($query);
    if ($method === 'GET' && $payload) $url .= (str_contains($url,'?')?'&':'?').http_build_query($payload);
    // URL para LOG/MÉTRICA com os VALORES da query mascarados: os clientToken viajam na query e
    // não podem ser gravados em claro na trilha nem em metricas_api (achado F6-07 g).
    $urlLog = VsmEndpointSecurityService::redactUrlForLog($base, substr($url, strlen($base)));
    $rawBody = $method === 'GET' ? '' : json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $headers = ['Content-Type: application/json', 'X-Trace-ID: '.$trace];
    $bearer = $this->resolveBearer();
    if($bearer !== '') $headers[] = 'Authorization: Bearer '.$bearer;
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
    Audit::event('vsm.request','info',['mensagem'=>'Enviando requisição para VSM','payload'=>['method'=>$method,'url'=>$urlLog,'headers'=>$safeHeaders,'body'=>SensitiveDataService::mask($payload),'hmac_ativo'=>!empty(App::config()['security']['vsm_hmac_enabled'])]]);
    JsonLogger::write('vsm','info','Requisição VSM iniciada',['method'=>$method,'url'=>$urlLog,'trace_id'=>$trace]);

    $attempts=RetryPolicyService::attempts();
    $last=null;
    for($attempt=1; $attempt <= $attempts; $attempt++){
      RetryPolicyService::sleep($attempt);
      $inicio = microtime(true);
      $ch=curl_init($url);
      $opts=[CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>max(5,min(120,$timeout)),CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS|CURLPROTO_HTTP,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS];
      $resolveEntry=VsmEndpointSecurityService::curlResolveEntry($base); if($resolveEntry!==null)$opts[CURLOPT_RESOLVE]=[$resolveEntry];
      if ($method !== 'GET') { $opts[CURLOPT_CUSTOMREQUEST]=$method; $opts[CURLOPT_POSTFIELDS]=$rawBody; }
      curl_setopt_array($ch,$opts);
      $body=curl_exec($ch); $err=curl_error($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
      $tempoMs=(int)((microtime(true)-$inicio)*1000);
      $json=json_decode((string)$body,true);
      $last=['body'=>$body,'err'=>$err,'http'=>$http,'ms'=>$tempoMs,'json'=>$json,'attempt'=>$attempt];
      // F6-07(e): na VSM, 409 é FALHA DE VALIDAÇÃO PERMANENTE ("Quantidade dos itens deve ser
      // maior que 0", etc.), não um conflito transitório — retentar só atrasa a ida para a DLQ.
      // O 409 genérico de RetryPolicyService (compartilhado com o Tiny) é excluído aqui, na VSM.
      $deveRetentar = RetryPolicyService::shouldRetry($http, $err ?: null, $json) && $http !== 409;
      if (!$deveRetentar || $attempt === $attempts) break;
      Audit::event('vsm.retry','alerta',['mensagem'=>'Retry VSM com backoff exponencial','contexto'=>['endpoint'=>$endpoint,'tentativa'=>$attempt,'http'=>$http,'erro'=>$err,'trace_id'=>$trace]]);
    }

    $body=$last['body']; $err=$last['err']; $http=(int)$last['http']; $tempoMs=(int)$last['ms']; $json=$last['json']; $attempt=(int)$last['attempt'];
    if($err) {
      MetricsService::registrar('vsm',$urlLog,$http,$tempoMs,false,'VSM_CURL_ERROR'); CircuitBreakerService::falha('vsm',$err); JsonLogger::write('vsm','erro','Erro cURL VSM',['erro'=>$err,'http'=>$http,'tempo_ms'=>$tempoMs,'tentativas'=>$attempt]);
      Audit::event('vsm.response','erro',['codigo_erro'=>'VSM_CURL_ERROR','mensagem'=>$err,'retorno'=>['http_code'=>$http,'tentativas'=>$attempt]]);
      return ['erro'=>$err,'codigo_erro'=>'VSM_CURL_ERROR','http_code'=>$http,'trace_id'=>$trace,'tempo_ms'=>$tempoMs,'tentativas'=>$attempt];
    }
    $ret = is_array($json) ? $json : ['raw'=>substr(SensitiveDataService::maskJson((string)$body),0,2000)];
    $ret['http_code']=$http; $ret['trace_id']=$trace; $ret['tempo_ms']=$tempoMs; $ret['tentativas']=$attempt;
    if($http < 200 || $http >= 300) $ret['codigo_erro']='VSM_HTTP_'.$http;
    MetricsService::registrar('vsm',$urlLog,$http,$tempoMs, !isset($ret['codigo_erro']), $ret['codigo_erro'] ?? null);
    if(isset($ret['codigo_erro'])) CircuitBreakerService::falha('vsm', $ret['codigo_erro']); else CircuitBreakerService::sucesso('vsm');
    JsonLogger::write('vsm', isset($ret['codigo_erro'])?'erro':'info','Resposta VSM recebida',['http'=>$http,'tempo_ms'=>$tempoMs,'codigo_erro'=>$ret['codigo_erro'] ?? null,'tentativas'=>$attempt]);
    Audit::event('vsm.response', isset($ret['codigo_erro'])?'erro':'sucesso',['mensagem'=>'Resposta recebida da VSM','retorno'=>SensitiveDataService::mask($ret)]);
    return $ret;
  }
  private function post(string $endpoint, array $payload, ?string $idempotencyKey=null, array $query=[]): array { return $this->request('POST', $endpoint, $payload, 30, $idempotencyKey, null, $query); }
  public function enviarBaixaEstoque(array $baixa, ?string $idempotencyKey=null): array { return $this->post($this->endpointBaixa, $baixa, $idempotencyKey); }

  /**
   * Cadastra o pedido em /v1/pedido/integradora. O contrato exige clientTokenLoja E
   * clientTokenIntegradora na QUERY (além do Bearer JWT). Sem credenciais configuradas, a query
   * fica vazia e o comportamento é o legado (a chamada tende a 401 na VSM real, como antes).
   */
  public function enviarPedido(array $pedido, ?string $idempotencyKey=null): array {
    $query = [];
    if ($this->clientTokenLoja !== '')         $query['clientTokenLoja'] = $this->clientTokenLoja;
    if ($this->clientTokenIntegradora !== '')  $query['clientTokenIntegradora'] = $this->clientTokenIntegradora;
    return $this->post($this->endpointPedido, $pedido, $idempotencyKey, $query);
  }

  public function consultarEstoque(string $sku): array {
    $cfg = EstoqueVsmSchedulerService::config();
    $endpoint = (string)($cfg['consulta_vsm_endpoint'] ?? ($cfg['vsm_endpoint_consulta_estoque'] ?? '/api/estoque/consulta'));
    $method = strtoupper((string)($cfg['consulta_vsm_metodo_http'] ?? 'POST'));
    $template = (string)($cfg['consulta_vsm_payload_template'] ?? '{"sku":"{{sku}}","trace_id":"{{trace_id}}"}');
    $json = str_replace(['{{sku}}','{{trace_id}}'], [$sku, RequestContext::id()], $template);
    $payload = json_decode($json, true);
    if (!is_array($payload)) $payload = ['sku'=>$sku, 'trace_id'=>RequestContext::id()];
    $timeout = (int)($cfg['consulta_vsm_timeout_segundos'] ?? 30);
    // Consulta vai para a API de CONSULTA (vsm_url_consulta); vazia → base de gravação.
    return $this->request($method, $endpoint, $payload, $timeout, null, $this->urlConsulta);
  }
}
