<?php
declare(strict_types=1);

function hub_root(): string { return dirname(__DIR__, 2); }
function hub_config_file(): string {
    $active = hub_root().'/config/config.php';
    return is_file($active) ? $active : hub_root().'/config/config.example.php';
}
function hub_read(string $relative): string {
    $path = hub_root().'/'.ltrim($relative, '/');
    if ($relative === 'config/config.php' && !is_file($path)) $path = hub_config_file();
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
