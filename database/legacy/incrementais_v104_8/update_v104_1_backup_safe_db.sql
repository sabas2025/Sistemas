-- V104.1 - Correção do backup com SafeDb em ambiente modular/banco único.
-- Esta atualização é principalmente de código. O SQL abaixo só garante tabelas técnicas
-- caso a instalação anterior não tenha aplicado módulos corretamente.

CREATE TABLE IF NOT EXISTS audit_daily_signatures (
  id INT AUTO_INCREMENT PRIMARY KEY,
  audit_date DATE NOT NULL,
  first_audit_id INT NULL,
  last_audit_id INT NULL,
  event_count INT NOT NULL DEFAULT 0,
  sha256 CHAR(64) NOT NULL,
  hmac CHAR(64) NOT NULL,
  signature_file VARCHAR(255) NULL,
  trace_id VARCHAR(64) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_audit_daily_signatures_date (audit_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS backups_banco (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NULL,
  arquivo VARCHAR(255) NOT NULL,
  tamanho_bytes BIGINT DEFAULT 0,
  status VARCHAR(30) DEFAULT 'sucesso',
  mensagem TEXT NULL,
  trace_id VARCHAR(64) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
