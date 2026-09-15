<?php
/**
 * Política Enterprise para schema em runtime.
 *
 * Em produção, requisições operacionais somente validam o schema. Criação/ALTER
 * ficam restritos ao instalador, migrations e Central Técnica. Em ambiente local
 * o reparo automático também permanece desativado por padrão e deve ser ativado
 * explicitamente em enterprise.schema_runtime_repair_enabled.
 */
class SchemaRuntimePolicyService {
  private static array $allowedClasses = [
    'Database','DatabaseSchemaGuardService','DatabaseAutoRepairService','SafeSqlUpgradeService',
    'SchemaMigrationService','MigrationController','DatabaseMaintenanceController',
    'BackupSchemaService','BackupService','CommercialHardeningService','LegacyDatabaseUpgradeController','InstallDatabaseProbe'
  ];

  public static function allowed(string $class): bool { return in_array($class, self::$allowedClasses, true); }

  public static function runtimeRepairEnabled(): bool {
    if (defined('HUB_SCHEMA_MAINTENANCE') && HUB_SCHEMA_MAINTENANCE === true) return true;
    // Nunca executa DDL em requisição web operacional. O fallback configurável existe
    // somente para comandos CLI controlados de manutenção/homologação.
    return PHP_SAPI === 'cli' && (bool)(cfg('enterprise.schema_runtime_repair_enabled') ?? false);
  }

  public static function requireTable(string $table, string $context = ''): void {
    if (Database::tableExists($table)) return;
    if (self::runtimeRepairEnabled()) {
      Database::ensureTableFromSql($table);
      if (Database::tableExists($table)) return;
    }
    throw new RuntimeException(self::message([$table], [], $context));
  }

  /** @param list<string> $tables */
  public static function requireTables(array $tables, string $context = ''): void {
    $missing = [];
    foreach (array_values(array_unique($tables)) as $table) {
      if (!Database::tableExists($table)) $missing[] = $table;
    }
    if ($missing && self::runtimeRepairEnabled()) {
      foreach ($missing as $table) {
        try { Database::ensureTableFromSql($table); }
        catch (Throwable $e) {
          if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e, ['table'=>$table,'context'=>$context]);
        }
      }
      Database::clearSchemaMetadataCache();
      $missing = array_values(array_filter($missing, fn(string $table): bool => !Database::tableExists($table)));
    }
    if ($missing) throw new RuntimeException(self::message($missing, [], $context));
  }

  /** @param list<string> $columns */
  public static function requireColumns(string $table, array $columns, string $context = ''): void {
    self::requireTable($table, $context);
    $missing = [];
    foreach (array_values(array_unique($columns)) as $column) {
      if (!Database::columnExists($table, $column)) $missing[] = $table.'.'.$column;
    }
    if ($missing) throw new RuntimeException(self::message([], $missing, $context));
  }

  private static function message(array $tables, array $columns, string $context): string {
    $parts = [];
    if ($tables) $parts[] = 'tabelas: '.implode(', ', $tables);
    if ($columns) $parts[] = 'colunas: '.implode(', ', $columns);
    $scope = $context !== '' ? ' em '.$context : '';
    return 'Schema incompleto'.$scope.' ('.implode('; ', $parts).'). Aplique as migrations 20260712_001, 20260712_002, 20260712_003, 20260712_004 e 20260713_007 ou use Central Técnica > Banco > Aplicar Enterprise Core. Nenhum DDL foi executado na requisição operacional.';
  }

  public static function report(): array {
    $base = dirname(__DIR__);
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    $items=[];
    foreach($rii as $file){
      if($file->getExtension()!=='php') continue;
      $path=$file->getPathname(); $code=file_get_contents($path) ?: '';
      if(!preg_match('/\b(CREATE\s+TABLE|ALTER\s+TABLE|DROP\s+TABLE|CREATE\s+INDEX)\b/i',$code)) continue;
      $rel=str_replace(dirname($base).'/', '', $path); $allowed=false;
      foreach(self::$allowedClasses as $class){ if(str_contains($rel, $class.'.php')){ $allowed=true; break; } }
      $items[]=['arquivo'=>$rel,'status'=>$allowed?'permitido':'revisar','motivo'=>$allowed?'DDL centralizado/maintenance':'DDL runtime fora do núcleo de schema'];
    }
    return ['total'=>count($items),'revisar'=>count(array_filter($items,fn($i)=>$i['status']==='revisar')),'items'=>$items];
  }
}
