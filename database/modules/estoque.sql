-- V42 - Estrutura modular do banco: estoque
-- Execute no banco do módulo correspondente.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS estoque_movimentos (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  origem VARCHAR(50) NOT NULL,
  referencia VARCHAR(120) NULL,
  sku VARCHAR(100) NOT NULL,
  quantidade DECIMAL(12,3) NOT NULL,
  tipo_movimento ENUM('baixa','entrada','ajuste') DEFAULT 'baixa',
  status VARCHAR(50) DEFAULT 'pendente',
  payload_origem LONGTEXT NULL,
  retorno_vsm LONGTEXT NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_est_mov_sku(sku),
  INDEX idx_est_mov_status(status),
  INDEX idx_est_mov_trace(trace_id),
  UNIQUE KEY uk_baixa_referencia_sku (referencia, sku),
  empresa_id INT NULL,
  INDEX idx_estoque_movimentos_empresa(empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS estoque_divergencias (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(100) NOT NULL,
  estoque_vsm DECIMAL(12,3) NULL,
  estoque_tiny DECIMAL(12,3) NULL,
  diferenca DECIMAL(12,3) NULL,
  origem VARCHAR(40) DEFAULT 'reconciliacao',
  status ENUM('aberto','corrigido','ignorado') DEFAULT 'aberto',
  acao_recomendada TEXT NULL,
  trace_id VARCHAR(80) NULL,
  detalhes LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_div_sku(sku),
  INDEX idx_div_status(status),
  INDEX idx_div_trace(trace_id),
  empresa_id INT NULL,
  INDEX idx_estoque_divergencias_empresa(empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS estoque_reconciliacao (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(120) NOT NULL,
  estoque_tiny DECIMAL(12,3) NULL,
  estoque_vsm DECIMAL(12,3) NULL,
  diferenca DECIMAL(12,3) NULL,
  status ENUM('ok','divergente','erro') DEFAULT 'ok',
  origem VARCHAR(60) DEFAULT 'manual',
  trace_id VARCHAR(80) NULL,
  detalhes LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_reconc_sku(sku),
  INDEX idx_reconc_status(status),
  INDEX idx_reconc_trace(trace_id),
  INDEX idx_reconc_data(criado_em),
  empresa_id INT NULL,
  INDEX idx_estoque_reconciliacao_empresa(empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- V57 - Estruturas de reconciliação esperadas pelo health check modular.
CREATE TABLE IF NOT EXISTS reconciliacao_execucoes (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NULL,
  tipo VARCHAR(60) DEFAULT 'estoque',
  status ENUM('pendente','executando','concluido','erro') DEFAULT 'pendente',
  total_itens INT DEFAULT 0,
  divergencias INT DEFAULT 0,
  mensagem TEXT NULL,
  iniciado_em DATETIME NULL,
  finalizado_em DATETIME NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_reconc_exec_status(status),
  INDEX idx_reconc_exec_trace(trace_id),
  INDEX idx_reconc_exec_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reconciliacao_itens (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  execucao_id BIGINT NULL,
  sku VARCHAR(120) NOT NULL,
  estoque_tiny DECIMAL(15,4) DEFAULT 0,
  estoque_vsm DECIMAL(15,4) DEFAULT 0,
  diferenca DECIMAL(15,4) DEFAULT 0,
  status ENUM('ok','divergente','erro') DEFAULT 'ok',
  acao_recomendada TEXT NULL,
  payload_json LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_reconc_item_exec(execucao_id),
  INDEX idx_reconc_item_sku(sku),
  INDEX idx_reconc_item_status(status),
  empresa_id INT NULL,
  INDEX idx_reconciliacao_itens_empresa(empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- V61 Estoque Enterprise
-- Estoque mestre, fila exclusiva, alertas, reconciliação automática, histórico por SKU e auditoria de estoque.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS estoque_configuracoes (
  chave VARCHAR(120) PRIMARY KEY,
  valor LONGTEXT NULL,
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO estoque_configuracoes(chave, valor) VALUES
('estoque_mestre','vsm'),
('permitir_vsm_tiny','1'),
('bloquear_loop_bidirecional','1'),
('reconciliacao_automatica','1'),
('alertar_estoque_negativo','1'),
('alertar_produto_sem_mapeamento','1'),
('retencao_dias','90'),
('retry_minutos','5,15,30,60');

CREATE TABLE IF NOT EXISTS fila_estoque (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  origem VARCHAR(40) NOT NULL,
  destino VARCHAR(40) NOT NULL,
  sku VARCHAR(120) NOT NULL,
  quantidade DECIMAL(15,4) DEFAULT 0,
  status ENUM('pendente','processando','sucesso','erro','falha_definitiva','cancelado') DEFAULT 'pendente',
  tentativas INT DEFAULT 0,
  proxima_tentativa DATETIME NULL,
  payload LONGTEXT NULL,
  retorno LONGTEXT NULL,
  ultimo_erro TEXT NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_fila_estoque_status(status),
  INDEX idx_fila_estoque_sku(sku),
  INDEX idx_fila_estoque_trace(trace_id),
  INDEX idx_fila_estoque_proxima(proxima_tentativa),
  empresa_id INT NULL,
  INDEX idx_fila_estoque_empresa(empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS estoque_alertas (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(120) NULL,
  tipo VARCHAR(80) NOT NULL,
  severidade ENUM('info','alerta','erro','critico') DEFAULT 'alerta',
  mensagem TEXT NOT NULL,
  contexto_json LONGTEXT NULL,
  status ENUM('aberto','resolvido','ignorado') DEFAULT 'aberto',
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  resolvido_em DATETIME NULL,
  INDEX idx_est_alerta_sku(sku),
  INDEX idx_est_alerta_tipo(tipo),
  INDEX idx_est_alerta_status(status),
  INDEX idx_est_alerta_trace(trace_id),
  empresa_id INT NULL,
  INDEX idx_estoque_alertas_empresa(empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS estoque_saldos_cache (
  sku VARCHAR(120) PRIMARY KEY,
  saldo_tiny DECIMAL(15,4) DEFAULT 0,
  saldo_vsm DECIMAL(15,4) DEFAULT 0,
  diferenca DECIMAL(15,4) DEFAULT 0,
  produto_mapeado TINYINT DEFAULT 0,
  produto_ativo TINYINT DEFAULT 1,
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  trace_id VARCHAR(80) NULL,
  INDEX idx_est_saldo_diff(diferenca),
  INDEX idx_est_saldo_mapeado(produto_mapeado),
  empresa_id INT NULL,
  INDEX idx_estoque_saldos_cache_empresa(empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS estoque_auditoria_sku (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(120) NOT NULL,
  evento VARCHAR(120) NOT NULL,
  status_anterior VARCHAR(80) NULL,
  status_novo VARCHAR(80) NULL,
  saldo_anterior DECIMAL(15,4) NULL,
  saldo_novo DECIMAL(15,4) NULL,
  origem VARCHAR(80) NULL,
  mensagem TEXT NULL,
  contexto_json LONGTEXT NULL,
  trace_id VARCHAR(80) NULL,
  usuario_id BIGINT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_est_aud_sku(sku),
  INDEX idx_est_aud_evento(evento),
  INDEX idx_est_aud_trace(trace_id),
  empresa_id INT NULL,
  INDEX idx_estoque_auditoria_sku_empresa(empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS estoque_reconciliacao_agendada (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) DEFAULT 'Reconciliação automática',
  frequencia VARCHAR(40) DEFAULT 'diaria',
  ativo TINYINT DEFAULT 1,
  ultima_execucao DATETIME NULL,
  proxima_execucao DATETIME NULL,
  parametros_json LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_est_rec_agendada_ativo(ativo),
  INDEX idx_est_rec_agendada_proxima(proxima_execucao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO estoque_reconciliacao_agendada(nome, frequencia, ativo, proxima_execucao)
SELECT 'Reconciliação automática diária', 'diaria', 1, DATE_ADD(NOW(), INTERVAL 1 DAY)
WHERE NOT EXISTS (SELECT 1 FROM estoque_reconciliacao_agendada LIMIT 1);


-- V62 - Estoque VSM real e idempotência
-- V62 - Worker Fiscal e Estoque Bidirecional com VSM como estoque real
SET NAMES utf8mb4;

-- Política padrão de estoque para a operação informada:
-- VSM possui estoque real; Tiny também vende; HUB sincroniza os dois lados com anti-loop/idempotência.
INSERT IGNORE INTO estoque_configuracoes(chave, valor) VALUES
('estoque_mestre','vsm'),
('permitir_vsm_tiny','1'),
('permitir_tiny_vsm','1'),
('estrategia_estoque','vsm_fonte_real'),
('ignorar_retorno_espelhado_minutos','10'),
('bloquear_loop_bidirecional','1'),
('reconciliacao_automatica','1'),
('alertar_estoque_negativo','1'),
('alertar_produto_sem_mapeamento','1'),
('retencao_dias','90'),
('retry_minutos','5,15,30,60');

CREATE TABLE IF NOT EXISTS estoque_eventos_sincronizacao (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  hash_evento VARCHAR(80) NOT NULL,
  origem VARCHAR(40) NOT NULL,
  referencia VARCHAR(160) NOT NULL,
  sku VARCHAR(120) NOT NULL,
  tipo_movimento VARCHAR(80) NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_est_evento_hash(hash_evento),
  INDEX idx_est_evento_sku(sku),
  INDEX idx_est_evento_origem(origem),
  INDEX idx_est_evento_trace(trace_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Garantias para fila fiscal automática.
-- V64 - Consulta programada de estoque VSM com modo automático/manual.
CREATE TABLE IF NOT EXISTS estoque_consulta_vsm_execucoes (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NULL,
  modo ENUM('automatico','manual') DEFAULT 'automatico',
  status ENUM('executando','concluido','parcial','erro','cancelado') DEFAULT 'executando',
  intervalo_minutos INT DEFAULT 60,
  limite_produtos INT DEFAULT 100,
  total_produtos INT DEFAULT 0,
  total_sucesso INT DEFAULT 0,
  total_erro INT DEFAULT 0,
  total_alterados INT DEFAULT 0,
  total_enfileirados_tiny INT DEFAULT 0,
  mensagem TEXT NULL,
  iniciado_em DATETIME NULL,
  finalizado_em DATETIME NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ecve_status(status),
  INDEX idx_ecve_iniciado(iniciado_em),
  INDEX idx_ecve_trace(trace_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS estoque_consulta_vsm_resultados (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  execucao_id BIGINT NOT NULL,
  sku VARCHAR(120) NOT NULL,
  status ENUM('sucesso','erro') DEFAULT 'sucesso',
  saldo_vsm DECIMAL(15,4) NULL,
  saldo_anterior DECIMAL(15,4) NULL,
  alterou TINYINT DEFAULT 0,
  retorno_json LONGTEXT NULL,
  erro TEXT NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ecvr_exec(execucao_id),
  INDEX idx_ecvr_sku(sku),
  INDEX idx_ecvr_status(status),
  INDEX idx_ecvr_alterou(alterou)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO estoque_configuracoes(chave, valor) VALUES
('consulta_vsm_ativa','1'),
('consulta_vsm_modo','automatico'),
('consulta_vsm_intervalo_minutos','60'),
('consulta_vsm_quantidade_produtos','100'),
('consulta_vsm_enviar_tiny_se_alterou','1'),
('consulta_vsm_apenas_produtos_ativos','1'),
('consulta_vsm_ordem','menos_recente'),
('consulta_vsm_variacao_minima','0'),
('consulta_vsm_metodo_http','POST'),
('consulta_vsm_endpoint','/api/estoque/consulta'),
('consulta_vsm_payload_template','{"sku":"{{sku}}","trace_id":"{{trace_id}}"}'),
('consulta_vsm_timeout_segundos','30'),
('consulta_vsm_alerta_falhas_percentual','30');

-- V103 - Consulta Tiny: tabelas ausentes corrigidas.
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

INSERT IGNORE INTO estoque_configuracoes(chave, valor) VALUES
('consulta_tiny_ativa','0'),
('consulta_tiny_modo','manual'),
('consulta_tiny_quantidade_produtos','100'),
('consulta_tiny_timeout_segundos','30');
