<?php
class HealthCheckService {
  public static function run(): array {
    $checks=[];
    $checks[] = self::check('PHP >= 8.0', version_compare(PHP_VERSION, '8.0.0', '>='), PHP_VERSION, 'Atualize o PHP do XAMPP se necessário.');
    $checks[] = self::check('Extensão PDO MySQL', extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql')?'OK':'Faltando', 'Ative pdo_mysql no php.ini.');
    $checks[] = self::check('Extensão cURL', extension_loaded('curl'), extension_loaded('curl')?'OK':'Faltando', 'Ative curl no php.ini.');
    $checks[] = self::check('Extensão OpenSSL', extension_loaded('openssl'), extension_loaded('openssl')?'OK':'Faltando', 'Ative openssl no php.ini para criptografar tokens.');
    $checks[] = self::check('Extensão ZipArchive', class_exists('ZipArchive'), class_exists('ZipArchive')?'OK':'Faltando', 'Ative zip no php.ini para gerar backup .zip.');
    foreach(['logs','backups'] as $p){ $dir=__DIR__.'/../../storage/'.$p; $checks[] = self::check('Pasta storage/'.$p.' gravável', is_dir($dir) && is_writable($dir), 'storage/'.$p, 'Crie a pasta e permita escrita.'); }
    try { $pdo=Database::connection('core'); $pdo->query('SELECT 1'); $checks[] = self::check('Conexão MySQL', true, 'OK', ''); }
    catch(Throwable $e){ $checks[] = self::check('Conexão MySQL', false, $e->getMessage(), 'Confira config/config.php e se o MySQL do XAMPP está ativo.'); }
    try {
      $cfg=App::config();
      $key=trim((string)($cfg['security']['encryption_key'] ?? ''));
      $unsafe=['','troque-esta-chave-apos-instalar','hub-vsm-tiny-local-key'];
      $checks[] = self::check('Chave de criptografia forte', !in_array($key,$unsafe,true) && strlen($key)>=32, strlen($key).' caracteres', 'Troque security.encryption_key no config/config.php por chave forte de 64+ caracteres.');
      $checks[] = self::check('HMAC obrigatório em produção', (bool)($cfg['security']['require_hmac_in_production'] ?? false), 'Configuração de segurança', 'Mantenha true em produção.');
    } catch(Throwable $e){ $checks[] = self::check('Configuração carregada', false, $e->getMessage(), 'Rode install.php novamente.'); }
    try { $icfg=IntegrationConfig::get(); $checks[] = self::check('Token Tiny V2 configurado', !empty($icfg['tiny_v2_token']), !empty($icfg['tiny_v2_token'])?'Configurado':'Vazio', 'Informe o token em Configurações.'); }
    catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    return $checks;
  }

  /**
   * P0-03 (reauditoria 2026-08-23): esta função tinha CURLOPT_FOLLOWLOCATION=true
   * (SSRF via redirect para host interno) e considerava "ok" qualquer HTTP < 500,
   * inclusive 404 - e o resultado alimenta VsmEnvironmentService::registerTestResult(),
   * ou seja, liberava produção com um falso positivo. Agora reusa o mesmo transporte
   * endurecido (SSRF/DNS pinning/allowlist de host, sem redirect) já usado em
   * VsmEndpointService::executeTest(), e só aceita 2xx/3xx-sem-seguir como sucesso real.
   */
  public static function testarVsm(array $cfg): array {
    $baseUrlBruta = rtrim((string)($cfg['vsm_url'] ?? ''), '/');
    $token = (string)($cfg['vsm_token'] ?? '');
    $endpointBruto = trim((string)($_POST['vsm_endpoint'] ?? '/swagger-ui/index.html'));
    if ($endpointBruto === '') $endpointBruto = '/swagger-ui/index.html';
    if ($baseUrlBruta === '') {
      $resultado = ['ok'=>false,'mensagem'=>'URL da VSM não configurada.','checks'=>[['nome'=>'Configuração VSM URL','ok'=>false,'detalhe'=>'Vazio','acao'=>'Informe a URL da VSM em Configurações.']]];
      if (class_exists('DiagnosticoApiService')) DiagnosticoApiService::registrar('vsm', null, 'erro', null, null, $resultado['mensagem'], $resultado);
      return $resultado;
    }
    $checks=[];
    try {
      $baseUrl = VsmEndpointSecurityService::validateBaseUrl($baseUrlBruta);
      $endpoint = VsmEndpointSecurityService::sanitizePath($endpointBruto);
    } catch (Throwable $e) {
      $checks[] = self::check('URL/endpoint VSM seguro', false, $e->getMessage(), 'Ajuste a URL/endpoint VSM e a allowlist security.vsm_allowed_hosts.');
      $resultado = ['ok'=>false,'mensagem'=>'Configuração VSM bloqueada por segurança: '.$e->getMessage(),'checks'=>$checks];
      if (class_exists('DiagnosticoApiService')) DiagnosticoApiService::registrar('vsm', null, 'erro', null, null, $resultado['mensagem'], $resultado);
      return $resultado;
    }
    $host = (string)(parse_url($baseUrl, PHP_URL_HOST) ?: $baseUrl);
    $dnsOk = true;
    try { VsmEndpointSecurityService::resolvePublicIps($host); }
    catch (Throwable $e) { $dnsOk = false; $checks[] = self::check('DNS VSM', false, $e->getMessage(), 'Confira internet, DNS ou o endereço da VSM.'); }
    if ($dnsOk) $checks[] = self::check('DNS VSM', true, $host.' resolvido para IP público válido', '');
    if (!extension_loaded('curl')) {
      $checks[] = self::check('cURL disponível', false, 'Extensão curl faltando', 'Ative extension=curl no php.ini do XAMPP.');
      return ['ok'=>false,'mensagem'=>'cURL não está disponível para testar VSM.','checks'=>$checks];
    }
    if (!$dnsOk) return ['ok'=>false,'mensagem'=>'DNS da VSM não pôde ser validado com segurança.','checks'=>$checks];
    $url = $baseUrl . $endpoint;
    $headers = ['Accept: application/json, text/html;q=0.9, */*;q=0.8', 'User-Agent: HubVsmTiny/1.0'];
    if ($token !== '') $headers[] = 'Authorization: Bearer '.$token;
    $start = microtime(true);
    $ch = curl_init($url);
    $curlOptions = [
      CURLOPT_RETURNTRANSFER => true,
      // Nunca seguir redirect: um alvo malicioso/comprometido poderia redirecionar
      // para um host interno (SSRF) que passaria despercebido no host original validado.
      CURLOPT_FOLLOWLOCATION => false,
      CURLOPT_MAXREDIRS => 0,
      CURLOPT_TIMEOUT => 20,
      CURLOPT_CONNECTTIMEOUT => 8,
      CURLOPT_HEADER => true,
      CURLOPT_NOBODY => false,
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_SSL_VERIFYHOST => 2,
      CURLOPT_PROTOCOLS => CURLPROTO_HTTPS|CURLPROTO_HTTP,
      CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
    ];
    $resolveEntry = VsmEndpointSecurityService::curlResolveEntry($baseUrl);
    if ($resolveEntry !== null) $curlOptions[CURLOPT_RESOLVE] = [$resolveEntry];
    curl_setopt_array($ch, $curlOptions);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $totalMs = (int)((microtime(true)-$start)*1000);
    $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $body = is_string($raw) ? substr($raw, $headerSize, 600) : '';
    curl_close($ch);
    $httpsOk = $errno === 0 && $http > 0;
    $authOk = !in_array($http, [401,403], true);
    // P0-03: só 2xx/3xx comprova de fato que o endpoint respondeu - 404/4xx/5xx não é sucesso.
    $statusOk = $httpsOk && $http >= 200 && $http < 400;
    $checks[] = self::check('HTTPS VSM', $httpsOk, $errno ? ($error ?: 'Erro cURL '.$errno) : 'HTTP '.$http.' em '.$totalMs.'ms', 'Verifique certificado SSL, firewall, internet do servidor ou URL.');
    $checks[] = self::check('Autenticação/Permissão', $authOk, $http ? 'HTTP '.$http : 'Sem HTTP code', 'Se retornar 401/403, confira VSM Token e permissões da API.');
    $checks[] = self::check('Endpoint testado (2xx/3xx obrigatório)', $statusOk, VsmEndpointSecurityService::redactUrlForLog($baseUrl, $endpoint), 'Confirme no Swagger se este endpoint existe no ambiente configurado. HTTP 404 e outros erros não contam mais como sucesso.');
    $okFinal = $statusOk && $authOk;
    $resultado = [
      'ok'=>$okFinal,
      'mensagem'=>$okFinal ? 'VSM respondeu ao teste de conexão.' : 'VSM não passou em todos os testes.',
      'url'=>VsmEndpointSecurityService::redactUrlForLog($baseUrl, $endpoint),
      'http_code'=>$http,
      'tempo_ms'=>$totalMs,
      'body_preview'=>$body,
      'checks'=>$checks
    ];
    if (class_exists('DiagnosticoApiService')) {
      DiagnosticoApiService::registrar('vsm', $resultado['url'], $okFinal ? 'online' : 'erro', $http ?: null, $totalMs, $resultado['mensagem'], $resultado);
    }
    return $resultado;
  }

  private static function check(string $nome, bool $ok, string $detalhe, string $acao): array { return compact('nome','ok','detalhe','acao'); }
}
