<?php
class SafeSqlUpgradeService {

  public static function ensureMigrationsTable(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
      id BIGINT AUTO_INCREMENT PRIMARY KEY,
      migration VARCHAR(180) NOT NULL UNIQUE,
      checksum VARCHAR(128) NULL,
      status ENUM('aplicada','falha') DEFAULT 'aplicada',
      mensagem TEXT NULL,
      aplicada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_schema_migrations_status(status),
      INDEX idx_schema_migrations_data(aplicada_em)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  }

  public static function migrationApplied(PDO $pdo, string $migration): bool {
    self::ensureMigrationsTable($pdo);
    $st=$pdo->prepare("SELECT id FROM schema_migrations WHERE migration=? AND status='aplicada' LIMIT 1");
    $st->execute([$migration]);
    return (bool)$st->fetch();
  }

  public static function recordMigration(PDO $pdo, string $migration, string $checksum='', string $status='aplicada', string $mensagem=''): void {
    self::ensureMigrationsTable($pdo);
    $st=$pdo->prepare("INSERT INTO schema_migrations(migration,checksum,status,mensagem) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE checksum=VALUES(checksum), status=VALUES(status), mensagem=VALUES(mensagem), aplicada_em=CURRENT_TIMESTAMP");
    $st->execute([$migration,$checksum,$status,$mensagem]);
  }

  public static function addColumnIfMissing(PDO $pdo, string $table, string $column, string $definition): string {
    // Achado I-10: era `SHOW COLUMNS ... LIKE ?`, inválido com prepares nativos — e AQUI sem
    // catch, então a atualização assistida de schema morria antes do ALTER.
    if (Database::columnExistsOn($pdo, $table, $column)) return "IGNORADO: {$table}.{$column} já existe.";
    $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` {$definition}");
    return "OK: {$table}.{$column} adicionada.";
  }
  public static function tableExists(PDO $pdo, string $table): bool { return Database::tableExistsOn($pdo, $table); }
  public static function runStatements(PDO $pdo, string $sql): array {
    $out=[]; foreach(array_filter(array_map('trim', explode(';',$sql))) as $stmt){
      try { $pdo->exec($stmt); $out[]='OK: '.substr(preg_replace('/\s+/',' ',$stmt),0,140); }
      catch(Throwable $e){ $m=$e->getMessage(); if(stripos($m,'Duplicate')!==false || stripos($m,'already exists')!==false) $out[]='IGNORADO: '.$m; else $out[]='ERRO: '.$m; }
    } return $out;
  }
}
