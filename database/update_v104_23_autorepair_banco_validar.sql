-- V104.23 - AutoRepair forte para Validar Banco/SchemaGuard
-- Esta migração registra a versão. A correção principal é em código PHP:
-- app/Services/DatabaseAutoRepairService.php
-- app/Services/DatabaseValidationService.php
-- app/Services/DatabaseMapService.php

CREATE TABLE IF NOT EXISTS schema_migrations (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  migration VARCHAR(160) NOT NULL UNIQUE,
  checksum VARCHAR(128) NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'aplicada',
  mensagem TEXT NULL,
  aplicada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO schema_migrations(migration, checksum, status, mensagem)
VALUES('v104_23_autorepair_banco_validar', SHA2('v104_23_autorepair_banco_validar', 256), 'aplicada', 'AutoRepair forte executa SQL modular/current antes da validação de banco.')
ON DUPLICATE KEY UPDATE status='aplicada', mensagem=VALUES(mensagem), aplicada_em=CURRENT_TIMESTAMP;
