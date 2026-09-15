<?php
declare(strict_types=1);
/**
 * Melhoria 4 da seção 8 (relatório V104.49.3-R6): rotação assistida dos segredos de config.php.
 *
 * A recomendação "rotacione todas as chaves" não era acionável: não havia ferramenta, e trocar as
 * chaves à mão em produção quebra dados já protegidos por elas. Este script torna a operação
 * explícita e reversível, e deixa claro o que cada rotação invalida.
 *
 * Uso:
 *   php scripts/rotate-secrets.php --audit                 só relata o estado atual (não escreve)
 *   php scripts/rotate-secrets.php --rotate=chave1,chave2  rotaciona as chaves indicadas
 *   php scripts/rotate-secrets.php --rotate=all            rotaciona todas
 *   php scripts/rotate-secrets.php --rotate=... --confirm  aplica de verdade (sem isto é simulação)
 *
 * SEMPRE grava um backup de config.php antes de alterar.
 *
 * ATENÇÃO — o que cada rotação invalida:
 *   backup_signature_key        assinaturas .sig.json existentes deixam de verificar; backups
 *                               antigos passam a ser tratados como origem externa no restore.
 *   encryption_key              qualquer dado cifrado com a chave anterior torna-se ilegível.
 *   token_vault_hmac_key        tokens guardados no cofre precisam ser reconectados.
 *   audit_daily_signature_key   a cadeia de assinatura diária quebra a partir da troca.
 *   fim_manifest_hmac_key       o manifesto de integridade precisa ser regerado.
 *   integration_replay_hmac_key a janela de anti-replay em curso é descartada.
 *   webhook_secret / vsm_hmac_secret  o OUTRO LADO da integração precisa do novo valor.
 */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "[FALHA] Somente CLI.\n"); exit(1); }

$root = dirname(__DIR__);
require_once $root.'/app/Services/SecretStrengthService.php';

$configPath = $root.'/config/config.php';
if (!is_file($configPath)) { fwrite(STDERR, "[FALHA] config/config.php não encontrado. Instale o sistema antes de rotacionar segredos.\n"); exit(1); }

$config = require $configPath;
if (!is_array($config)) { fwrite(STDERR, "[FALHA] config/config.php não devolveu um array.\n"); exit(1); }
$security = $config['security'] ?? [];
if (!is_array($security)) $security = [];
$producao = (string)($config['app_env'] ?? 'production') === 'production';

$args = array_slice($argv, 1);
$rotateArg = null;
$confirm = in_array('--confirm', $args, true);
foreach ($args as $a) { if (str_starts_with($a, '--rotate=')) $rotateArg = substr($a, strlen('--rotate=')); }

// ---------- auditoria ----------
$audit = SecretStrengthService::audit($security, $producao);
echo "Segredos avaliados: {$audit['avaliadas']} (ambiente: ".($producao ? 'produção' : 'não-produção').")\n";
if ($audit['ok']) {
    echo "[OK] Nenhum problema de força, reuso ou valor conhecido.\n";
} else {
    foreach ($audit['problemas'] as $p) {
        printf("[%s] %s: %s\n        Ação: %s\n", strtoupper($p['severidade']), $p['chave'], $p['problema'], $p['acao']);
    }
}

if ($rotateArg === null) {
    echo "\nNada foi alterado (modo auditoria). Para rotacionar: --rotate=all --confirm\n";
    exit($audit['ok'] ? 0 : 1);
}

// ---------- rotação ----------
$disponiveis = array_keys(SecretStrengthService::keys());
$alvos = $rotateArg === 'all' ? $disponiveis : array_values(array_filter(array_map('trim', explode(',', $rotateArg))));
$invalidas = array_diff($alvos, $disponiveis);
if ($invalidas) {
    fwrite(STDERR, "[FALHA] Chave desconhecida: ".implode(', ', $invalidas)."\n        Conhecidas: ".implode(', ', $disponiveis)."\n");
    exit(1);
}

echo "\nChaves a rotacionar (".count($alvos)."): ".implode(', ', $alvos)."\n";
if (!$confirm) {
    echo "\nSIMULAÇÃO. Nada foi gravado. Releia o cabeçalho deste script para saber o que cada\n";
    echo "rotação invalida e repita o comando com --confirm quando tiver certeza.\n";
    exit(0);
}

$backupPath = $root.'/config/config.php.bak-'.date('Ymd-His');
if (!@copy($configPath, $backupPath)) { fwrite(STDERR, "[FALHA] Não foi possível gravar o backup em {$backupPath}. Nada foi alterado.\n"); exit(1); }
@chmod($backupPath, 0600);

foreach ($alvos as $chave) { $security[$chave] = SecretStrengthService::generate(); }
$config['security'] = $security;

$export = "<?php\n// Atualizado por scripts/rotate-secrets.php em ".date('c')."\nreturn ".var_export($config, true).";\n";
$tmp = $configPath.'.tmp'.bin2hex(random_bytes(4));
if (@file_put_contents($tmp, $export) === false || !@rename($tmp, $configPath)) {
    @unlink($tmp);
    fwrite(STDERR, "[FALHA] Não foi possível gravar config.php. O backup está em {$backupPath}.\n");
    exit(1);
}
@chmod($configPath, 0640);

echo "[OK] ".count($alvos)." chave(s) rotacionada(s).\n";
echo "     Backup do config anterior: ".basename($backupPath)."\n";
echo "     Guarde-o em local seguro e apague-o quando confirmar que o sistema subiu bem.\n";
if (in_array('fim_manifest_hmac_key', $alvos, true)) echo "     Regenere o manifesto de integridade (painel > Segurança > FIM).\n";
if (in_array('webhook_secret', $alvos, true) || in_array('vsm_hmac_secret', $alvos, true)) echo "     Informe o novo segredo ao outro lado da integração ANTES do próximo webhook.\n";
