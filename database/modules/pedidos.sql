-- V42 - Estrutura modular do banco: pedidos
-- Execute no banco do módulo correspondente.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS pedidos_integracao (
  -- Auditoria de capacidade 2026-09-14 (achado C-02): a chave era INT (teto 2.147.483.647). A 500
  -- pedidos/min esta tabela estoura em ~8 anos, e AUTO_INCREMENT nao reaproveita id de linha apagada,
  -- entao expurgo de retencao nao adia nada. No estouro o INSERT falha com "Duplicate entry
  -- '2147483647' for key 'PRIMARY'" e a entrada de trabalho morre por inteiro. BIGINT segue a
  -- convencao dominante do schema (97 das 100 PKs grandes) e casa com as colunas que a referenciam.
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  origem VARCHAR(50) NOT NULL,
  pedido_origem_id VARCHAR(100) NOT NULL,
  pedido_tiny_id VARCHAR(100) NULL,
  empresa_id INT NULL,
  filial_id INT NULL,
  cliente_nome VARCHAR(180) NULL,
  cliente_documento VARCHAR(30) NULL,
  valor_total DECIMAL(12,2) DEFAULT 0,
  status VARCHAR(50) DEFAULT 'recebido',
  payload_origem LONGTEXT NULL,
  payload_tiny LONGTEXT NULL,
  retorno_tiny LONGTEXT NULL,
  erro TEXT NULL,
  tentativas INT DEFAULT 0,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_origem_pedido(origem,pedido_origem_id),
  INDEX idx_pedidos_status(status),
  INDEX idx_pedidos_data(criado_em),
  -- Auditoria de capacidade 2026-09-14 (achado C-03): esta é a tabela de maior volume com escopo
  -- de empresa e era a ÚNICA das 31 sem índice em empresa_id. A migration da R7 pulou-a porque a
  -- coluna já existia desde antes, e o índice foi junto no pulo. Com 100 clientes, toda listagem
  -- de pedidos varria as linhas de todos eles antes de filtrar.
  INDEX idx_pedidos_integracao_empresa(empresa_id),
  -- O filtro real é "empresa + status" e "empresa + data": índice composto evita ler as linhas de
  -- outras empresas para depois descartá-las.
  INDEX idx_pedidos_empresa_status(empresa_id,status),
  INDEX idx_pedidos_empresa_data(empresa_id,criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS integracao_execucoes (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NOT NULL,
  tipo VARCHAR(80) NOT NULL,
  origem VARCHAR(60) NULL,
  destino VARCHAR(60) NULL,
  referencia VARCHAR(120) NULL,
  status ENUM('iniciado','sucesso','erro') DEFAULT 'iniciado',
  iniciado_em DATETIME NOT NULL,
  finalizado_em DATETIME NULL,
  duracao_ms INT NULL,
  erro_codigo VARCHAR(80) NULL,
  erro_mensagem TEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_exec_trace(trace_id),
  INDEX idx_exec_status(status),
  INDEX idx_exec_tipo(tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS webhook_requisicoes (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NULL,
  origem_ip VARCHAR(45) NULL,
  assinatura VARCHAR(255) NULL,
  timestamp_cliente VARCHAR(80) NULL,
  nonce VARCHAR(120) NULL,
  payload_hash VARCHAR(128) NULL,
  headers LONGTEXT NULL,
  status ENUM('aceito','bloqueado','erro') DEFAULT 'aceito',
  mensagem TEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_webhook_nonce(nonce),
  INDEX idx_webhook_trace(trace_id),
  INDEX idx_webhook_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS eventos_processados (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  origem VARCHAR(60) NOT NULL,
  referencia VARCHAR(160) NOT NULL,
  tipo_evento VARCHAR(80) NOT NULL,
  hash_payload VARCHAR(128) NOT NULL,
  trace_id VARCHAR(80) NULL,
  payload LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_evento_origem_ref_tipo_hash (origem, referencia, tipo_evento, hash_payload),
  INDEX idx_eventos_ref (referencia),
  INDEX idx_eventos_trace (trace_id),
  INDEX idx_eventos_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS tiny_webhooks (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tipo ENUM('estoque','produto','nota_fiscal','situacao_pedido','generico') DEFAULT 'generico',
  cnpj VARCHAR(20) NULL,
  id_ecommerce VARCHAR(120) NULL,
  referencia VARCHAR(180) NULL,
  hash_payload VARCHAR(128) NOT NULL,
  status ENUM('recebido','enfileirado','respondido','registrado','ignorado','duplicado','erro') DEFAULT 'recebido',
  payload LONGTEXT NULL,
  headers LONGTEXT NULL,
  retorno LONGTEXT NULL,
  mensagem TEXT NULL,
  trace_id VARCHAR(80) NULL,
  recebido_repetido INT DEFAULT 0,
  ultima_repeticao DATETIME NULL,
  processado_em DATETIME NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_tiny_webhook_tipo_ref_hash (tipo, referencia, hash_payload),
  INDEX idx_tiny_webhooks_tipo(tipo),
  INDEX idx_tiny_webhooks_status(status),
  INDEX idx_tiny_webhooks_ref(referencia),
  INDEX idx_tiny_webhooks_trace(trace_id),
  INDEX idx_tiny_webhooks_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS evento_correlacao (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NOT NULL,
  origem VARCHAR(60) NULL,
  tipo_evento VARCHAR(80) NULL,
  pedido_id VARCHAR(120) NULL,
  produto_sku VARCHAR(120) NULL,
  nf_chave VARCHAR(120) NULL,
  webhook_id BIGINT NULL,
  fila_id BIGINT NULL,
  detalhes LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_corr_trace(trace_id),
  INDEX idx_corr_sku(produto_sku),
  INDEX idx_corr_pedido(pedido_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- V59 - tabelas consolidadas do ciclo Tiny -> Hub -> VSM -> Tiny
CREATE TABLE IF NOT EXISTS pedidos_validacao (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NULL,
  origem VARCHAR(30) NOT NULL DEFAULT 'tiny',
  destino VARCHAR(30) NOT NULL DEFAULT 'vsm',
  pedido_origem_id VARCHAR(120) NULL,
  numero_pedido VARCHAR(120) NULL,
  cliente_nome VARCHAR(255) NULL,
  cliente_documento VARCHAR(30) NULL,
  valor_total DECIMAL(15,2) DEFAULT 0,
  quantidade_itens INT DEFAULT 0,
  status_tiny VARCHAR(120) NULL,
  status_validacao VARCHAR(40) NOT NULL DEFAULT 'recebido',
  erros_json LONGTEXT NULL,
  avisos_json LONGTEXT NULL,
  payload_json LONGTEXT NULL,
  payload_vsm_json LONGTEXT NULL,
  fila_id BIGINT NULL,
  pedido_hub_id BIGINT NULL,
  status_hub VARCHAR(50) NULL,
  recebido_vsm_em DATETIME NULL,
  enviado_tiny_em DATETIME NULL,
  xml_nfe_id INT NULL,
  aprovado_por INT NULL,
  aprovado_em DATETIME NULL,
  enviado_em DATETIME NULL,
  retorno_vsm_json LONGTEXT NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pedido_tiny (origem, pedido_origem_id),
  INDEX idx_ped_val_status (status_validacao),
  INDEX idx_ped_val_trace (trace_id),
  INDEX idx_ped_val_criado (criado_em),
  empresa_id INT NULL,
  INDEX idx_pedidos_validacao_empresa(empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pedidos_validacao_historico (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_validacao_id INT NOT NULL,
  acao VARCHAR(60) NOT NULL,
  resultado VARCHAR(40) NOT NULL,
  mensagem TEXT NULL,
  contexto_json LONGTEXT NULL,
  usuario_id INT NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ped_hist_validacao (pedido_validacao_id),
  INDEX idx_ped_hist_acao (acao),
  empresa_id INT NULL,
  INDEX idx_pedidos_validacao_historico_empresa(empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pedidos_hub (
  -- Auditoria de capacidade 2026-09-14 (achado C-02): a chave era INT (teto 2.147.483.647). A 500
  -- pedidos/min esta tabela estoura em ~8 anos, e AUTO_INCREMENT nao reaproveita id de linha apagada,
  -- entao expurgo de retencao nao adia nada. No estouro o INSERT falha com "Duplicate entry
  -- '2147483647' for key 'PRIMARY'" e a entrada de trabalho morre por inteiro. BIGINT segue a
  -- convencao dominante do schema (97 das 100 PKs grandes) e casa com as colunas que a referenciam.
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NULL,
  pedido_tiny_id VARCHAR(120) NULL,
  pedido_vsm_id VARCHAR(120) NULL,
  numero_pedido VARCHAR(120) NULL,
  status_hub VARCHAR(50) NOT NULL DEFAULT 'recebido_tiny',
  status_tiny VARCHAR(120) NULL,
  status_vsm VARCHAR(120) NULL,
  cliente_nome VARCHAR(255) NULL,
  cliente_documento VARCHAR(30) NULL,
  valor_total DECIMAL(15,2) DEFAULT 0,
  data_recebido_tiny DATETIME NULL,
  data_enviado_vsm DATETIME NULL,
  data_recebido_vsm DATETIME NULL,
  data_enviado_tiny DATETIME NULL,
  ultimo_erro TEXT NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pedido_hub_tiny (pedido_tiny_id),
  INDEX idx_pedido_hub_status (status_hub),
  INDEX idx_pedido_hub_trace (trace_id),
  INDEX idx_pedido_hub_vsm (pedido_vsm_id),
  empresa_id INT NULL,
  INDEX idx_pedidos_hub_empresa(empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pedidos_payloads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_hub_id BIGINT NOT NULL,
  origem VARCHAR(40) NOT NULL,
  destino VARCHAR(40) NULL,
  tipo_payload VARCHAR(80) NOT NULL,
  payload_json LONGTEXT NULL,
  hash_payload CHAR(64) NULL,
  trace_id VARCHAR(80) NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ped_payload_hub (pedido_hub_id),
  INDEX idx_ped_payload_tipo (tipo_payload),
  INDEX idx_ped_payload_hash (hash_payload),
  empresa_id INT NULL,
  INDEX idx_pedidos_payloads_empresa(empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pedidos_status_historico (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_hub_id BIGINT NOT NULL,
  status_anterior VARCHAR(50) NULL,
  status_novo VARCHAR(50) NOT NULL,
  origem VARCHAR(40) NULL,
  mensagem TEXT NULL,
  erro TEXT NULL,
  usuario_id INT NULL,
  trace_id VARCHAR(80) NULL,
  contexto_json LONGTEXT NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ped_status_hub (pedido_hub_id),
  INDEX idx_ped_status_novo (status_novo),
  INDEX idx_ped_status_trace (trace_id),
  empresa_id INT NULL,
  INDEX idx_pedidos_status_historico_empresa(empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pedidos_nfe_xml (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_hub_id BIGINT NOT NULL,
  chave_nfe VARCHAR(80) NULL,
  numero_nfe VARCHAR(50) NULL,
  serie VARCHAR(20) NULL,
  xml_original LONGTEXT NULL,
  xml_hash CHAR(64) NULL,
  status_xml VARCHAR(40) NOT NULL DEFAULT 'recebido',
  validado TINYINT DEFAULT 0,
  erro_validacao TEXT NULL,
  retorno_tiny_json LONGTEXT NULL,
  enviado_tiny_em DATETIME NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_xml_hash (xml_hash),
  INDEX idx_xml_hub (pedido_hub_id),
  INDEX idx_xml_chave (chave_nfe),
  INDEX idx_xml_status (status_xml),
  empresa_id INT NULL,
  INDEX idx_pedidos_nfe_xml_empresa(empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


