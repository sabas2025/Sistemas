<?php
declare(strict_types=1);
/**
 * Melhoria 4 da seção 8 (relatório V104.49.3-R6): higiene de segredos, verificada na CI.
 *
 * A recomendação "rotacione as chaves" não impedia o pior caso, que é um segredo REAL vazar para
 * dentro do pacote distribuído. Esta checagem garante duas coisas:
 *   1. config/config.example.php não traz nenhum segredo preenchido (só vazio ou placeholder);
 *   2. nenhum arquivo do pacote carrega um valor que pareça segredo real de produção.
 *
 * Não é um detector de segredo genérico: é a defesa do caso concreto que a auditoria encontrou
 * (nome de cliente e host de produção dentro do pacote).
 */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "[FALHA] Somente CLI.\n"); exit(1); }

$root = dirname(__DIR__, 2);
require_once $root.'/app/Services/SecretStrengthService.php';

$problemas = [];

// 1. O config de exemplo não pode trazer segredo preenchido.
$exemplo = $root.'/config/config.example.php';
if (!is_file($exemplo)) {
    $problemas[] = 'config/config.example.php não encontrado.';
} else {
    $cfg = require $exemplo;
    $sec = is_array($cfg) ? ($cfg['security'] ?? []) : [];
    foreach (array_keys(SecretStrengthService::keys()) as $chave) {
        $valor = trim((string)($sec[$chave] ?? ''));
        if ($valor !== '') {
            $problemas[] = "config.example.php traz valor preenchido em security.{$chave}. O exemplo deve vir vazio — o instalador gera.";
        }
    }
}

// 2. Nenhum arquivo do pacote pode carregar segredo com cara de produção.
$suspeitos = [];
$diretorios = ['app', 'public', 'workers', 'views', 'config', 'scripts', 'database'];
$padrao = '/(encryption_key|backup_signature_key|integration_replay_hmac_key|audit_daily_signature_key|token_vault_hmac_key|fim_manifest_hmac_key|webhook_secret|vsm_hmac_secret)\s*(?:=>|=|:)\s*[\'"]([^\'"]{16,})[\'"]/i';
foreach ($diretorios as $dir) {
    $caminho = $root.'/'.$dir;
    if (!is_dir($caminho)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($caminho, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile() || !in_array($f->getExtension(), ['php','sql','js','json'], true)) continue;
        $rel = ltrim(str_replace($root, '', $f->getPathname()), '/');
        $conteudo = (string)file_get_contents($f->getPathname());
        if (!preg_match_all($padrao, $conteudo, $ms, PREG_SET_ORDER)) continue;
        foreach ($ms as $m) {
            $valor = $m[2];
            // Placeholders do instalador, exemplos declarados e valores de CI são aceitos.
            if (preg_match('/^\{[A-Z_]+\}$/', $valor)) continue;
            if (str_starts_with(strtolower($valor), 'ci-')) continue;
            if (str_contains(strtolower($valor), 'example') || str_contains(strtolower($valor), 'change')) continue;
            if (str_contains(strtolower($valor), 'teste-de-regressao')) continue;
            $suspeitos[] = "{$rel}: security.{$m[1]} com valor literal de ".strlen($valor).' caracteres.';
        }
    }
}
$problemas = array_merge($problemas, $suspeitos);

if ($problemas === []) {
    echo "[OK] Higiene de segredos: exemplo sem valores preenchidos e nenhum segredo literal no pacote.\n";
    exit(0);
}
fwrite(STDERR, "[FALHA] Higiene de segredos reprovada:\n");
foreach ($problemas as $p) fwrite(STDERR, "  - {$p}\n");
exit(1);
