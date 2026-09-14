-- V104.48.1-R2 - recuperação VSM, segurança e Self-Test.
-- Corrige bases atualizadas com tabelas VSM/SOC ausentes e compatibiliza selftest_relatorios.
-- Idempotente: somente CREATE TABLE IF NOT EXISTS e ALTER condicional; não remove dados.
-- Em banco modular estrito, prefira Central Técnica > Banco > Aplicar Enterprise Core,
-- que resolve cada tabela no módulo oficial. Este arquivo manual atende banco único.
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

-- Compatibilidade com schema_migrations antigo.
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='schema_migrations' AND COLUMN_NAME='version')=0,'ALTER TABLE schema_migrations ADD COLUMN version VARCHAR(40) NULL','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='schema_migrations' AND COLUMN_NAME='description')=0,'ALTER TABLE schema_migrations ADD COLUMN description TEXT NULL','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='schema_migrations' AND COLUMN_NAME='checksum')=0,'ALTER TABLE schema_migrations ADD COLUMN checksum VARCHAR(128) NULL','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='schema_migrations' AND COLUMN_NAME='status')=0,'ALTER TABLE schema_migrations ADD COLUMN status ENUM(''aplicada'',''falha'') DEFAULT ''aplicada''','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='schema_migrations' AND COLUMN_NAME='mensagem')=0,'ALTER TABLE schema_migrations ADD COLUMN mensagem TEXT NULL','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='schema_migrations' AND COLUMN_NAME='aplicada_em')=0,'ALTER TABLE schema_migrations ADD COLUMN aplicada_em TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

CREATE TABLE IF NOT EXISTS security_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NULL,
  tipo VARCHAR(80) NOT NULL,
  severidade ENUM('baixo','medio','alto','critico') NOT NULL DEFAULT 'medio',
  ip VARCHAR(64) NULL,
  usuario_id BIGINT NULL,
  rota VARCHAR(190) NULL,
  metodo VARCHAR(12) NULL,
  user_agent VARCHAR(255) NULL,
  detalhe TEXT NULL,
  contexto LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_security_events_tipo (tipo),
  INDEX idx_security_events_sev (severidade),
  INDEX idx_security_events_ip (ip),
  INDEX idx_security_events_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ips_bloqueados (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(64) NOT NULL UNIQUE,
  motivo VARCHAR(190) NOT NULL,
  severidade ENUM('medio','alto','critico') NOT NULL DEFAULT 'alto',
  bloqueado_ate DATETIME NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  INDEX idx_ips_bloqueados_ativo (ativo),
  INDEX idx_ips_bloqueados_ate (bloqueado_ate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limit_hits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(64) NOT NULL,
  usuario_id BIGINT NULL,
  rota VARCHAR(190) NOT NULL,
  metodo VARCHAR(12) NOT NULL,
  janela_inicio DATETIME NOT NULL,
  hits INT NOT NULL DEFAULT 1,
  updated_at DATETIME NULL,
  UNIQUE KEY uk_rate_bucket (ip, usuario_id, rota, metodo, janela_inicio),
  INDEX idx_rate_cleanup (janela_inicio),
  INDEX idx_rate_ip (ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vsm_endpoints (
  id INT AUTO_INCREMENT PRIMARY KEY,
  chave VARCHAR(100) NOT NULL UNIQUE,
  nome VARCHAR(160) NOT NULL,
  categoria VARCHAR(60) NOT NULL DEFAULT 'geral',
  metodo_http VARCHAR(10) NOT NULL DEFAULT 'GET',
  endpoint VARCHAR(255) NOT NULL,
  ativo TINYINT NOT NULL DEFAULT 0,
  timeout_segundos INT NOT NULL DEFAULT 30,
  retry_maximo INT NOT NULL DEFAULT 3,
  ordem_execucao INT NOT NULL DEFAULT 0,
  modo_teste_seguro TINYINT NOT NULL DEFAULT 1,
  origem VARCHAR(40) NOT NULL DEFAULT 'manual',
  contract_verified TINYINT NOT NULL DEFAULT 0,
  contract_source VARCHAR(255) NULL,
  contract_checked_at DATETIME NULL,
  is_template TINYINT NOT NULL DEFAULT 0,
  descricao TEXT NULL,
  ultimo_status_http INT NULL,
  ultimo_tempo_ms INT NULL,
  ultimo_erro TEXT NULL,
  ultima_resposta MEDIUMTEXT NULL,
  ultima_execucao_em DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_vsm_endpoints_categoria (categoria),
  INDEX idx_vsm_endpoints_ativo (ativo),
  INDEX idx_vsm_endpoints_ordem (ordem_execucao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vsm_campos_mapeamento (
  id INT AUTO_INCREMENT PRIMARY KEY,
  categoria VARCHAR(60) NOT NULL DEFAULT 'produto',
  campo_vsm VARCHAR(120) NOT NULL,
  campo_hub VARCHAR(120) NOT NULL,
  tipo_dado VARCHAR(50) NULL,
  valor_padrao VARCHAR(255) NULL,
  regra_validacao VARCHAR(255) NULL,
  transformacao VARCHAR(120) NULL,
  exemplo_payload TEXT NULL,
  obrigatorio TINYINT NOT NULL DEFAULT 0,
  ativo TINYINT NOT NULL DEFAULT 1,
  origem VARCHAR(40) NOT NULL DEFAULT 'manual',
  contract_verified TINYINT NOT NULL DEFAULT 0,
  contract_source VARCHAR(255) NULL,
  is_template TINYINT NOT NULL DEFAULT 0,
  observacao TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_vsm_campo (categoria, campo_vsm, campo_hub),
  INDEX idx_vsm_campos_categoria (categoria),
  INDEX idx_vsm_campos_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS selftest_relatorios (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  status ENUM('ok','atencao','erro') DEFAULT 'atencao',
  resumo TEXT NULL,
  detalhes LONGTEXT NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_selftest_status(status),
  INDEX idx_selftest_trace(trace_id),
  INDEX idx_selftest_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Completa tabelas parciais de versões antigas.
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='selftest_relatorios' AND COLUMN_NAME='resumo')=0,'ALTER TABLE selftest_relatorios ADD COLUMN resumo TEXT NULL','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='selftest_relatorios' AND COLUMN_NAME='detalhes')=0,'ALTER TABLE selftest_relatorios ADD COLUMN detalhes LONGTEXT NULL','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='selftest_relatorios' AND COLUMN_NAME='trace_id')=0,'ALTER TABLE selftest_relatorios ADD COLUMN trace_id VARCHAR(80) NULL','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='selftest_relatorios' AND COLUMN_NAME='criado_em')=0,'ALTER TABLE selftest_relatorios ADD COLUMN criado_em TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vsm_endpoints' AND COLUMN_NAME='modo_teste_seguro')=0,'ALTER TABLE vsm_endpoints ADD COLUMN modo_teste_seguro TINYINT NOT NULL DEFAULT 1','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vsm_endpoints' AND COLUMN_NAME='origem')=0,'ALTER TABLE vsm_endpoints ADD COLUMN origem VARCHAR(40) NOT NULL DEFAULT ''manual''','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vsm_endpoints' AND COLUMN_NAME='contract_verified')=0,'ALTER TABLE vsm_endpoints ADD COLUMN contract_verified TINYINT NOT NULL DEFAULT 0','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vsm_endpoints' AND COLUMN_NAME='contract_source')=0,'ALTER TABLE vsm_endpoints ADD COLUMN contract_source VARCHAR(255) NULL','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vsm_endpoints' AND COLUMN_NAME='contract_checked_at')=0,'ALTER TABLE vsm_endpoints ADD COLUMN contract_checked_at DATETIME NULL','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vsm_endpoints' AND COLUMN_NAME='is_template')=0,'ALTER TABLE vsm_endpoints ADD COLUMN is_template TINYINT NOT NULL DEFAULT 0','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vsm_campos_mapeamento' AND COLUMN_NAME='tipo_dado')=0,'ALTER TABLE vsm_campos_mapeamento ADD COLUMN tipo_dado VARCHAR(50) NULL','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vsm_campos_mapeamento' AND COLUMN_NAME='valor_padrao')=0,'ALTER TABLE vsm_campos_mapeamento ADD COLUMN valor_padrao VARCHAR(255) NULL','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vsm_campos_mapeamento' AND COLUMN_NAME='regra_validacao')=0,'ALTER TABLE vsm_campos_mapeamento ADD COLUMN regra_validacao VARCHAR(255) NULL','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vsm_campos_mapeamento' AND COLUMN_NAME='exemplo_payload')=0,'ALTER TABLE vsm_campos_mapeamento ADD COLUMN exemplo_payload TEXT NULL','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vsm_campos_mapeamento' AND COLUMN_NAME='origem')=0,'ALTER TABLE vsm_campos_mapeamento ADD COLUMN origem VARCHAR(40) NOT NULL DEFAULT ''manual''','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vsm_campos_mapeamento' AND COLUMN_NAME='contract_verified')=0,'ALTER TABLE vsm_campos_mapeamento ADD COLUMN contract_verified TINYINT NOT NULL DEFAULT 0','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vsm_campos_mapeamento' AND COLUMN_NAME='contract_source')=0,'ALTER TABLE vsm_campos_mapeamento ADD COLUMN contract_source VARCHAR(255) NULL','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vsm_campos_mapeamento' AND COLUMN_NAME='is_template')=0,'ALTER TABLE vsm_campos_mapeamento ADD COLUMN is_template TINYINT NOT NULL DEFAULT 0','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vsm_endpoint_logs' AND COLUMN_NAME='modo_teste_seguro')=0,'ALTER TABLE vsm_endpoint_logs ADD COLUMN modo_teste_seguro TINYINT NOT NULL DEFAULT 1','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;


