<?php
class TinyV3TokenService {
  public static function getTokenRow(?string $ambiente=null): ?array {
    $pdo = Database::forTable('tiny_v3_tokens');
    try {
      $amb = $ambiente ?: (IntegrationConfig::get()['tiny_v3_ambiente'] ?? 'homologacao');
      $st = $pdo->prepare("SELECT * FROM tiny_v3_tokens WHERE ambiente=? ORDER BY id DESC LIMIT 1");
      $st->execute([$amb]);
      $row = $st->fetch();
      if ($row) return $row;
      // Compatibilidade com bancos antigos sem ambiente preenchido.
      $row = $pdo->query("SELECT * FROM tiny_v3_tokens ORDER BY id DESC LIMIT 1")->fetch();
      return $row ?: null;
    } catch(Throwable $e){
      try { $row = $pdo->query("SELECT * FROM tiny_v3_tokens ORDER BY id DESC LIMIT 1")->fetch(); return $row ?: null; }
      catch(Throwable $e2){ return null; }
    }
  }

  public static function accessToken(): string {
    $cfg = IntegrationConfig::get();
    $ambienteV3 = $cfg['tiny_v3_ambiente'] ?? 'homologacao';
    $row = self::getTokenRow($ambienteV3);

    // V103: o Token Vault passa a ser a fonte principal do segredo em runtime.
    try {
      $vaultToken = TokenVaultService::getActive('tiny_v3', $ambienteV3, 'access_token');
      if ($vaultToken !== '') return $vaultToken;
    } catch (Throwable $e) {
      Audit::event('tiny.v3.token.vault_indisponivel','alerta',[
        'mensagem'=>'Token Vault indisponível; usando fallback criptografado tiny_v3_tokens.',
        'codigo_erro'=>'TOKEN_VAULT_UNAVAILABLE',
        'contexto'=>['erro'=>$e->getMessage()]
      ]);
    }

    if ($row && !empty($row['access_token'])) {
      if (self::isExpired($row)) {
        $refresh = self::refresh(false, $ambienteV3);
        Audit::event('tiny.v3.token.refresh_automatico', empty($refresh['erro']) ? 'sucesso' : 'erro', [
          'mensagem' => empty($refresh['erro']) ? 'Token Tiny V3 renovado automaticamente.' : 'Falha ao renovar token Tiny V3 automaticamente.',
          'retorno' => SensitiveDataService::mask($refresh),
          'codigo_erro' => $refresh['codigo_erro'] ?? null,
          'acao_recomendada' => empty($refresh['erro']) ? 'Continuar operação.' : 'Verificar refresh token, client_id, client_secret e token URL da Tiny V3.'
        ]);
        if (empty($refresh['erro'])) {
          $row = self::getTokenRow($ambienteV3);
        } else {
          return '';
        }
      }
      $token = $row ? CryptoService::decrypt($row['access_token']) : '';
      if ($token) {
        try { TokenVaultService::store('tiny_v3', $ambienteV3, 'access_token', $token, $row['expires_at'] ?? null); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
      }
      return $token ?: '';
    }

    // Produção: OAuth é obrigatório. Não usa token manual silenciosamente.
    if (($cfg['ambiente'] ?? 'homologacao') === 'producao' || $ambienteV3 === 'producao') {
      Audit::event('tiny.v3.token.oauth_obrigatorio','erro',[
        'mensagem'=>'Tiny V3 em produção exige OAuth salvo na tabela tiny_v3_tokens para o ambiente de produção.',
        'codigo_erro'=>'TINY_V3_OAUTH_REQUIRED',
        'acao_recomendada'=>'Conectar Tiny V3 via OAuth/refresh token de produção antes de ativar Tiny V3 operacional.'
      ]);
      return '';
    }

    // Homologação: permite token manual somente para laboratório controlado.
    $manual = (string)($cfg['tiny_v3_token'] ?? '');
    if ($manual !== '') {
      Audit::event('tiny.v3.token.manual_usado','alerta',[
        'mensagem'=>'Token manual Tiny V3 usado em homologação. Para produção, OAuth é obrigatório.',
        'codigo_erro'=>'TINY_V3_MANUAL_TOKEN_HOMOLOGATION_ONLY',
        'acao_recomendada'=>'Salvar access/refresh token OAuth por ambiente na tabela tiny_v3_tokens antes de produção.'
      ]);
    }
    return $manual;
  }

  public static function isExpired(?array $row=null): bool {
    $row = $row ?: self::getTokenRow();
    if (!$row || empty($row['expires_at'])) return false;
    return strtotime($row['expires_at']) <= (time()+60);
  }

  public static function saveManual(string $accessToken, ?string $refreshToken=null, int $expiresIn=3600, ?string $scope=null, ?string $ambiente=null): void {
    self::saveToken($accessToken, $refreshToken, $expiresIn, $scope, 'manual', $ambiente);
  }

  public static function saveOAuthToken(string $accessToken, ?string $refreshToken=null, int $expiresIn=3600, ?string $scope=null, ?string $ambiente=null): void {
    self::saveToken($accessToken, $refreshToken, $expiresIn, $scope, 'oauth', $ambiente);
  }

  private static function saveToken(string $accessToken, ?string $refreshToken, int $expiresIn, ?string $scope, string $origem, ?string $ambiente=null): void {
    if (trim($accessToken)==='') throw new InvalidArgumentException('Access token Tiny V3 vazio.');
    $cfg = IntegrationConfig::get();
    $amb = $ambiente ?: ($cfg['tiny_v3_ambiente'] ?? 'homologacao');
    if (!in_array($amb, ['homologacao','producao'], true)) $amb = 'homologacao';
    $pdo=Database::forTable('tiny_v3_tokens');
    $expiresAt=date('Y-m-d H:i:s', time()+max(60,$expiresIn));
    try {
      $pdo->prepare("INSERT INTO tiny_v3_tokens(ambiente,access_token,refresh_token,expires_at,scope,origem,criado_em,atualizado_em) VALUES(?,?,?,?,?,?,NOW(),NOW())")
        ->execute([$amb, CryptoService::encrypt($accessToken), CryptoService::encrypt((string)$refreshToken), $expiresAt, $scope, $origem]);
    } catch (Throwable $e) {
      // Compatibilidade com bancos antigos: permite salvar token e orienta atualização V20.
      $pdo->prepare("INSERT INTO tiny_v3_tokens(access_token,refresh_token,expires_at,scope,origem,criado_em,atualizado_em) VALUES(?,?,?,?,?,NOW(),NOW())")
        ->execute([CryptoService::encrypt($accessToken), CryptoService::encrypt((string)$refreshToken), $expiresAt, $scope, $origem]);
      Audit::event('tiny.v3.token.salvar.banco_antigo','alerta',[
        'mensagem'=>'Token Tiny V3 salvo em banco antigo sem coluna ambiente. Execute Atualização V20 para separar homologação/produção.',
        'codigo_erro'=>'TINY_V3_TOKEN_ENV_COLUMN_MISSING',
        'acao_recomendada'=>'Acessar Ficha Tiny V3 e clicar em Aplicar estrutura V20.'
      ]);
    }
    try {
      TokenVaultService::store('tiny_v3', $amb, 'access_token', $accessToken, $expiresAt);
      if ((string)$refreshToken !== '') TokenVaultService::store('tiny_v3', $amb, 'refresh_token', (string)$refreshToken, null);
    } catch (Throwable $e) {
      Audit::event('tiny.v3.token.vault_aviso','alerta',['mensagem'=>'Token salvo na tabela Tiny V3, mas o vault interno não foi atualizado.','codigo_erro'=>'TOKEN_VAULT_WARNING','contexto'=>['erro'=>$e->getMessage()]]);
    }
    Audit::event('tiny.v3.token.salvar','sucesso',[
      'mensagem'=>'Token Tiny V3 salvo/atualizado com criptografia AES-256-GCM e espelhado no vault interno quando disponível.',
      'contexto'=>['ambiente'=>$amb,'origem'=>$origem,'expires_at'=>$expiresAt]
    ]);
  }

  private static function refreshLockName(string $ambiente): string {
    $cfg = class_exists('App') ? App::config() : [];
    $instance = trim((string)($cfg['installation_id'] ?? ''));
    if ($instance === '') {
      $db = (string)($cfg['db']['name'] ?? 'hub');
      $base = (string)($cfg['base_url'] ?? '');
      $instance = hash('sha256', $db.'|'.$base.'|'.realpath(__DIR__.'/../..'));
    }
    // GET_LOCK aceita até 64 caracteres. O hash evita colisão entre instalações no mesmo MySQL.
    return 'hub_tiny_v3_'.substr(hash('sha256', $instance.'|'.$ambiente), 0, 48);
  }

  /**
   * @return array{acquired:bool,driver:string,name:string,handle?:resource,degraded?:bool,error?:string}
   */
  private static function acquireRefreshLock(PDO $pdo, string $ambiente, int $timeoutSeconds=8): array {
    $name = self::refreshLockName($ambiente);
    $timeout = max(0, min(30, $timeoutSeconds));
    try {
      $st = $pdo->prepare('SELECT GET_LOCK(?, ?)');
      $st->execute([$name, $timeout]);
      $value = $st->fetchColumn();
      if ((int)$value === 1) return ['acquired'=>true,'driver'=>'mysql','name'=>$name];
      if ((string)$value === '0' || $value === 0) return ['acquired'=>false,'driver'=>'mysql','name'=>$name,'error'=>'busy'];
      throw new RuntimeException('GET_LOCK retornou valor indisponível.');
    } catch (Throwable $mysqlError) {
      // Fallback local para hospedagens que bloqueiam GET_LOCK. Em múltiplos nós, o alerta registra modo degradado.
      $dir = __DIR__.'/../../storage/locks';
      if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
        return ['acquired'=>false,'driver'=>'none','name'=>$name,'error'=>'lock_storage_unavailable'];
      }
      $path = $dir.'/'.preg_replace('/[^a-z0-9_-]/i', '_', $name).'.lock';
      $handle = @fopen($path, 'c+');
      if (!is_resource($handle)) return ['acquired'=>false,'driver'=>'none','name'=>$name,'error'=>'lock_file_unavailable'];
      $deadline = microtime(true) + $timeout;
      do {
        if (@flock($handle, LOCK_EX | LOCK_NB)) {
          @ftruncate($handle, 0);
          @fwrite($handle, json_encode(['pid'=>getmypid(),'ambiente'=>$ambiente,'at'=>date('c')], JSON_UNESCAPED_SLASHES));
          try {
            if (class_exists('JsonLogger')) JsonLogger::write('tiny','warning','Refresh OAuth usando lock local degradado',[
              'ambiente'=>$ambiente,'trace_id'=>class_exists('RequestContext')?RequestContext::id():null,
              'mysql_error'=>class_exists('SensitiveDataService')?SensitiveDataService::maskJson($mysqlError->getMessage()):'GET_LOCK indisponível'
            ]);
          } catch(Throwable $ignored){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $ignored); }
          return ['acquired'=>true,'driver'=>'file','name'=>$name,'handle'=>$handle,'degraded'=>true];
        }
        usleep(100000);
      } while (microtime(true) < $deadline);
      @fclose($handle);
      return ['acquired'=>false,'driver'=>'file','name'=>$name,'error'=>'busy'];
    }
  }

  private static function releaseRefreshLock(PDO $pdo, array $lock, string $ambiente): void {
    if (empty($lock['acquired'])) return;
    try {
      if (($lock['driver'] ?? '') === 'mysql') {
        $st = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $st->execute([(string)$lock['name']]);
      } elseif (($lock['driver'] ?? '') === 'file' && isset($lock['handle']) && is_resource($lock['handle'])) {
        @flock($lock['handle'], LOCK_UN);
        @fclose($lock['handle']);
      }
    } catch (Throwable $e) {
      try {
        if (class_exists('JsonLogger')) JsonLogger::write('tiny','warning','Falha ao liberar lock de refresh OAuth',[
          'ambiente'=>$ambiente,
          'driver'=>$lock['driver'] ?? 'desconhecido',
          'erro'=>class_exists('SensitiveDataService')?SensitiveDataService::maskJson($e->getMessage()):'falha ao liberar lock',
          'trace_id'=>class_exists('RequestContext')?RequestContext::id():null
        ]);
      } catch(Throwable $ignored){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $ignored); }
    }
  }

  /**
   * Renova o OAuth Tiny V3.
   * $force=false: uso automático; após aguardar o lock, reutiliza token já renovado por outro processo.
   * $force=true: uso manual; sempre chama o endpoint OAuth e nunca informa renovação sem executá-la.
   */
  public static function refresh(bool $force=false, ?string $ambiente=null): array {
    $cfg = IntegrationConfig::get();
    $amb = $ambiente ?: ($cfg['tiny_v3_ambiente'] ?? 'homologacao');
    if (!in_array($amb, ['homologacao','producao'], true)) $amb = 'homologacao';
    $pdo = Database::forTable('tiny_v3_tokens');
    $lock = self::acquireRefreshLock($pdo, $amb);
    if (empty($lock['acquired'])) {
      return [
        'erro'=>'Renovação OAuth Tiny V3 já está em andamento ou o mecanismo de lock está indisponível.',
        'codigo_erro'=>($lock['error'] ?? '') === 'busy' ? 'TINY_V3_REFRESH_LOCK_BUSY' : 'TINY_V3_REFRESH_LOCK_UNAVAILABLE',
        'retryable'=>true,
        'ambiente'=>$amb
      ];
    }
    try {
      $latest = self::getTokenRow($amb);
      if (!$force && $latest && !self::isExpired($latest)) {
        return ['sucesso'=>true,'mensagem'=>'Token Tiny V3 já foi renovado por outro processo.','ambiente'=>$amb,'origem'=>'oauth_lock_peer','renovado_agora'=>false];
      }

      $row = self::getTokenRow($amb);
      $refreshToken = '';
      try { $refreshToken = TokenVaultService::getActive('tiny_v3', $amb, 'refresh_token'); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
      if ($refreshToken === '') $refreshToken = $row ? CryptoService::decrypt($row['refresh_token'] ?? '') : '';
      if (!$refreshToken) return ['erro'=>'Refresh token Tiny V3 não configurado para o ambiente '.$amb.'.','codigo_erro'=>'TINY_V3_REFRESH_TOKEN_MISSING'];
      $tokenUrl = (string)($cfg['tiny_v3_token_url'] ?? '');
      if (!$tokenUrl) return ['erro'=>'Token URL Tiny V3 não configurada.','codigo_erro'=>'TINY_V3_TOKEN_URL_MISSING'];
      $clientId = (string)($cfg['tiny_v3_client_id'] ?? '');
      $clientSecret = (string)($cfg['tiny_v3_client_secret'] ?? '');
      $post = ['grant_type'=>'refresh_token','refresh_token'=>$refreshToken];
      if ($clientId) $post['client_id']=$clientId;
      if ($clientSecret) $post['client_secret']=$clientSecret;
      try { $tokenUrl=TinyEndpointSecurityService::validateUrl($tokenUrl); $securityOptions=TinyEndpointSecurityService::curlSecurityOptions($tokenUrl); }
      catch(Throwable $e){ return ['erro'=>'Token URL Tiny V3 bloqueada pela política de segurança.','codigo_erro'=>'TINY_V3_TOKEN_URL_BLOCKED']; }
      $ch = curl_init($tokenUrl);
      curl_setopt_array($ch,$securityOptions+[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
      $body=curl_exec($ch); $err=curl_error($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
      if($err) return ['erro'=>$err,'codigo_erro'=>'TINY_V3_REFRESH_CURL_ERROR','http_code'=>$http];
      $json=json_decode((string)$body,true);
      if($http < 200 || $http >= 300) return ['erro'=>'Tiny V3 recusou renovação de token.','codigo_erro'=>'TINY_V3_REFRESH_HTTP_ERROR','http_code'=>$http,'raw'=>SensitiveDataService::mask((string)$body)];
      if(!is_array($json) || empty($json['access_token'])) return ['erro'=>'Retorno inválido ao renovar token Tiny V3.','codigo_erro'=>'TINY_V3_REFRESH_INVALID_RESPONSE','http_code'=>$http,'raw'=>SensitiveDataService::mask((string)$body)];
      self::saveOAuthToken((string)$json['access_token'], (string)($json['refresh_token'] ?? $refreshToken), (int)($json['expires_in'] ?? 3600), (string)($json['scope'] ?? ''), $amb);
      return ['sucesso'=>true,'http_code'=>$http,'mensagem'=>'Token Tiny V3 renovado via OAuth.','ambiente'=>$amb,'origem'=>$force?'oauth_manual_forced':'oauth','renovado_agora'=>true];
    } finally {
      self::releaseRefreshLock($pdo, $lock, $amb);
    }
  }

  public static function status(): array {
    $cfg=IntegrationConfig::get();
    $amb = $cfg['tiny_v3_ambiente'] ?? 'homologacao';
    $row=self::getTokenRow($amb);
    return [
      'ambiente'=>$amb,
      'tem_token_tabela'=>!!$row,
      'tem_token_manual'=>!empty($cfg['tiny_v3_token']),
      'expires_at'=>$row['expires_at'] ?? null,
      'expirado'=>self::isExpired($row),
      'scope'=>$row['scope'] ?? null,
      'origem'=>$row['origem'] ?? null,
    ];
  }


  /**
   * Resumo compatível para telas de homologação V3.
   * Mantém compatibilidade com serviços antigos que esperavam statusResumo().
   */
  public static function statusResumo(): array {
    $cfg = IntegrationConfig::get();
    $status = self::status();
    return [
      'ambiente' => $status['ambiente'] ?? ($cfg['tiny_v3_ambiente'] ?? 'homologacao'),
      'tem_token_salvo' => !empty($status['tem_token_tabela']),
      'tem_token_manual' => !empty($status['tem_token_manual']),
      'expirado' => !empty($status['expirado']),
      'expires_at' => $status['expires_at'] ?? null,
      'scope' => $status['scope'] ?? null,
      'origem' => $status['origem'] ?? null,
      'client_id_configurado' => trim((string)($cfg['tiny_v3_client_id'] ?? '')) !== '',
      'client_secret_configurado' => trim((string)($cfg['tiny_v3_client_secret'] ?? '')) !== '',
      'redirect_uri_configurado' => trim((string)($cfg['tiny_v3_redirect_uri'] ?? '')) !== '',
      'token_url_configurada' => trim((string)($cfg['tiny_v3_token_url'] ?? '')) !== '',
    ];
  }

  public static function homologationReady(): array {
    $cfg = IntegrationConfig::get();
    $status = self::status();
    $issues = [];
    if (empty($cfg['tiny_v3_url'])) $issues[]='Base URL Tiny V3 não configurada.';
    if (empty($cfg['tiny_v3_token_url'])) $issues[]='Token URL Tiny V3 não configurada.';
    if (empty($cfg['tiny_v3_client_id'])) $issues[]='Client ID Tiny V3 não configurado.';
    if (empty($cfg['tiny_v3_client_secret'])) $issues[]='Client Secret Tiny V3 não configurado.';
    if (empty($status['tem_token_tabela'])) $issues[]='Nenhum token OAuth salvo para o ambiente '.$status['ambiente'].'.';
    if (!empty($status['expirado'])) $issues[]='Token OAuth Tiny V3 expirado.';
    if (($cfg['ambiente'] ?? 'homologacao') === 'producao' && (($status['origem'] ?? '') !== 'oauth')) $issues[]='Produção exige token com origem OAuth.';
    return ['ok'=>empty($issues), 'issues'=>$issues, 'status'=>$status];
  }
}
