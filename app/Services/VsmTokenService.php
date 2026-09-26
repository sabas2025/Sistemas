<?php
/**
 * F6-07 etapa 2 (2026-09-26) — token de autenticação da VSM Conecta Venda.
 *
 * Contrato (contracts/vsm/pedidos-integradora.openapi.json):
 *   POST /v1/auth/token  body application/json {clientToken, clientSecret}
 *     -> 201 {accessToken, tokenType, expiration, expiresIn (segundos, ~7200)}
 *   securityScheme accessToken = http bearer JWT.
 *
 * Diferença para o Tiny V3: a VSM NÃO usa refresh_token. Quando o JWT expira, o Hub simplesmente
 * troca de novo clientToken+clientSecret. O JWT é cacheado em configuracoes_integracao
 * (vsm_access_token cifrado + vsm_access_token_expira_em) para não bater no /v1/auth/token a cada
 * chamada.
 *
 * SSRF: a URL de auth é derivada de vsm_url e validada por VsmEndpointSecurityService, igual ao
 * VsmService (allowlist de host/porta, recusa de IP privado, IP fixado no request).
 */
class VsmTokenService {
  /** Margem de segurança: renova o JWT 60s antes de vencer. */
  private const SKEW_SECONDS = 60;

  /** Devolve um JWT válido, renovando quando necessário. String vazia = não foi possível obter. */
  public static function accessToken(): string {
    $cfg = IntegrationConfig::get();
    $jwt = (string)($cfg['vsm_access_token'] ?? '');
    if ($jwt !== '' && !self::isExpired($cfg['vsm_access_token_expira_em'] ?? null)) {
      return $jwt;
    }
    $res = self::refresh(false);
    if (!empty($res['erro'])) return '';
    $cfg = IntegrationConfig::get();
    return (string)($cfg['vsm_access_token'] ?? '');
  }

  public static function isExpired($expiraEm): bool {
    $expiraEm = trim((string)$expiraEm);
    if ($expiraEm === '') return true; // sem cache = precisa emitir
    $ts = strtotime($expiraEm);
    if ($ts === false) return true;
    return $ts <= (time() + self::SKEW_SECONDS);
  }

  /** Configuração de credenciais + base, ou uma lista de pendências. */
  public static function status(): array {
    $cfg = IntegrationConfig::get();
    return [
      'base_configurada'          => trim((string)($cfg['vsm_url'] ?? '')) !== '',
      'client_token_configurado'  => trim((string)($cfg['vsm_client_token'] ?? '')) !== '',
      'client_secret_configurado' => trim((string)($cfg['vsm_client_secret'] ?? '')) !== '',
      'client_token_loja_configurado' => trim((string)($cfg['vsm_client_token_loja'] ?? '')) !== '',
      'tem_token_cache'           => trim((string)($cfg['vsm_access_token'] ?? '')) !== '',
      'expira_em'                 => $cfg['vsm_access_token_expira_em'] ?? null,
      'expirado'                  => self::isExpired($cfg['vsm_access_token_expira_em'] ?? null),
    ];
  }

