<?php
$root = dirname(__DIR__, 2);
$checks = [
  'bootstrap cobre configuração antes do dispatcher' => str_contains(file_get_contents($root.'/public/index.php'), 'hub_boot_render_error'),
  'shutdown captura fatal error' => str_contains(file_get_contents($root.'/public/index.php'), 'register_shutdown_function'),
  'IpBlock não deixa schema escapar' => preg_match('/function enforce\(\).*?try\s*\{.*?ensureSchema/s', file_get_contents($root.'/app/Services/IpBlockService.php')) === 1,
  'SecurityEvent possui fallback em arquivo' => str_contains(file_get_contents($root.'/app/Services/SecurityEventService.php'), 'security-fallback.log'),
  'htaccess compatível Apache 2.2/2.4' => str_contains(file_get_contents($root.'/public/.htaccess'), 'mod_authz_core'),
  'diagnóstico HTTP 500 é somente CLI' => str_contains(file_get_contents($root.'/scripts/diagnose-http-500.php'), "PHP_SAPI !== 'cli'"),
];
$failed = 0;
foreach ($checks as $label=>$ok) { echo ($ok?'[OK] ':'[FALHA] ').$label.PHP_EOL; if(!$ok)$failed++; }
exit($failed?1:0);
