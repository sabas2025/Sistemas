<?php
declare(strict_types=1);
/**
 * Reauditoria 2026-09-14 (achado A-03): provisiona um ambiente completo e descartável para o
 * E2E autenticado do CI - schema aplicado, config gerada, administrador de teste criado e
 * install.lock presente. Antes o job não subia banco nem servidor e não definia HUB_BASE_URL,
 * então os testes autenticados eram pulados em silêncio e o gate passava sem validar nada.
 *
 * Uso exclusivo de CI: cria credenciais conhecidas e app_env=local. Nunca executar em produção.
 * Variáveis: HUB_TEST_DB_HOST, HUB_TEST_DB_PORT, HUB_TEST_DB_USER, HUB_TEST_DB_PASS, HUB_TEST_DB_NAME.
 */

const E2E_ADMIN_EMAIL = 'ci@example.invalid';
const E2E_ADMIN_PASSWORD = 'CI-only-password-2026';

$root = dirname(__DIR__, 2);
$host = getenv('HUB_TEST_DB_HOST') ?: '127.0.0.1';
$port = (int)(getenv('HUB_TEST_DB_PORT') ?: 3306);
$user = getenv('HUB_TEST_DB_USER') ?: 'root';
$pass = getenv('HUB_TEST_DB_PASS') ?: '';
$db   = getenv('HUB_TEST_DB_NAME') ?: 'hub_ci_e2e';

// Reauditoria 2026-09-14 (achado B-05, revisão da própria correção do A-03): este script faz
// DROP DATABASE, sobrescreve config/config.php e cria um administrador com senha conhecida - e
// era distribuído dentro do pacote de produção protegido apenas pelo nome do banco. Um engano de
// variável num servidor real destruiria configuração e dados. As guardas abaixo tornam isso
// inviável fora de um ambiente efêmero de CI.
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "[FALHA] Execução permitida somente por CLI.\n");
    exit(1);
}
if (getenv('HUB_CI_E2E_CONFIRM') !== '1') {
    fwrite(STDERR, "[FALHA] Ambiente de CI não confirmado. Este script DESTRÓI banco e sobrescreve config/config.php.\n"
        . "        Execute apenas em ambiente descartável, com HUB_CI_E2E_CONFIRM=1.\n");
    exit(1);
}
if (!preg_match('/(test|homolog|ci|e2e)/i', $db)) {
    fwrite(STDERR, "[FALHA] Banco recusado: use nome contendo test/homolog/ci/e2e.\n");
    exit(1);
}
if (is_file($root . '/storage/install.lock')) {
    fwrite(STDERR, "[FALHA] storage/install.lock presente: esta árvore é uma instalação real, não um ambiente de CI.\n"
        . "        Remova a dúvida antes de prosseguir - o script não sobrescreve instalações existentes.\n");
    exit(1);
}
if (is_file($root . '/config/config.php')) {
    $atual = (string)file_get_contents($root . '/config/config.php');
    if (!str_contains($atual, 'provision-e2e-environment.php')) {
        fwrite(STDERR, "[FALHA] config/config.php existe e não foi gerado por este script.\n"
            . "        Recuse-se a sobrescrever configuração que pode ser de uma instalação real.\n");
        exit(1);
    }
}

$admin = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
]);
$quoted = '`' . str_replace('`', '``', $db) . '`';
$admin->exec("DROP DATABASE IF EXISTS {$quoted}");
$admin->exec("CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$schema = (string)file_get_contents($root . '/database/install_final_current.sql');
$schema = strtr($schema, [
    '{DB_NAME}' => str_replace('`', '``', $db),
    '{ADMIN_NOME}' => 'CI Admin',
    '{ADMIN_EMAIL}' => E2E_ADMIN_EMAIL,
    '{ADMIN_HASH}' => password_hash(E2E_ADMIN_PASSWORD, PASSWORD_DEFAULT),
    '{AMBIENTE}' => 'homologacao',
    '{TINY_VERSION}' => 'v2',
    '{TINY_V2_URL}' => 'https://api.tiny.com.br',
    '{TINY_V2_TOKEN}' => '',
    '{TINY_V3_URL}' => 'https://api.tiny.com.br/public-api/v3',
    '{TINY_V3_TOKEN}' => '',
    '{VSM_URL}' => 'https://vsm.example.invalid',
    '{VSM_TOKEN}' => '',
    '{WEBHOOK_SECRET}' => 'ci-webhook-secret',
]);

$pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
]);
foreach (splitSql($schema) as $statement) {
    // query()+closeCursor() drena qualquer result set pendente (mesma razão do achado de
    // portabilidade MariaDB em mysql-runtime-regression.php).
    $result = $pdo->query($statement);
    if ($result instanceof PDOStatement) $result->closeCursor();
}

