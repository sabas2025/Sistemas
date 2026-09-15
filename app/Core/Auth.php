<?php
class Auth {
  private const DUMMY_PASSWORD_HASH = '$2y$12$Qk5DyNfX8hF8b0iJ3X3h8.jVA3Oa0zvzkCJrCkTPHcE5Yj6mN4Psu';

  public static function check(): bool { return isset($_SESSION['user']); }
  public static function user(): ?array { return $_SESSION['user'] ?? null; }

  public static function requireLogin(): void {
    if(!self::check()) redirect('index.php?page=login');
    $cfg = class_exists('App') ? App::config() : [];
    $sec = $cfg['security'] ?? [];
    $now = time();
    $idle = (int)($sec['session_idle_timeout_seconds'] ?? 1800);
    $absolute = (int)($sec['session_absolute_timeout_seconds'] ?? 28800);
    if (!empty($_SESSION['last_activity']) && $idle > 0 && ($now - (int)$_SESSION['last_activity']) > $idle) {
      if(class_exists('SecurityAuditService')) SecurityAuditService::record('sessao.expirada_inatividade','alerta');
      session_destroy(); redirect('index.php?page=login&expired=1');
    }
    if (!empty($_SESSION['login_at']) && $absolute > 0 && ($now - (int)$_SESSION['login_at']) > $absolute) {
      if(class_exists('SecurityAuditService')) SecurityAuditService::record('sessao.expirada_absoluta','alerta');
      session_destroy(); redirect('index.php?page=login&expired=1');
    }
    $_SESSION['last_activity'] = $now;
    if (isset($_SESSION['user']['id'], $_SESSION['session_version']) && Database::columnExists('usuarios','session_version')) {
      try {
        $dbv = AuthRepository::sessionVersion((int)$_SESSION['user']['id']);
        if ($dbv !== (int)$_SESSION['session_version']) {
          if(class_exists('SecurityAuditService')) SecurityAuditService::record('sessao.versao_invalidada','alerta');
          session_destroy(); redirect('index.php?page=login&expired=1');
        }
      } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    }
    $fp=self::fingerprintHash();
    if(isset($_SESSION['fingerprint']) && !hash_equals($_SESSION['fingerprint'],$fp)){
      if(class_exists('SecurityAuditService')) SecurityAuditService::record('sessao.fingerprint_invalido','critico');
      session_destroy(); redirect('index.php?page=login');
    }
  }

  private static function fingerprintHash(): string {
    $sec = (class_exists('App') ? (App::config()['security'] ?? []) : []);
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $ipPart = !empty($sec['session_fingerprint_use_ip_prefix']) ? RequestContext::ipPrefix() : 'sem-ip';
    return hash('sha256', $ua.'|'.$ipPart.'|HUB_SESSION_FP_V100');
  }

