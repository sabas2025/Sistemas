<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Comando disponível somente no terminal.\n");
}

$root = dirname(__DIR__);
$storage = $root . '/storage';
$authorizationPath = $storage . '/install-authorization.json';
$lockPath = $storage . '/install-authorization.lock';
$force = in_array('--force', $argv ?? [], true);
$ttlSeconds = 15 * 60;

function alignWithProjectOwner(string $path, string $projectRoot, int $mode): void
{
    $reference = $projectRoot;
    $owner = @fileowner($reference);
    $group = @filegroup($reference);
    while (is_int($owner) && $owner === 0 && $reference !== dirname($reference)) {
        $reference = dirname($reference);
        $candidateOwner = @fileowner($reference);
        if (is_int($candidateOwner) && $candidateOwner !== 0) {
            $owner = $candidateOwner;
            $group = @filegroup($reference);
            break;
        }
    }
    if (is_int($owner)) @chown($path, $owner);
    if (is_int($group)) @chgrp($path, $group);
    @chmod($path, $mode);
}

if (!is_dir($storage) && !mkdir($storage, 0770, true) && !is_dir($storage)) {
    fwrite(STDERR, "Não foi possível criar o diretório storage.\n");
    exit(1);
}
alignWithProjectOwner($storage, $root, 0770);

$lockHandle = fopen($lockPath, 'c');
if ($lockHandle === false || !flock($lockHandle, LOCK_EX)) {
    if (is_resource($lockHandle)) fclose($lockHandle);
    fwrite(STDERR, "Não foi possível bloquear a geração da autorização.\n");
    exit(1);
}
alignWithProjectOwner($lockPath, $root, 0660);

try {
    $existing = null;
    if (is_file($authorizationPath)) {
        $raw = file_get_contents($authorizationPath);
        $existing = is_string($raw) ? json_decode($raw, true) : null;
    }
    $existingActive = is_array($existing)
        && in_array((string)($existing['state'] ?? ''), ['issued', 'bound', 'running'], true)
        && (int)($existing['expires_at'] ?? 0) >= time();
    if ($existingActive && !$force) {
        fwrite(STDERR, "Já existe uma autorização ativa. Use-a ou execute novamente com --force para revogá-la.\n");
        exit(2);
    }

    $token = bin2hex(random_bytes(32));
    $now = time();
    $record = [
        'version' => 1,
        'id' => bin2hex(random_bytes(16)),
        'state' => 'issued',
        'token_hash' => hash('sha256', $token),
        'created_at' => $now,
        'expires_at' => $now + $ttlSeconds,
    ];
    $json = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) throw new RuntimeException('Não foi possível serializar a autorização.');

    $temporaryPath = $authorizationPath . '.tmp.' . bin2hex(random_bytes(6));
    if (file_put_contents($temporaryPath, $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Não foi possível gravar a autorização temporária.');
    }
    alignWithProjectOwner($temporaryPath, $root, 0600);
    if (!rename($temporaryPath, $authorizationPath)) {
        @unlink($temporaryPath);
        throw new RuntimeException('Não foi possível publicar a autorização.');
    }
    alignWithProjectOwner($authorizationPath, $root, 0600);

    fwrite(STDOUT, "Autorização temporária criada.\n");
    fwrite(STDOUT, "Validade: 15 minutos. Uso único e vinculado ao navegador após a confirmação.\n");
    fwrite(STDOUT, "\nCódigo: {$token}\n\n");
    fwrite(STDOUT, "Abra public/install.php por HTTPS, cole o código no formulário e confirme.\n");
    fwrite(STDOUT, "O código não deve ser colocado na URL nem compartilhado.\n");
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
} finally {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
}