$adminCount = (int)$pdo->query('SELECT COUNT(*) FROM usuarios WHERE email=' . $pdo->quote(E2E_ADMIN_EMAIL))->fetchColumn();
if ($adminCount !== 1) {
    fwrite(STDERR, "[FALHA] Administrador de teste não foi criado pelo schema.\n");
    exit(1);
}

$config = <<<PHPCFG
<?php
// Gerado por scripts/ci/provision-e2e-environment.php - ambiente de CI descartável.
return [
  'app_name' => 'Hub de Integração (CI)',
  'installation_id' => 'ci-e2e',
  'base_url' => '',
  'app_env' => 'local',
  'security' => [
    'block_search_engines' => true,
    'force_https' => false,
    'admin_ip_allowlist' => '',
    'canonical_host' => '',
    'trusted_proxies' => '',
    'session_idle_timeout_seconds' => 1800,
    'session_absolute_timeout_seconds' => 28800,
    'max_login_attempts' => 50,
    'password_min_length' => 10,
    'login_lock_minutes' => 1,
    'login_ip_rate_limit_per_hour' => 1000,
    'login_ip_rate_limit_per_minute' => 500,
    'login_user_rate_limit_per_hour' => 1000,
    'login_user_rate_limit_per_minute' => 500,
    'route_rate_limit_per_minute' => 600,
    'route_rate_limit_sensitive_per_minute' => 600,
    'require_admin_2fa' => false,
    'encryption_key' => 'ci-e2e-encryption-key-0123456789abcdef',
    'backup_signature_key' => 'ci-e2e-backup-signature-0123456789abcdef',
    'integration_replay_hmac_key' => 'ci-e2e-replay-hmac-0123456789abcdef',
    'audit_daily_signature_key' => 'ci-e2e-audit-signature-0123456789abcdef',
    'token_vault_hmac_key' => 'ci-e2e-token-vault-0123456789abcdef',
    'fim_manifest_hmac_key' => 'ci-e2e-fim-manifest-0123456789abcdef',
    'webhook_secret' => 'ci-e2e-webhook-secret-0123456789abcdef',
  ],
  'db_storage_mode' => 'single',
  'db' => ['host' => '{$host}', 'name' => '{$db}', 'user' => '{$user}', 'pass' => '{$pass}', 'charset' => 'utf8mb4'],
  'db_modules' => [
    'core' => ['name' => '{$db}'], 'pedidos' => ['name' => '{$db}'], 'produtos' => ['name' => '{$db}'],
    'estoque' => ['name' => '{$db}'], 'fiscal' => ['name' => '{$db}'], 'fila' => ['name' => '{$db}'],
    'observabilidade' => ['name' => '{$db}'], 'backups' => ['name' => '{$db}'],
  ],
  'tiny' => ['version' => 'v2', 'v2_url' => 'https://api.tiny.com.br/api2', 'v3_url' => 'https://api.tiny.com.br/public-api/v3'],
  'commercial' => ['license_mode' => 'off', 'allow_unlicensed_internal_use' => true],
  'vsm' => ['url' => 'https://vsm.example.invalid'],
];
PHPCFG;

if (!is_dir($root . '/config') || file_put_contents($root . '/config/config.php', $config) === false) {
    fwrite(STDERR, "[FALHA] Não foi possível gravar config/config.php.\n");
    exit(1);
}
if (!is_dir($root . '/storage')) @mkdir($root . '/storage', 0770, true);
file_put_contents($root . '/storage/install.lock', "installed_at=" . date('c') . PHP_EOL . "mode=ci_e2e" . PHP_EOL);

echo "[OK] Ambiente E2E provisionado em {$db}: schema aplicado, config gerada e administrador " . E2E_ADMIN_EMAIL . " disponível.\n";

/** @return list<string> */
function splitSql(string $sql): array {
    $out = []; $buffer = ''; $quote = ''; $escape = false; $lineComment = false; $length = strlen($sql);
    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i]; $next = $i + 1 < $length ? $sql[$i + 1] : '';
        if ($lineComment) { if ($char === "\n") { $lineComment = false; $buffer .= $char; } continue; }
        if ($quote === '' && $char === '-' && $next === '-') { $lineComment = true; $i++; continue; }
        if ($escape) { $buffer .= $char; $escape = false; continue; }
        if ($quote !== '') { $buffer .= $char; if ($char === '\\') { $escape = true; continue; } if ($char === $quote) $quote = ''; continue; }
        if ($char === "'" || $char === '"' || $char === '`') { $quote = $char; $buffer .= $char; continue; }
        if ($char === ';') { if (trim($buffer) !== '') $out[] = trim($buffer); $buffer = ''; continue; }
        $buffer .= $char;
    }
    if (trim($buffer) !== '') $out[] = trim($buffer);
    return $out;
}
