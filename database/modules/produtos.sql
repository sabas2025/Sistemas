-- V42 - Estrutura modular do banco: produtos
-- Execute no banco do módulo correspondente.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS produtos_mapeamento (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sku_tiny VARCHAR(100) NULL,
  sku_vsm VARCHAR(100) NULL,
  produto_tiny_id VARCHAR(100) NULL,
  produto_vsm_id VARCHAR(100) NULL,
  descricao VARCHAR(255) NULL,
  ativo TINYINT DEFAULT 1,
  estoque_atual DECIMAL(12,3) NULL,
  status_tiny VARCHAR(20) NULL,
  ultima_sincronizacao DATETIME NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_skus(sku_tiny, sku_vsm),
  empresa_id INT NULL,
  INDEX idx_produtos_mapeamento_empresa(empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS produtos_vsm_eventos (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tipo_evento ENUM('produto_novo','produto_cadastro_atualizado','produto_estoque_atualizado','produto_status_atualizado') NOT NULL,
  sku VARCHAR(100) NOT NULL,
  produto_vsm_id VARCHAR(120) NULL,
  produto_tiny_id VARCHAR(120) NULL,
  status_vsm VARCHAR(30) NULL,
  estoque_vsm DECIMAL(12,3) NULL,
  status_tiny_anterior VARCHAR(30) NULL,
  status_tiny_novo VARCHAR(30) NULL,
  estoque_tiny_anterior DECIMAL(12,3) NULL,
  estoque_tiny_novo DECIMAL(12,3) NULL,
  status_processamento ENUM('recebido','enfileirado','processado','erro','ignorado') DEFAULT 'recebido',
  payload LONGTEXT NULL,
  retorno LONGTEXT NULL,
  trace_id VARCHAR(80) NULL,
  fila_id BIGINT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_prod_vsm_sku(sku),
  INDEX idx_prod_vsm_tipo(tipo_evento),
  INDEX idx_prod_vsm_status(status_processamento),
  INDEX idx_prod_vsm_trace(trace_id),
  INDEX idx_prod_vsm_data(criado_em),
  empresa_id INT NULL,
  INDEX idx_produtos_vsm_eventos_empresa(empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS produto_pendencias (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(100) NOT NULL,
  tipo_evento VARCHAR(80) NOT NULL,
  motivo VARCHAR(120) NOT NULL,
  mensagem TEXT NULL,
  payload LONGTEXT NULL,
  retorno_tiny LONGTEXT NULL,
  fila_id BIGINT NULL,
  trace_id VARCHAR(80) NULL,
  status ENUM('aberto','resolvido','ignorado') DEFAULT 'aberto',
  resolucao TEXT NULL,
  resolvido_por INT NULL,
  resolvido_em DATETIME NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_prod_pend_sku(sku),
  INDEX idx_prod_pend_status(status),
  INDEX idx_prod_pend_trace(trace_id),
  empresa_id INT NULL,
  INDEX idx_produto_pendencias_empresa(empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS vsm_endpoint_catalogo (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(160) NOT NULL,
  tipo ENUM('produto','estoque','status','baixa','consulta','webhook','outro') DEFAULT 'outro',
  metodo ENUM('GET','POST','PUT','PATCH','DELETE') DEFAULT 'POST',
  endpoint VARCHAR(255) NOT NULL,
  ambiente VARCHAR(30) DEFAULT 'homologacao',
  versao VARCHAR(40) DEFAULT 'v1',
  status ENUM('pendente','online','erro','desativado') DEFAULT 'pendente',
  ultimo_http_code INT NULL,
  tempo_medio_ms INT NULL,
  ultima_falha TEXT NULL,
  ultimo_teste_em DATETIME NULL,
  payload_exemplo LONGTEXT NULL,
  retorno_exemplo LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_vsm_endpoint(nome,ambiente,versao),
  INDEX idx_vsm_endpoint_status(status),
  INDEX idx_vsm_endpoint_tipo(tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS vsm_payload_catalogo (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tipo_evento VARCHAR(80) NOT NULL,
  versao VARCHAR(40) DEFAULT 'v1',
  origem ENUM('exemplo','homologacao','producao') DEFAULT 'exemplo',
  payload_exemplo LONGTEXT NULL,
  payload_real LONGTEXT NULL,
  hash_payload VARCHAR(128) NULL,
  observacao TEXT NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_vsm_payload_tipo(tipo_evento),
  INDEX idx_vsm_payload_hash(hash_payload),
  INDEX idx_vsm_payload_trace(trace_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS vsm_endpoint_metricas (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  endpoint VARCHAR(255) NOT NULL,
  metodo VARCHAR(10) DEFAULT 'POST',
  ambiente VARCHAR(30) DEFAULT 'homologacao',
  status ENUM('online','atencao','erro') DEFAULT 'atencao',
  http_code INT NULL,
  tempo_ms INT NULL,
  erro TEXT NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_vsm_metricas_endpoint(endpoint),
  INDEX idx_vsm_metricas_status(status),
  INDEX idx_vsm_metricas_data(criado_em),
  INDEX idx_vsm_metricas_trace(trace_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- V45 - Governança de produto novo VSM -> Tiny
CREATE TABLE IF NOT EXISTS categorias_mapeamento (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  id_categoria_vsm VARCHAR(120) NULL,
  nome_categoria_vsm VARCHAR(255) NOT NULL,
  id_categoria_tiny VARCHAR(120) NOT NULL,
  nome_categoria_tiny VARCHAR(255) NOT NULL,
  ativo TINYINT DEFAULT 1,
  prioridade INT DEFAULT 0,
  observacao TEXT NULL,
  criado_por INT NULL,
  atualizado_por INT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_cat_vsm_tiny(id_categoria_vsm, id_categoria_tiny),
  INDEX idx_cat_vsm_nome(nome_categoria_vsm),
  INDEX idx_cat_tiny_nome(nome_categoria_tiny),
  INDEX idx_cat_ativo(ativo),
  empresa_id INT NULL,
  INDEX idx_categorias_mapeamento_empresa(empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS produtos_pendentes_integracao (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  origem ENUM('vsm','tiny','hub') DEFAULT 'vsm',
  sku VARCHAR(120) NOT NULL,
  ean VARCHAR(60) NULL,
  nome VARCHAR(255) NULL,
  categoria_vsm_id VARCHAR(120) NULL,
  categoria_vsm_nome VARCHAR(255) NULL,
  categoria_tiny_id_sugerida VARCHAR(120) NULL,
  categoria_tiny_nome_sugerida VARCHAR(255) NULL,
  payload_json LONGTEXT NULL,
  payload_hash VARCHAR(128) NOT NULL,
  acao_recomendada TEXT NULL,
  status ENUM('pendente','aprovado','rejeitado','vinculado','erro') DEFAULT 'pendente',
  motivo VARCHAR(120) NULL,
  mensagem TEXT NULL,
  fila_id BIGINT NULL,
  trace_id VARCHAR(80) NULL,
  aprovado_por INT NULL,
  aprovado_em DATETIME NULL,
  rejeitado_por INT NULL,
  rejeitado_em DATETIME NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_prod_pendente_payload(origem, sku, payload_hash),
  INDEX idx_prod_pendente_sku(sku),
  INDEX idx_prod_pendente_status(status),
  INDEX idx_prod_pendente_trace(trace_id),
  INDEX idx_prod_pendente_categoria(categoria_vsm_id),
  empresa_id INT NULL,
  INDEX idx_produtos_pendentes_integracao_empresa(empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- V57: configurações V46 movidas para core.sql; aqui permanecem somente tabelas de produtos.



-- V57: seeds do catálogo VSM pertencem ao módulo produtos.
INSERT IGNORE INTO vsm_endpoint_catalogo(nome,tipo,metodo,endpoint,ambiente,versao,status) VALUES
('Baixa de estoque VSM','baixa','POST','/api/estoque/baixa','homologacao','v1','pendente'),
('Produto novo VSM','produto','POST','/api/produtos','homologacao','v1','pendente'),
('Consulta estoque VSM','consulta','GET','/api/estoque/consulta','homologacao','v1','pendente');

-- V57 - Tabela cache/sombra de produtos Tiny para análise de duplicidade e comparação antes da aprovação.
CREATE TABLE IF NOT EXISTS produtos_tiny (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  produto_tiny_id VARCHAR(120) NULL,
  sku VARCHAR(120) NULL,
  codigo VARCHAR(120) NULL,
  ean VARCHAR(60) NULL,
  gtin VARCHAR(60) NULL,
  nome VARCHAR(255) NULL,
  descricao TEXT NULL,
  categoria_tiny_id VARCHAR(120) NULL,
  categoria_tiny_nome VARCHAR(255) NULL,
  ncm VARCHAR(20) NULL,
  estoque_atual DECIMAL(15,4) DEFAULT 0,
  status_tiny VARCHAR(50) NULL,
  payload_json LONGTEXT NULL,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_produtos_tiny_produto_id(produto_tiny_id),
  INDEX idx_produtos_tiny_sku(sku),
  INDEX idx_produtos_tiny_codigo(codigo),
  INDEX idx_produtos_tiny_ean(ean),
  INDEX idx_produtos_tiny_gtin(gtin),
  INDEX idx_produtos_tiny_nome(nome),
  empresa_id INT NULL,
  INDEX idx_produtos_tiny_empresa(empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- V58: tabelas de governança de produtos ausentes no install modular produtos.
CREATE TABLE IF NOT EXISTS produtos_vsm (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(100) NOT NULL,
  nome VARCHAR(255) NULL,
  status VARCHAR(40) DEFAULT 'ativo',
  payload LONGTEXT NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_produtos_vsm_sku(sku),
  INDEX idx_produtos_vsm_status(status),
  empresa_id INT NULL,
  INDEX idx_produtos_vsm_empresa(empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS produtos_aprovacao_historico (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  produto_pendente_id BIGINT NOT NULL,
  acao VARCHAR(80) NOT NULL,
  resultado ENUM('sucesso','bloqueado','erro','info') DEFAULT 'info',
  mensagem TEXT NULL,
  contexto_json LONGTEXT NULL,
  usuario_id INT NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_prod_aprovacao_pendente(produto_pendente_id),
  INDEX idx_prod_aprovacao_acao(acao),
  INDEX idx_prod_aprovacao_trace(trace_id),
  empresa_id INT NULL,
  INDEX idx_produtos_aprovacao_historico_empresa(empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


