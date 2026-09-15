-- V104.24 - Correção AutoRepair/config.php/db_storage_mode
-- Objetivo: evitar Warning 1265 em configuracoes_integracao.ambiente e registrar migração.

CREATE TABLE IF NOT EXISTS schema_migrations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  migration VARCHAR(180) NOT NULL UNIQUE,
  checksum VARCHAR(128) NULL,
  status ENUM('aplicada','falha') DEFAULT 'aplicada',
  mensagem TEXT NULL,
  aplicada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_schema_migrations_status(status),
  INDEX idx_schema_migrations_data(aplicada_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE configuracoes_integracao MODIFY ambiente VARCHAR(30) DEFAULT 'homologacao';
ALTER TABLE configuracoes_integracao MODIFY tiny_versao VARCHAR(10) DEFAULT 'v2';
ALTER TABLE configuracoes_integracao MODIFY tiny_v3_ambiente VARCHAR(30) DEFAULT 'homologacao';

UPDATE configuracoes_integracao
SET ambiente = 'homologacao'
WHERE ambiente IS NULL OR ambiente = '' OR ambiente LIKE '{%';

UPDATE configuracoes_integracao
SET tiny_versao = 'v2'
WHERE tiny_versao IS NULL OR tiny_versao = '' OR tiny_versao LIKE '{%';

UPDATE configuracoes_integracao
SET tiny_v3_ambiente = 'homologacao'
WHERE tiny_v3_ambiente IS NULL OR tiny_v3_ambiente = '' OR tiny_v3_ambiente LIKE '{%';

INSERT INTO schema_migrations(migration, checksum, status, mensagem)
VALUES('v104_24_autorepair_config_db_storage_mode', SHA2('v104_24_autorepair_config_db_storage_mode', 256), 'aplicada', 'AutoRepair substitui placeholders, diagnostica db_storage_mode e normaliza ambiente/tiny_versao.')
ON DUPLICATE KEY UPDATE status='aplicada', mensagem=VALUES(mensagem), aplicada_em=CURRENT_TIMESTAMP;
