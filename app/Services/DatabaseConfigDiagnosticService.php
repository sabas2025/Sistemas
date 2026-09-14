<?php
/**
 * V104.24 - Diagnóstico de configuração real do banco.
 * Mostra exatamente qual banco cada módulo usa, se o modo single está absoluto,
 * quais tabelas existem e qual ação corretiva aplicar sem expor senha.
 */
class DatabaseConfigDiagnosticService {
  public static function run(): array {
    $cfg = App::config();
    $mode = (string)($cfg['db_storage_mode'] ?? 'single');
    $single = Database::isSingleDatabaseMode($cfg);
    $base = $cfg['db'] ?? [];
    $modulesOfficial = ['core','pedidos','produtos','estoque','fiscal','fila','observabilidade','backups'];
    $rows = [];
    $seen = [];
    foreach ($modulesOfficial as $module) {
      $expected = $single ? ($base['name'] ?? '') : (($cfg['db_modules'][$module]['name'] ?? ($module==='core' ? ($base['name'] ?? '') : '')));
      $resolved = [];
      try {
        $pdo = Database::connection($module);
        $dbName = Database::currentDatabaseName($pdo);
        $tables = self::countTables($pdo);
        $knownInModule = self::knownTablesForModule($module, $single);
        $missing = self::missingTables($pdo, $knownInModule);
        $resolved = [
          'status' => count($missing) === 0 ? 'ok' : 'alerta',
          'module' => $module,
          'expected_database' => $expected,
          'connected_database' => $dbName,
          'table_count' => $tables,
          'known_tables' => count($knownInModule),
          'missing_tables' => $missing,
          'missing_count' => count($missing),
          'same_as_base' => ($dbName === ($base['name'] ?? '')),
        ];
      } catch (Throwable $e) {
        $resolved = [
          'status' => 'erro',
          'module' => $module,
          'expected_database' => $expected,
          'connected_database' => '',
          'table_count' => 0,
          'known_tables' => 0,
          'missing_tables' => [],
          'missing_count' => 0,
          'same_as_base' => false,
          'error' => $e->getMessage(),
        ];
      }
      $rows[] = $resolved;
      if (!empty($resolved['connected_database'])) $seen[$resolved['connected_database']] = true;
    }

    $risk = [];
    if ($single && count($seen) > 1) {
      $risk[] = 'Modo single ativo, mas módulos conectaram em mais de um banco. Revisar config.php e cache.';
    }
    if ($single && !empty($cfg['db_modules'])) {
      $risk[] = 'db_modules existe no config.php, mas em V104.24 é ignorado quando db_storage_mode=single.';
    }
    $missingTotal = array_sum(array_map(fn($r)=>(int)($r['missing_count'] ?? 0), $rows));
    if ($missingTotal > 0) {
      $risk[] = 'Há tabelas ausentes no banco conectado. Execute Validar Banco/SchemaGuard ou database/repair_current.sql no banco principal.';
    }

    return [
      'version' => class_exists('SystemVersionService') ? SystemVersionService::version() : 'V104.24',
      'mode_configured' => $mode,
      'single_database_effective' => $single,
      'base_database' => (string)($base['name'] ?? ''),
      'connected_databases' => array_keys($seen),
      'modules' => $rows,
      'risk' => $risk,
      'recommendation' => $single
        ? 'Para hospedagem compartilhada, mantenha db_storage_mode=single e todos os módulos usando cfg[db].'
        : 'Para VPS modular, confirme db_modular_strict=true e bancos/permissões em todos os módulos.',
    ];
  }

  private static function countTables(PDO $pdo): int {
    try { return (int)$pdo->query('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchColumn(); }
    catch (Throwable $e) { return 0; }
  }

  private static function knownTablesForModule(string $module, bool $single): array {
    $tables = [];
    foreach (Database::knownTables() as $table) {
      if ($single || Database::tableModule($table) === $module) $tables[] = $table;
    }
    return $tables;
  }

  private static function missingTables(PDO $pdo, array $tables): array {
    $missing = [];
    foreach ($tables as $table) {
      if (!Database::tableExistsOn($pdo, $table)) $missing[] = $table;
      if (count($missing) >= 12) { $missing[] = '...'; break; }
    }
    return $missing;
  }
}
