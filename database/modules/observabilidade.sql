-- V104.49.3-R5 (2026-08-22) - Estrutura modular consolidada do banco: observabilidade
-- Execute no banco do módulo correspondente.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS logs_integracao (
  -- Auditoria de capacidade 2026-09-14 (achado C-02): a chave era INT (teto 2.147.483.647). A 500
  -- pedidos/min esta tabela estoura em ~2 anos, e AUTO_INCREMENT nao reaproveita id de linha apagada,
  -- entao expurgo de retencao nao adia nada. No estouro o INSERT falha com "Duplicate entry
  -- '2147483647' for key 'PRIMARY'" e a entrada de trabalho morre por inteiro. BIGINT segue a
  -- convencao dominante do schema (97 das 100 PKs grandes) e casa com as colunas que a referenciam.
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NULL,
  tipo VARCHAR(50) NOT NULL,
  nivel ENUM('info','alerta','erro','critico') DEFAULT 'info',
  codigo_erro VARCHAR(80) NULL,
  mensagem TEXT NOT NULL,
  payload LONGTEXT NULL,
  ip VARCHAR(45) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_logs_tipo(tipo),
  INDEX idx_logs_nivel(nivel),
  INDEX idx_logs_data(criado_em),
  INDEX idx_logs_trace(trace_id),
  INDEX idx_logs_codigo(codigo_erro),
  -- Auditoria de capacidade 2026-09-14 (achado C-05): com 100 clientes na mesma instalacao, os
  -- logs de integracao de todos ficavam no mesmo balde sem filtro - quem investigasse o cliente A
  -- via linhas do cliente B. auditoria_eventos e security_events seguem GLOBAIS de proposito (sao
  -- a trilha forense da instalacao); logs_integracao e operacional, por pedido, por cliente.
  empresa_id INT NULL,
  INDEX idx_logs_integracao_empresa(empresa_id),
  INDEX idx_logs_empresa_data(empresa_id,criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS auditoria_eventos (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NOT NULL,
  usuario_id INT NULL,
  acao VARCHAR(120) NOT NULL,
  entidade VARCHAR(80) NULL,
  entidade_id VARCHAR(120) NULL,
  status ENUM('sucesso','erro','alerta','info') DEFAULT 'info',
  nivel ENUM('info','alerta','erro','critico') DEFAULT 'info',
  codigo_erro VARCHAR(80) NULL,
  mensagem TEXT NULL,
  causa_provavel TEXT NULL,
  acao_recomendada TEXT NULL,
  payload LONGTEXT NULL,
  retorno LONGTEXT NULL,
  contexto LONGTEXT NULL,
  ip VARCHAR(45) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_trace(trace_id),
  INDEX idx_audit_acao(acao),
  INDEX idx_audit_status(status),
  INDEX idx_audit_entidade(entidade, entidade_id),
  INDEX idx_audit_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS notificacoes (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tipo ENUM('pedido_novo','pedido_integrado','erro_integracao','fila','estoque','baixa_estoque','produto_novo','integracao_sucesso','nota_fiscal','sistema') DEFAULT 'sistema',
  titulo VARCHAR(160) NOT NULL,
  mensagem TEXT NOT NULL,
  severidade ENUM('info','sucesso','alerta','erro','critico') DEFAULT 'info',
  entidade VARCHAR(80) NULL,
  entidade_id VARCHAR(120) NULL,
  link VARCHAR(255) NULL,
  trace_id VARCHAR(80) NULL,
  payload LONGTEXT NULL,
  lida TINYINT DEFAULT 0,
  lida_em DATETIME NULL,
  criada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notif_lida(lida),
  INDEX idx_notif_tipo(tipo),
  INDEX idx_notif_sev(severidade),
  INDEX idx_notif_trace(trace_id),
  INDEX idx_notif_data(criada_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS notificacoes_config (
  id INT AUTO_INCREMENT PRIMARY KEY,
  painel TINYINT DEFAULT 1,
  som TINYINT DEFAULT 1,
  browser_push TINYINT DEFAULT 1,
  email TINYINT DEFAULT 0,
  whatsapp TINYINT DEFAULT 0,
  pedido_novo TINYINT DEFAULT 1,
  erro_integracao TINYINT DEFAULT 1,
  fila_parada TINYINT DEFAULT 1,
  estoque_alerta TINYINT DEFAULT 1,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO notificacoes_config(id) VALUES(1);


CREATE TABLE IF NOT EXISTS diagnostico_api (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  sistema VARCHAR(30) NOT NULL,
  endpoint VARCHAR(255) NULL,
  status ENUM('online','atencao','erro') DEFAULT 'atencao',
  http_code INT NULL,
  tempo_ms INT NULL,
  mensagem TEXT NULL,
  detalhes LONGTEXT NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_diag_sistema(sistema),
  INDEX idx_diag_status(status),
  INDEX idx_diag_data(criado_em),
  INDEX idx_diag_trace(trace_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS tiny_v3_endpoint_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  endpoint VARCHAR(180) NULL,
  metodo VARCHAR(10) NULL,
  http_code INT NULL,
  sucesso TINYINT DEFAULT 0,
  tempo_ms INT NULL,
  trace_id VARCHAR(80) NULL,
  request_body LONGTEXT NULL,
  response_body LONGTEXT NULL,
  erro TEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tiny_v3_endpoint_logs_data(criado_em),
  INDEX idx_tiny_v3_endpoint_logs_trace(trace_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS metricas_api (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  sistema VARCHAR(40) NOT NULL,
  endpoint VARCHAR(255) NULL,
  metodo VARCHAR(10) DEFAULT 'POST',
  http_code INT NULL,
  tempo_ms INT NULL,
  sucesso TINYINT DEFAULT 0,
  codigo_erro VARCHAR(100) NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_metricas_sistema(sistema),
  INDEX idx_metricas_sucesso(sucesso),
  INDEX idx_metricas_data(criado_em),
  INDEX idx_metricas_trace(trace_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS security_audit (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NULL,
  usuario_id INT NULL,
  evento VARCHAR(120) NOT NULL,
  nivel ENUM('info','alerta','erro','critico') DEFAULT 'info',
  ip VARCHAR(45) NULL,
  user_agent TEXT NULL,
  session_id VARCHAR(128) NULL,
  detalhes LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sec_evento(evento),
  INDEX idx_sec_nivel(nivel),
  INDEX idx_sec_trace(trace_id),
  INDEX idx_sec_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS auditoria_timeline (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NOT NULL,
  fase VARCHAR(120) NOT NULL,
  status ENUM('info','sucesso','alerta','erro','critico') DEFAULT 'info',
  entidade VARCHAR(80) NULL,
  entidade_id VARCHAR(120) NULL,
  detalhes LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_timeline_trace(trace_id),
  INDEX idx_timeline_fase(fase),
  INDEX idx_timeline_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS auditoria_detalhes (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NOT NULL,
  entidade VARCHAR(80) NULL,
  entidade_id VARCHAR(120) NULL,
  antes_json LONGTEXT NULL,
  depois_json LONGTEXT NULL,
  diff_json LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_det_trace(trace_id),
  INDEX idx_audit_det_entidade(entidade, entidade_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS tiny_v2_endpoint_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NULL,
  endpoint VARCHAR(255) NULL,
  metodo VARCHAR(10) DEFAULT 'POST',
  http_code INT NULL,
  sucesso TINYINT DEFAULT 0,
  tempo_ms INT NULL,
  request_body LONGTEXT NULL,
  response_body LONGTEXT NULL,
  erro TEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tinyv2_trace(trace_id),
  INDEX idx_tinyv2_endpoint(endpoint),
  INDEX idx_tinyv2_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS auditoria_assinaturas (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  auditoria_evento_id BIGINT NOT NULL,
  trace_id VARCHAR(80) NULL,
  hash_sha256 VARCHAR(128) NOT NULL,
  hash_anterior VARCHAR(128) NULL,
  hash_canonico LONGTEXT NULL,
  cadeia_valida TINYINT DEFAULT 1,
  algoritmo VARCHAR(30) DEFAULT 'sha256-chain',
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_auditoria_assinatura(auditoria_evento_id),
  INDEX idx_aud_sig_trace(trace_id),
  INDEX idx_aud_sig_hash(hash_sha256)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS audit_exports (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NULL,
  formato ENUM('html_pdf','csv','json') DEFAULT 'json',
  filtros LONGTEXT NULL,
  total_registros INT DEFAULT 0,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_exports_trace(trace_id),
  INDEX idx_audit_exports_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS auditoria_hash_chain (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  auditoria_id BIGINT NOT NULL,
  trace_id VARCHAR(80) NULL,
  hash_anterior CHAR(64) NOT NULL,
  hash_atual CHAR(64) NOT NULL,
  base_assinatura LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_auditoria_hash(auditoria_id),
  INDEX idx_chain_trace(trace_id),
  INDEX idx_chain_hash(hash_atual)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS hosting_checks (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  provedor VARCHAR(80) DEFAULT 'infinityfree',
  score INT DEFAULT 0,
  resultado_json LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_hosting_checks_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- V57: permissões production_ready/hosting ficam no módulo core.


-- V57: permissões de laboratório ficam no módulo core.



-- V44 - snapshots de testes do dashboard e health modular
CREATE TABLE IF NOT EXISTS dashboard_testes_execucoes (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tipo VARCHAR(60) NOT NULL DEFAULT 'dashboard',
  status ENUM('ok','erro','atencao') DEFAULT 'ok',
  total INT DEFAULT 0,
  erros INT DEFAULT 0,
  payload LONGTEXT NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_dash_test_tipo(tipo),
  INDEX idx_dash_test_trace(trace_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS module_health_snapshots (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  modulo VARCHAR(60) NOT NULL,
  status ENUM('ok','erro','atencao') DEFAULT 'ok',
  mensagem TEXT NULL,
  payload LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_module_health_modulo(modulo),
  INDEX idx_module_health_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- V58: log HTTP dos endpoints VSM no módulo observabilidade.
CREATE TABLE IF NOT EXISTS vsm_endpoint_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  endpoint_id INT NULL,
  chave VARCHAR(100) NULL,
  metodo_http VARCHAR(10) NOT NULL,
  url VARCHAR(600) NOT NULL,
  status_http INT NULL,
  tempo_ms INT NULL,
  sucesso TINYINT NOT NULL DEFAULT 0,
  erro TEXT NULL,
  resposta MEDIUMTEXT NULL,
  modo_teste_seguro TINYINT NOT NULL DEFAULT 1,
  trace_id VARCHAR(80) NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_vsm_logs_endpoint (endpoint_id),
  INDEX idx_vsm_logs_chave (chave),
  INDEX idx_vsm_logs_sucesso (sucesso),
  INDEX idx_vsm_logs_criado (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




-- V102 - Assinatura diária de auditoria por SHA256 + HMAC
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

-- V104.49.2-R4: tabelas Enterprise exigidas pelo Mapa do Banco.
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
