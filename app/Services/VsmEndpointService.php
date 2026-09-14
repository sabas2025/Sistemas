<?php
class VsmEndpointService {
  public static function ensureSchema(): array {
    SchemaRuntimePolicyService::requireTables(['vsm_endpoints','vsm_campos_mapeamento','vsm_endpoint_logs'], 'catálogo de endpoints VSM');
    SchemaRuntimePolicyService::requireColumns('vsm_endpoints', ['modo_teste_seguro','origem','contract_verified','contract_source','contract_checked_at','is_template'], 'catálogo de endpoints VSM');
    SchemaRuntimePolicyService::requireColumns('vsm_campos_mapeamento', ['tipo_dado','valor_padrao','regra_validacao','exemplo_payload','origem','contract_verified','contract_source','is_template'], 'mapeamento VSM');
    SchemaRuntimePolicyService::requireColumns('vsm_endpoint_logs', ['modo_teste_seguro'], 'logs VSM');
    return ['Schema VSM validado sem DDL runtime.'];
  }

  /**
   * P0-05 (reauditoria 2026-08-23): antes este método cacheava IntegrationConfig::get()
   * (que devolve tokens/segredos JÁ DESCRIPTOGRAFADOS) via SimpleCache, que grava em
   * storage/cache/*.cache.php - ou seja, segredos em texto claro em disco. Agora o
   * cache é só em memória do processo (nunca vai para disco) e mantém o mesmo TTL de
   * 60s para não penalizar workers de longa duração que chamam isso em loop.
   */
  private static ?array $baseConfigCache = null;
  private static int $baseConfigCacheExpiresAt = 0;

  public static function baseConfig(): array {
    if (self::$baseConfigCache === null || time() >= self::$baseConfigCacheExpiresAt) {
      self::$baseConfigCache = IntegrationConfig::get();
      self::$baseConfigCacheExpiresAt = time() + 60;
    }
    return self::$baseConfigCache;
  }

  /** Força releitura do banco na próxima chamada (ex.: após salvar configurações). */
  public static function invalidateBaseConfigCache(): void {
    self::$baseConfigCache = null;
    self::$baseConfigCacheExpiresAt = 0;
  }

  public static function listEndpoints(?string $categoria=null): array {
    self::ensureSchema();
    $cacheKey='vsm_endpoints_'.($categoria ?: 'all');
    return SimpleCache::remember($cacheKey, 30, function() use ($categoria){
      $pdo=Database::connection('core'); $sql='SELECT * FROM vsm_endpoints'; $params=[];
      if($categoria){ $sql.=' WHERE categoria=?'; $params[]=$categoria; }
      $sql.=' ORDER BY categoria ASC, ordem_execucao ASC, nome ASC';
      $st=$pdo->prepare($sql); $st->execute($params); $rows=$st->fetchAll();
      foreach($rows as &$row)$row['contract_source']=self::safeContractSourceForDisplay((string)($row['contract_source']??''));
      unset($row); return $rows;
    });
  }

  public static function listCampos(?string $categoria=null): array {
    self::ensureSchema();
    $cacheKey='vsm_campos_'.($categoria ?: 'all');
    return SimpleCache::remember($cacheKey, 30, function() use ($categoria){
      $pdo=Database::connection('core'); $sql='SELECT * FROM vsm_campos_mapeamento'; $params=[];
      if($categoria){ $sql.=' WHERE categoria=?'; $params[]=$categoria; }
      $sql.=' ORDER BY categoria ASC, obrigatorio DESC, campo_vsm ASC';
      $st=$pdo->prepare($sql); $st->execute($params); $rows=$st->fetchAll();
      foreach($rows as &$row)$row['contract_source']=self::safeContractSourceForDisplay((string)($row['contract_source']??''));
      unset($row); return $rows;
    });
  }

