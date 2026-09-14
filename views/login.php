<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex,nofollow,noarchive,nosnippet,noimageindex">
<meta name="theme-color" content="#2563eb">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Hub Integração">
<link rel="manifest" href="manifest.webmanifest">
<link rel="icon" href="assets/img/favicon-32.png" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" sizes="180x180" href="assets/img/apple-touch-icon.png">
<meta name="application-name" content="Hub de Integração">
<meta name="msapplication-TileColor" content="#2563eb">

  <title>Login - Hub de Integração</title>
  <link href="assets/bootstrap-login-lite.min.css?v=104.49.3" rel="stylesheet">
  <link href="assets/app.min.css?v=104.49.3" rel="stylesheet">
  <link href="assets/login.min.css?v=104.49.3" rel="stylesheet">
  <link href="assets/responsive-enterprise.min.css?v=104.49.3" rel="stylesheet">
<link href="assets/scroll-enterprise.min.css?v=104.49.3" rel="stylesheet">
  <script src="assets/pwa.min.js" defer></script>
</head>
<body class="login-page login-futurista">
  <div class="login-bg-orb orb-a"></div>
  <div class="login-bg-orb orb-b"></div>
  <div class="login-bg-grid"></div>

  <main class="login-shell" aria-label="Acesso ao Hub de Integração">
    <section class="login-hero d-none d-lg-flex">
      <div class="hub-logo-xl" aria-hidden="true">
        <svg viewBox="0 0 120 120" role="img" focusable="false">
          <defs>
            <linearGradient id="hubLoginGradient" x1="0" x2="1" y1="0" y2="1">
              <stop offset="0%" stop-color="#67e8f9"/>
              <stop offset="52%" stop-color="#38bdf8"/>
              <stop offset="100%" stop-color="#22c55e"/>
            </linearGradient>
          </defs>
          <path d="M60 8 105 34v52L60 112 15 86V34L60 8Z" fill="rgba(15,23,42,.72)" stroke="url(#hubLoginGradient)" stroke-width="4"/>
          <path d="M37 71V49M83 71V49M37 60h46" stroke="url(#hubLoginGradient)" stroke-width="8" stroke-linecap="round"/>
          <circle cx="37" cy="49" r="7" fill="#67e8f9"/>
          <circle cx="83" cy="49" r="7" fill="#22c55e"/>
          <circle cx="60" cy="60" r="7" fill="#e0f2fe"/>
          <circle cx="37" cy="71" r="7" fill="#22c55e"/>
          <circle cx="83" cy="71" r="7" fill="#67e8f9"/>
        </svg>
      </div>
      <div>
        <span class="login-kicker">Middleware Enterprise</span>
        <h1>Hub de Integração</h1>
        <p>Central segura para integração, homologação, auditoria, estoque, pedidos e XML/NF-e.</p>
        <div class="login-flow-pill">
          <span>API</span><i></i><span>HUB</span><i></i><span>ERP</span><i></i><span>Auditoria</span>
        </div>
      </div>
    </section>

    <section class="login-card login-card-futurista">
      <div class="login-brand-block">
        <div class="hub-logo-sm" aria-hidden="true">
          <svg viewBox="0 0 64 64" role="img" focusable="false">
            <path d="M32 4 56 18v28L32 60 8 46V18L32 4Z" fill="rgba(15,23,42,.92)" stroke="currentColor" stroke-width="2.5"/>
            <path d="M19 38V26M45 38V26M19 32h26" stroke="currentColor" stroke-width="4.5" stroke-linecap="round"/>
            <circle cx="19" cy="26" r="3.8" fill="currentColor"/>
            <circle cx="45" cy="26" r="3.8" fill="currentColor"/>
            <circle cx="32" cy="32" r="3.8" fill="#fff"/>
            <circle cx="19" cy="38" r="3.8" fill="currentColor"/>
            <circle cx="45" cy="38" r="3.8" fill="currentColor"/>
          </svg>
        </div>
        <div>
          <h3 class="fw-bold mb-1">Hub de Integração</h3>
          <p class="text-muted mb-0">Acesso seguro ao painel operacional.</p>
        </div>
      </div>

      <?php if(isset($erro)): ?><div class="alert alert-danger mt-3"><?=e($erro)?></div><?php endif; ?>

      <form method="post" action="index.php?page=login" class="mt-4">
        <?=Csrf::input()?>
        <?php if(!empty($precisa2fa)): ?>
          <?php $tf=$twoFactorInfo ?? Auth::pendingTwoFactorInfo(); ?>
          <?php if(!empty($tf['setup_required'])): ?>
            <div class="alert alert-warning"><b>2FA obrigatório para administrador.</b><br>Abra o Google Authenticator, Microsoft Authenticator ou similar, toque em adicionar conta e escaneie o QR Code abaixo.</div>
            <?php if(!empty($tf['qr_data_uri'])): ?>
              <div class="totp-qr-box mb-3" aria-label="QR Code para cadastro do autenticador">
                <img src="<?=e($tf['qr_data_uri'])?>" alt="QR Code 2FA para Google Authenticator" class="totp-qr-img">
              </div>
            <?php else: ?>
              <div class="alert alert-info small">Não foi possível montar o QR Code local. Use a chave manual abaixo no aplicativo autenticador.</div>
            <?php endif; ?>
            <div class="mb-3"><label class="form-label">Chave manual TOTP</label><input class="form-control js-select-on-focus" readonly value="<?=e($tf['secret'] ?? '')?>"><div class="form-text">Use esta chave somente se a câmera não conseguir ler o QR Code.</div></div>
            <?php if(!empty($tf['provisioning_uri'])): ?><details class="small text-muted mb-3"><summary>Mostrar URI técnica OTP</summary><code class="totp-uri-code"><?=e($tf['provisioning_uri'])?></code></details><?php endif; ?>
          <?php else: ?>
            <div class="alert alert-info">Informe o código 2FA do aplicativo autenticador.</div>
          <?php endif; ?>
          <input type="hidden" name="email" value="<?=e($email ?? ($_SESSION['pending_2fa_email'] ?? ''))?>">
          <div class="mb-3"><label class="form-label">Código 2FA</label><input class="form-control form-control-lg" name="codigo_2fa" inputmode="numeric" autocomplete="one-time-code" required autofocus></div>
        <?php else: ?>
          <div class="mb-3"><label class="form-label">E-mail</label><input class="form-control form-control-lg" name="email" autocomplete="username" required></div>
          <div class="mb-3"><label class="form-label">Senha</label><input type="password" class="form-control form-control-lg" name="senha" autocomplete="current-password" required></div>
        <?php endif; ?>
        <button class="btn btn-primary btn-lg w-100 login-submit">Entrar no Hub</button>
        <button class="btn btn-outline-primary btn-lg w-100 mt-2 d-none" type="button" data-pwa-install>📲 Instalar aplicativo do Hub</button>
      </form>

      <div class="login-security-note mt-4">
        <span>🔐</span>
        <small>Ambiente protegido. Use as credenciais criadas no instalador.</small>
      </div>
    </section>
  </main>
</body>
</html>
