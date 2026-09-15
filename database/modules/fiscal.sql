-- V43 - Estrutura modular do banco: fiscal / NF-e
-- Execute no banco do módulo fiscal.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS notas_fiscais (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  origem VARCHAR(40) NOT NULL DEFAULT 'tiny',
  destino VARCHAR(40) NOT NULL DEFAULT 'vsm',
  pedido_origem_id VARCHAR(120) NULL,
  pedido_tiny_id VARCHAR(120) NULL,
  nota_tiny_id VARCHAR(120) NULL,
  numero VARCHAR(60) NULL,
  serie VARCHAR(30) NULL,
  chave_acesso VARCHAR(60) NULL,
  modelo VARCHAR(10) DEFAULT '55',
  status ENUM('rascunho','autorizada','cancelada','denegada','erro','enviada_vsm','pendente_envio') DEFAULT 'pendente_envio',
  valor_total DECIMAL(12,2) DEFAULT 0,
  emitente_documento VARCHAR(30) NULL,
  destinatario_documento VARCHAR(30) NULL,
  data_emissao DATETIME NULL,
  data_autorizacao DATETIME NULL,
  xml_path VARCHAR(255) NULL,
  danfe_path VARCHAR(255) NULL,
  payload_tiny LONGTEXT NULL,
  retorno_vsm LONGTEXT NULL,
  mensagem TEXT NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_nfe_chave (chave_acesso),
  INDEX idx_nfe_status(status),
  INDEX idx_nfe_numero(numero, serie),
  INDEX idx_nfe_pedido(pedido_origem_id, pedido_tiny_id),
  INDEX idx_nfe_trace(trace_id),
  empresa_id INT NULL,
  INDEX idx_notas_fiscais_empresa(empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notas_fiscais_eventos (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  nota_fiscal_id BIGINT NULL,
  tipo_evento ENUM('recebida_tiny','autorizada','cancelada','xml_salvo','enviada_vsm','erro_envio_vsm','ignorada','reprocessada') NOT NULL,
  status VARCHAR(60) NULL,
  mensagem TEXT NULL,
  payload LONGTEXT NULL,
  retorno LONGTEXT NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_nfe_evento_nota(nota_fiscal_id),
  INDEX idx_nfe_evento_tipo(tipo_evento),
  INDEX idx_nfe_evento_trace(trace_id),
  empresa_id INT NULL,
  INDEX idx_notas_fiscais_eventos_empresa(empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS nfe_integracao (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  nota_fiscal_id BIGINT NULL,
  origem VARCHAR(40) DEFAULT 'tiny',
  destino VARCHAR(40) DEFAULT 'vsm',
  acao ENUM('enviar_xml','enviar_status','consultar_status','cancelar','reprocessar') DEFAULT 'enviar_xml',
  status ENUM('pendente','processando','sucesso','erro','ignorado') DEFAULT 'pendente',
  tentativas INT DEFAULT 0,
  proxima_tentativa DATETIME NULL,
  payload LONGTEXT NULL,
  retorno LONGTEXT NULL,
  codigo_erro VARCHAR(100) NULL,
  ultimo_erro TEXT NULL,
  mensagem TEXT NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_nfe_int_status(status),
  INDEX idx_nfe_int_nota(nota_fiscal_id),
  INDEX idx_nfe_int_trace(trace_id),
  empresa_id INT NULL,
  INDEX idx_nfe_integracao_empresa(empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS nfe_xml (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  nota_fiscal_id BIGINT NOT NULL,
  tipo ENUM('procNFe','cancelamento','carta_correcao','danfe_pdf','outro') DEFAULT 'procNFe',
  conteudo LONGTEXT NULL,
  hash_sha256 VARCHAR(80) NULL,
  origem VARCHAR(40) DEFAULT 'tiny',
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_nfe_xml_hash(hash_sha256),
  INDEX idx_nfe_xml_nota(nota_fiscal_id),
  empresa_id INT NULL,
  INDEX idx_nfe_xml_empresa(empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS nfe_status_historico (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  nota_fiscal_id BIGINT NOT NULL,
  status_anterior VARCHAR(60) NULL,
  status_novo VARCHAR(60) NOT NULL,
  motivo TEXT NULL,
  origem VARCHAR(40) DEFAULT 'sistema',
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_nfe_status_nota(nota_fiscal_id),
  INDEX idx_nfe_status_trace(trace_id),
  empresa_id INT NULL,
  INDEX idx_nfe_status_historico_empresa(empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- V60 - Central Fiscal Enterprise & Stability sem SNGPC/ANVISA
CREATE TABLE IF NOT EXISTS fiscal_reconciliacao_snapshots (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  resumo_json LONGTEXT NULL,
  divergencias_json LONGTEXT NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_fiscal_rec_trace(trace_id),
  INDEX idx_fiscal_rec_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fiscal_configuracoes_cache (
  chave VARCHAR(120) PRIMARY KEY,
  valor LONGTEXT NULL,
  expira_em DATETIME NULL,
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_fiscal_cache_expira(expira_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


