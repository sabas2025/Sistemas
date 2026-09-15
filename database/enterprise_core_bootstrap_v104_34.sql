-- V104.34 - Bootstrap Enterprise Core manual/seguro.
-- Use este arquivo quando Central Técnica > Enterprise Core mostrar tabelas PENDENTES.
-- Seguro/idempotente: usa CREATE TABLE IF NOT EXISTS e INSERT ... ON DUPLICATE.
-- Não apaga dados, não altera regras Tiny/VSM e não ativa IA automaticamente.

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

-- V104.32 Enterprise Core - migrações versionadas, event store, idempotência, observabilidade e LLM isolado.
CREATE TABLE IF NOT EXISTS integration_events (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  event_uuid VARCHAR(80) NOT NULL UNIQUE,
  fila_id BIGINT NULL,
  idempotency_key VARCHAR(190) NULL,
  source_system VARCHAR(40) NOT NULL,
  target_system VARCHAR(40) NOT NULL,
  entity_type VARCHAR(60) NOT NULL,
  entity_id VARCHAR(160) NULL,
  operation VARCHAR(80) NOT NULL,
  status ENUM('recebido','processando','sucesso','erro','ignorado','reprocessado') DEFAULT 'recebido',
  payload_hash VARCHAR(128) NULL,
  response_hash VARCHAR(128) NULL,
  error_code VARCHAR(120) NULL,
  error_message TEXT NULL,
  attempts INT DEFAULT 0,
  trace_id VARCHAR(80) NULL,
  metadata_json LONGTEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  finished_at DATETIME NULL,
  INDEX idx_ie_status_created(status, created_at),
  INDEX idx_ie_entity(entity_type, entity_id),
  INDEX idx_ie_trace(trace_id),
  INDEX idx_ie_idempotency(idempotency_key),
  INDEX idx_ie_source_target(source_system, target_system)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS integration_idempotency (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  idempotency_key VARCHAR(190) NOT NULL UNIQUE,
  entity_type VARCHAR(60) NULL,
  entity_id VARCHAR(160) NULL,
  operation VARCHAR(80) NULL,
  request_hash VARCHAR(128) NULL,
  response_hash VARCHAR(128) NULL,
  status ENUM('claimed','completed','failed','expired') DEFAULT 'claimed',
  trace_id VARCHAR(80) NULL,
  expires_at DATETIME NULL,
  metadata_json LONGTEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_idemp_status(status),
  INDEX idx_idemp_entity(entity_type, entity_id),
  INDEX idx_idemp_expires(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS worker_heartbeats (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  worker_name VARCHAR(120) NOT NULL UNIQUE,
  status ENUM('iniciando','rodando','ok','erro','parado') DEFAULT 'rodando',
  pid INT NULL,
  host VARCHAR(160) NULL,
  started_at DATETIME NULL,
  last_seen_at DATETIME NOT NULL,
  last_error TEXT NULL,
  metrics_json LONGTEXT NULL,
  trace_id VARCHAR(80) NULL,
  INDEX idx_worker_status(status),
  INDEX idx_worker_seen(last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS observability_snapshots (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  metric_key VARCHAR(160) NOT NULL,
  metric_value DECIMAL(18,4) NULL,
  status ENUM('ok','atencao','erro','critico') DEFAULT 'ok',
  tags_json LONGTEXT NULL,
  trace_id VARCHAR(80) NULL,
  captured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_obs_key_time(metric_key, captured_at),
  INDEX idx_obs_status(status),
  INDEX idx_obs_trace(trace_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS enterprise_quality_gates (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  gate_key VARCHAR(120) NOT NULL UNIQUE,
  status ENUM('ok','atencao','bloqueio') DEFAULT 'atencao',
  score INT DEFAULT 0,
  mensagem TEXT NULL,
  detalhes_json LONGTEXT NULL,
  trace_id VARCHAR(80) NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_eqg_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS llm_prompts (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  prompt_key VARCHAR(120) NOT NULL UNIQUE,
  version VARCHAR(40) NOT NULL DEFAULT '1.0.0',
  status ENUM('rascunho','ativo','arquivado') DEFAULT 'rascunho',
  template LONGTEXT NOT NULL,
  safety_rules LONGTEXT NULL,
  max_tokens INT DEFAULT 2048,
  temperature DECIMAL(4,2) DEFAULT 0.20,
  checksum VARCHAR(128) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_llm_prompts_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS llm_audit_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NULL,
  prompt_key VARCHAR(120) NULL,
  provider VARCHAR(60) NULL,
  model VARCHAR(120) NULL,
  environment VARCHAR(30) DEFAULT 'homologacao',
  user_id BIGINT NULL,
  risk_level ENUM('baixo','medio','alto','critico') DEFAULT 'baixo',
  status ENUM('bloqueado','permitido','erro','simulado') DEFAULT 'simulado',
  approval_required TINYINT(1) DEFAULT 1,
  real_execution_allowed TINYINT(1) DEFAULT 0,
  input_hash VARCHAR(128) NULL,
  output_hash VARCHAR(128) NULL,
  redacted_input_sample LONGTEXT NULL,
  tokens_input INT DEFAULT 0,
  tokens_output INT DEFAULT 0,
  cost_estimate DECIMAL(12,6) DEFAULT 0,
  findings_json LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_llm_trace(trace_id),
  INDEX idx_llm_risk(risk_level),
  INDEX idx_llm_prompt(prompt_key),
  INDEX idx_llm_env(environment),
  INDEX idx_llm_user(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- As colunas abaixo são aplicadas idempotentemente pelo SchemaMigrationService para bases existentes.
-- Em instalação nova, o SQL consolidado pode ser complementado pelo repair_current.sql.

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

INSERT INTO schema_migrations(migration, checksum, status, mensagem) VALUES
('v104_32_enterprise_core', SHA2('v104_32_enterprise_core', 256), 'aplicada', 'Enterprise Core V104.32 aplicado ou confirmado via bootstrap V104.34.'),
('v104_33_llm_governance_secure', SHA2('v104_33_llm_governance_secure', 256), 'aplicada', 'Governança LLM V104.33 aplicada ou confirmada via bootstrap V104.34.'),
('v104_34_enterprise_core_bootstrap', SHA2('v104_34_enterprise_core_bootstrap', 256), 'aplicada', 'Bootstrap Enterprise Core V104.34 aplicado manualmente.');