  public static function validateEndpointPath(string $endpoint): string {
    $endpoint=trim($endpoint);
    if($endpoint==='') throw new RuntimeException('Endpoint VSM é obrigatório.');
    if(!str_starts_with($endpoint,'/')) throw new RuntimeException('Endpoint deve ser caminho relativo e começar com /. Exemplo: /pedidos/{id}');
    if(preg_match('#https?://#i',$endpoint) || preg_match('#(^|/)(localhost|127\.0\.0\.1|0\.0\.0\.0|::1)(/|$)#i',$endpoint)) throw new RuntimeException('Endpoint não pode conter URL completa, localhost ou IP interno. Use apenas caminho relativo da VSM.');
    if(str_contains($endpoint,'..') || str_contains($endpoint,'\\')) throw new RuntimeException('Endpoint contém trecho inseguro.');
    if(!preg_match('#^/[a-zA-Z0-9_./{}\-?=&%]+$#',$endpoint)) throw new RuntimeException('Endpoint contém caracteres inválidos.');
    $semPlaceholders=preg_replace('/\{[a-zA-Z][a-zA-Z0-9_]*\}/','',$endpoint);
    if($semPlaceholders===null || str_contains($semPlaceholders,'{') || str_contains($semPlaceholders,'}')) throw new RuntimeException('Placeholder inválido. Use o formato {id}, {sku} ou outro nome iniciado por letra.');
    return $endpoint;
  }

  public static function validateMethod(string $method): string {
    $method=strtoupper(trim($method));
    $allowed=['GET','POST','PUT','PATCH','DELETE'];
    if(!in_array($method,$allowed,true)) throw new RuntimeException('Método HTTP inválido.');
    return $method;
  }

  /** @return string[] */
  public static function placeholders(string $endpoint): array {
    preg_match_all('/\{([a-zA-Z][a-zA-Z0-9_]*)\}/', $endpoint, $matches);
    return array_values(array_unique($matches[1] ?? []));
  }

