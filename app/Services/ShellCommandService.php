<?php
class ShellCommandService {
  public static function disabled(): bool {
    $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
    foreach (['exec','shell_exec','system','passthru','proc_open','popen','pcntl_exec'] as $f) {
      if (!in_array($f, $disabled, true)) return false;
    }
    return true;
  }
  public static function assertNoShellUse(): array {
    $scan = class_exists('CodeExecutionAuditService') ? CodeExecutionAuditService::scan() : ['hits'=>[]];
    return array_values(array_filter($scan['hits'] ?? [], fn($h)=>($h['classe'] ?? '') === 'bloqueio_obrigatorio'));
  }
  public static function detailedScan(): array { return CodeExecutionAuditService::scan(); }
}
