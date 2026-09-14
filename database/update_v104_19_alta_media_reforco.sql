
-- V104.19 - Alta prioridade técnica + média prioridade comercial reforçadas
CREATE TABLE IF NOT EXISTS comercial_license_remote_cache (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  status VARCHAR(40) NOT NULL,
  mensagem TEXT NULL,
  payload_hash CHAR(64) NULL,
  payload_json LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_license_remote_status (status),
  INDEX idx_license_remote_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS comercial_billing_provider_events (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  provider VARCHAR(60) NOT NULL DEFAULT 'manual',
  evento VARCHAR(100) NOT NULL,
  status VARCHAR(40) NOT NULL,
  referencia VARCHAR(120) NULL,
  payload_hash CHAR(64) NULL,
  payload_json LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_billing_provider (provider),
  INDEX idx_billing_provider_status (status),
  INDEX idx_billing_provider_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS comercial_demo_reset_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  status VARCHAR(40) NOT NULL,
  mensagem TEXT NULL,
  contexto_json LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_demo_reset_status (status),
  INDEX idx_demo_reset_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS connector_operational_checks (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  total INT NOT NULL DEFAULT 0,
  ok INT NOT NULL DEFAULT 0,
  alerta INT NOT NULL DEFAULT 0,
  erro INT NOT NULL DEFAULT 0,
  snapshot_json LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_connector_check_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS system_release_checks (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  version VARCHAR(40) NOT NULL,
  status VARCHAR(40) NOT NULL,
  score INT NOT NULL DEFAULT 0,
  snapshot_json LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_release_checks_version (version),
  INDEX idx_release_checks_status (status),
  INDEX idx_release_checks_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO schema_migrations(migration, checksum, status, mensagem)
VALUES('v104_19_refatoracao_comercial_tecnica', SHA2('v104_19_refatoracao_comercial_tecnica',256), 'aplicada', 'Refatoração técnica e maturidade comercial V104.19 aplicada.');