  private static function contractMetadata(array $p, ?array $endpointDefinition=null): array {
    if(empty($p['confirmar_contrato'])) return ['manual_pending',0,null];
    $source=trim((string)($p['contract_source'] ?? ''));
    if($source==='') throw new RuntimeException('Para confirmar o contrato, informe uma fonte auditável: URL HTTPS, SHA-256, referência OpenAPI ou contrato.');
    if(strlen($source)>255) throw new RuntimeException('A fonte do contrato deve ter no máximo 255 caracteres.');
    $isHttps=str_starts_with(strtolower($source),'https://') && filter_var($source,FILTER_VALIDATE_URL)!==false;
    $isSha=(bool)preg_match('/^sha256:[a-f0-9]{64}$/i',$source);
    $isReference=(bool)preg_match('/^(openapi|contrato):[a-zA-Z0-9][a-zA-Z0-9._\/-]{2,199}$/',$source);
    if(!$isHttps && !$isSha && !$isReference) throw new RuntimeException('Fonte de contrato inválida. Use https://..., sha256:<64 hex>, openapi:arquivo.json ou contrato:<referência>.');
    if($isHttps)$source=self::normalizeHttpsContractSource($source);
    if(str_starts_with(strtolower($source),'openapi:')){
      $reference=substr($source,8);
      if(!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{1,119}\.json$/',$reference)) throw new RuntimeException('Referência OpenAPI local inválida. Use openapi:arquivo.json, sem diretórios.');
      $validation=VsmOpenApiContractService::validateFile(__DIR__.'/../../contracts/vsm/'.$reference);
      if(empty($validation['ok'])) throw new RuntimeException('Contrato OpenAPI inválido: '.implode('; ',$validation['errors'] ?? []));
      if($endpointDefinition!==null){
        $comparison=VsmOpenApiContractService::compareCatalog($validation,[$endpointDefinition]);
        if(empty($comparison['ok'])) throw new RuntimeException('Método e caminho não existem no contrato OpenAPI informado.');
      }
    }
    return ['manual_confirmed',1,$source];
  }

  private static function normalizeHttpsContractSource(string $source): string {
    $parts=parse_url($source);
    if(!is_array($parts)||strtolower((string)($parts['scheme']??''))!=='https'||empty($parts['host'])||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment']))throw new RuntimeException('URL de contrato deve usar HTTPS e não pode conter credenciais, query ou fragmento.');
    if(preg_match('/[\x00-\x1F\x7F]/',$source))throw new RuntimeException('URL de contrato contém caracteres de controle.');
    $path=rawurldecode((string)($parts['path']??''));
    if(str_contains($path,'..')||preg_match('/[\x00-\x1F\x7F]/',$path))throw new RuntimeException('URL de contrato contém caminho inseguro.');
    $port=isset($parts['port'])?':'.(int)$parts['port']:'';
    $host=strtolower((string)$parts['host']);
    if(str_contains($host,':'))$host='['.$host.']';
    if(preg_match('#^/contract-ref-sha256-([a-f0-9]{64})$#i',(string)($parts['path']??''),$already))return 'https://'.$host.$port.'/contract-ref-sha256-'.strtolower($already[1]);
    return 'https://'.$host.$port.'/contract-ref-sha256-'.hash('sha256',$source);
  }

  public static function safeContractSourceForDisplay(string $source): ?string {
    $source=trim($source);
    if($source==='')return null;
    if(preg_match('/^(?:sha256:[a-f0-9]{64}|(?:openapi|contrato):[a-zA-Z0-9][a-zA-Z0-9._\/-]{2,199})$/i',$source))return $source;
    if(str_starts_with(strtolower($source),'https://')){
      try{return self::normalizeHttpsContractSource($source);}catch(Throwable $ignored){
        $host=(string)(parse_url($source,PHP_URL_HOST)??'referencia-redigida');
        $host=preg_match('/^[a-zA-Z0-9.-]+$/',$host)?strtolower($host):'referencia-redigida';
        return 'https://'.$host.'/contract-ref-sha256-'.hash('sha256',$source);
      }
    }
    return 'sha256:'.hash('sha256',$source);
  }

  private static function definitionHash(array $definition): string {
    ksort($definition);
    return hash('sha256',(string)json_encode($definition,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
  }

  public static function saveEndpoint(array $p): int {
    self::ensureSchema();
    $pdo=Database::connection('core');
    $id=(int)($p['id'] ?? 0);
    $method=self::validateMethod((string)($p['metodo_http'] ?? 'GET'));
    $path=VsmEndpointSecurityService::sanitizePath(self::validateEndpointPath((string)($p['endpoint'] ?? '')));
    $definition=['chave'=>trim((string)($p['chave'] ?? '')),'metodo_http'=>$method,'endpoint'=>$path];
    [$origem,$contractVerified,$contractSource]=self::contractMetadata($p,$definition);
    $dados=[
      trim((string)($p['chave'] ?? '')),
      trim((string)($p['nome'] ?? '')),
      trim((string)($p['categoria'] ?? 'geral')),
      $method,
      $path,
      $contractVerified && !empty($p['ativo'])?1:0,
      max(1,min(120,(int)($p['timeout_segundos'] ?? 30))),
      max(0,min(10,(int)($p['retry_maximo'] ?? 3))),
      (int)($p['ordem_execucao'] ?? 0),
      !empty($p['modo_teste_seguro'])?1:0,
      trim((string)($p['descricao'] ?? '')) ?: null,
    ];
    if($dados[0]==='' || $dados[1]==='') throw new RuntimeException('Preencha chave e nome.');
    if($id>0){
      $pdo->prepare("UPDATE vsm_endpoints SET chave=?, nome=?, categoria=?, metodo_http=?, endpoint=?, ativo=?, timeout_segundos=?, retry_maximo=?, ordem_execucao=?, modo_teste_seguro=?, descricao=?, origem=?, contract_verified=?, contract_source=?, contract_checked_at=IF(?=1,NOW(),NULL), is_template=0, updated_at=NOW() WHERE id=?")->execute([...$dados,$origem,$contractVerified,$contractSource,$contractVerified,$id]);
      if($contractVerified) Audit::event('vsm.contrato.endpoint.confirmado','sucesso',['entidade'=>'vsm_endpoints','entidade_id'=>$id,'mensagem'=>'Definição VSM reconfirmada explicitamente.','contexto'=>['definition_sha256'=>self::definitionHash($definition),'contract_source'=>$contractSource,'user_id'=>RequestContext::userId()]]);
      SimpleCache::forget('vsm_'); return $id;
    }
    $pdo->prepare("INSERT INTO vsm_endpoints(chave,nome,categoria,metodo_http,endpoint,ativo,timeout_segundos,retry_maximo,ordem_execucao,modo_teste_seguro,descricao,origem,contract_verified,contract_source,contract_checked_at,is_template) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,IF(?=1,NOW(),NULL),0)")->execute([...$dados,$origem,$contractVerified,$contractSource,$contractVerified]);
    $newId=(int)$pdo->lastInsertId();
    if($contractVerified) Audit::event('vsm.contrato.endpoint.confirmado','sucesso',['entidade'=>'vsm_endpoints','entidade_id'=>$newId,'mensagem'=>'Definição VSM confirmada explicitamente.','contexto'=>['definition_sha256'=>self::definitionHash($definition),'contract_source'=>$contractSource,'user_id'=>RequestContext::userId()]]);
    SimpleCache::forget('vsm_'); return $newId;
  }

  public static function saveCampo(array $p): int {
    self::ensureSchema();
    $pdo=Database::connection('core');
    $id=(int)($p['id'] ?? 0);
    [$origem,$contractVerified,$contractSource]=self::contractMetadata($p);
    $dados=[
      trim((string)($p['categoria'] ?? 'produto')), trim((string)($p['campo_vsm'] ?? '')), trim((string)($p['campo_hub'] ?? '')),
      trim((string)($p['tipo_dado'] ?? '')) ?: null, trim((string)($p['valor_padrao'] ?? '')) ?: null, trim((string)($p['regra_validacao'] ?? '')) ?: null,
      trim((string)($p['transformacao'] ?? '')) ?: null, trim((string)($p['exemplo_payload'] ?? '')) ?: null,
      !empty($p['obrigatorio'])?1:0, $contractVerified && !empty($p['ativo'])?1:0, trim((string)($p['observacao'] ?? '')) ?: null
    ];
    if($dados[1]==='' || $dados[2]==='') throw new RuntimeException('Preencha campo VSM e campo Hub.');
    if(!preg_match('/^[a-zA-Z0-9_.\[\]-]+$/',$dados[1]) || !preg_match('/^[a-zA-Z0-9_.\[\]-]+$/',$dados[2])) throw new RuntimeException('Campos devem usar letras, números, ponto, underline, hífen ou colchetes.');
    $definition=['categoria'=>$dados[0],'campo_vsm'=>$dados[1],'campo_hub'=>$dados[2],'tipo_dado'=>$dados[3],'regra_validacao'=>$dados[5],'transformacao'=>$dados[6]];
    if($id>0){ $pdo->prepare("UPDATE vsm_campos_mapeamento SET categoria=?, campo_vsm=?, campo_hub=?, tipo_dado=?, valor_padrao=?, regra_validacao=?, transformacao=?, exemplo_payload=?, obrigatorio=?, ativo=?, observacao=?, origem=?, contract_verified=?, contract_source=?, is_template=0, updated_at=NOW() WHERE id=?")->execute([...$dados,$origem,$contractVerified,$contractSource,$id]); if($contractVerified) Audit::event('vsm.contrato.campo.confirmado','sucesso',['entidade'=>'vsm_campos_mapeamento','entidade_id'=>$id,'mensagem'=>'Mapeamento VSM reconfirmado explicitamente.','contexto'=>['definition_sha256'=>self::definitionHash($definition),'contract_source'=>$contractSource,'user_id'=>RequestContext::userId()]]); SimpleCache::forget('vsm_'); return $id; }
    $pdo->prepare("INSERT INTO vsm_campos_mapeamento(categoria,campo_vsm,campo_hub,tipo_dado,valor_padrao,regra_validacao,transformacao,exemplo_payload,obrigatorio,ativo,observacao,origem,contract_verified,contract_source,is_template) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,0)")->execute([...$dados,$origem,$contractVerified,$contractSource]);
    $newId=(int)$pdo->lastInsertId();
    if($contractVerified) Audit::event('vsm.contrato.campo.confirmado','sucesso',['entidade'=>'vsm_campos_mapeamento','entidade_id'=>$newId,'mensagem'=>'Mapeamento VSM confirmado explicitamente.','contexto'=>['definition_sha256'=>self::definitionHash($definition),'contract_source'=>$contractSource,'user_id'=>RequestContext::userId()]]);
    SimpleCache::forget('vsm_'); return $newId;
  }

  public static function buildUrl(string $endpoint, array $params=[]): string {
    $endpoint=VsmEndpointSecurityService::sanitizePath(self::validateEndpointPath($endpoint));
    $cfg=self::baseConfig();
    $base=VsmEndpointSecurityService::validateBaseUrl((string)($cfg['vsm_url'] ?? 'https://conectavenda.homolog.vsm.com.br'));
    $missing=[];
    $endpoint=preg_replace_callback('/\{([a-zA-Z][a-zA-Z0-9_]*)\}/',static function(array $match) use ($params,&$missing): string {
      $key=$match[1];
      if(!array_key_exists($key,$params) || !is_scalar($params[$key]) || trim((string)$params[$key])==='') { $missing[]=$key; return $match[0]; }
      return rawurlencode((string)$params[$key]);
    },$endpoint);
    if($endpoint===null) throw new RuntimeException('Falha ao processar placeholders do endpoint VSM.');
    if($missing!==[]) throw new RuntimeException('Preencha os parâmetros obrigatórios do endpoint: '.implode(', ',array_values(array_unique($missing))).'.');
    return $base.'/'.ltrim($endpoint,'/');
  }

  public static function testEndpoint(int $id, array $params=[], bool $modoSeguro=true): array {
    self::ensureSchema();
    $pdo=Database::connection('core');
    $st=$pdo->prepare('SELECT * FROM vsm_endpoints WHERE id=?'); $st->execute([$id]); $ep=$st->fetch();
    if(!$ep) throw new RuntimeException('Endpoint VSM não encontrado.');
    $ep['contract_source']=self::safeContractSourceForDisplay((string)($ep['contract_source']??''));
    if(empty($ep['contract_verified'])) throw new RuntimeException(!empty($ep['is_template']) ? 'Endpoint é apenas um modelo local não verificado. Importe e valide o OpenAPI oficial antes de testar.' : 'Endpoint manual pendente de validação. Informe uma fonte auditável e confirme o contrato antes de testar.');
    // A superfície administrativa comum é exclusivamente de sonda segura.
    return self::executeTest($ep, $params, true);
  }

  private static function executeTest(array $ep, array $params=[], bool $modoSeguro=true): array {
    $url=self::buildUrl($ep['endpoint'], $params); $cfg=self::baseConfig();
    $token=(string)($cfg['vsm_token'] ?? ''); $headers=['Accept: application/json']; if($token!=='') $headers[]='Authorization: Bearer '.$token;
    $timeout=max(1,(int)($ep['timeout_segundos'] ?? 15)); $method=self::validateMethod((string)($ep['metodo_http'] ?? 'GET'));
    $start=microtime(true); $body=''; $status=null; $error=null; $diagnosticCode=null; $contentType=null; $ok=false; $sendMethod=$method; $probeSubstituted=false;
    try{
      $payload=null;
      if($modoSeguro && in_array($method,['POST','PUT','PATCH','DELETE'],true)) { $sendMethod='GET'; $probeSubstituted=true; $url .= (str_contains($url,'?')?'&':'?').'hub_safe_test=1&trace_id='.rawurlencode(RequestContext::id()); }
      elseif(in_array($method,['POST','PUT','PATCH'],true)) { $payload=json_encode(['hub_teste'=>true,'trace_id'=>RequestContext::id()],JSON_UNESCAPED_UNICODE); $headers[]='Content-Type: application/json'; }
      if(!function_exists('curl_init')) throw new RuntimeException('Extensão cURL é obrigatória para testes VSM seguros com fixação de DNS.');
      $ch=curl_init($url);
      $curlOptions=[
        CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$sendMethod,CURLOPT_HTTPHEADER=>$headers,
        CURLOPT_TIMEOUT=>$timeout,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
        CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS|CURLPROTO_HTTP,
        CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS,
      ];
      $resolveEntry=VsmEndpointSecurityService::curlResolveEntry((string)(self::baseConfig()['vsm_url'] ?? ''));
      if($resolveEntry!==null)$curlOptions[CURLOPT_RESOLVE]=[$resolveEntry];
      curl_setopt_array($ch,$curlOptions);
      if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,$payload);
      $result=curl_exec($ch); $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $contentType=(string)(curl_getinfo($ch,CURLINFO_CONTENT_TYPE)?:''); $curlCode=curl_errno($ch); $err=curl_error($ch); curl_close($ch);
      if($result===false && $err==='')$err='Falha cURL sem mensagem.';
      $body=(string)$result; if($err){$error=$err;$diagnosticCode='CURL_'.$curlCode;}
      $ok = !$err && $status !== null && $status >= 200 && $status < 400;
    }catch(Throwable $e){ $error=$e->getMessage(); $diagnosticCode='EXCEPTION_'.strtoupper((new ReflectionClass($e))->getShortName()); }
    $ms=(int)round((microtime(true)-$start)*1000);
    $contentType=mb_substr((string)(preg_replace('/[^a-zA-Z0-9.+;=\-\/ ]/','',$contentType)??''),0,120);
    $responseMetadata=['body_bytes'=>strlen($body),'body_sha256'=>$body!==''?hash('sha256',$body):null,'content_type'=>$contentType!==''?$contentType:null,'diagnostic_code'=>$diagnosticCode];
    $preview=(string)json_encode($responseMetadata,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $safeError=$error!==null?($diagnosticCode??'VSM_PROBE_ERROR'):null;
    $logUrl=VsmEndpointSecurityService::redactUrlForLog((string)($cfg['vsm_url']??''),(string)$ep['endpoint']);
    $healthError=$probeSubstituted ? ($safeError ?: 'Sonda GET segura executada; não comprova o método '.$method.'.') : $safeError;
    $healthStatus=$probeSubstituted ? null : $status;
    Database::connection('core')->prepare('UPDATE vsm_endpoints SET ultimo_status_http=?, ultimo_tempo_ms=?, ultimo_erro=?, ultima_resposta=?, ultima_execucao_em=NOW() WHERE id=?')->execute([$healthStatus,$ms,$healthError,$preview,(int)$ep['id']]);
    Database::connection('observabilidade')->prepare('INSERT INTO vsm_endpoint_logs(endpoint_id,chave,metodo_http,url,status_http,tempo_ms,sucesso,erro,resposta,modo_teste_seguro,trace_id) VALUES(?,?,?,?,?,?,?,?,?,?,?)')->execute([(int)$ep['id'],$ep['chave'],$sendMethod,$logUrl,$status,$ms,$ok?1:0,$safeError,$preview,$modoSeguro?1:0,RequestContext::id()]);
    Audit::event('vsm.endpoint.teste',$ok?'sucesso':'erro',['entidade'=>'vsm_endpoints','entidade_id'=>(int)$ep['id'],'mensagem'=>'Sonda segura de endpoint VSM executada.','contexto'=>['url'=>$logUrl,'http'=>$status,'ms'=>$ms,'erro'=>$safeError,'modo_seguro'=>$modoSeguro,'metodo_solicitado'=>$method,'metodo_executado'=>$sendMethod,'prova_metodo_original'=>!$probeSubstituted]]);
    SimpleCache::forget('vsm_');
    return ['ok'=>$ok,'status'=>$status,'tempo_ms'=>$ms,'erro'=>$safeError,'resposta'=>$preview,'url'=>$logUrl,'endpoint'=>$ep,'modo_seguro'=>$modoSeguro,'metodo_solicitado'=>$method,'metodo_executado'=>$sendMethod,'probe_substituido'=>$probeSubstituted];
  }

  /**
   * P2 (reauditoria 2026-08-23): 3xx (redirect) contava como "online" junto com 2xx -
   * um endpoint que responde com redirect não foi de fato alcançado/confirmado no destino
   * final (o teste seguro não segue redirect, por desenho anti-SSRF). Agora só 2xx é
   * "online"; 3xx vira um status próprio para não ser lido como sucesso nem como erro puro.
   */
  public static function health(): array {
    $eps=self::listEndpoints(); $rows=[];
    foreach($eps as $ep){
      $status='nao_testado';
      if(empty($ep['contract_verified'])) $status='nao_verificado';
      elseif(empty($ep['ativo'])) $status='inativo';
      elseif($ep['ultimo_status_http']){
        $http=(int)$ep['ultimo_status_http'];
        $status = ($http>=200 && $http<300) ? 'online' : (($http>=300 && $http<400) ? 'redirecionado' : 'erro');
      }
      $rows[]=$ep+['health_status'=>$status];
    }
    return $rows;
  }

  public static function logs(int $limit=100): array {
    self::ensureSchema(); $st=Database::connection('observabilidade')->prepare('SELECT * FROM vsm_endpoint_logs ORDER BY id DESC LIMIT '.max(1,min(500,$limit))); $st->execute(); $rows=$st->fetchAll();
    foreach($rows as &$row){
      $stored=(string)($row['resposta']??''); $metadata=json_decode($stored,true);
      if(!is_array($metadata)||!array_key_exists('body_bytes',$metadata)||!array_key_exists('body_sha256',$metadata)){
        $row['resposta']=(string)json_encode(['legacy_body_hidden'=>true,'body_bytes'=>strlen($stored),'body_sha256'=>$stored!==''?hash('sha256',$stored):null,'content_type'=>null,'diagnostic_code'=>'LEGACY_RESPONSE_REDACTED'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
      }
      $error=(string)($row['erro']??'');
      if($error!==''&&!preg_match('/^(?:CURL_\d+|EXCEPTION_[A-Z0-9_]+|VSM_PROBE_ERROR)$/',$error))$row['erro']='LEGACY_ERROR_SHA256_'.hash('sha256',$error);
    }
    unset($row); return $rows;
  }
}
