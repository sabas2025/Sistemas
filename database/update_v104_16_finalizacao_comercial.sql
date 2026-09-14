-- V104.16 - Limpeza final, versão centralizada e preparação produção comercial
-- Seguro para bases existentes quando executado no MySQL/MariaDB via phpMyAdmin.
-- Para aplicação automática pelo painel, prefira Central Técnica > Validar Banco/SchemaGuard.

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

CREATE TABLE IF NOT EXISTS comercial_clientes_licencas (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  cliente_nome VARCHAR(180) NOT NULL,
  documento VARCHAR(32) NULL,
  email_responsavel VARCHAR(180) NULL,
  plano VARCHAR(80) NOT NULL DEFAULT 'profissional',
  status VARCHAR(30) NOT NULL DEFAULT 'trial',
  ambiente VARCHAR(30) NOT NULL DEFAULT 'homologacao',
  limite_empresas INT NOT NULL DEFAULT 1,
  limite_filiais INT NOT NULL DEFAULT 3,
  limite_conectores INT NOT NULL DEFAULT 2,
  data_inicio DATE NULL,
  data_expiracao DATE NULL,
  license_key_hash VARCHAR(128) NULL,
  licenca_origem VARCHAR(40) NOT NULL DEFAULT 'manual',
  ultimo_check_em TIMESTAMP NULL DEFAULT NULL,
  bloquear_ao_vencer TINYINT(1) NOT NULL DEFAULT 0,
  assinatura_hmac VARCHAR(128) NULL,
  observacoes TEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_comercial_cliente_documento (documento),
  INDEX idx_comercial_licenca_status_expira (status,data_expiracao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @db = DATABASE();
SET @exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='comercial_clientes_licencas' AND COLUMN_NAME='licenca_origem');
SET @sql = IF(@exists=0, 'ALTER TABLE comercial_clientes_licencas ADD COLUMN licenca_origem VARCHAR(40) NOT NULL DEFAULT ''manual''', 'SELECT ''licenca_origem existe''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='comercial_clientes_licencas' AND COLUMN_NAME='ultimo_check_em');
SET @sql = IF(@exists=0, 'ALTER TABLE comercial_clientes_licencas ADD COLUMN ultimo_check_em TIMESTAMP NULL DEFAULT NULL', 'SELECT ''ultimo_check_em existe''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='comercial_clientes_licencas' AND COLUMN_NAME='bloquear_ao_vencer');
SET @sql = IF(@exists=0, 'ALTER TABLE comercial_clientes_licencas ADD COLUMN bloquear_ao_vencer TINYINT(1) NOT NULL DEFAULT 0', 'SELECT ''bloquear_ao_vencer existe''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='comercial_clientes_licencas' AND COLUMN_NAME='assinatura_hmac');
SET @sql = IF(@exists=0, 'ALTER TABLE comercial_clientes_licencas ADD COLUMN assinatura_hmac VARCHAR(128) NULL', 'SELECT ''assinatura_hmac existe''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='comercial_clientes_licencas' AND INDEX_NAME='idx_comercial_licenca_status_expira');
SET @sql = IF(@exists=0, 'ALTER TABLE comercial_clientes_licencas ADD INDEX idx_comercial_licenca_status_expira (status,data_expiracao)', 'SELECT ''idx_comercial_licenca_status_expira existe''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO schema_migrations(migration,checksum,status,mensagem)
VALUES('v104_16_finalizacao_comercial','schema_v104_16','aplicada','Versão centralizada, licenciamento, conectores plugáveis e produção comercial.')
ON DUPLICATE KEY UPDATE checksum=VALUES(checksum), status=VALUES(status), mensagem=VALUES(mensagem), aplicada_em=CURRENT_TIMESTAMP;
