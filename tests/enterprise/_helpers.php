<?php
declare(strict_types=1);

function hub_root(): string { return dirname(__DIR__, 2); }
/**
 * Achado I-24 (2026-09-15): as cinco asserções sobre configuração afirmam sobre o DEFAULT QUE O
 * PACOTE ENTREGA — política de idempotência, reparo de schema desligado, allowlist do Tiny, lease
 * da fila. O helper antigo preferia `config/config.php` quando ele existia, isto é, lia a
 * configuração VIVA da máquina. Em CI não há config.php e o resultado saía certo por ausência;
 * num Hub instalado de verdade — toda hospedagem, e esta sessão depois de subir o Hub — as quatro
 * suítes reprovavam medindo o arquivo errado. O veredito dependia do estado de runtime.
 *
 * Agora o default é sempre o `config.example.php`, que é o arquivo versionado, o que o instalador
 * usa de base e o único sobre o qual "vem assim de fábrica" é uma afirmação verificável. Quem um
 * dia precisar da configuração ativa deve lê-la por caminho explícito, e dizer por quê.
 */
function hub_config_default_file(): string { return hub_root().'/config/config.example.php'; }
function hub_read(string $relative): string {
    // 'config/config.php' é apelido histórico para o default entregue - ver I-24 acima.
    $path = $relative === 'config/config.php' ? hub_config_default_file() : hub_root().'/'.ltrim($relative, '/');
    return is_file($path) ? (string)file_get_contents($path) : '';
}
function hub_asset_exists(string $base): bool {
    $root = hub_root().'/public/assets/';
    return is_file($root.$base) || is_file($root.preg_replace('/\.(css|js)$/', '.min.$1', $base));
}
function hub_layout_references(string $layout, string $base): bool {
    $min = preg_replace('/\.(css|js)$/', '.min.$1', $base);
    return str_contains($layout, $base) || str_contains($layout, $min);
}
function hub_sw_version(): string {
    $sw = hub_read('public/sw.js');
    return preg_match("/const\s+HUB_VERSION\s*=\s*['\"]([0-9]+\.[0-9]+\.[0-9]+)['\"]/", $sw, $m) ? (string)$m[1] : '';
}
function hub_check(array &$checks, string $name, bool $ok, string $detail=''): void {
    $checks[]=['name'=>$name,'ok'=>$ok,'detail'=>$detail];
}
function hub_finish(array $checks): never {
    $failed=0;
    foreach ($checks as $c) {
        echo ($c['ok'] ? '[OK] ' : '[FALHA] ').$c['name'].($c['detail']!==''?' - '.$c['detail']:'').PHP_EOL;
        if (!$c['ok']) $failed++;
    }
    exit($failed===0 ? 0 : 1);
}
