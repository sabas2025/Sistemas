-- V104.22 - Compatibilidade de Backups arquivo/nome_arquivo/caminho
-- Preferencial: acessar Central Técnica > Backups; o BackupSchemaService cria colunas faltantes de forma idempotente.
-- Este SQL garante a tabela base para instalação limpa.
CREATE TABLE IF NOT EXISTS backups_banco (
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
  INDEX idx_backup_trace(trace_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO schema_migrations(migration, checksum, status, mensagem)
VALUES('v104_22_backup_compatibilidade_nome_arquivo','backup_compat','aplicada','Compatibilidade arquivo/nome_arquivo/caminho em backups_banco')
ON DUPLICATE KEY UPDATE status='aplicada', mensagem=VALUES(mensagem);
