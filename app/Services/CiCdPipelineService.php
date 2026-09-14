<?php
/** V104.18 - Prontidão de pipeline CI/CD. */
class CiCdPipelineService {
  public static function status(): array {
    $checks = [
      'php_lint' => is_file(__DIR__.'/../../scripts/ci/php-lint.sh'),
      'sql_inventory' => is_file(__DIR__.'/../../scripts/ci/sql-inventory-check.php'),
      'playwright' => is_file(__DIR__.'/../../tests/e2e/playwright.config.js'),
      'load_smoke' => is_file(__DIR__.'/../../tests/load/light-smoke.sh'),
      'github_actions' => is_file(__DIR__.'/../../.github/workflows/hub-ci.yml'),
    ];
    $missing = array_keys(array_filter($checks, fn($v)=>!$v));
    return [
      'status' => empty($missing) ? 'ok':'alerta',
      'mensagem' => empty($missing) ? 'Pipeline CI/CD mínimo presente.' : 'Itens CI/CD ausentes: '.implode(', ', $missing),
      'checks' => $checks,
    ];
  }
}
