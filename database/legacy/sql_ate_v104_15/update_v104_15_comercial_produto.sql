-- V104.15 - Camada comercial do produto, licenças, conectores, cobrança e demo
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
  observacoes TEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_comercial_cliente_documento (documento)
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

INSERT IGNORE INTO schema_migrations(migration, checksum, status, mensagem) VALUES('v104_15_comercial_produto', SHA2('v104_15_comercial_produto',256), 'aplicada', 'Camada comercial do produto aplicada.');
