<?php
/**
 * V102 - Auditoria de execução de código.
 * Classifica execute()/PDO, PDO::exec(), curl_exec() e chamadas reais de sistema operacional.
 */
class CodeExecutionAuditService {
  private const OS_FUNCS = ['exec','shell_exec','system','passthru','proc_open','popen','pcntl_exec'];

  public static function scan(): array {
    $root = dirname(__DIR__, 2);
    $files = self::phpFiles($root);
    $counts = [
      'pdo_execute'=>0,
      'pdo_exec'=>0,
      'curl_exec'=>0,
      'os_exec_real'=>0,
      'os_exec_suspeito'=>0,
      'shell_disabled_ok'=>self::shellDisabledOk(),
    ];
    $hits = [];
    foreach ($files as $file) {
      $rel = str_replace($root.'/', '', $file);
      $lines = preg_split('/\R/', (string)@file_get_contents($file));
      foreach ($lines as $i=>$line) {
        $num = $i + 1;
        $trimmedLine = ltrim((string)$line);
        if (str_starts_with($trimmedLine, '//') || str_starts_with($trimmedLine, '#') || str_starts_with($trimmedLine, '*')) continue;
        if (preg_match('/->\s*execute\s*\(/i', $line) || preg_match('/::\s*execute\s*\(/i', $line)) {
          $counts['pdo_execute']++;
          if (self::shouldKeepDetail($rel)) $hits[] = self::hit('PDO::execute', 'seguro_parametrizado', $rel, $num, $line, 'Execução de statement preparado; não é execução do sistema operacional.');
        }
        if (preg_match('/->\s*exec\s*\(/i', $line) || preg_match('/::\s*exec\s*\(/i', $line)) {
          $counts['pdo_exec']++;
          $risk = preg_match('/->\s*exec\s*\(\s*\$|::\s*exec\s*\(\s*\$/', $line) ? 'revisar_sql_textual' : 'ddl_controlado';
          $hits[] = self::hit('PDO::exec', $risk, $rel, $num, $line, 'SQL textual/DDL interno. Permitido apenas em migração/install/restore validado.');
        }
        if (preg_match('/\bcurl_exec\s*\(/i', $line)) {
          $counts['curl_exec']++;
          $hits[] = self::hit('curl_exec', 'http_client', $rel, $num, $line, 'Chamada HTTP via cURL; não executa shell.');
        }
        foreach (self::OS_FUNCS as $func) {
          if (preg_match('/(?<!->)(?<!::)(?<![A-Za-z0-9_\$])'.preg_quote($func,'/').'\s*\(/i', $line)) {
            // ignora definição de método cujo nome seja exec() em classe controlada, se ocorrer
            if (preg_match('/function\s+'.preg_quote($func,'/').'\s*\(/i', $line)) continue;
            $counts['os_exec_real']++;
            $hits[] = self::hit($func, 'bloqueio_obrigatorio', $rel, $num, $line, 'Chamada real ao sistema operacional. Remover ou substituir por implementação PHP segura.');
          }
        }
      }
    }
    return [
      'status'=>$counts['os_exec_real'] === 0 ? 'ok' : 'critico',
      'counts'=>$counts,
      'hits'=>$hits,
      'recomendacao'=>$counts['os_exec_real'] === 0
        ? 'Nenhuma chamada real ao sistema operacional encontrada. Manter disable_functions no php.ini: exec,shell_exec,system,passthru,proc_open,popen,pcntl_exec.'
        : 'Remover imediatamente as chamadas reais ao sistema operacional antes de produção.',
      'gerado_em'=>date('Y-m-d H:i:s'),
    ];
  }

  public static function enforceNoOsExecution(): void {
    $scan = self::scan();
    if (($scan['counts']['os_exec_real'] ?? 0) > 0 && App::isProduction()) {
      SecurityEventService::log('codigo.os_exec_detectado','critico','Função real de sistema operacional detectada em produção.', ['scan'=>SensitiveDataService::mask($scan)]);
      throw new RuntimeException('Produção bloqueada: chamada real ao sistema operacional detectada no código.');
    }
  }

  private static function phpFiles(string $root): array {
    $paths = [];
    foreach (['app','public','config'] as $dir) {
      $base = $root.'/'.$dir;
      if (!is_dir($base)) continue;
      $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
      foreach ($it as $f) if ($f->isFile() && strtolower($f->getExtension()) === 'php') $paths[] = $f->getPathname();
    }
    sort($paths);
    return $paths;
  }

  private static function shellDisabledOk(): bool {
    $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
    foreach (self::OS_FUNCS as $f) if (!in_array($f, $disabled, true)) return false;
    return true;
  }

  private static function shouldKeepDetail(string $rel): bool { return str_contains($rel,'Controllers') || str_contains($rel,'Services'); }
  private static function hit(string $tipo, string $classe, string $arquivo, int $linha, string $codigo, string $analise): array {
    return ['tipo'=>$tipo,'classe'=>$classe,'arquivo'=>$arquivo,'linha'=>$linha,'codigo'=>trim(function_exists('mb_substr') ? mb_substr($codigo,0,220) : substr($codigo,0,220)),'analise'=>$analise];
  }
}
