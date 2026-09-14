<?php
declare(strict_types=1);

// Telemetria técnica e anônima do PWA. Não aceita cookies, tokens, formulários ou payloads livres.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo '{"ok":false}';
    exit;
}

// Reauditoria 2026-09-14 (achado A-07): o metadado ausente era tratado como 'same-origin',
// de forma que qualquer cliente não-navegador (curl, script) passava pela checagem. Um
// navegador atual sempre envia Sec-Fetch-Site; a ausência agora é tratada como não confiável.
$fetchSite = strtolower((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
if (!in_array($fetchSite, ['same-origin', 'same-site'], true)) {
    http_response_code(403);
    echo '{"ok":false}';
    exit;
}

$length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($length < 2 || $length > 4096) {
    http_response_code(413);
    echo '{"ok":false}';
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode((string)$raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo '{"ok":false}';
    exit;
}

$allowedEvents = [
    'js_error', 'promise_rejection', 'sw_install_failed', 'sw_activate_failed',
    'sw_refresh_failed', 'sw_registration_failed', 'cache_diagnostics_failed'
];
$event = (string)($data['event'] ?? '');
if (!in_array($event, $allowedEvents, true)) {
    http_response_code(422);
    echo '{"ok":false}';
    exit;
}

function pwa_text(mixed $value, int $max): string {
    $text = preg_replace('/[\r\n\t]+/', ' ', (string)$value) ?? '';
    $text = preg_replace('/(token|secret|password|senha|authorization|cookie)\s*[:=]\s*[^ ,;]+/i', '$1=[redacted]', $text) ?? '';
    return mb_substr($text, 0, $max, 'UTF-8');
}

// Reauditoria 2026-09-14 (achado A-07): a sequência ler -> incrementar -> escrever tinha
// LOCK_EX só na escrita, então duas requisições simultâneas liam o mesmo valor e o limite era
// ultrapassado por corrida; além disso criava um arquivo por IP/minuto sem nenhuma limpeza.
// AtomicRateCounterService faz leitura e escrita sob o mesmo lock exclusivo e coleta os
// contadores ociosos.
// Melhoria 7: a política deste endpoint é declarada em RateLimitService::POLICIES['pwa_telemetry'],
// junto das demais, em vez de ficar só neste arquivo. Este script roda fora do bootstrap da
// aplicação, então as três classes são carregadas explicitamente (nenhuma delas depende de App).
require_once dirname(__DIR__) . '/app/Services/AtomicRateCounterService.php';
require_once dirname(__DIR__) . '/app/Services/SecurityHealthService.php';
require_once dirname(__DIR__) . '/app/Services/RateLimitService.php';
$ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
// Achado B-01: política FECHADA neste endpoint. Telemetria é descartável - perder registros
// enquanto o armazenamento está indisponível é inofensivo, enquanto aceitar POSTs públicos sem
// nenhum limite não é.
$rateState = RateLimitService::hit('pwa_telemetry', $ip);
if ($rateState['degraded']) {
    http_response_code(503);
    header('Retry-After: 300');
    echo '{"ok":false}';
    exit;
}
if ($rateState['limited']) {
    http_response_code(429);
    header('Retry-After: 60');
    echo '{"ok":false}';
    exit;
}

$record = [
    'event' => $event,
    'version' => pwa_text($data['version'] ?? '', 24),
    'path' => pwa_text($data['path'] ?? '', 160),
    'online' => !empty($data['online']),
    'standalone' => !empty($data['standalone']),
    'message' => pwa_text($data['message'] ?? '', 240),
    'source' => pwa_text($data['source'] ?? '', 160),
    'line' => max(0, min(1000000, (int)($data['line'] ?? 0))),
    'column' => max(0, min(1000000, (int)($data['column'] ?? 0))),
    'received_at' => gmdate('c'),
    'request_id' => bin2hex(random_bytes(8)),
];

$logDir = dirname(__DIR__) . '/storage/security-reports';
if (!is_dir($logDir)) @mkdir($logDir, 0750, true);
$logFile = $logDir . '/pwa-telemetry-' . gmdate('Y-m') . '.jsonl';

// Reauditoria 2026-09-14 (achado A-07): o JSONL mensal crescia sem cota. Acima do teto a
// telemetria é descartada com 202 (o cliente não deve reenviar) em vez de pressionar o disco,
// e arquivos de meses anteriores são removidos após a retenção.
const PWA_TELEMETRY_MAX_BYTES = 8 * 1024 * 1024;
const PWA_TELEMETRY_RETENTION_MONTHS = 3;
if (is_file($logFile) && (int)@filesize($logFile) >= PWA_TELEMETRY_MAX_BYTES) {
    http_response_code(202);
    echo '{"ok":true,"stored":false}';
    exit;
}
if (random_int(1, 500) === 1) {
    $cutoff = gmdate('Y-m', strtotime('-' . PWA_TELEMETRY_RETENTION_MONTHS . ' months'));
    foreach (glob($logDir . '/pwa-telemetry-*.jsonl') ?: [] as $old) {
        if (preg_match('/pwa-telemetry-(\d{4}-\d{2})\.jsonl$/', $old, $m) && $m[1] < $cutoff) @unlink($old);
    }
}

$ok = @file_put_contents($logFile, json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX) !== false;

http_response_code($ok ? 202 : 503);
echo $ok ? '{"ok":true}' : '{"ok":false}';