-- Completa também estruturas SOC parcialmente criadas.
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_events' AND COLUMN_NAME='trace_id')=0,'ALTER TABLE security_events ADD COLUMN trace_id VARCHAR(80) NULL','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_events' AND COLUMN_NAME='tipo')=0,'ALTER TABLE security_events ADD COLUMN tipo VARCHAR(80) NOT NULL','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_events' AND COLUMN_NAME='severidade')=0,'ALTER TABLE security_events ADD COLUMN severidade ENUM(''baixo'',''medio'',''alto'',''critico'') NOT NULL DEFAULT ''medio''','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_events' AND COLUMN_NAME='ip')=0,'ALTER TABLE security_events ADD COLUMN ip VARCHAR(64) NULL','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_events' AND COLUMN_NAME='usuario_id')=0,'ALTER TABLE security_events ADD COLUMN usuario_id BIGINT NULL','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_events' AND COLUMN_NAME='rota')=0,'ALTER TABLE security_events ADD COLUMN rota VARCHAR(190) NULL','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_events' AND COLUMN_NAME='metodo')=0,'ALTER TABLE security_events ADD COLUMN metodo VARCHAR(12) NULL','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_events' AND COLUMN_NAME='user_agent')=0,'ALTER TABLE security_events ADD COLUMN user_agent VARCHAR(255) NULL','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_events' AND COLUMN_NAME='detalhe')=0,'ALTER TABLE security_events ADD COLUMN detalhe TEXT NULL','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_events' AND COLUMN_NAME='contexto')=0,'ALTER TABLE security_events ADD COLUMN contexto LONGTEXT NULL','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_events' AND COLUMN_NAME='created_at')=0,'ALTER TABLE security_events ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ips_bloqueados' AND COLUMN_NAME='ip')=0,'ALTER TABLE ips_bloqueados ADD COLUMN ip VARCHAR(64) NOT NULL','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ips_bloqueados' AND COLUMN_NAME='motivo')=0,'ALTER TABLE ips_bloqueados ADD COLUMN motivo VARCHAR(190) NOT NULL','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ips_bloqueados' AND COLUMN_NAME='severidade')=0,'ALTER TABLE ips_bloqueados ADD COLUMN severidade ENUM(''medio'',''alto'',''critico'') NOT NULL DEFAULT ''alto''','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ips_bloqueados' AND COLUMN_NAME='bloqueado_ate')=0,'ALTER TABLE ips_bloqueados ADD COLUMN bloqueado_ate DATETIME NULL','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ips_bloqueados' AND COLUMN_NAME='ativo')=0,'ALTER TABLE ips_bloqueados ADD COLUMN ativo TINYINT(1) NOT NULL DEFAULT 1','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ips_bloqueados' AND COLUMN_NAME='created_at')=0,'ALTER TABLE ips_bloqueados ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ips_bloqueados' AND COLUMN_NAME='updated_at')=0,'ALTER TABLE ips_bloqueados ADD COLUMN updated_at DATETIME NULL','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rate_limit_hits' AND COLUMN_NAME='ip')=0,'ALTER TABLE rate_limit_hits ADD COLUMN ip VARCHAR(64) NOT NULL','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rate_limit_hits' AND COLUMN_NAME='usuario_id')=0,'ALTER TABLE rate_limit_hits ADD COLUMN usuario_id BIGINT NULL','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rate_limit_hits' AND COLUMN_NAME='rota')=0,'ALTER TABLE rate_limit_hits ADD COLUMN rota VARCHAR(190) NOT NULL','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rate_limit_hits' AND COLUMN_NAME='metodo')=0,'ALTER TABLE rate_limit_hits ADD COLUMN metodo VARCHAR(12) NOT NULL','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rate_limit_hits' AND COLUMN_NAME='janela_inicio')=0,'ALTER TABLE rate_limit_hits ADD COLUMN janela_inicio DATETIME NOT NULL','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rate_limit_hits' AND COLUMN_NAME='hits')=0,'ALTER TABLE rate_limit_hits ADD COLUMN hits INT NOT NULL DEFAULT 1','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rate_limit_hits' AND COLUMN_NAME='updated_at')=0,'ALTER TABLE rate_limit_hits ADD COLUMN updated_at DATETIME NULL','SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

INSERT INTO schema_migrations(migration,version,description,checksum,status,mensagem,aplicada_em)
VALUES('20260712_004_vsm_security_selftest_recovery','V104.48.1-R2','Cria tabelas VSM e de segurança ausentes e corrige compatibilidade do Self-Test',NULL,'aplicada','Migration 004 aplicada sem remoção de dados',CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE version=VALUES(version),description=VALUES(description),status='aplicada',mensagem=VALUES(mensagem),aplicada_em=CURRENT_TIMESTAMP;
