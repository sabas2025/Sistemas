
-- V104.18 - Fechamento prioridade alta/média comercial
CREATE TABLE IF NOT EXISTS comercial_license_checks (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  status VARCHAR(40) NOT NULL,
  mensagem TEXT NULL,
  contexto_json LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_license_checks_status (status),
  INDEX idx_license_checks_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS comercial_billing_gateway_events (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tipo VARCHAR(80) NOT NULL,
  status VARCHAR(40) NOT NULL,
  mensagem TEXT NULL,
  contexto_json LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_billing_gateway_tipo (tipo),
  INDEX idx_billing_gateway_status (status),
  INDEX idx_billing_gateway_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tenant_scope_audit_snapshots (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  status VARCHAR(40) NOT NULL,
  resumo TEXT NULL,
  snapshot_json LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tenant_scope_status (status),
  INDEX idx_tenant_scope_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO schema_migrations(migration, checksum, status, mensagem)
VALUES('v104_18_prioridades_alta_media_final', SHA2('v104_18_prioridades_alta_media_final',256), 'aplicada', 'Fechamento prioridades alta/média, licença remota, billing, tenant auditável e CI/CD.');
