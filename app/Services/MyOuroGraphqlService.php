<?php
/** Consulta fixa derivada da seção 11 do relatório; nunca envia mutations ou atualiza o Tiny. */
class MyOuroGraphqlService {
  public const STOCK_QUERY = 'query EstoquePorProdutoLoja($produto: Int!, $loja: Int!) { estoque_produto(codigoProduto: $produto, codigoLoja: $loja) { codigoLoja codigoProduto quantidadeEstoque nomeProduto } }';

  public static function envelope(int $produto, int $loja): array {
    foreach ([$produto,$loja] as $id) if ($id < 1 || $id > 2147483647) throw new InvalidArgumentException('Código fora do intervalo GraphQL Int positivo.');
    return ['operationName'=>'EstoquePorProdutoLoja','query'=>self::STOCK_QUERY,'variables'=>['produto'=>$produto,'loja'=>$loja]];
  }

  public static function decode(int $http, string $body, int $produto, int $loja): array {
    if ($http === 401) return ['ok'=>false,'codigo'=>'MYOURO_TOKEN_INVALID'];
    if ($http === 403) return ['ok'=>false,'codigo'=>'MYOURO_FORBIDDEN'];
    if ($http < 200 || $http >= 300) return ['ok'=>false,'codigo'=>'MYOURO_HTTP_'.$http];
    try { $json = json_decode($body,true,64,JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { return ['ok'=>false,'codigo'=>'MYOURO_INVALID_JSON']; }
    if (!is_array($json)) return ['ok'=>false,'codigo'=>'MYOURO_INVALID_RESPONSE'];
    if (!empty($json['errors'])) return ['ok'=>false,'codigo'=>isset($json['data']) ? 'MYOURO_PARTIAL_RESPONSE' : 'MYOURO_GRAPHQL_ERRORS'];
    if (!isset($json['data']) || !is_array($json['data']) || !array_key_exists('estoque_produto',$json['data'])) return ['ok'=>false,'codigo'=>'MYOURO_INVALID_RESPONSE'];
    $row = $json['data']['estoque_produto'];
    if ($row === null) return ['ok'=>false,'codigo'=>'MYOURO_NOT_FOUND'];
    if (!is_array($row) || ($row['codigoLoja'] ?? null) !== $loja || ($row['codigoProduto'] ?? null) !== $produto || !isset($row['quantidadeEstoque']) || !is_numeric($row['quantidadeEstoque'])) return ['ok'=>false,'codigo'=>'MYOURO_SCOPE_OR_SCHEMA_MISMATCH'];
    return ['ok'=>true,'codigo'=>'MYOURO_OK','estoque'=>array_intersect_key($row,array_flip(['codigoLoja','codigoProduto','quantidadeEstoque','nomeProduto']))];
  }

  public function consultarEstoque(int $empresa, int $produto): array {
    if ($empresa !== IntegrationTenantService::boundEmpresaId()) throw new RuntimeException('TENANT_SCOPE_VIOLATION');
    $cfg = MyOuroConfigService::get($empresa);
    if (empty($cfg['habilitado'])) throw new RuntimeException('Habilite e salve a conexão MyOuro antes de consultar.');
    if (($cfg['url'] ?? '') !== MyOuroConfigService::URL) throw new RuntimeException('URL MyOuro divergente.');
    $token = (string)CryptoService::decrypt($cfg['token_encrypted'] ?? '', 'myouro:'.$empresa);
    if ($token === '' || preg_match('/[\s\x00-\x1F\x7F]/', $token)) throw new RuntimeException('Token MyOuro ausente ou inválido.');
    $envelope = self::envelope($produto, (int)$cfg['codigo_loja']);
    if (!function_exists('curl_init')) throw new RuntimeException('Extensão PHP cURL ausente.');
    // Reusa allowlist, validação de IP público e pinning do cliente VSM. Não segue redirects.
    $resolve = VsmEndpointSecurityService::curlResolveEntry($cfg['url']);
    $trace = RequestContext::id(); $start = microtime(true); $body = '';
    $ch = curl_init($cfg['url']);
    try {
      curl_setopt_array($ch, [CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($envelope,JSON_THROW_ON_ERROR),
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json','Authorization: Bearer '.$token,'X-Trace-ID: '.$trace],
        CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,
        CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_PROXY=>'',
        CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_RETURNTRANSFER=>false,
        CURLOPT_WRITEFUNCTION=>static function($curl,string $chunk) use (&$body): int {
          if (strlen($body)+strlen($chunk)>1048576) return 0;
          $body .= $chunk; return strlen($chunk);
        }]);
      if ($resolve !== null) curl_setopt($ch,CURLOPT_RESOLVE,[$resolve]);
      $sent = curl_exec($ch); $http = (int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
      $result = $sent === false ? ['ok'=>false,'codigo'=>'MYOURO_TRANSPORT_ERROR'] : self::decode($http,$body,$produto,(int)$cfg['codigo_loja']);
    } finally { curl_close($ch); }
    $result['trace_id']=$trace; $result['tempo_ms']=(int)((microtime(true)-$start)*1000);
    // Resultados antigos não validam uma configuração alterada durante a consulta.
    $st=Database::forTable('myouro_conexoes')->prepare('UPDATE myouro_conexoes SET ultimo_teste_ok=?,ultimo_teste_em=NOW(),ultimo_teste_codigo=? WHERE empresa_id=? AND token_encrypted=? AND codigo_loja=? AND url=?');
    $st->execute([(int)$result['ok'],$result['codigo'],$empresa,$cfg['token_encrypted'],$cfg['codigo_loja'],$cfg['url']]);
    Audit::event('myouro.consulta_estoque',$result['ok']?'sucesso':'erro',['mensagem'=>'Consulta MyOuro concluída','contexto'=>['empresa_id'=>$empresa,'operacao'=>'EstoquePorProdutoLoja','trace_id'=>$trace,'codigo'=>$result['codigo'],'tempo_ms'=>$result['tempo_ms']]]);
    return $result;
  }
}
