-- V104.12 - Registro de correção de banco único/modular e SchemaGuard rescue
CREATE TABLE IF NOT EXISTS schema_migrations (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  migration VARCHAR(180) NOT NULL UNIQUE,
  checksum VARCHAR(128) NULL,
  status ENUM('aplicada','falha') DEFAULT 'aplicada',
  mensagem TEXT NULL,
  aplicada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_schema_migrations_status(status),
  INDEX idx_schema_migrations_data(aplicada_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations(migration, checksum, status, mensagem)
VALUES('v104_12_schema_guard_rescue','v104.12','aplicada','Correção de resolução banco único/modular e SchemaGuard rescue.')
ON DUPLICATE KEY UPDATE checksum=VALUES(checksum), status=VALUES(status), mensagem=VALUES(mensagem), aplicada_em=CURRENT_TIMESTAMP;
