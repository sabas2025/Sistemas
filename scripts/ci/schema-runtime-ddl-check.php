<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$allowed = [
    'app/Services/R7UpgradeService.php' => 'migrations allowlist 008–017, executor CLI explícito e bloqueio GET_LOCK',
    'app/Core/Database.php' => 'DDL centralizado e compatibilidade de schema',
    'app/Controllers/LegacyDatabaseUpgradeController.php' => 'upgrade administrativo legado explicitamente acionado',
    'app/Services/BackupService.php' => 'restore administrativo validado',
    'app/Services/SafeSqlUpgradeService.php' => 'upgrade SQL administrativo validado',
    'app/Services/BackupSchemaService.php' => 'schema do módulo de backup',
    'app/Services/DatabaseAutoRepairService.php' => 'reparo administrativo explícito',
    'app/Services/DatabaseSchemaGuardService.php' => 'validação/reparo administrativo explícito',
    'app/Services/SchemaRuntimePolicyService.php' => 'gateway central bloqueado em requisições web operacionais',
    'app/Services/SchemaMigrationService.php' => 'executor central de migrations',
    'app/Services/InstallDatabaseProbe.php' => 'sonda isolada usada exclusivamente pelo instalador inicial',
    'public/install.php' => 'instalação inicial fora do fluxo operacional autenticado',
];
$pattern = '/(?:\b(?:CREATE\s+TABLE|ALTER\s+TABLE|DROP\s+TABLE|TRUNCATE\s+TABLE|CREATE\s+(?:UNIQUE\s+)?INDEX|DROP\s+INDEX)\b|Database::(?:addColumnIfMissing|ensureTableFromSql|ensureSchemaMigrations)\s*\(|file_get_contents\s*\([^\n]*(?:database|update_v)[^\n]*\.sql)/i';
$paths = ['app', 'public', 'workers'];
$items = [];
foreach ($paths as $base) {
    $dir = $root . '/' . $base;
    if (!is_dir($dir)) { continue; }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') { continue; }
        $real = $file->getPathname();
        $contents = file_get_contents($real);
        if (!is_string($contents) || !preg_match($pattern, $contents)) { continue; }
        $relative = str_replace('\\', '/', substr($real, strlen($root) + 1));
        $isAllowed = array_key_exists($relative, $allowed);
        $items[] = [
            'arquivo' => $relative,
            'status' => $isAllowed ? 'permitido' : 'revisar',
            'motivo' => $isAllowed ? $allowed[$relative] : 'DDL encontrado fora da allowlist de instalação/manutenção',
        ];
    }
}
usort($items, static fn(array $a, array $b): int => strcmp($a['arquivo'], $b['arquivo']));
$review = array_values(array_filter($items, static fn(array $i): bool => $i['status'] === 'revisar'));
$report = ['total' => count($items), 'revisar' => count($review), 'items' => $items];
$outDir = $root . '/storage/audit-v104-49-3-r5';
if (!is_dir($outDir)) { @mkdir($outDir, 0700, true); }
@file_put_contents($outDir . '/schema-runtime-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX);
if ($review !== []) {
    fwrite(STDERR, "[ERRO] DDL em fluxo não autorizado:\n");
    foreach ($review as $item) { fwrite(STDERR, ' - ' . $item['arquivo'] . "\n"); }
    exit(1);
}
echo '[OK] DDL restrito a ' . count($items) . " arquivos autorizados de instalação/manutenção.\n";
