<?php
/**
 * Bootstrap resiliente V104.48.2.
 * Captura falhas antes do dispatcher (configuração, sessão, autoload, banco e schema)
 * para impedir a tela genérica "HTTP ERROR 500" sem rastreabilidade.
 */

$hubBootHandled = false;
$hubBootTrace = 'BOOT-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(4)));

/** @return never */
function hub_boot_render_error(string $trace, Throwable $error, int $status = 500): void {
    global $hubBootHandled;
    $hubBootHandled = true;
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('X-Trace-ID: ' . $trace);
    }

    $root = dirname(__DIR__);
    $logDir = $root . '/storage/logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0770, true);
    $record = sprintf(
        "[%s] %s %s em %s:%d\n%s\n\n",
        date('c'),
        $trace,
        get_class($error) . ': ' . $error->getMessage(),
        $error->getFile(),
        $error->getLine(),
        $error->getTraceAsString()
    );
    @file_put_contents($logDir . '/bootstrap-fallback.log', $record, FILE_APPEND | LOCK_EX);

    $message = strtolower($error->getMessage());
    $recoverable = str_contains($message, 'schema incompleto')
        || str_contains($message, 'could not find driver')
        || str_contains($message, 'access denied')
        || str_contains($message, 'unknown database')
        || str_contains($message, 'connection refused')
        || str_contains($message, 'config/config.php')
        || str_contains($message, 'pdo_mysql');
    $title = $recoverable ? 'Configuração ou banco indisponível' : 'Falha de inicialização';
    $safeDetail = $recoverable
        ? 'Verifique se o config/config.php antigo foi preservado, se o PDO MySQL está ativo e se as migrations pendentes foram aplicadas.'
        : 'A falha foi registrada no log interno para diagnóstico seguro.';

    echo '<!doctype html><html lang="pt-br"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Recuperação — Hub de Integração</title>'
        . '<style>body{margin:0;background:#f3f6fb;color:#172033;font-family:system-ui,-apple-system,Segoe UI,Arial,sans-serif}'
        . '.wrap{max-width:720px;margin:8vh auto;padding:20px}.card{background:#fff;border:1px solid #dbe3ef;border-radius:18px;padding:28px;box-shadow:0 18px 55px rgba(20,35,60,.12)}'
        . 'h1{font-size:1.45rem;margin:0 0 10px}.muted{color:#59677c}.trace{font-family:ui-monospace,Consolas,monospace;background:#eef3f9;border-radius:10px;padding:12px;word-break:break-all}'
        . '.actions{display:grid;gap:10px;margin-top:20px}.btn{display:block;text-decoration:none;text-align:center;padding:12px 16px;border-radius:10px;background:#225ad6;color:#fff;font-weight:700}'
        . '.btn.alt{background:#e9eef8;color:#243249}</style></head><body><main class="wrap"><section class="card">'
        . '<h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>'
        . '<p class="muted">' . htmlspecialchars($safeDetail, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><b>Trace ID</b></p><div class="trace">' . htmlspecialchars($trace, ENT_QUOTES, 'UTF-8') . '</div>'
        . '<div class="actions"><a class="btn" href="index.php?page=login">Tentar abrir o login</a>'
        . '<a class="btn alt" href="install.php">Abrir instalador/reparação</a></div>'
        . '<p class="muted" style="margin-top:18px;font-size:.9rem">Log interno: storage/logs/bootstrap-fallback.log. Não envie senhas ou tokens ao solicitar suporte.</p>'
        . '</section></main></body></html>';
    exit;
}

register_shutdown_function(static function () use (&$hubBootHandled, $hubBootTrace): void {
    if ($hubBootHandled) return;
    $last = error_get_last();
    if (!$last || !in_array((int)$last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) return;
    $error = new ErrorException((string)$last['message'], 0, (int)$last['type'], (string)$last['file'], (int)$last['line']);
    hub_boot_render_error($hubBootTrace, $error, 500);
});

try {
    $configFile = __DIR__ . '/../config/config.php';
    if (!is_file($configFile)) {
        throw new RuntimeException('config/config.php ausente. Preserve o arquivo durante atualização ou execute o install.php em instalação nova.');
    }
    $__cfg_boot = require $configFile;
    if (!is_array($__cfg_boot)) throw new RuntimeException('config/config.php inválido: retorno esperado em formato array.');

    // HUB_SESSION_HARDENED_V94: cookie seguro, HttpOnly e SameSite antes de abrir sessão.
    $trusted = array_filter(array_map('trim', explode(',', (string)($__cfg_boot['security']['trusted_proxies'] ?? ''))));
    $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $proxyOk = in_array($remote, $trusted, true) || $remote === '127.0.0.1' || $remote === '::1';
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($proxyOk && (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'));

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('HUBINTEGRACAOSESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.gc_maxlifetime', '28800');
    }

    // Auditoria de capacidade 2026-09-14 (achado C-07): sessão compartilhada entre servidores.
    // O autoloader sobe ANTES de session_start() para que o handler possa ser registrado — é o
    // único ponto em que isso é possível. Com security.session_driver='file' (padrão) nada muda.
    //
    // Falha ao registrar NUNCA derruba o bootstrap: cai no handler de arquivo, que é o
    // comportamento anterior. Um Hub que não inicia porque a tabela de sessão não existe seria
    // pior do que um Hub que não escala horizontalmente.
    require_once __DIR__ . '/../app/Core/Autoload.php';
    try {
        if (class_exists('DatabaseSessionHandler')) DatabaseSessionHandler::registerIfEnabled();
    } catch (Throwable $__sessErr) {
        error_log('Sessão em banco indisponível; usando arquivo: '.$__sessErr->getMessage());
    }

    if (!session_start()) throw new RuntimeException('Não foi possível iniciar a sessão PHP. Verifique session.save_path e permissões.');

    require_once __DIR__ . '/../app/Core/Helpers.php';

    App::setupErrors();
    App::enforceProductionSafety();
    App::sendSecurityHeaders();

    $page = (string)($_GET['page'] ?? 'dashboard');
    $method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $traceId = RequestContext::id();
    $hubBootTrace = $traceId;

    IpBlockService::enforce();
    WafService::inspect();
    RouteRateLimiterService::enforce($page);

    header('X-Trace-ID: ' . $traceId);
    Audit::event('request.inicio', 'info', [
        'mensagem' => 'Requisição iniciada',
        'contexto' => ['page' => $page, 'method' => $method, 'client_trace_id' => RequestContext::clientTraceId()],
    ]);

    FastRouteDispatcherService::dispatch($page, $method);
    $hubBootHandled = true;
} catch (Throwable $e) {
    $trace = $hubBootTrace;
    if (class_exists('RequestContext')) {
        try { $trace = RequestContext::id(); } catch (Throwable) {}
    }
    if (class_exists('Audit')) {
        try { $trace = Audit::exception($e, 'sistema.erro_fatal'); } catch (Throwable) {}
    }

    $message = strtolower($e->getMessage());
    $status = str_contains($message, 'schema incompleto')
        || str_contains($message, 'could not find driver')
        || str_contains($message, 'access denied')
        || str_contains($message, 'unknown database')
        || str_contains($message, 'connection refused')
        || str_contains($message, 'config/config.php')
        ? 503 : 500;

    hub_boot_render_error($trace, $e, $status);
}
