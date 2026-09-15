<?php
class DatabaseMapService {
  /**
   * Monta o mapa do banco em modo leitura.
   *
   * V104.28: a tela "Mapa do Banco" não deve executar AutoRepair/SchemaGuard automaticamente.
   * Em bases grandes ou hospedagem compartilhada isso podia deixar a requisição carregando por muito
   * tempo e parecer loop no navegador. Reparos continuam disponíveis em "Validar/Reparar Banco".
   *
   * @return array{total:int,ok:int,atencao:int,erro:int,linhas:array<int,array<string,mixed>>,gerado_em:string,trace_id:string,warnings:array<int,string>,modo:string,parcial:bool}
   */
  public static function resumo(bool $autoRepair = false): array {
    @set_time_limit(30);
    $startedAt = microtime(true);
    $deadline = $startedAt + 15.0;
    $warnings = [];

    if ($autoRepair) {
      try {
        if (class_exists('DatabaseAutoRepairService')) DatabaseAutoRepairService::repair(false);
        DatabaseSchemaGuardService::repair(false);
        Database::clearTableResolutionCache();
      } catch (Throwable $e) {
        $warnings[] = 'Reparo prévio não concluído: '.self::safeMessage($e->getMessage());
      }
    }

    $rows = [];
    $official = self::officialColumns();
    $columnCache = [];
    $partial = false;

    foreach (Database::knownTables() as $table) {
      if (microtime(true) > $deadline) {
        $partial = true;
        $warnings[] = 'Mapa interrompido por segurança após 15 segundos. Use Validar/Reparar Banco para diagnóstico completo.';
        break;
      }

      $officialModule = Database::officialTableModule($table);
      $module = $officialModule;
      $exists = false;
      $missing = [];
      $colsCount = 0;
      $dbName = '';

      try {
        $module = Database::resolveTableModule($table);
        $pdo = Database::forTable($table);
        $dbName = Database::currentDatabaseName($pdo);
        $inspection = method_exists('Database', 'inspectTableOn')
          ? Database::inspectTableOn($pdo, $table)
          : ['exists'=>Database::tableExistsOn($pdo, $table), 'error'=>null];
        if (!empty($inspection['error'])) {
          throw new RuntimeException((string)$inspection['error']);
        }
        $exists = !empty($inspection['exists']);
        if ($exists) {
          $columnsByTable = self::columnsByTable($pdo, $columnCache);
          $actual = $columnsByTable[$table] ?? self::columnsForTable($pdo, $table);
          $colsCount = count($actual);
          foreach (($official[$table] ?? []) as $col) {
            if (!in_array($col, $actual, true)) $missing[] = $col;
          }
        }
      } catch (Throwable $e) {
        $missing[] = 'erro: '.self::safeMessage($e->getMessage());
      }

      $rows[] = [
        'tabela' => $table,
        'modulo' => $module,
        'modulo_oficial' => $officialModule,
        'banco' => $dbName,
        'existe' => $exists,
        'colunas' => $colsCount,
        'colunas_ausentes' => $missing,
        'status' => !$exists ? 'erro' : ($missing ? 'atencao' : 'ok'),
      ];
    }

    usort($rows, fn($a,$b)=>[$a['status'],$a['modulo'],$a['tabela']] <=> [$b['status'],$b['modulo'],$b['tabela']]);
    return [
      'total' => count($rows),
      'ok' => count(array_filter($rows, fn($r)=>$r['status']==='ok')),
      'atencao' => count(array_filter($rows, fn($r)=>$r['status']==='atencao')),
      'erro' => count(array_filter($rows, fn($r)=>$r['status']==='erro')),
      'linhas' => $rows,
      'gerado_em' => date('Y-m-d H:i:s'),
      'trace_id' => RequestContext::id(),
      'warnings' => array_values(array_unique($warnings)),
      'modo' => $autoRepair ? 'leitura_com_reparo' : 'leitura_segura',
      'parcial' => $partial,
    ];
  }

  /**
   * @param array<string,array<string,array<int,string>>> $cache
   * @return array<string,array<int,string>>
   */
  private static function columnsByTable(PDO $pdo, array &$cache): array {
    $dbName = Database::currentDatabaseName($pdo);
    $key = spl_object_id($pdo).':'.$dbName;
    if (isset($cache[$key])) return $cache[$key];

    $map = [];
    try {
      $st = $pdo->query('SELECT TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, ORDINAL_POSITION');
      foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $table = (string)($row['TABLE_NAME'] ?? '');
        $column = (string)($row['COLUMN_NAME'] ?? '');
        if ($table === '' || $column === '') continue;
        $map[$table][] = $column;
      }
    } catch (Throwable $e) {
      // Fallback compatível com hospedagens que restringem INFORMATION_SCHEMA.
      foreach (Database::knownTables() as $table) {
        try {
          $cols = [];
          $st = $pdo->query('SHOW COLUMNS FROM `'.$table.'`');
          foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) $cols[] = (string)$row['Field'];
          if ($cols) $map[$table] = $cols;
        } catch(Throwable $ignored){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $ignored); }
      }
    }

    return $cache[$key] = $map;
  }

  /** @return array<int,string> */
  private static function columnsForTable(PDO $pdo, string $table): array {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return [];
    try {
      $cols = [];
      $st = $pdo->query('SHOW COLUMNS FROM `'.$table.'`');
      foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $field = (string)($row['Field'] ?? '');
        if ($field !== '') $cols[] = $field;
      }
      return array_values(array_unique($cols));
    } catch (Throwable $showError) {
      $database = Database::currentDatabaseName($pdo);
      if ($database !== '') {
        try {
          $st = $pdo->prepare('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION');
          $st->execute([$database, $table]);
          return array_values(array_filter(array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN))));
        } catch (Throwable $metadataError) {
          throw new RuntimeException('Tabela encontrada, mas não foi possível listar as colunas: '.$metadataError->getMessage());
        }
      }
      throw new RuntimeException('Tabela encontrada, mas não foi possível listar as colunas: '.$showError->getMessage());
    }
  }

  /** @return array<string,array<int,string>> */
  private static function officialColumns(): array {
    $map = [];
    foreach (glob(__DIR__.'/../../database/modules/*.sql') ?: [] as $path) {
      $sql = (string)file_get_contents($path);
      if (!preg_match_all('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?([a-zA-Z0-9_]+)`?\s*\((.*?)\)\s*ENGINE\s*=\s*InnoDB/is', $sql, $matches, PREG_SET_ORDER)) continue;
      foreach ($matches as $m) {
        $table = $m[1];
        $body = $m[2];
        $cols = [];
        foreach (preg_split('/\n/', $body) as $line) {
          $line = trim($line);
          if (preg_match('/^`?([a-zA-Z0-9_]+)`?\s+(?:BIGINT|INT|VARCHAR|CHAR|TEXT|LONGTEXT|DATETIME|DATE|TIMESTAMP|DECIMAL|ENUM|JSON|TINYINT|DOUBLE|FLOAT)\b/i', $line, $cm)) {
            $cols[] = $cm[1];
          }
        }
        $map[$table] = array_values(array_unique($cols));
      }
    }
    return $map;
  }

  private static function safeMessage(string $message): string {
    $message = preg_replace('/password\s*=\s*[^;\s]+/i', 'password=***', $message) ?? $message;
    $message = preg_replace('/SQLSTATE\[[^\]]+\]:\s*/i', '', $message) ?? $message;
    return mb_substr($message, 0, 220, 'UTF-8');
  }
}
