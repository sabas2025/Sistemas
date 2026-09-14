-- V104.48.1-R1 - recuperação das tabelas comerciais e Enterprise Core ausentes.
-- Corrige bases atualizadas nas quais as migrations 001/002 adicionaram colunas,
-- mas não criaram todas as tabelas novas. Idempotente: não remove nem trunca dados.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS schema_migrations (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  migration VARCHAR(180) NOT NULL UNIQUE,
  version VARCHAR(40) NULL,
  description TEXT NULL,
  checksum VARCHAR(128) NULL,
  status ENUM('aplicada','falha') DEFAULT 'aplicada',
  mensagem TEXT NULL,
  aplicada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_schema_migrations_status(status),
  INDEX idx_schema_migrations_data(aplicada_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Compatibilidade com schema_migrations de versões antigas.
SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='schema_migrations' AND COLUMN_NAME='version')=0,
  'ALTER TABLE schema_migrations ADD COLUMN version VARCHAR(40) NULL', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='schema_migrations' AND COLUMN_NAME='description')=0,
  'ALTER TABLE schema_migrations ADD COLUMN description TEXT NULL', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='schema_migrations' AND COLUMN_NAME='checksum')=0,
  'ALTER TABLE schema_migrations ADD COLUMN checksum VARCHAR(128) NULL', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='schema_migrations' AND COLUMN_NAME='status')=0,
  'ALTER TABLE schema_migrations ADD COLUMN status ENUM(''aplicada'',''falha'') DEFAULT ''aplicada''', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='schema_migrations' AND COLUMN_NAME='mensagem')=0,
  'ALTER TABLE schema_migrations ADD COLUMN mensagem TEXT NULL', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='schema_migrations' AND COLUMN_NAME='aplicada_em')=0,
  'ALTER TABLE schema_migrations ADD COLUMN aplicada_em TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

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

CREATE TABLE IF NOT EXISTS comercial_conectores_catalogo (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(80) NOT NULL,
  nome VARCHAR(140) NOT NULL,
  categoria VARCHAR(80) NOT NULL DEFAULT 'erp',
  status VARCHAR(30) NOT NULL DEFAULT 'planejado',
  descricao TEXT NULL,
  requisitos TEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_comercial_conector_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS comercial_cobranca_faturas (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  cliente_licenca_id BIGINT NULL,
  descricao VARCHAR(220) NOT NULL,
  valor_centavos INT NOT NULL DEFAULT 0,
  status VARCHAR(30) NOT NULL DEFAULT 'aberta',
  vencimento DATE NULL,
  forma_pagamento VARCHAR(60) NULL,
  referencia_externa VARCHAR(120) NULL,
  observacoes TEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_comercial_fatura_status (status),
  INDEX idx_comercial_fatura_cliente (cliente_licenca_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS comercial_demo_ambientes (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(140) NOT NULL,
  url VARCHAR(255) NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'planejado',
  usa_dados_reais TINYINT(1) NOT NULL DEFAULT 0,
  observacoes TEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS comercial_suporte_chamados (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  cliente_licenca_id BIGINT NULL,
  titulo VARCHAR(220) NOT NULL,
  prioridade VARCHAR(30) NOT NULL DEFAULT 'normal',
  status VARCHAR(30) NOT NULL DEFAULT 'aberto',
  sla_resposta_horas INT NOT NULL DEFAULT 8,
  sla_resolucao_horas INT NOT NULL DEFAULT 48,
  aberto_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  prazo_resposta_em DATETIME NULL,
  prazo_resolucao_em DATETIME NULL,
  fechado_em DATETIME NULL,
  observacoes TEXT NULL,
  INDEX idx_suporte_status (status),
  INDEX idx_suporte_cliente (cliente_licenca_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS comercial_sla_eventos (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  chamado_id BIGINT NULL,
  tipo VARCHAR(80) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'registrado',
  mensagem TEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sla_chamado (chamado_id),
  INDEX idx_sla_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS enterprise_regression_runs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NULL,
  score INT DEFAULT 0,
  total INT DEFAULT 0,
  ok_count INT DEFAULT 0,
  error_count INT DEFAULT 0,
  results_json LONGTEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_regression_trace(trace_id),
  INDEX idx_regression_created(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS enterprise_ui_preferences (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NULL,
  profile_key VARCHAR(80) NOT NULL DEFAULT 'default',
  density ENUM('compact','comfortable','spacious') DEFAULT 'comfortable',
  theme ENUM('system','light','dark','futurista') DEFAULT 'futurista',
  settings_json LONGTEXT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_ui_user_profile(user_id, profile_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  claim_count INT DEFAULT 0,
  last_duplicate_at DATETIME NULL,
  original_fila_id BIGINT NULL,
  owner_token CHAR(64) NULL,
  locked_until DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_idemp_status(status),
  INDEX idx_idemp_entity(entity_type, entity_id),
  INDEX idx_idemp_expires(expires_at),
  INDEX idx_idemp_lock(status,locked_until)
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

INSERT INTO schema_migrations(migration,version,description,checksum,status,mensagem,aplicada_em)
VALUES('20260712_003_missing_core_tables','V104.48.1-R1','Cria tabelas comerciais e Enterprise Core ausentes em bases atualizadas',NULL,'aplicada','Migration 003 aplicada sem remoção de dados',CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE version=VALUES(version),description=VALUES(description),status='aplicada',mensagem=VALUES(mensagem),aplicada_em=CURRENT_TIMESTAMP;
