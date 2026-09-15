<?php
/**
 * V104.22 - Guard de schema específico para Backups com compatibilidade total de colunas legadas.
 *
 * Corrige instalações antigas/parciais onde backups_banco existe com colunas
 * diferentes da tela atual ou ainda não existe no banco único/modular.
 */
class BackupSchemaService {
  public static function ensure(): void {
    $pdo = Database::forTable('backups_banco');

    $pdo->exec("CREATE TABLE IF NOT EXISTS backups_banco (
      id BIGINT AUTO_INCREMENT PRIMARY KEY,
      usuario_id INT NULL,
      arquivo VARCHAR(255) NULL,
      nome_arquivo VARCHAR(255) NULL,
      caminho VARCHAR(500) NULL,
      tamanho_bytes BIGINT DEFAULT 0,
      hash_sha256 VARCHAR(128) NULL,
      hmac_sha256 VARCHAR(128) NULL,
      trust_score INT NOT NULL DEFAULT 0,
      status VARCHAR(40) NOT NULL DEFAULT 'sucesso',
      mensagem TEXT NULL,
      trace_id VARCHAR(80) NULL,
      criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_backup_data(criado_em),
      INDEX idx_backup_status(status),
      INDEX idx_backup_trace(trace_id),
      INDEX idx_backup_proveniencia(proveniencia)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Colunas mínimas usadas por controller, view, restore e auditoria.
    self::addColumn('usuario_id', 'INT NULL');
    self::addColumn('arquivo', 'VARCHAR(255) NULL');
    // Compatibilidade permanente: versões antigas do controller/view consultavam nome_arquivo/caminho.
    // Mantemos as colunas para evitar SQLSTATE[42S22] em cache, deploy parcial ou classmap antigo.
    self::addColumn('nome_arquivo', 'VARCHAR(255) NULL');
    self::addColumn('caminho', 'VARCHAR(500) NULL');
    self::addColumn('tamanho_bytes', 'BIGINT DEFAULT 0');
    self::addColumn('hash_sha256', 'VARCHAR(128) NULL');
    self::addColumn('hmac_sha256', 'VARCHAR(128) NULL');
    self::addColumn('trust_score', 'INT NOT NULL DEFAULT 0');
    self::addColumn('status', "VARCHAR(40) NOT NULL DEFAULT 'sucesso'");
    // Melhoria 9 da seção 8: proveniência consultável (a fonte autoritativa continua sendo a
    // assinatura .sig.json, protegida por HMAC — esta coluna é para listagem, filtro e relatório).
    self::addColumn('proveniencia', "VARCHAR(32) NOT NULL DEFAULT 'local_generated'");
    self::addColumn('mensagem', 'TEXT NULL');
    self::addColumn('trace_id', 'VARCHAR(80) NULL');
    self::addColumn('criado_em', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');

    // Compatibilidade com versões que gravavam nome_arquivo/caminho.
    self::migrateLegacyColumns($pdo);

    Database::addIndexIfMissing('backups_banco', 'idx_backup_data', 'INDEX idx_backup_data(criado_em)');
    Database::addIndexIfMissing('backups_banco', 'idx_backup_status', 'INDEX idx_backup_status(status)');
    Database::addIndexIfMissing('backups_banco', 'idx_backup_trace', 'INDEX idx_backup_trace(trace_id)');
    Database::addIndexIfMissing('backups_banco', 'idx_backup_proveniencia', 'INDEX idx_backup_proveniencia(proveniencia)');

    $pdo->exec("CREATE TABLE IF NOT EXISTS backups (
      id BIGINT AUTO_INCREMENT PRIMARY KEY,
      arquivo VARCHAR(255) NOT NULL,
      tamanho BIGINT DEFAULT 0,
      status VARCHAR(40) DEFAULT 'gerado',
      criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    try { Database::recordMigration('v104_22_backup_compatibilidade_nome_arquivo', 'backup_schema_guard', 'Compatibilidade permanente arquivo/nome_arquivo/caminho na tela Backups.'); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
  }

  private static function addColumn(string $column, string $definition): void {
    try { Database::addColumnIfMissing('backups_banco', $column, $definition); }
    catch (PDOException $e) {
      $msg = strtolower($e->getMessage());
      // Em instalações antigas, algumas colunas podem existir com tipo diferente. Não derruba a tela.
      if (str_contains($msg, 'duplicate column') || str_contains($msg, '1060')) return;
      throw $e;
    }
  }

  private static function migrateLegacyColumns(PDO $pdo): void {
    try {
      if (Database::columnExistsOn($pdo, 'backups_banco', 'nome_arquivo')) {
        $pdo->exec("UPDATE backups_banco SET arquivo = nome_arquivo WHERE (arquivo IS NULL OR arquivo = '') AND nome_arquivo IS NOT NULL AND nome_arquivo <> ''");
      }
      if (Database::columnExistsOn($pdo, 'backups_banco', 'caminho')) {
        $pdo->exec("UPDATE backups_banco SET arquivo = SUBSTRING_INDEX(caminho, '/', -1) WHERE (arquivo IS NULL OR arquivo = '') AND caminho IS NOT NULL AND caminho <> ''");
      }
      $pdo->exec("UPDATE backups_banco SET arquivo = CONCAT('backup_registro_', id, '.zip') WHERE arquivo IS NULL OR arquivo = ''");
      // Sincroniza colunas antigas e novas para qualquer trecho legado continuar seguro.
      $pdo->exec("UPDATE backups_banco SET nome_arquivo = arquivo WHERE (nome_arquivo IS NULL OR nome_arquivo = '') AND arquivo IS NOT NULL AND arquivo <> ''");
      $pdo->exec("UPDATE backups_banco SET caminho = CONCAT('storage/backups/', arquivo) WHERE (caminho IS NULL OR caminho = '') AND arquivo IS NOT NULL AND arquivo <> ''");
      $pdo->exec("UPDATE backups_banco SET status = 'sucesso' WHERE status IS NULL OR status = ''");
    } catch (Throwable $e) {
      if(class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e, ['table'=>'backups_banco']);
    }
  }

  public static function list(int $limit = 100): array {
    self::ensure();
    $limit = max(1, min(500, $limit));
    $pdo = Database::forTable('backups_banco');
    $sql = "SELECT id, arquivo, nome_arquivo, caminho, tamanho_bytes, hash_sha256, hmac_sha256, trust_score, status, mensagem, trace_id, criado_em
            FROM backups_banco
            ORDER BY id DESC
            LIMIT {$limit}";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
}
