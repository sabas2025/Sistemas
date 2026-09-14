-- V102 - SOC, anti-replay, auditoria diária, token vault e circuit breakers independentes
CREATE TABLE IF NOT EXISTS token_vault (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  provider VARCHAR(40) NOT NULL,
  ambiente VARCHAR(30) NOT NULL DEFAULT 'homologacao',
  token_type VARCHAR(40) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  ciphertext LONGTEXT NOT NULL,
  version INT NOT NULL DEFAULT 1,
  expires_at DATETIME NULL,
  rotated_at DATETIME NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  trace_id VARCHAR(80) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_vault_provider (provider, ambiente, token_type, active),
  INDEX idx_vault_exp (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS integration_replay_guard (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  origem VARCHAR(40) NOT NULL,
  rota VARCHAR(180) NULL,
  request_hash CHAR(64) NOT NULL,
  request_time DATETIME NOT NULL,
  trace_id VARCHAR(80) NULL,
  ip VARCHAR(80) NULL,
  status ENUM('aceito','bloqueado') DEFAULT 'aceito',
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_replay_origem_hash (origem, request_hash),
  INDEX idx_replay_time (request_time),
  INDEX idx_replay_trace (trace_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_daily_signatures (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  audit_date DATE NOT NULL UNIQUE,
  first_audit_id BIGINT NULL,
  last_audit_id BIGINT NULL,
  event_count INT NOT NULL DEFAULT 0,
  sha256 CHAR(64) NOT NULL,
  hmac CHAR(64) NOT NULL,
  signature_file VARCHAR(180) NULL,
  trace_id VARCHAR(80) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_daily_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO circuit_breakers(sistema,status) VALUES
('vsm','fechado'),('tiny_v2','fechado'),('tiny_v3','fechado'),('fiscal','fechado');

INSERT IGNORE INTO permissoes_perfil(perfil,modulo,acao,permitido) VALUES
('admin','seguranca','visualizar',1),('admin','seguranca','gerenciar',1),('gerente','seguranca','visualizar',1),('operador','seguranca','visualizar',0);
