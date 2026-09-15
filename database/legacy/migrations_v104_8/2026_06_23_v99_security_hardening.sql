-- V99 - Blindagem final de segurança operacional
-- Aplica tabelas auxiliares para rate limit, eventos de segurança, IPs bloqueados e classificação de schema.

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
  contexto JSON NULL,
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

CREATE TABLE IF NOT EXISTS schema_classificacao (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tabela VARCHAR(120) NOT NULL UNIQUE,
  modulo VARCHAR(80) NOT NULL DEFAULT 'core',
  status ENUM('em_uso','legado','futuro','remover_avaliar') NOT NULL DEFAULT 'em_uso',
  observacao VARCHAR(255) NULL,
  updated_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_schema_classificacao_status(status),
  INDEX idx_schema_classificacao_modulo(modulo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissoes_perfil(perfil,modulo,acao,permitido) VALUES
('admin','seguranca','eventos',1),
('admin','seguranca','bloquear_ip',1),
('admin','seguranca','fim',1),
('admin','seguranca','csp',1),
('admin','backup','restaurar',1),
('admin','backup','assinar',1),
('gerente','seguranca','eventos',1),
('gerente','seguranca','fim',0),
('gerente','backup','restaurar',0),
('operador','seguranca','eventos',0);

INSERT IGNORE INTO schema_migrations(version,descricao) VALUES('v99','Blindagem final: proxy confiável, rate limit por rota, FIM HMAC, backup assinado, CSP report e classificação de tabelas');