  /**
   * Troca clientToken+clientSecret por um JWT em POST /v1/auth/token e cacheia o resultado.
   * $force=false reaproveita um token válido emitido por outro processo enquanto esperava o lock.
   * @return array{sucesso?:bool,erro?:string,codigo_erro?:string,http_code?:int,expira_em?:string}
   */
  public static function refresh(bool $force=false): array {
    $trace = class_exists('RequestContext') ? RequestContext::id() : '';
    $cfg = IntegrationConfig::get();
    $base = rtrim(trim((string)($cfg['vsm_url'] ?? '')), '/');
    $clientToken = trim((string)($cfg['vsm_client_token'] ?? ''));
    $clientSecret = trim((string)($cfg['vsm_client_secret'] ?? ''));
    if ($base === '') return ['erro'=>'URL da VSM não configurada.','codigo_erro'=>'VSM_URL_MISSING','trace_id'=>$trace];
    if ($clientToken === '' || $clientSecret === '') {
      return ['erro'=>'Credenciais VSM (clientToken/clientSecret) não configuradas.','codigo_erro'=>'VSM_CREDENTIALS_MISSING','trace_id'=>$trace];
    }
    try { $base = VsmEndpointSecurityService::validateBaseUrl($base); }
    catch (Throwable $e) { return ['erro'=>'URL da VSM bloqueada pela política de segurança.','codigo_erro'=>'VSM_URL_BLOCKED','trace_id'=>$trace]; }

    $pdo = Database::forTable('configuracoes_integracao');
    $lock = self::acquireLock($pdo);
    try {
      // Se outro processo já renovou enquanto esperávamos o lock, reaproveita.
      if (!$force) {
        $fresh = IntegrationConfig::get();
        if (trim((string)($fresh['vsm_access_token'] ?? '')) !== '' && !self::isExpired($fresh['vsm_access_token_expira_em'] ?? null)) {
          return ['sucesso'=>true,'renovado_agora'=>false,'origem'=>'lock_peer','trace_id'=>$trace];
        }
      }

      $endpoint = VsmEndpointSecurityService::sanitizePath('/v1/auth/token');
      $url = $base.'/'.ltrim($endpoint,'/');
      $rawBody = json_encode(['clientToken'=>$clientToken,'clientSecret'=>$clientSecret], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
      $headers = ['Content-Type: application/json','Accept: application/json','X-Trace-ID: '.$trace];

      $attempts = RetryPolicyService::attempts();
      $last = null;
      for ($attempt=1; $attempt <= $attempts; $attempt++) {
        RetryPolicyService::sleep($attempt);
        $inicio = microtime(true);
        $ch = curl_init($url);
        $opts = [CURLOPT_HTTPHEADER=>$headers,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$rawBody,CURLOPT_RETURNTRANSFER=>true,
                 CURLOPT_TIMEOUT=>30,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
                 CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS|CURLPROTO_HTTP,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS];
        $resolve = VsmEndpointSecurityService::curlResolveEntry($base); if ($resolve !== null) $opts[CURLOPT_RESOLVE] = [$resolve];
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch); $err = curl_error($ch); $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $ms = (int)((microtime(true)-$inicio)*1000);
        $json = json_decode((string)$body, true);
        $last = ['body'=>$body,'err'=>$err,'http'=>$http,'ms'=>$ms,'json'=>$json];
        // 401/403 (credencial inválida) e 404/422 (loja/crédito) NÃO são transitórios: não retenta.
        if (!($err !== '' || $http === 429 || $http >= 500) || $attempt === $attempts) break;
      }

      $http = (int)$last['http']; $json = $last['json']; $err = (string)$last['err'];
      if (class_exists('MetricsService')) MetricsService::registrar('vsm_auth', $url, $http, (int)$last['ms'], empty($err) && $http>=200 && $http<300, $err ?: null);
      if ($err !== '') {
        Audit::event('vsm.auth.erro','erro',['codigo_erro'=>'VSM_AUTH_CURL_ERROR','mensagem'=>$err,'retorno'=>['http_code'=>$http]]);
        return ['erro'=>$err,'codigo_erro'=>'VSM_AUTH_CURL_ERROR','http_code'=>$http,'trace_id'=>$trace];
      }
      if ($http < 200 || $http >= 300 || !is_array($json) || empty($json['accessToken'])) {
        Audit::event('vsm.auth.erro','erro',['codigo_erro'=>'VSM_AUTH_HTTP_'.$http,'mensagem'=>'VSM recusou a emissão de token.','retorno'=>SensitiveDataService::mask(['http_code'=>$http,'body'=>is_array($json)?$json:substr((string)$last['body'],0,500)])]);
        return ['erro'=>'VSM recusou a emissão de token de autenticação.','codigo_erro'=>'VSM_AUTH_HTTP_'.$http,'http_code'=>$http,'trace_id'=>$trace];
      }

      $jwt = (string)$json['accessToken'];
      $expiresIn = (int)($json['expiresIn'] ?? 0);
      if ($expiresIn <= 0) $expiresIn = 3600; // piso defensivo quando a VSM não manda expiresIn
      $expiraEm = date('Y-m-d H:i:s', time() + max(60, $expiresIn));
      self::store($pdo, $jwt, $expiraEm);
      Audit::event('vsm.auth.sucesso','sucesso',['mensagem'=>'Token VSM emitido via /v1/auth/token.','retorno'=>['expira_em'=>$expiraEm,'expires_in'=>$expiresIn,'http_code'=>$http]]);
      return ['sucesso'=>true,'renovado_agora'=>true,'http_code'=>$http,'expira_em'=>$expiraEm,'trace_id'=>$trace];
    } finally {
      self::releaseLock($pdo, $lock);
    }
  }

  private static function store(PDO $pdo, string $jwt, string $expiraEm): void {
    $sets = []; $values = [];
    if (Database::columnExists('configuracoes_integracao','vsm_access_token')) { $sets[]='vsm_access_token=?'; $values[]=CryptoService::encrypt($jwt); }
    if (Database::columnExists('configuracoes_integracao','vsm_access_token_expira_em')) { $sets[]='vsm_access_token_expira_em=?'; $values[]=$expiraEm; }
    if ($sets === []) return;
    $pdo->prepare('UPDATE configuracoes_integracao SET '.implode(',', $sets).' WHERE id=1')->execute($values);
  }

  /** Lock cooperativo por GET_LOCK; se indisponível, segue sem lock (emitir token duplicado é inócuo). */
  private static function acquireLock(PDO $pdo): array {
    $name = 'hub_vsm_auth_'.substr(hash('sha256', (string)(App::config()['db']['name'] ?? 'hub')), 0, 40);
    try {
      $st = $pdo->prepare('SELECT GET_LOCK(?, ?)'); $st->execute([$name, 8]);
      return ['acquired'=>((int)$st->fetchColumn() === 1),'name'=>$name];
    } catch (Throwable $e) { return ['acquired'=>false,'name'=>$name]; }
  }

  private static function releaseLock(PDO $pdo, array $lock): void {
    if (empty($lock['acquired'])) return;
    try { $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([(string)$lock['name']]); }
    catch (Throwable $e) { if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
  }
}
