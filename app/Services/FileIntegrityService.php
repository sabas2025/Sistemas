<?php
class FileIntegrityService {
  public static function manifestPath(): string { return __DIR__.'/../../storage/file_integrity_manifest.json'; }
  /**
   * P1-11 (reauditoria 2026-08-23): cobria só o nível superior de app/Core,
   * app/Services, app/Controllers e public - cerca de 235/388 arquivos PHP do projeto.
   * Connectors, Legacy, views e workers ficavam de fora, e qualquer coisa dentro de uma
   * subpasta desses diretórios também não era vista. Agora percorre recursivamente todos
   * os diretórios de código executável do projeto.
   */
  public static function criticalFiles(): array {
    $base = realpath(__DIR__.'/../..');
    $dirs = ['app/Core','app/Services','app/Controllers','app/Connectors','app/Legacy','app/Models','public','views','workers'];
    $files=[];
    foreach ($dirs as $d) {
      $full = $base.'/'.$d;
      if (!is_dir($full)) continue;
      $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS)
      );
      foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') $files[] = $file->getPathname();
      }
    }
    sort($files); return $files;
  }
  public static function buildManifest(): array {
    $m=[]; $base = realpath(__DIR__.'/../..');
    foreach (self::criticalFiles() as $f) $m[str_replace($base.'/', '', $f)] = hash_file('sha256', $f);
    $manifest = ['created_at'=>date('c'), 'files'=>$m];
    $manifest['hmac'] = self::signFiles($manifest['files']);
    return $manifest;
  }

  private static function hmacKey(): string {
    $cfg = App::config()['security'] ?? [];
    $k = (string)($cfg['fim_manifest_hmac_key'] ?? '');
    if ($k === '') $k = (string)($cfg['encryption_key'] ?? '');
    if ($k === '') $k = 'hub-fim-dev-key-change-me';
    return hash('sha256', $k, true);
  }
  private static function signFiles(array $files): string {
    ksort($files);
    return hash_hmac('sha256', json_encode($files, JSON_UNESCAPED_SLASHES), self::hmacKey());
  }

  public static function saveManifest(): bool {
    $path = self::manifestPath();
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) return false;
    $json = json_encode(self::buildManifest(), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;
    try { $suffix = bin2hex(random_bytes(6)); } catch (Throwable) { $suffix = str_replace('.', '', uniqid('', true)); }
    $tmp = $path.'.tmp.'.$suffix;
    if (file_put_contents($tmp, $json.PHP_EOL, LOCK_EX) === false) { @unlink($tmp); return false; }
    @chmod($tmp, 0600);
    if (!@rename($tmp, $path)) { @unlink($tmp); return false; }
    @chmod($path, 0600);
    return true;
  }
  public static function check(): array {
    if (!is_file(self::manifestPath())) return ['status'=>'sem_manifesto','alterados'=>[], 'faltantes'=>[], 'novos'=>[], 'total'=>0];
    $old = json_decode((string)file_get_contents(self::manifestPath()), true); $oldFiles = $old['files'] ?? [];
    if (!is_array($old) || empty($old['hmac']) || !hash_equals((string)$old['hmac'], self::signFiles((array)$oldFiles))) {
      SecurityEventService::log('fim.manifesto_assinatura_invalida','critico','Manifesto FIM sem assinatura válida');
      return ['status'=>'manifesto_assinatura_invalida','alterados'=>[], 'faltantes'=>[], 'novos'=>[], 'total'=>0];
    }
    $now = self::buildManifest()['files']; $alterados=[]; $faltantes=[]; $novos=[];
    foreach ($oldFiles as $p=>$h) { if (!isset($now[$p])) $faltantes[]=$p; elseif ($now[$p] !== $h) $alterados[]=$p; }
    foreach ($now as $p=>$h) if (!isset($oldFiles[$p])) $novos[]=$p;
    $status = (!$alterados && !$faltantes) ? 'ok' : 'falha';
    if ($status !== 'ok') SecurityEventService::log('fim.alteracao_detectada','critico','Arquivos críticos alterados',['alterados'=>$alterados,'faltantes'=>$faltantes,'novos'=>$novos]);
    return compact('status','alterados','faltantes','novos') + ['total'=>count($now)];
  }
}
