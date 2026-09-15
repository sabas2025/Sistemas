-- V42 - Estrutura modular do banco: fila
-- Execute no banco do módulo correspondente.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS fila_integracao (
  -- Auditoria de capacidade 2026-09-14 (achado C-02): a chave era INT (teto 2.147.483.647). A 500
  -- pedidos/min esta tabela estoura em ~2,7 anos, e AUTO_INCREMENT nao reaproveita id de linha apagada,
  -- entao expurgo de retencao nao adia nada. No estouro o INSERT falha com "Duplicate entry
  -- '2147483647' for key 'PRIMARY'" e a entrada de trabalho morre por inteiro. BIGINT segue a
  -- convencao dominante do schema (97 das 100 PKs grandes) e casa com as colunas que a referenciam.
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tipo VARCHAR(50) NOT NULL,
  prioridade ENUM('critica','alta','normal','baixa') DEFAULT 'normal',
  categoria VARCHAR(60) NULL,
  referencia VARCHAR(120) NULL,
  payload LONGTEXT NULL,
  retorno LONGTEXT NULL,
  status ENUM('pendente','processando','sucesso','erro','falha_definitiva','ignorado') DEFAULT 'pendente',
  codigo_erro VARCHAR(80) NULL,
  tentativas INT DEFAULT 0,
  proxima_tentativa DATETIME NULL,
  processando_desde DATETIME NULL,
  processado_em DATETIME NULL,
  idempotency_key VARCHAR(190) NULL,
  locked_by VARCHAR(120) NULL,
  locked_at DATETIME NULL,
  lease_expires_at DATETIME NULL,
  heartbeat_at DATETIME NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_fila_status(status),
  INDEX idx_fila_codigo(codigo_erro),
  INDEX idx_fila_tipo(tipo),
  INDEX idx_fila_prioridade(prioridade,status),
  INDEX idx_fila_idempotency_key(idempotency_key),
  INDEX idx_fila_status_proxima_prioridade(status,proxima_tentativa,prioridade,id),
  INDEX idx_fila_locked(locked_by,locked_at),
  INDEX idx_fila_lease(status,lease_expires_at),
  empresa_id INT NULL,
  INDEX idx_fila_integracao_empresa(empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS fila_reprocessamento_historico (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  fila_id BIGINT NOT NULL,
  status_anterior VARCHAR(40) NULL,
  tentativas_anteriores INT DEFAULT 0,
  codigo_erro_anterior VARCHAR(120) NULL,
  retorno_anterior LONGTEXT NULL,
  locked_by_anterior VARCHAR(120) NULL,
  solicitado_por BIGINT NULL,
  motivo VARCHAR(500) NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_fila_reproc_fila(fila_id),
  INDEX idx_fila_reproc_trace(trace_id),
  INDEX idx_fila_reproc_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS fila_morta (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  fila_id BIGINT NULL,
  tipo VARCHAR(80) NOT NULL,
  referencia VARCHAR(160) NULL,
  payload_original LONGTEXT NULL,
  ultimo_retorno LONGTEXT NULL,
  codigo_erro VARCHAR(100) NULL,
  motivo TEXT NULL,
  tentativas INT DEFAULT 0,
  trace_id VARCHAR(80) NULL,
  status ENUM('aberto','reprocessado','ignorado','resolvido') DEFAULT 'aberto',
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_fila_morta_fila (fila_id),
  INDEX idx_fila_morta_status(status),
  INDEX idx_fila_morta_tipo(tipo),
  INDEX idx_fila_morta_trace(trace_id),
  INDEX idx_fila_morta_data(criado_em),
  empresa_id INT NULL,
  INDEX idx_fila_morta_empresa(empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS payload_snapshots (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NULL,
  fila_id BIGINT NULL,
  origem VARCHAR(60) NULL,
  destino VARCHAR(60) NULL,
  referencia VARCHAR(160) NULL,
  etapa ENUM('original','transformado','enviado','resposta','erro') DEFAULT 'original',
  conteudo LONGTEXT NULL,
  hash_conteudo VARCHAR(128) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_snapshot_trace(trace_id),
  INDEX idx_snapshot_fila(fila_id),
  INDEX idx_snapshot_etapa(etapa),
  INDEX idx_snapshot_ref(referencia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS circuit_breakers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sistema VARCHAR(40) NOT NULL UNIQUE,
  status ENUM('fechado','aberto','meio_aberto') DEFAULT 'fechado',
  falhas_consecutivas INT DEFAULT 0,
  aberto_ate DATETIME NULL,
  ultima_falha TEXT NULL,
  ultimo_sucesso_em DATETIME NULL,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_cb_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;




INSERT IGNORE INTO circuit_breakers(sistema,status) VALUES
('vsm','fechado'),
('tiny_v2','fechado'),
('tiny_v3','fechado'),
('fiscal','fechado');

CREATE TABLE IF NOT EXISTS fila_analytics_snapshots (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  snapshot_json LONGTEXT NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_fila_snap_trace(trace_id),
  INDEX idx_fila_snap_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- V60 - Fila fiscal separada para reenvio/reprocessamento de NF-e/XML
CREATE TABLE IF NOT EXISTS fila_fiscal (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  nota_fiscal_id BIGINT NULL,
  nfe_integracao_id BIGINT NULL,
  acao ENUM('validar_xml','enviar_tiny','enviar_vsm','reprocessar','consultar_status') DEFAULT 'reprocessar',
  prioridade ENUM('critica','alta','normal','baixa') DEFAULT 'normal',
  status ENUM('pendente','processando','sucesso','erro','falha_definitiva','ignorado') DEFAULT 'pendente',
  tentativas INT DEFAULT 0,
  proxima_tentativa DATETIME NULL,
  payload LONGTEXT NULL,
  retorno LONGTEXT NULL,
  ultimo_erro TEXT NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_fila_fiscal_status(status),
  INDEX idx_fila_fiscal_nota(nota_fiscal_id),
  INDEX idx_fila_fiscal_integracao(nfe_integracao_id),
  INDEX idx_fila_fiscal_trace(trace_id),
  INDEX idx_fila_fiscal_proxima(proxima_tentativa),
  empresa_id INT NULL,
  INDEX idx_fila_fiscal_empresa(empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;




-- V104.8 - Anti replay com janela de tempo para integrações VSM/Tiny
CREATE TABLE IF NOT EXISTS integration_replay_guard (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  origem VARCHAR(40) NOT NULL,
  rota VARCHAR(180) NULL,
  request_hash CHAR(64) NOT NULL,
  payload_hash CHAR(64) NULL,
  time_bucket BIGINT UNSIGNED NOT NULL DEFAULT 0,
  request_time DATETIME NOT NULL,
  hmac_validated_at DATETIME NULL,
  trace_id VARCHAR(80) NULL,
  ip VARCHAR(80) NULL,
  status ENUM('aceito','bloqueado') DEFAULT 'aceito',
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_replay_origem_hash_bucket (origem, request_hash, time_bucket),
  INDEX idx_replay_hash_time (origem, request_hash, request_time),
  INDEX idx_replay_time (request_time),
  INDEX idx_replay_trace (trace_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- V104.49.2-R4: tabelas Enterprise exigidas pelo Mapa do Banco.
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
