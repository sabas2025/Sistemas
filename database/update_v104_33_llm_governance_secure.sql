-- V104.33 - Governança LLM segura, desativada por padrão, auditável e com aprovação humana.
CREATE TABLE IF NOT EXISTS llm_policy_settings (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(120) NOT NULL UNIQUE,
  setting_value LONGTEXT NULL,
  value_type ENUM('string','int','float','bool','json') DEFAULT 'string',
  updated_by BIGINT NULL,
  trace_id VARCHAR(80) NULL,
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_llm_policy_key(setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS llm_approval_queue (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  approval_uuid VARCHAR(80) NOT NULL UNIQUE,
  requester_user_id BIGINT NULL,
  approver_user_id BIGINT NULL,
  prompt_key VARCHAR(120) NULL,
  provider VARCHAR(60) NULL,
  model VARCHAR(120) NULL,
  environment VARCHAR(30) DEFAULT 'homologacao',
  risk_level ENUM('baixo','medio','alto','critico') DEFAULT 'baixo',
  status ENUM('pendente','aprovado','rejeitado','expirado','cancelado') DEFAULT 'pendente',
  input_hash VARCHAR(128) NULL,
  redacted_context LONGTEXT NULL,
  reason TEXT NULL,
  decision_reason TEXT NULL,
  trace_id VARCHAR(80) NULL,
  expires_at DATETIME NULL,
  decided_at DATETIME NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_llm_approval_status(status, criado_em),
  INDEX idx_llm_approval_trace(trace_id),
  INDEX idx_llm_approval_risk(risk_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS llm_usage_daily (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  usage_date DATE NOT NULL,
  provider VARCHAR(60) NOT NULL DEFAULT 'none',
  model VARCHAR(120) NULL,
  environment VARCHAR(30) NOT NULL DEFAULT 'homologacao',
  requests INT NOT NULL DEFAULT 0,
  blocked_requests INT NOT NULL DEFAULT 0,
  tokens_input BIGINT NOT NULL DEFAULT 0,
  tokens_output BIGINT NOT NULL DEFAULT 0,
  cost_estimate DECIMAL(12,6) NOT NULL DEFAULT 0,
  trace_id VARCHAR(80) NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_llm_usage_day_provider_model_env(usage_date, provider, model, environment),
  INDEX idx_llm_usage_date(usage_date),
  INDEX idx_llm_usage_env(environment)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Colunas novas em llm_audit_logs são aplicadas de forma idempotente pelo SchemaMigrationService::applyEnterpriseCore().
