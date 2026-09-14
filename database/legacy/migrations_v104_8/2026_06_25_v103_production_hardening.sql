-- V103 - Hardening final de produção: workers, anti-replay, estoque Tiny e classificação modular.
-- Execute no módulo indicado nos comentários quando usar bancos separados.

-- [fila] Anti-replay por janela/bucket; remove bloqueio infinito por unique antiga quando possível.
CREATE TABLE IF NOT EXISTS integration_replay_guard (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  origem VARCHAR(40) NOT NULL,
  rota VARCHAR(180) NULL,
  request_hash CHAR(64) NOT NULL,
  payload_hash CHAR(64) NULL,
  time_bucket BIGINT UNSIGNED NOT NULL DEFAULT 0,
  request_time DATETIME NOT NULL,
  trace_id VARCHAR(80) NULL,
  ip VARCHAR(80) NULL,
  status ENUM('aceito','bloqueado') DEFAULT 'aceito',
  hmac_validated_at DATETIME NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_replay_origem_hash_bucket (origem, request_hash, time_bucket),
  INDEX idx_replay_hash_time (origem, request_hash, request_time),
  INDEX idx_replay_time (request_time),
  INDEX idx_replay_trace (trace_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- [estoque] Tabelas de consulta Tiny mapeadas no código e agora criadas no schema.
CREATE TABLE IF NOT EXISTS estoque_consulta_tiny_execucoes (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NULL,
  modo ENUM('automatico','manual') DEFAULT 'manual',
  status ENUM('executando','concluido','parcial','erro','cancelado') DEFAULT 'executando',
  limite_produtos INT DEFAULT 100,
  total_produtos INT DEFAULT 0,
  total_sucesso INT DEFAULT 0,
  total_erro INT DEFAULT 0,
  total_alterados INT DEFAULT 0,
  mensagem TEXT NULL,
  iniciado_em DATETIME NULL,
  finalizado_em DATETIME NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ecte_status(status),
  INDEX idx_ecte_iniciado(iniciado_em),
  INDEX idx_ecte_trace(trace_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS estoque_consulta_tiny_resultados (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  execucao_id BIGINT NOT NULL,
  sku VARCHAR(120) NOT NULL,
  status ENUM('sucesso','erro') DEFAULT 'sucesso',
  saldo_tiny DECIMAL(15,4) NULL,
  saldo_anterior DECIMAL(15,4) NULL,
  alterou TINYINT DEFAULT 0,
  retorno_json LONGTEXT NULL,
  erro TEXT NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ectr_exec(execucao_id),
  INDEX idx_ectr_sku(sku),
  INDEX idx_ectr_status(status),
  INDEX idx_ectr_alterou(alterou)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO circuit_breakers(sistema,status) VALUES
('vsm','fechado'),('tiny_v2','fechado'),('tiny_v3','fechado'),('fiscal','fechado');

INSERT IGNORE INTO schema_migrations(migration,checksum,status,mensagem)
VALUES('v103_production_hardening','v103','aplicada','Workers fora de public, anti-replay pós-HMAC, tabelas Tiny estoque e SOC reforçado.');
