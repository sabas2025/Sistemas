<?php
class SimpleCache {
  private static function dir(): string {
    $dir = __DIR__ . '/../../storage/cache';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir;
  }
  private static function file(string $key): string {
    return self::dir() . '/' . preg_replace('/[^a-zA-Z0-9_.-]/','_', $key) . '.cache.php';
  }
  public static function remember(string $key, int $ttlSeconds, callable $callback) {
    $file = self::file($key);
    if (is_file($file)) {
      $data = @include $file;
      if (is_array($data) && ($data['expires'] ?? 0) >= time()) return $data['value'];
    }
    $value = $callback();
    @file_put_contents($file, '<?php return ' . var_export(['expires'=>time()+max(1,$ttlSeconds),'value'=>$value], true) . ';');
    return $value;
  }
  public static function forget(string $prefix=''): void {
    foreach (glob(self::dir() . '/' . ($prefix ? preg_replace('/[^a-zA-Z0-9_.-]/','_', $prefix) . '*' : '*') . '.cache.php') ?: [] as $file) @unlink($file);
  }
}
