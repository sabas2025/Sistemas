-- V104.7 - SchemaGuard
-- Este arquivo é intencionalmente seguro e não usa ALTER condicional incompatível.
-- A correção idempotente de tabelas/colunas é executada pelo PHP em:
-- Central Técnica > Validar Banco
-- Serviço: app/Services/DatabaseSchemaGuardService.php

CREATE TABLE IF NOT EXISTS schema_migrations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  migration VARCHAR(120) NOT NULL UNIQUE,
  checksum VARCHAR(64) NULL,
  status ENUM('pendente','aplicada','falha') DEFAULT 'aplicada',
  mensagem TEXT NULL,
  aplicada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations(migration,checksum,status,mensagem)
VALUES('v104_7_schema_guard','v104.7','aplicada','Suba os arquivos V104.7 e execute Central Técnica > Validar Banco para reparo idempotente.');
