<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
error_reporting(E_ALL);
$unexpected = [];
// Record independently of a test's own handler. Suppressed, intentional errors (@) stay suppressed.
set_error_handler(static function(int $level, string $message, string $file, int $line) use (&$unexpected): bool {
    if (!(error_reporting() & $level)) return false;
    $unexpected[] = "$file:$line [$level] $message";
    return false;
});
register_shutdown_function(static function() use (&$unexpected): void {
    if ($unexpected) {
        fwrite(STDERR, "[FALHA] Diagnósticos PHP inesperados:\n".implode("\n", $unexpected)."\n");
        exit(1);
    }
});
$test = realpath($argv[1] ?? '');
$root = realpath(__DIR__.'/../../tests/enterprise');
if (!$test || dirname($test) !== $root || !str_ends_with($test, '_test.php')) exit(2);
require $test;