  private static function tooManyAttempts(string $email, ?string $ip): bool {
    $cfg = class_exists('App') ? App::config() : [];
    $sec = $cfg['security'] ?? [];
    $maxIpHour = (int)($sec['login_ip_rate_limit_per_hour'] ?? 20);
    $maxIpMinute = (int)($sec['login_ip_rate_limit_per_minute'] ?? 5);
    $maxUserHour = (int)($sec['login_user_rate_limit_per_hour'] ?? 10);
    $maxUserMinute = (int)($sec['login_user_rate_limit_per_minute'] ?? 3);
    try {
      if ($ip) {
        if (AuthRepository::countFailedByIp($ip, '1 HOUR') >= $maxIpHour) return true;
        if (AuthRepository::countFailedByIp($ip, '1 MINUTE') >= $maxIpMinute) return true;
      }
      if ($email !== '') {
        if (AuthRepository::countFailedByEmail($email, '1 HOUR') >= $maxUserHour) return true;
        if (AuthRepository::countFailedByEmail($email, '1 MINUTE') >= $maxUserMinute) return true;
      }
    } catch (Throwable $e) {
      if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e, ['mode'=>'degraded_rate_limit']);
      if (class_exists('LoginRateLimitFallbackService')) {
        $estado = LoginRateLimitFallbackService::evaluate($email,$ip,[
          'ip_hour'=>$maxIpHour,'ip_minute'=>$maxIpMinute,
          'user_hour'=>$maxUserHour,'user_minute'=>$maxUserMinute,
        ]);
        // Melhoria 7: quando o banco E o disco falham, não devolvemos "não limitado" (seria
        // liberar força bruta) nem "limitado para sempre" (trancaria todos para fora por causa de
        // dois problemas de infraestrutura). Caímos para o contador de sessão abaixo, que é fraco
        // mas é um limite real, e a degradação fica visível para o operador.
        if (!$estado['degraded']) return $estado['limited'];
        if (class_exists('SecurityHealthService')) {
          SecurityHealthService::degrade('login_rate_limit','Banco e contador local indisponíveis; limite de login caiu para o contador de sessão.');
        }
      }
      // Último recurso seguro: limita a sessão atual em vez de abrir tentativas ilimitadas.
      $key='login_rl_degraded_'.hash('sha256',strtolower($email).'|'.(string)$ip);
      $events=array_values(array_filter((array)($_SESSION[$key]??[]),fn($t)=>(int)$t>=time()-3600));
      $_SESSION[$key]=$events;
      return count($events)>=max(1,min($maxIpHour,$maxUserHour));
    }
    return false;
  }

  public static function requirePerfil(array $perfis): void {
    self::requireLogin();
    if (!in_array($_SESSION['user']['perfil'] ?? '', $perfis, true)) {
      Audit::event('seguranca.acesso_negado','erro',['codigo_erro'=>'ACCESS_DENIED','mensagem'=>'Usuário tentou acessar função sem permissão.']);
      http_response_code(403); exit('Acesso negado.');
    }
  }

  public static function pendingTwoFactorInfo(): array {
    $secret = (string)($_SESSION['pending_2fa_secret'] ?? '');
    $email = (string)($_SESSION['pending_2fa_email'] ?? '');
    $setupRequired = !empty($_SESSION['pending_2fa_setup_required']);
    $uri = ($secret !== '' && $email !== '') ? TwoFactorService::provisioningUri($email, $secret) : '';
    $qrDataUri = '';
    if ($setupRequired && $uri !== '' && class_exists('QrCodeService')) {
      try { $qrDataUri = QrCodeService::dataUri($uri, 5, 4); } catch (Throwable $e) { $qrDataUri = ''; }
    }
    return [
      'setup_required' => $setupRequired,
      'secret' => $secret,
      'provisioning_uri' => $uri,
      'qr_data_uri' => $qrDataUri
    ];
  }

  private static function startPending2fa(array $u, string $secret, bool $setupRequired=false): string {
    $_SESSION['pending_2fa_user_id'] = (int)$u['id'];
    $_SESSION['pending_2fa_email'] = (string)$u['email'];
    $_SESSION['pending_2fa_ok_password'] = 1;
    $_SESSION['pending_2fa_setup_required'] = $setupRequired ? 1 : 0;
    $_SESSION['pending_2fa_secret'] = $setupRequired ? $secret : '';
    $_SESSION['pending_2fa_started_at'] = time();
    if(class_exists('SecurityAuditService')) SecurityAuditService::record($setupRequired?'auth.2fa_setup_obrigatorio':'auth.2fa_requerido','info',['email'=>$u['email']]);
    return $setupRequired ? '2fa_setup_required' : '2fa_required';
  }

  public static function completeTwoFactor(string $codigo2fa) {
    $uid = (int)($_SESSION['pending_2fa_user_id'] ?? 0);
    if ($uid <= 0 || empty($_SESSION['pending_2fa_ok_password'])) return false;
    $startedAt = (int)($_SESSION['pending_2fa_started_at'] ?? time());
    if ($startedAt > 0 && (time() - $startedAt) > 900) {
      if(class_exists('SecurityAuditService')) SecurityAuditService::record('auth.2fa_sessao_expirada','alerta',['uid'=>$uid]);
      unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_email'], $_SESSION['pending_2fa_ok_password'], $_SESSION['pending_2fa_setup_required'], $_SESSION['pending_2fa_secret'], $_SESSION['pending_2fa_started_at']);
      return false;
    }
    $u = AuthRepository::userById($uid);
    if (!$u) return false;
    $secret = TwoFactorService::decryptSecret($u['two_factor_secret'] ?? '');
    $sessionSecret = (!empty($_SESSION['pending_2fa_setup_required']) && !empty($_SESSION['pending_2fa_secret'])) ? (string)$_SESSION['pending_2fa_secret'] : '';
    $verifiedWithSessionSecret = false;
    if ($secret !== '' && TwoFactorService::verify($secret, $codigo2fa)) {
      // verificado pelo segredo persistido no banco
    } elseif ($sessionSecret !== '' && TwoFactorService::verify($sessionSecret, $codigo2fa)) {
      // compatibilidade: na etapa de cadastro, o QR exibido vem da sessão. Se o segredo no banco
      // foi criptografado com chave antiga/inválida ou gravado diferente, não bloqueia o primeiro acesso.
      $secret = $sessionSecret;
      $verifiedWithSessionSecret = true;
    } else {
      if(class_exists('SecurityAuditService')) SecurityAuditService::record('auth.2fa_invalido','alerta',['email'=>$u['email'] ?? '']);
      AuthRepository::insertAttempt((string)($u['email'] ?? ''), RequestContext::ip(), 0, 'Código 2FA inválido.');
      return false;
    }
    if ($verifiedWithSessionSecret) {
      try { AuthRepository::save2faSecret((int)$u['id'], TwoFactorService::encryptSecret($secret), true); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    }
    AuthRepository::mark2faVerified((int)$u['id']);
    unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_email'], $_SESSION['pending_2fa_ok_password'], $_SESSION['pending_2fa_setup_required'], $_SESSION['pending_2fa_secret'], $_SESSION['pending_2fa_started_at']);
    $u['two_factor_enabled'] = 1;
    return self::finalizeLogin($u);
  }

  public static function login(string $email, string $senha, ?string $codigo2fa=null) {
    $email = trim($email);
    if ($codigo2fa !== null && trim($codigo2fa) !== '' && !empty($_SESSION['pending_2fa_ok_password'])) {
      return self::completeTwoFactor($codigo2fa);
    }

    $ip = RequestContext::ip();
    if (self::tooManyAttempts($email, $ip)) {
      password_verify($senha, self::DUMMY_PASSWORD_HASH);
      AuthRepository::insertAttempt($email, $ip, 0, 'IP/usuário temporariamente limitado por excesso de tentativas.');
      if(class_exists('SecurityAuditService')) SecurityAuditService::record('auth.rate_limit_login','alerta',['email'=>$email,'ip'=>$ip]);
      return false;
    }

    $u = AuthRepository::userByEmail($email);
    $hashParaVerificar = $u['senha'] ?? self::DUMMY_PASSWORD_HASH;
    $senhaValida = password_verify($senha, $hashParaVerificar);
    if ($u && !empty($u['bloqueado_ate']) && strtotime($u['bloqueado_ate']) > time()) {
      AuthRepository::insertAttempt($email, $ip, 0, 'Usuário temporariamente bloqueado por excesso de tentativas.');
      return false;
    }

    if($u && $senhaValida) {
      $cfg = class_exists('App') ? App::config() : [];
      $sec = $cfg['security'] ?? [];
      $isAdmin = strtolower((string)($u['perfil'] ?? '')) === 'admin';
      $must2fa = !empty($u['two_factor_enabled']) || ($isAdmin && !empty($sec['require_admin_2fa']));
      if ($must2fa) {
        $secret = TwoFactorService::decryptSecret($u['two_factor_secret'] ?? '');
        $setupRequired = empty($u['two_factor_enabled']) || $secret === '' || empty($u['two_factor_last_verified_at']);
        if ($secret === '') {
          $secret = TwoFactorService::generateSecret();
          AuthRepository::save2faSecret((int)$u['id'], TwoFactorService::encryptSecret($secret), true);
        } elseif ($setupRequired) {
          AuthRepository::save2faSecret((int)$u['id'], TwoFactorService::encryptSecret($secret), true);
        }
        if ($codigo2fa === null || trim($codigo2fa) === '') {
          return self::startPending2fa($u, $secret, $setupRequired);
        }
        if (!TwoFactorService::verify($secret, $codigo2fa)) {
          if(class_exists('SecurityAuditService')) SecurityAuditService::record('auth.2fa_invalido','alerta',['email'=>$email]);
          AuthRepository::insertAttempt($email, $ip, 0, 'Código 2FA inválido.');
          return false;
        }
        AuthRepository::mark2faVerified((int)$u['id']);
        $u['two_factor_enabled'] = 1;
      }
      return self::finalizeLogin($u);
    }

    if ($u) {
      $cfg = class_exists('App') ? App::config() : [];
      $sec = $cfg['security'] ?? [];
      AuthRepository::markFailure($u, (int)($sec['max_login_attempts'] ?? 5), max(60, (int)($sec['login_lock_minutes'] ?? 15) * 60));
    }
    AuthRepository::insertAttempt($email, $ip, 0, 'Login inválido.');
    Audit::event('auth.login_falha','alerta',['codigo_erro'=>'LOGIN_INVALID','mensagem'=>'Tentativa de login inválida.','contexto'=>['email'=>$email,'ip'=>$ip]]);
    return false;
  }

  private static function finalizeLogin(array $u): bool {
    session_regenerate_id(true);
    unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_email'], $_SESSION['pending_2fa_ok_password'], $_SESSION['pending_2fa_setup_required'], $_SESSION['pending_2fa_secret'], $_SESSION['pending_2fa_started_at']);
    $empresaId = isset($u['empresa_id']) && (int)$u['empresa_id'] > 0 ? (int)$u['empresa_id'] : null;
    $_SESSION['user']=['id'=>$u['id'],'nome'=>$u['nome'],'email'=>$u['email'],'perfil'=>$u['perfil'],'deve_trocar_senha'=>(int)($u['deve_trocar_senha'] ?? 0),'2fa_ativo'=>(int)($u['two_factor_enabled'] ?? 0),'empresa_id'=>$empresaId];
    // Achado H-01: é AQUI que o isolamento multiempresa deixa de ser inerte. Até 2026-09-15
    // TenantContextService::set() não tinha nenhum chamador no aplicativo, então
    // $_SESSION['tenant_empresa_id'] nunca existia, currentEmpresaId() devolvia null e
    // TenantScopeService::where() devolvia predicado VAZIO — os 162 pontos que roteiam consultas
    // pelo serviço não filtravam nada. Medido por HTTP, com duas empresas povoadas, a tela de
    // pedidos mostrava as linhas das duas.
    //
    // Fica depois do session_regenerate_id() acima, de propósito: a chave tem de nascer na sessão
    // NOVA, não na que foi descartada.
    //
    // empresa_id nulo mantém o comportamento anterior (vê tudo) em vez de trancar o usuário fora
    // de tudo. É a escolha deliberada da migration 20260915_014: aplicar não esvazia tela de
    // ninguém, e o isolamento entra em vigor por usuário conforme as empresas são atribuídas.
    if (class_exists('TenantContextService')) TenantContextService::set($empresaId, null);
    $_SESSION['fingerprint'] = self::fingerprintHash();
    $_SESSION['session_version'] = (int)($u['session_version'] ?? 0);
    $_SESSION['login_at'] = time();
    $_SESSION['last_activity'] = time();
    AuthRepository::markSuccess((int)$u['id']);
    AuthRepository::insertAttempt((string)$u['email'], RequestContext::ip(), 1, 'Login realizado com sucesso.');
    Audit::event('auth.login','sucesso',['entidade'=>'usuarios','entidade_id'=>$u['id'],'mensagem'=>'Login realizado com sucesso.']);
    return true;
  }

  public static function logout(): void {
    if(class_exists('SecurityAuditService')) SecurityAuditService::record('auth.logout','info');
    session_destroy(); redirect('index.php?page=login');
  }
}
