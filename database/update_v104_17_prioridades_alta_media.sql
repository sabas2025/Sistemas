-- V104.17 - prioridades alta e média
-- Banco único absoluto, diagnóstico real, licença HMAC, suporte/SLA e demo resetável.

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
