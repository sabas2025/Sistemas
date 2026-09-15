-- Hub Tiny/VSM - instalação consolidada V81
-- Gerado para substituir histórico de update_vXX.sql.
-- Use este arquivo para instalações novas; use o Atualizador Seguro somente em bases antigas.

-- ===== install.sql =====
CREATE DATABASE IF NOT EXISTS `{DB_NAME}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `{DB_NAME}`;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  senha VARCHAR(255) NOT NULL,
  perfil ENUM('admin','gerente','operador') DEFAULT 'admin',
  ativo TINYINT DEFAULT 1,
  ultimo_login DATETIME NULL,
  deve_trocar_senha TINYINT DEFAULT 1,
  two_factor_enabled TINYINT DEFAULT 0,
  two_factor_secret TEXT NULL,
  two_factor_created_at DATETIME NULL,
  two_factor_last_verified_at DATETIME NULL,
  tentativas_login INT DEFAULT 0,
  bloqueado_ate DATETIME NULL,
  session_version INT NOT NULL DEFAULT 0,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS empresas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(160) NOT NULL,
  cnpj VARCHAR(20) NULL,
  ativo TINYINT DEFAULT 1,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS filiais (
  id INT AUTO_INCREMENT PRIMARY KEY,
  empresa_id INT NOT NULL,
  nome VARCHAR(160) NOT NULL,
  cnpj VARCHAR(20) NULL,
  cidade VARCHAR(100) NULL,
  ativo TINYINT DEFAULT 1,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_filiais_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS configuracoes_integracao (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ambiente ENUM('homologacao','producao') DEFAULT 'homologacao',
  tiny_versao ENUM('v2','v3') DEFAULT 'v2',
  tiny_v2_url VARCHAR(255) DEFAULT 'https://api.tiny.com.br/api2',
  tiny_v2_token TEXT NULL,
  tiny_v3_ambiente ENUM('homologacao','producao') DEFAULT 'homologacao',
  tiny_v3_url VARCHAR(255) DEFAULT 'https://api.tiny.com.br/public-api/v3',
  tiny_v3_token TEXT NULL,
  tiny_v3_auth_url VARCHAR(255) NULL,
  tiny_v3_token_url VARCHAR(255) NULL,
  tiny_v3_client_id VARCHAR(255) NULL,
  tiny_v3_client_secret TEXT NULL,
  tiny_v3_redirect_uri VARCHAR(255) NULL,
  tiny_v3_scopes TEXT NULL,
  tiny_v3_produtos_listar VARCHAR(255) NULL,
  tiny_v3_produtos_obter VARCHAR(255) NULL,
  tiny_v3_produtos_criar VARCHAR(255) NULL,
  tiny_v3_produtos_alterar VARCHAR(255) NULL,
  tiny_v3_produtos_preco VARCHAR(255) NULL,
  tiny_v3_estoque_consultar VARCHAR(255) NULL,
  tiny_v3_estoque_atualizar VARCHAR(255) NULL,
  tiny_v3_pedidos_obter VARCHAR(255) NULL,
  tiny_v3_pedidos_lancar_estoque VARCHAR(255) NULL,
  tiny_v3_notas_obter VARCHAR(255) NULL,
  tiny_v3_manual_access_token TEXT NULL,
  tiny_v3_manual_refresh_token TEXT NULL,
  vsm_url VARCHAR(255) DEFAULT 'https://conectavenda.homolog.vsm.com.br',
  vsm_token TEXT NULL,
  vsm_api_principal VARCHAR(40) DEFAULT 'pedidos-integradora',
  vsm_api_loja VARCHAR(40) DEFAULT 'desativado',
  vsm_swagger_integradora VARCHAR(255) DEFAULT 'https://conectavenda.homolog.vsm.com.br/swagger-ui/index.html?urls.primaryName=pedidos-integradora',
  vsm_swagger_loja VARCHAR(255) DEFAULT 'https://conectavenda.homolog.vsm.com.br/swagger-ui/index.html?urls.primaryName=pedidos-loja',
  vsm_waf_agressivo TINYINT DEFAULT 0,
  vsm_api_observacao TEXT NULL,
  vsm_endpoint_baixa_estoque VARCHAR(255) DEFAULT '/api/estoque/baixa',
  vsm_endpoint_produto_novo VARCHAR(255) DEFAULT '/api/produtos',
  vsm_endpoint_consulta_estoque VARCHAR(255) DEFAULT '/api/estoque/consulta',
  fluxo_tiny_vsm_estoque TINYINT DEFAULT 1,
  fluxo_vsm_tiny_produto TINYINT DEFAULT 1,
  fluxo_vsm_tiny_pedido TINYINT DEFAULT 0,
  webhook_secret TEXT NULL,
  tiny_webhook_secret TEXT NULL,
  tiny_webhook_cnpj_autorizados TEXT NULL,
  tiny_webhook_exigir_secret TINYINT DEFAULT 1,
  tiny_webhook_rate_limit INT DEFAULT 60,
  tiny_webhook_max_bytes INT DEFAULT 1048576,
  bloquear_inativo_com_estoque TINYINT DEFAULT 1,
  sync_criar_produto_tiny TINYINT DEFAULT 1,
  sync_atualizar_produto_tiny TINYINT DEFAULT 1,
  sync_atualizar_estoque_tiny TINYINT DEFAULT 1,
  sync_atualizar_status_tiny TINYINT DEFAULT 1,
  sync_atualizar_preco_tiny TINYINT DEFAULT 1,
  sync_atualizar_descricao_tiny TINYINT DEFAULT 1,
  sync_atualizar_categoria_tiny TINYINT DEFAULT 1,
  sync_atualizar_marca_tiny TINYINT DEFAULT 1,
  sync_criar_produto_se_nao_existir TINYINT DEFAULT 0,
  sync_bloquear_estoque_negativo TINYINT DEFAULT 1,
  sync_receber_pedidos_vsm TINYINT DEFAULT 0,
  sync_enviar_pedido_tiny TINYINT DEFAULT 0,
  sync_vsm_enviar_estoque_tiny TINYINT DEFAULT 1,
  sync_vsm_status_produto_tiny TINYINT DEFAULT 1,
  sync_vsm_enviar_nota_tiny TINYINT DEFAULT 1,
  sync_bloquear_produto_novo_vsm TINYINT DEFAULT 1,
  sync_permitir_produto_novo_vsm_manual TINYINT DEFAULT 0,
  sync_tiny_enviar_nota_vsm TINYINT DEFAULT 0,
  sync_tiny_enviar_pedido_vsm TINYINT DEFAULT 1,
  sync_tiny_enviar_estoque_vsm TINYINT DEFAULT 0,
  sync_tiny_status_produto_vsm TINYINT DEFAULT 0,
  sync_tiny_bloquear_produto_novo_vsm TINYINT DEFAULT 1,
  sync_exigir_mapeamento_sku TINYINT DEFAULT 1,
  sync_exigir_nfe_autorizada TINYINT DEFAULT 1,
  sync_ordem_envio TEXT NULL,
  tiny_v3_operacional TINYINT DEFAULT 0,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pedidos_integracao (
  id INT AUTO_INCREMENT PRIMARY KEY,
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
  INDEX idx_pedidos_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
  UNIQUE KEY uk_skus(sku_tiny, sku_vsm)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fila_integracao (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tipo VARCHAR(50) NOT NULL,
  prioridade ENUM('critica','alta','normal','baixa') DEFAULT 'normal',
  categoria VARCHAR(60) NULL,
  referencia VARCHAR(120) NULL,
  payload LONGTEXT NULL,
  retorno LONGTEXT NULL,
  status ENUM('pendente','processando','sucesso','erro','falha_definitiva') DEFAULT 'pendente',
  codigo_erro VARCHAR(80) NULL,
  tentativas INT DEFAULT 0,
  proxima_tentativa DATETIME NULL,
  processando_desde DATETIME NULL,
  processado_em DATETIME NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_fila_status(status),
  INDEX idx_fila_codigo(codigo_erro),
  INDEX idx_fila_tipo(tipo),
  INDEX idx_fila_prioridade(prioridade,status)
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
  INDEX idx_prod_vsm_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
  UNIQUE KEY uk_baixa_referencia_sku (referencia, sku)
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
  INDEX idx_prod_pend_trace(trace_id)
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
  INDEX idx_div_trace(trace_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS logs_integracao (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NULL,
  tipo VARCHAR(50) NOT NULL,
  nivel ENUM('info','alerta','erro','critico') DEFAULT 'info',
  codigo_erro VARCHAR(80) NULL,
  mensagem TEXT NOT NULL,
  payload LONGTEXT NULL,
  ip VARCHAR(45) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_logs_tipo(tipo),
  INDEX idx_logs_nivel(nivel),
  INDEX idx_logs_data(criado_em),
  INDEX idx_logs_trace(trace_id),
  INDEX idx_logs_codigo(codigo_erro)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS auditoria_eventos (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NOT NULL,
  usuario_id INT NULL,
  acao VARCHAR(120) NOT NULL,
  entidade VARCHAR(80) NULL,
  entidade_id VARCHAR(120) NULL,
  status ENUM('sucesso','erro','alerta','info') DEFAULT 'info',
  nivel ENUM('info','alerta','erro','critico') DEFAULT 'info',
  codigo_erro VARCHAR(80) NULL,
  mensagem TEXT NULL,
  causa_provavel TEXT NULL,
  acao_recomendada TEXT NULL,
  payload LONGTEXT NULL,
  retorno LONGTEXT NULL,
  contexto LONGTEXT NULL,
  ip VARCHAR(45) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_trace(trace_id),
  INDEX idx_audit_acao(acao),
  INDEX idx_audit_status(status),
  INDEX idx_audit_entidade(entidade, entidade_id),
  INDEX idx_audit_data(criado_em)
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


CREATE TABLE IF NOT EXISTS notificacoes (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tipo ENUM('pedido_novo','pedido_integrado','erro_integracao','fila','estoque','baixa_estoque','produto_novo','integracao_sucesso','nota_fiscal','sistema') DEFAULT 'sistema',
  titulo VARCHAR(160) NOT NULL,
  mensagem TEXT NOT NULL,
  severidade ENUM('info','sucesso','alerta','erro','critico') DEFAULT 'info',
  entidade VARCHAR(80) NULL,
  entidade_id VARCHAR(120) NULL,
  link VARCHAR(255) NULL,
  trace_id VARCHAR(80) NULL,
  payload LONGTEXT NULL,
  lida TINYINT DEFAULT 0,
  lida_em DATETIME NULL,
  criada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notif_lida(lida),
  INDEX idx_notif_tipo(tipo),
  INDEX idx_notif_sev(severidade),
  INDEX idx_notif_trace(trace_id),
  INDEX idx_notif_data(criada_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notificacoes_config (
  id INT AUTO_INCREMENT PRIMARY KEY,
  painel TINYINT DEFAULT 1,
  som TINYINT DEFAULT 1,
  browser_push TINYINT DEFAULT 1,
  email TINYINT DEFAULT 0,
  whatsapp TINYINT DEFAULT 0,
  pedido_novo TINYINT DEFAULT 1,
  erro_integracao TINYINT DEFAULT 1,
  fila_parada TINYINT DEFAULT 1,
  estoque_alerta TINYINT DEFAULT 1,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS configuracoes_historico (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NULL,
  campo VARCHAR(120) NOT NULL,
  valor_antigo TEXT NULL,
  valor_novo TEXT NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_conf_hist_campo(campo),
  INDEX idx_conf_hist_trace(trace_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_tentativas (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(160) NULL,
  ip VARCHAR(45) NULL,
  sucesso TINYINT DEFAULT 0,
  mensagem VARCHAR(255) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_login_email(email),
  INDEX idx_login_ip(ip),
  INDEX idx_login_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



CREATE TABLE IF NOT EXISTS permissoes_perfil (
  id INT AUTO_INCREMENT PRIMARY KEY,
  perfil ENUM('admin','gerente','operador') NOT NULL,
  modulo VARCHAR(80) NOT NULL,
  acao VARCHAR(80) NOT NULL,
  permitido TINYINT DEFAULT 1,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_perm(perfil,modulo,acao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS backups_banco (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NULL,
  arquivo VARCHAR(255) NOT NULL,
  tamanho_bytes BIGINT DEFAULT 0,
  status ENUM('sucesso','erro') DEFAULT 'sucesso',
  mensagem TEXT NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_backup_data(criado_em)
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


CREATE TABLE IF NOT EXISTS diagnostico_api (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  sistema VARCHAR(30) NOT NULL,
  endpoint VARCHAR(255) NULL,
  status ENUM('online','atencao','erro') DEFAULT 'atencao',
  http_code INT NULL,
  tempo_ms INT NULL,
  mensagem TEXT NULL,
  detalhes LONGTEXT NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_diag_sistema(sistema),
  INDEX idx_diag_status(status),
  INDEX idx_diag_data(criado_em),
  INDEX idx_diag_trace(trace_id)
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



CREATE TABLE IF NOT EXISTS tiny_v3_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ambiente ENUM('homologacao','producao') DEFAULT 'homologacao',
  access_token TEXT NOT NULL,
  refresh_token TEXT NULL,
  expires_at DATETIME NULL,
  scope TEXT NULL,
  origem VARCHAR(30) DEFAULT 'manual',
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NULL,
  INDEX idx_tiny_v3_tokens_expira(expires_at),
  INDEX idx_tiny_v3_tokens_ambiente(ambiente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tiny_v3_endpoint_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  endpoint VARCHAR(180) NULL,
  metodo VARCHAR(10) NULL,
  http_code INT NULL,
  sucesso TINYINT DEFAULT 0,
  tempo_ms INT NULL,
  trace_id VARCHAR(80) NULL,
  request_body LONGTEXT NULL,
  response_body LONGTEXT NULL,
  erro TEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tiny_v3_endpoint_logs_data(criado_em),
  INDEX idx_tiny_v3_endpoint_logs_trace(trace_id)
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
  INDEX idx_fila_morta_data(criado_em)
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

CREATE TABLE IF NOT EXISTS metricas_api (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  sistema VARCHAR(40) NOT NULL,
  endpoint VARCHAR(255) NULL,
  metodo VARCHAR(10) DEFAULT 'POST',
  http_code INT NULL,
  tempo_ms INT NULL,
  sucesso TINYINT DEFAULT 0,
  codigo_erro VARCHAR(100) NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_metricas_sistema(sistema),
  INDEX idx_metricas_sucesso(sucesso),
  INDEX idx_metricas_data(criado_em),
  INDEX idx_metricas_trace(trace_id)
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
  INDEX idx_reconc_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mapper_versions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(80) NOT NULL,
  versao VARCHAR(30) NOT NULL,
  descricao TEXT NULL,
  ativo TINYINT DEFAULT 1,
  schema_exemplo LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_mapper_nome_versao(nome, versao),
  INDEX idx_mapper_ativo(ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;




CREATE TABLE IF NOT EXISTS security_audit (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NULL,
  usuario_id INT NULL,
  evento VARCHAR(120) NOT NULL,
  nivel ENUM('info','alerta','erro','critico') DEFAULT 'info',
  ip VARCHAR(45) NULL,
  user_agent TEXT NULL,
  session_id VARCHAR(128) NULL,
  detalhes LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sec_evento(evento),
  INDEX idx_sec_nivel(nivel),
  INDEX idx_sec_trace(trace_id),
  INDEX idx_sec_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS auditoria_timeline (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NOT NULL,
  fase VARCHAR(120) NOT NULL,
  status ENUM('info','sucesso','alerta','erro','critico') DEFAULT 'info',
  entidade VARCHAR(80) NULL,
  entidade_id VARCHAR(120) NULL,
  detalhes LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_timeline_trace(trace_id),
  INDEX idx_timeline_fase(fase),
  INDEX idx_timeline_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS auditoria_detalhes (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NOT NULL,
  entidade VARCHAR(80) NULL,
  entidade_id VARCHAR(120) NULL,
  antes_json LONGTEXT NULL,
  depois_json LONGTEXT NULL,
  diff_json LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_det_trace(trace_id),
  INDEX idx_audit_det_entidade(entidade, entidade_id)
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


CREATE TABLE IF NOT EXISTS tiny_testes_reais (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NULL,
  sku VARCHAR(120) NOT NULL,
  acao VARCHAR(60) NOT NULL,
  modo_tiny VARCHAR(40) NULL,
  versao_efetiva VARCHAR(60) NULL,
  sucesso TINYINT DEFAULT 0,
  payload_vsm LONGTEXT NULL,
  payload_tiny LONGTEXT NULL,
  retorno_json LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tiny_testes_sku(sku),
  INDEX idx_tiny_testes_trace(trace_id),
  INDEX idx_tiny_testes_sucesso(sucesso),
  INDEX idx_tiny_testes_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tiny_v2_endpoint_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NULL,
  endpoint VARCHAR(255) NULL,
  metodo VARCHAR(10) DEFAULT 'POST',
  http_code INT NULL,
  sucesso TINYINT DEFAULT 0,
  tempo_ms INT NULL,
  request_body LONGTEXT NULL,
  response_body LONGTEXT NULL,
  erro TEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tinyv2_trace(trace_id),
  INDEX idx_tinyv2_endpoint(endpoint),
  INDEX idx_tinyv2_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tiny_error_catalogo (
  id INT AUTO_INCREMENT PRIMARY KEY,
  versao ENUM('v2','v3') NOT NULL,
  codigo VARCHAR(80) NOT NULL,
  titulo VARCHAR(180) NOT NULL,
  causa TEXT NULL,
  acao_recomendada TEXT NULL,
  retry_policy VARCHAR(120) NULL,
  ativo TINYINT DEFAULT 1,
  UNIQUE KEY uk_tiny_error(versao,codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS homologacao_checklist (
  id INT AUTO_INCREMENT PRIMARY KEY,
  chave VARCHAR(80) NOT NULL UNIQUE,
  titulo VARCHAR(180) NOT NULL,
  descricao TEXT NULL,
  status ENUM('pendente','ok','falha','nao_aplicavel') DEFAULT 'pendente',
  resultado LONGTEXT NULL,
  trace_id VARCHAR(80) NULL,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_homologacao_status(status),
  INDEX idx_homologacao_chave(chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO homologacao_checklist(chave,titulo,descricao,status) VALUES
('tiny_token','Testar token Tiny','Executar teste de conexão Tiny V2 com SKU real ou SKU de homologação.','pendente'),
('vsm_conexao','Testar conexão VSM','Validar DNS, HTTPS, token e endpoint configurado da VSM.','pendente'),
('vsm_produto_tiny','Produto VSM para Tiny','Simular ou receber produto real da VSM e criar/atualizar no Tiny.','pendente'),
('vsm_estoque_tiny','Estoque VSM para Tiny','Atualizar saldo de SKU existente no Tiny a partir da VSM.','pendente'),
('vsm_status_tiny','Status ativo/inativo VSM para Tiny','Inativar/ativar produto pela VSM respeitando bloqueio de estoque.','pendente'),
('tiny_baixa_vsm','Baixa Tiny para VSM','Receber evento do Tiny e enviar baixa de estoque para a VSM.','pendente'),
('auditoria_trace','Auditoria e Trace ID','Conferir payload original, transformado, enviado e retorno.','pendente'),
('fila_dlq','Fila morta / DLQ','Forçar erro controlado e validar reprocessamento/fila morta.','pendente'),
('reconciliacao','Reconciliação de estoque','Comparar saldo Tiny x VSM e registrar divergência.','pendente'),
('tiny_v3_token','Tiny V3 OAuth válido','Salvar token OAuth por ambiente e validar access/refresh token.','pendente'),
('tiny_v3_refresh','Tiny V3 refresh token','Executar renovação automática e confirmar sucesso.','pendente'),
('tiny_v3_produto_sku','Tiny V3 produto por SKU','Consultar SKU real e confirmar comparação exata.','pendente'),
('tiny_v3_estoque','Tiny V3 estoque por SKU','Consultar/atualizar estoque em SKU real de homologação.','pendente'),
('tiny_v3_logs','Tiny V3 logs técnicos','Confirmar tiny_v3_endpoint_logs com request/response mascarados.','pendente'),
('vsm_estoque_consulta','VSM consulta estoque por SKU','Validar endpoint real de consulta de estoque da VSM para reconciliação.','pendente');

INSERT IGNORE INTO mapper_versions(nome,versao,descricao,ativo) VALUES
('produto_vsm_para_tiny','v1','Mapper padrão para produto novo criado na VSM e enviado ao Tiny.',1),
('baixa_tiny_para_vsm','v1','Mapper padrão para baixa/alteração de estoque originada no Tiny e enviada à VSM.',1);

INSERT IGNORE INTO circuit_breakers(sistema,status) VALUES('vsm','fechado'),('tiny','fechado');

INSERT IGNORE INTO notificacoes_config(id) VALUES(1);

INSERT IGNORE INTO permissoes_perfil(perfil,modulo,acao,permitido) VALUES
('admin','ficha_tecnica','visualizar',1),('admin','seguranca','visualizar',1),('admin','seguranca','gerenciar',1),('admin','auditoria','exportar',1),('admin','dashboard','visualizar',1),('admin','tiny_webhooks','visualizar',1),('admin','estoque','visualizar',1),('admin','estoque','simular',1),('admin','produtos_vsm','visualizar',1),('admin','produtos_vsm','simular',1),('admin','fluxos','editar',1),('admin','pedidos','visualizar',1),('admin','pedidos','detalhe',1),('admin','fila','visualizar',1),('admin','fila','reprocessar',1),('admin','configuracoes','editar',1),('admin','configuracoes','visualizar',1),('admin','auditoria','visualizar',1),('admin','usuarios','gerenciar',1),('admin','backup','gerar',1),('admin','backup','visualizar',1),('admin','logs','visualizar',1),('admin','logs','exportar',1),('admin','notificacoes','visualizar',1),('admin','produtos','visualizar',1),
('gerente','ficha_tecnica','visualizar',1),('gerente','seguranca','visualizar',1),('gerente','seguranca','gerenciar',0),('gerente','auditoria','exportar',1),('gerente','dashboard','visualizar',1),('gerente','tiny_webhooks','visualizar',1),('gerente','estoque','visualizar',1),('gerente','estoque','simular',1),('gerente','produtos_vsm','visualizar',1),('gerente','produtos_vsm','simular',1),('gerente','fluxos','editar',1),('gerente','pedidos','visualizar',1),('gerente','pedidos','detalhe',1),('gerente','fila','visualizar',1),('gerente','fila','reprocessar',1),('gerente','configuracoes','editar',1),('gerente','configuracoes','visualizar',1),('gerente','auditoria','visualizar',1),('gerente','usuarios','gerenciar',0),('gerente','backup','gerar',0),('gerente','backup','visualizar',1),('gerente','logs','visualizar',1),('gerente','logs','exportar',1),('gerente','notificacoes','visualizar',1),('gerente','produtos','visualizar',1),
('admin','fila_morta','visualizar',1),('admin','fila_morta','reprocessar',1),('admin','laboratorio','visualizar',1),('admin','laboratorio','executar',1),('admin','reconciliacao','visualizar',1),('admin','reconciliacao','executar',1),('admin','produtos_vsm','simular',1),('admin','metricas','visualizar',1),('admin','selftest','visualizar',1),('admin','selftest','executar',1),('gerente','fila_morta','visualizar',1),('gerente','fila_morta','reprocessar',1),('gerente','laboratorio','visualizar',1),('gerente','laboratorio','executar',1),('gerente','reconciliacao','visualizar',1),('gerente','reconciliacao','executar',1),('gerente','produtos_vsm','simular',1),('gerente','metricas','visualizar',1),('gerente','selftest','visualizar',1),('gerente','selftest','executar',1),('operador','fila_morta','visualizar',0),('operador','fila_morta','reprocessar',0),('operador','laboratorio','visualizar',0),('operador','laboratorio','executar',0),('operador','reconciliacao','visualizar',0),('operador','reconciliacao','executar',0),('operador','metricas','visualizar',0),('operador','selftest','visualizar',0),('operador','selftest','executar',0),
('operador','ficha_tecnica','visualizar',0),('operador','seguranca','visualizar',0),('operador','seguranca','gerenciar',0),('operador','auditoria','exportar',0),('operador','dashboard','visualizar',1),('operador','tiny_webhooks','visualizar',0),('operador','estoque','visualizar',1),('operador','estoque','simular',0),('operador','produtos_vsm','visualizar',1),('operador','produtos_vsm','simular',0),('operador','fluxos','editar',0),('operador','pedidos','visualizar',1),('operador','pedidos','detalhe',1),('operador','fila','visualizar',1),('operador','fila','reprocessar',0),('operador','configuracoes','editar',0),('operador','configuracoes','visualizar',0),('operador','auditoria','visualizar',0),('operador','usuarios','gerenciar',0),('operador','backup','gerar',0),('operador','backup','visualizar',0),('operador','logs','visualizar',0),('operador','logs','exportar',0),('operador','notificacoes','visualizar',1),('operador','produtos','visualizar',1);


INSERT IGNORE INTO usuarios(id,nome,email,senha,perfil,ativo) VALUES
(1,'{ADMIN_NOME}','{ADMIN_EMAIL}','{ADMIN_HASH}','admin',1)
ON DUPLICATE KEY UPDATE nome=VALUES(nome), senha=VALUES(senha), perfil='admin', ativo=1;

INSERT IGNORE INTO empresas(id,nome,cnpj) VALUES(1,'Empresa Demonstração','');



CREATE TABLE IF NOT EXISTS homologacao_automatica_relatorios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NULL,
  aprovado TINYINT DEFAULT 0,
  liberado_tiny_v3 TINYINT DEFAULT 0,
  ambiente VARCHAR(30) NULL,
  tiny_v3_ambiente VARCHAR(30) NULL,
  resumo_json LONGTEXT NULL,
  relatorio_json LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_auto_hom_trace(trace_id),
  INDEX idx_auto_hom_aprovado(aprovado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO homologacao_checklist(chave,titulo,descricao,status) VALUES
('auto_banco','Homologação automática: banco','Validação automática de conexão e estrutura mínima do banco.','pendente'),
('auto_tiny_v3_oauth','Homologação automática: OAuth Tiny V3','Validação automática de Client ID, Redirect URI, token e refresh.','pendente'),
('auto_tiny_v3_modulos','Homologação automática: módulos Tiny V3','Testes automáticos de produto, estoque, pedido e logs Tiny V3.','pendente'),
('auto_vsm','Homologação automática: VSM','Validação automática da configuração mínima da VSM.','pendente'),
('auto_fila_auditoria','Homologação automática: fila/auditoria','Validação automática de fila, DLQ e auditoria por Trace ID.','pendente');

INSERT INTO configuracoes_integracao(id, ambiente, tiny_versao, tiny_v2_url, tiny_v2_token, tiny_v3_url, tiny_v3_ambiente, tiny_v3_token, tiny_v3_auth_url, tiny_v3_token_url, tiny_v3_client_id, tiny_v3_client_secret, tiny_v3_redirect_uri, tiny_v3_scopes, tiny_v3_manual_access_token, tiny_v3_manual_refresh_token, vsm_url, vsm_token, vsm_endpoint_baixa_estoque, vsm_endpoint_produto_novo, vsm_endpoint_consulta_estoque, fluxo_tiny_vsm_estoque, fluxo_vsm_tiny_produto, fluxo_vsm_tiny_pedido, webhook_secret, tiny_webhook_secret, tiny_webhook_cnpj_autorizados, tiny_webhook_exigir_secret, tiny_webhook_rate_limit, tiny_webhook_max_bytes, bloquear_inativo_com_estoque, sync_criar_produto_tiny, sync_atualizar_produto_tiny, sync_atualizar_estoque_tiny, sync_atualizar_status_tiny, sync_atualizar_preco_tiny, sync_atualizar_descricao_tiny, sync_atualizar_categoria_tiny, sync_atualizar_marca_tiny, sync_criar_produto_se_nao_existir, sync_bloquear_estoque_negativo, tiny_v3_operacional)
VALUES(1, '{AMBIENTE}', '{TINY_VERSION}', '{TINY_V2_URL}', '{TINY_V2_TOKEN}', '{TINY_V3_URL}', 'homologacao', '{TINY_V3_TOKEN}', 'https://accounts.tiny.com.br/realms/tiny/protocol/openid-connect/auth', 'https://accounts.tiny.com.br/realms/tiny/protocol/openid-connect/token', '', '', '', '', '', '', '{VSM_URL}', '{VSM_TOKEN}', '/api/estoque/baixa', '/api/produtos', '/api/estoque/consulta', 1, 1, 0, '{WEBHOOK_SECRET}', '{WEBHOOK_SECRET}', '', 1, 60, 1048576, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 1, 0)
ON DUPLICATE KEY UPDATE
ambiente=VALUES(ambiente), tiny_versao=VALUES(tiny_versao), tiny_v2_url=VALUES(tiny_v2_url), tiny_v2_token=VALUES(tiny_v2_token), tiny_v3_url=VALUES(tiny_v3_url), tiny_v3_ambiente=VALUES(tiny_v3_ambiente), tiny_v3_token=VALUES(tiny_v3_token), tiny_v3_auth_url=VALUES(tiny_v3_auth_url), tiny_v3_token_url=VALUES(tiny_v3_token_url), tiny_v3_client_id=VALUES(tiny_v3_client_id), tiny_v3_client_secret=VALUES(tiny_v3_client_secret), tiny_v3_redirect_uri=VALUES(tiny_v3_redirect_uri), tiny_v3_scopes=VALUES(tiny_v3_scopes), tiny_v3_manual_access_token=VALUES(tiny_v3_manual_access_token), tiny_v3_manual_refresh_token=VALUES(tiny_v3_manual_refresh_token), vsm_url=VALUES(vsm_url), vsm_token=VALUES(vsm_token), vsm_endpoint_baixa_estoque=VALUES(vsm_endpoint_baixa_estoque), vsm_endpoint_produto_novo=VALUES(vsm_endpoint_produto_novo), vsm_endpoint_consulta_estoque=VALUES(vsm_endpoint_consulta_estoque), fluxo_tiny_vsm_estoque=VALUES(fluxo_tiny_vsm_estoque), fluxo_vsm_tiny_produto=VALUES(fluxo_vsm_tiny_produto), fluxo_vsm_tiny_pedido=VALUES(fluxo_vsm_tiny_pedido), webhook_secret=VALUES(webhook_secret), tiny_webhook_secret=VALUES(tiny_webhook_secret), tiny_webhook_cnpj_autorizados=VALUES(tiny_webhook_cnpj_autorizados), tiny_webhook_exigir_secret=VALUES(tiny_webhook_exigir_secret), tiny_webhook_rate_limit=VALUES(tiny_webhook_rate_limit), tiny_webhook_max_bytes=VALUES(tiny_webhook_max_bytes), bloquear_inativo_com_estoque=VALUES(bloquear_inativo_com_estoque), sync_criar_produto_tiny=VALUES(sync_criar_produto_tiny), sync_atualizar_produto_tiny=VALUES(sync_atualizar_produto_tiny), sync_atualizar_estoque_tiny=VALUES(sync_atualizar_estoque_tiny), sync_atualizar_status_tiny=VALUES(sync_atualizar_status_tiny), sync_atualizar_preco_tiny=VALUES(sync_atualizar_preco_tiny), sync_atualizar_descricao_tiny=VALUES(sync_atualizar_descricao_tiny), sync_atualizar_categoria_tiny=VALUES(sync_atualizar_categoria_tiny), sync_atualizar_marca_tiny=VALUES(sync_atualizar_marca_tiny), sync_criar_produto_se_nao_existir=VALUES(sync_criar_produto_se_nao_existir), sync_bloquear_estoque_negativo=VALUES(sync_bloquear_estoque_negativo), tiny_v3_operacional=VALUES(tiny_v3_operacional);




INSERT IGNORE INTO permissoes_perfil(perfil,modulo,acao,permitido) VALUES
('admin','produtos_vsm','visualizar',1),
('admin','reconciliacao','visualizar',1),
('gerente','produtos_vsm','visualizar',1),
('gerente','reconciliacao','visualizar',1),
('admin','homologacao','visualizar',1),
('admin','homologacao','executar',1),
('admin','homologacao','relatorio',1),
('admin','database','validar',1),
('gerente','homologacao','visualizar',1),
('gerente','homologacao','relatorio',1),
('gerente','database','validar',1);


INSERT IGNORE INTO tiny_error_catalogo(versao,codigo,titulo,causa,acao_recomendada,retry_policy) VALUES
('v2','TINY_V2_TOKEN_INVALID','Token Tiny V2 inválido','Credencial inválida, expirada ou copiada incorretamente.','Atualize o token Tiny V2 em Configurações e teste novamente.','manual'),
('v2','TINY_V2_RATE_LIMIT','Limite de API Tiny V2','Muitas chamadas em pouco tempo.','Reduza frequência do worker e aguarde a janela de limite.','5/15/30min'),
('v2','TINY_V2_PRODUTO_NAO_ENCONTRADO','Produto não encontrado no Tiny V2','SKU inexistente ou divergente.','Vincule SKU manualmente ou crie produto no Tiny.','manual'),
('v3','TINY_V3_OAUTH_EXPIRED','OAuth Tiny V3 expirado','Access token expirou e refresh falhou.','Reconectar OAuth Tiny V3 no painel.','manual'),
('v3','TINY_V3_ENDPOINT_ERROR','Endpoint Tiny V3 com falha','Endpoint retornou erro HTTP ou payload inesperado.','Abrir logs Tiny V3 e validar endpoint configurado.','1/5/15min');


-- V24 Production Ready
CREATE TABLE IF NOT EXISTS instalacao_prechecks (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  item VARCHAR(160) NOT NULL,
  status ENUM('ok','falha','atencao') DEFAULT 'atencao',
  detalhe TEXT NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_precheck_status(status),
  INDEX idx_precheck_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS upgrade_snapshots (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  versao VARCHAR(30) NOT NULL,
  tipo ENUM('pre_upgrade','post_upgrade','manual') DEFAULT 'pre_upgrade',
  snapshot_json LONGTEXT NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_upgrade_versao(versao),
  INDEX idx_upgrade_trace(trace_id),
  INDEX idx_upgrade_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vsm_endpoint_catalogo (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(160) NOT NULL,
  tipo ENUM('produto','estoque','status','baixa','consulta','webhook','outro') DEFAULT 'outro',
  metodo ENUM('GET','POST','PUT','PATCH','DELETE') DEFAULT 'POST',
  endpoint VARCHAR(255) NOT NULL,
  ambiente ENUM('homologacao','producao') DEFAULT 'homologacao',
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
  ambiente ENUM('homologacao','producao') DEFAULT 'homologacao',
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

CREATE TABLE IF NOT EXISTS auditoria_assinaturas (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  auditoria_evento_id BIGINT NOT NULL,
  trace_id VARCHAR(80) NULL,
  hash_sha256 VARCHAR(128) NOT NULL,
  hash_anterior VARCHAR(128) NULL,
  hash_canonico LONGTEXT NULL,
  cadeia_valida TINYINT DEFAULT 1,
  algoritmo VARCHAR(30) DEFAULT 'sha256-chain',
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_auditoria_assinatura(auditoria_evento_id),
  INDEX idx_aud_sig_trace(trace_id),
  INDEX idx_aud_sig_hash(hash_sha256)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_exports (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NULL,
  formato ENUM('html_pdf','csv','json') DEFAULT 'json',
  filtros LONGTEXT NULL,
  total_registros INT DEFAULT 0,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_exports_trace(trace_id),
  INDEX idx_audit_exports_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS post_install_tests (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  score INT DEFAULT 0,
  resultado_json LONGTEXT NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_post_install_trace(trace_id),
  INDEX idx_post_install_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fila_analytics_snapshots (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  snapshot_json LONGTEXT NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_fila_snap_trace(trace_id),
  INDEX idx_fila_snap_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tiny_v2_retry_policies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(80) NOT NULL UNIQUE,
  retry TINYINT DEFAULT 1,
  atrasos_minutos VARCHAR(120) DEFAULT '1,5,15',
  acao_recomendada TEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO vsm_endpoint_catalogo(nome,tipo,metodo,endpoint,ambiente,versao,status) VALUES
('Baixa de estoque VSM','baixa','POST','/api/estoque/baixa','homologacao','v1','pendente'),
('Produto novo VSM','produto','POST','/api/produtos','homologacao','v1','pendente'),
('Consulta estoque VSM','consulta','GET','/api/estoque/consulta','homologacao','v1','pendente');

INSERT IGNORE INTO tiny_v2_retry_policies(codigo,retry,atrasos_minutos,acao_recomendada) VALUES
('TINY_V2_TOKEN_INVALID',0,'','Atualizar token Tiny V2 manualmente.'),
('TINY_V2_RATE_LIMIT',1,'5,15,30,60','Reduzir frequência do worker e aguardar janela de limite.'),
('TINY_V2_TIMEOUT',1,'1,5,15,30','Reprocessar com backoff progressivo.'),
('TINY_V2_HTTP_500',1,'1,5,15,30','Reprocessar e monitorar instabilidade da API.'),
('TINY_V2_PRODUTO_NAO_ENCONTRADO',0,'','Vincular SKU ou cadastrar produto antes de reprocessar.');

INSERT IGNORE INTO permissoes_perfil(perfil,modulo,acao,permitido) VALUES
('admin','ficha_tecnica','visualizar',1),('gerente','ficha_tecnica','visualizar',1),('operador','ficha_tecnica','visualizar',0),
('admin','fila_morta','visualizar',1),('gerente','fila_morta','visualizar',1),('operador','fila_morta','visualizar',0),
('admin','database','validar',1),('gerente','database','validar',1),('operador','database','validar',0);
CREATE TABLE IF NOT EXISTS auditoria_hash_chain (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  auditoria_id BIGINT NOT NULL,
  trace_id VARCHAR(80) NULL,
  hash_anterior CHAR(64) NOT NULL,
  hash_atual CHAR(64) NOT NULL,
  base_assinatura LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_auditoria_hash(auditoria_id),
  INDEX idx_chain_trace(trace_id),
  INDEX idx_chain_hash(hash_atual)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hosting_checks (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  provedor VARCHAR(80) DEFAULT 'infinityfree',
  score INT DEFAULT 0,
  resultado_json LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_hosting_checks_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO permissoes_perfil(perfil,modulo,acao,permitido) VALUES
('admin','production_ready','visualizar',1),('gerente','production_ready','visualizar',1),('operador','production_ready','visualizar',0),
('admin','hosting','visualizar',1),('gerente','hosting','visualizar',1),('operador','hosting','visualizar',0);


INSERT IGNORE INTO permissoes_perfil(perfil,modulo,acao,permitido) VALUES
('admin','laboratorio','executar',1),
('gerente','laboratorio','executar',1),
('operador','laboratorio','executar',0);


CREATE TABLE IF NOT EXISTS schema_migrations (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  migration VARCHAR(180) NOT NULL UNIQUE,
  checksum VARCHAR(128) NULL,
  status ENUM('aplicada','falha') DEFAULT 'aplicada',
  mensagem TEXT NULL,
  aplicada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_schema_migrations_status(status),
  INDEX idx_schema_migrations_data(aplicada_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- V45 - Governança Produto Novo VSM -> Tiny
ALTER TABLE configuracoes_integracao ADD COLUMN IF NOT EXISTS sync_aprovacao_manual_produto_novo_vsm TINYINT DEFAULT 1;
ALTER TABLE configuracoes_integracao ADD COLUMN IF NOT EXISTS sync_exigir_categoria_mapeada_vsm TINYINT DEFAULT 1;
ALTER TABLE configuracoes_integracao ADD COLUMN IF NOT EXISTS sync_permitir_atualizar_produto_existente_vsm TINYINT DEFAULT 1;
ALTER TABLE configuracoes_integracao ADD COLUMN IF NOT EXISTS sync_permitir_estoque_vsm_tiny TINYINT DEFAULT 1;
ALTER TABLE configuracoes_integracao ADD COLUMN IF NOT EXISTS sync_permitir_status_vsm_tiny TINYINT DEFAULT 1;
UPDATE configuracoes_integracao SET sync_bloquear_produto_novo_vsm=1, sync_permitir_produto_novo_vsm_manual=1, sync_aprovacao_manual_produto_novo_vsm=1, sync_exigir_categoria_mapeada_vsm=1, sync_criar_produto_tiny=0, sync_criar_produto_se_nao_existir=0, sync_exigir_mapeamento_sku=1 WHERE id=1;

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
  INDEX idx_cat_ativo(ativo)
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
  INDEX idx_prod_pendente_categoria(categoria_vsm_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- V46 - Camadas extras de aprovação de produto VSM -> Tiny
-- Objetivo: impedir criação no Tiny sem checklist, confirmação forte, EAN/GTIN, NCM, categoria e análise de duplicidade.

ALTER TABLE configuracoes_integracao ADD COLUMN IF NOT EXISTS sync_exigir_ean_produto_novo_vsm TINYINT DEFAULT 1;
ALTER TABLE configuracoes_integracao ADD COLUMN IF NOT EXISTS sync_exigir_ncm_produto_novo_vsm TINYINT DEFAULT 1;
ALTER TABLE configuracoes_integracao ADD COLUMN IF NOT EXISTS sync_exigir_confirmacao_forte_produto_vsm TINYINT DEFAULT 1;
ALTER TABLE configuracoes_integracao ADD COLUMN IF NOT EXISTS sync_bloquear_duplicidade_produto_vsm TINYINT DEFAULT 1;

UPDATE configuracoes_integracao
SET sync_bloquear_produto_novo_vsm=1,
    sync_permitir_produto_novo_vsm_manual=1,
    sync_aprovacao_manual_produto_novo_vsm=1,
    sync_exigir_categoria_mapeada_vsm=1,
    sync_exigir_ean_produto_novo_vsm=1,
    sync_exigir_ncm_produto_novo_vsm=1,
    sync_exigir_confirmacao_forte_produto_vsm=1,
    sync_bloquear_duplicidade_produto_vsm=1,
    sync_criar_produto_tiny=0,
    sync_criar_produto_se_nao_existir=0,
    sync_exigir_mapeamento_sku=1
WHERE id=1;

ALTER TABLE produtos_pendentes_integracao ADD COLUMN IF NOT EXISTS ncm VARCHAR(20) NULL AFTER ean;
ALTER TABLE produtos_pendentes_integracao ADD COLUMN IF NOT EXISTS checklist_json LONGTEXT NULL AFTER mensagem;
ALTER TABLE produtos_pendentes_integracao ADD COLUMN IF NOT EXISTS duplicidades_json LONGTEXT NULL AFTER checklist_json;
ALTER TABLE produtos_pendentes_integracao ADD COLUMN IF NOT EXISTS bloqueios_json LONGTEXT NULL AFTER duplicidades_json;
ALTER TABLE produtos_pendentes_integracao ADD COLUMN IF NOT EXISTS ultima_analise_em DATETIME NULL AFTER bloqueios_json;

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
  INDEX idx_prod_aprovacao_trace(trace_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO schema_migrations(version,descricao)
VALUES('v46','Camadas extras de aprovação produto VSM com checklist, duplicidade e confirmação forte');
CREATE TABLE IF NOT EXISTS tiny_validacoes_execucoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NULL,
  versao VARCHAR(10) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'pendente',
  score INT NOT NULL DEFAULT 0,
  ambiente VARCHAR(30) NULL,
  sku_teste VARCHAR(120) NULL,
  resumo TEXT NULL,
  resultado_json LONGTEXT NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tiny_val_versao (versao),
  INDEX idx_tiny_val_status (status),
  INDEX idx_tiny_val_criado (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS production_readiness_v50 (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NULL,
  status VARCHAR(30) NOT NULL,
  score INT NOT NULL DEFAULT 0,
  resultado_json LONGTEXT NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pr50_status(status),
  INDEX idx_pr50_criado(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO schema_migrations(version, descricao) VALUES('v50','Produção segura, Tiny validado, simulador VSM e sistema leve');

-- V57 - Correções preventivas de instalação e tabelas esperadas pelo código/health check.
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
  INDEX idx_produtos_tiny_nome(nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
  INDEX idx_reconc_item_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- V58 - Auditoria total de tabelas e correção de consultas modulares
-- Execute no ambiente já instalado caso tenha vindo da V57.

-- CORE
CREATE TABLE IF NOT EXISTS vsm_endpoints (
  id INT AUTO_INCREMENT PRIMARY KEY,
  chave VARCHAR(100) NOT NULL UNIQUE,
  nome VARCHAR(160) NOT NULL,
  categoria VARCHAR(60) NOT NULL DEFAULT 'geral',
  metodo_http VARCHAR(10) NOT NULL DEFAULT 'GET',
  endpoint VARCHAR(255) NOT NULL,
  ativo TINYINT NOT NULL DEFAULT 1,
  timeout_segundos INT NOT NULL DEFAULT 30,
  retry_maximo INT NOT NULL DEFAULT 3,
  ordem_execucao INT NOT NULL DEFAULT 0,
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
  transformacao VARCHAR(120) NULL,
  obrigatorio TINYINT NOT NULL DEFAULT 0,
  ativo TINYINT NOT NULL DEFAULT 1,
  observacao TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_vsm_campo (categoria, campo_vsm, campo_hub),
  INDEX idx_vsm_campos_categoria (categoria),
  INDEX idx_vsm_campos_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- OBSERVABILIDADE
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
  trace_id VARCHAR(80) NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_vsm_logs_endpoint (endpoint_id),
  INDEX idx_vsm_logs_chave (chave),
  INDEX idx_vsm_logs_sucesso (sucesso),
  INDEX idx_vsm_logs_criado (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notificacoes (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tipo VARCHAR(80) NOT NULL DEFAULT 'geral',
  titulo VARCHAR(180) NOT NULL,
  mensagem TEXT NULL,
  severidade ENUM('info','sucesso','alerta','erro','critico') DEFAULT 'info',
  entidade VARCHAR(80) NULL,
  entidade_id VARCHAR(120) NULL,
  link VARCHAR(255) NULL,
  lida TINYINT DEFAULT 0,
  lida_em DATETIME NULL,
  trace_id VARCHAR(80) NULL,
  payload LONGTEXT NULL,
  criada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notif_lida(lida),
  INDEX idx_notif_tipo(tipo),
  INDEX idx_notif_trace(trace_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notificacoes_config (
  id INT PRIMARY KEY DEFAULT 1,
  painel_ativo TINYINT DEFAULT 1,
  email_ativo TINYINT DEFAULT 0,
  whatsapp_ativo TINYINT DEFAULT 0,
  som_ativo TINYINT DEFAULT 0,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO notificacoes_config(id) VALUES(1);

-- PRODUTOS
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
  INDEX idx_produtos_vsm_status(status)
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
  INDEX idx_prod_aprovacao_trace(trace_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ESTOQUE
CREATE TABLE IF NOT EXISTS estoque_movimentos (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  origem VARCHAR(50) NOT NULL,
  referencia VARCHAR(120) NULL,
  sku VARCHAR(100) NOT NULL,
  quantidade DECIMAL(12,3) NOT NULL,
  tipo_movimento VARCHAR(40) DEFAULT 'baixa',
  status VARCHAR(50) DEFAULT 'pendente',
  payload_origem LONGTEXT NULL,
  retorno_vsm LONGTEXT NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_baixa_referencia_sku (referencia, sku),
  INDEX idx_est_mov_sku(sku),
  INDEX idx_est_mov_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- BACKUPS
CREATE TABLE IF NOT EXISTS backups (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  arquivo VARCHAR(255) NOT NULL,
  tamanho BIGINT DEFAULT 0,
  status VARCHAR(40) DEFAULT 'gerado',
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO schema_migrations(version,descricao) VALUES('v58','Auditoria total de tabelas modulares e correção Dashboard/API para Database::forTable');
-- V42 - Estrutura modular do banco: pedidos
-- Execute no banco do módulo correspondente.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS pedidos_integracao (
  id INT AUTO_INCREMENT PRIMARY KEY,
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
  INDEX idx_pedidos_data(criado_em)
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
  fila_id INT NULL,
  pedido_hub_id INT NULL,
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
  INDEX idx_ped_val_criado (criado_em)
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
  INDEX idx_ped_hist_acao (acao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pedidos_hub (
  id INT AUTO_INCREMENT PRIMARY KEY,
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
  INDEX idx_pedido_hub_vsm (pedido_vsm_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pedidos_payloads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_hub_id INT NOT NULL,
  origem VARCHAR(40) NOT NULL,
  destino VARCHAR(40) NULL,
  tipo_payload VARCHAR(80) NOT NULL,
  payload_json LONGTEXT NULL,
  hash_payload CHAR(64) NULL,
  trace_id VARCHAR(80) NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ped_payload_hub (pedido_hub_id),
  INDEX idx_ped_payload_tipo (tipo_payload),
  INDEX idx_ped_payload_hash (hash_payload)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pedidos_status_historico (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_hub_id INT NOT NULL,
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
  INDEX idx_ped_status_trace (trace_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pedidos_nfe_xml (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_hub_id INT NOT NULL,
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
  INDEX idx_xml_status (status_xml)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- V60 Enterprise Fiscal & Stability - tabelas adicionais no banco único
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

CREATE TABLE IF NOT EXISTS fila_fiscal (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  nota_fiscal_id BIGINT NULL,
  nfe_integracao_id BIGINT NULL,
  acao ENUM('validar_xml','enviar_tiny','enviar_vsm','reprocessar','consultar_status') DEFAULT 'reprocessar',
  prioridade ENUM('critica','alta','normal','baixa') DEFAULT 'normal',
  status ENUM('pendente','processando','sucesso','erro','falha_definitiva') DEFAULT 'pendente',
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
  INDEX idx_fila_fiscal_trace(trace_id),
  INDEX idx_fila_fiscal_proxima(proxima_tentativa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- V61 Estoque Enterprise
-- Estoque mestre, fila exclusiva, alertas, reconciliação automática, histórico por SKU e auditoria de estoque.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS estoque_configuracoes (
  chave VARCHAR(120) PRIMARY KEY,
  valor LONGTEXT NULL,
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO estoque_configuracoes(chave, valor) VALUES
('estoque_mestre','tiny'),
('permitir_vsm_tiny','0'),
('bloquear_loop_bidirecional','1'),
('reconciliacao_automatica','1'),
('alertar_estoque_negativo','1'),
('alertar_produto_sem_mapeamento','1'),
('retencao_dias','90'),
('retry_minutos','5,15,30,60')
ON DUPLICATE KEY UPDATE valor=VALUES(valor);

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
  INDEX idx_fila_estoque_proxima(proxima_tentativa)
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
  INDEX idx_est_alerta_trace(trace_id)
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
  INDEX idx_est_saldo_mapeado(produto_mapeado)
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
  INDEX idx_est_aud_trace(trace_id)
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
INSERT INTO estoque_configuracoes(chave, valor) VALUES
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
('retry_minutos','5,15,30,60')
ON DUPLICATE KEY UPDATE valor=VALUES(valor);

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
CREATE TABLE IF NOT EXISTS fila_fiscal (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  nota_fiscal_id BIGINT NULL,
  nfe_integracao_id BIGINT NULL,
  acao ENUM('validar_xml','enviar_tiny','enviar_vsm','reprocessar','consultar_status') DEFAULT 'reprocessar',
  prioridade ENUM('critica','alta','normal','baixa') DEFAULT 'normal',
  status ENUM('pendente','processando','sucesso','erro','falha_definitiva') DEFAULT 'pendente',
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
  INDEX idx_fila_fiscal_proxima(proxima_tentativa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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

INSERT INTO estoque_configuracoes(chave, valor) VALUES
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
('consulta_vsm_alerta_falhas_percentual','30')
ON DUPLICATE KEY UPDATE valor=VALUES(valor);


-- ===== Base consolidada V82 =====
-- V54 - Instalação consolidada base
-- Use este arquivo para novas instalações quando desejar começar direto pela base V54.
-- Para ambientes existentes, use Validar Banco/Health de Módulos e correções seguras por serviço.
CREATE DATABASE IF NOT EXISTS hub_vsm_tiny CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hub_vsm_tiny;
-- V81: comando SOURCE removido para evitar dependência de arquivo legado inexistente: SOURCE install.sql;
-- V72: SOURCE legado removido; estrutura consolidada em install_final_v82.sql.


-- ===== Módulos consolidados V82 =====
-- V54 - Instalação modular consolidada
-- V81: referência a install_multidb legado removida; usar schema consolidado.
-- Execute depois de configurar os bancos modulares no config.php.
-- V81: comando SOURCE removido para evitar dependência de arquivo legado inexistente: SOURCE install_multidb_v43.sql;
-- V72: SOURCE legado removido; estrutura consolidada em install_final_v82.sql.


-- ===== Seeds consolidados V82 =====
-- V54 - Seeds técnicos recomendados
INSERT IGNORE INTO system_build_info(versao, build, descricao, desenvolvido_por)
VALUES('v54','v54-enterprise-light-production','Build atual do Hub de Integração Enterprise','Desenvolvido por Sabas');


-- ===== modules/backups.sql =====
-- V42 - Estrutura modular do banco: backups
-- Execute no banco do módulo correspondente.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS backups_banco (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NULL,
  arquivo VARCHAR(255) NOT NULL,
  tamanho_bytes BIGINT DEFAULT 0,
  status ENUM('sucesso','erro') DEFAULT 'sucesso',
  mensagem TEXT NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_backup_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- V58: compatibilidade com tela antiga/serviços que consultam tabela backups.
CREATE TABLE IF NOT EXISTS backups (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  arquivo VARCHAR(255) NOT NULL,
  tamanho BIGINT DEFAULT 0,
  status VARCHAR(40) DEFAULT 'gerado',
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ===== modules/core.sql =====
-- V42 - Estrutura modular do banco: core
-- Execute no banco do módulo correspondente.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  senha VARCHAR(255) NOT NULL,
  perfil ENUM('admin','gerente','operador') DEFAULT 'admin',
  ativo TINYINT DEFAULT 1,
  ultimo_login DATETIME NULL,
  deve_trocar_senha TINYINT DEFAULT 1,
  two_factor_enabled TINYINT DEFAULT 0,
  two_factor_secret TEXT NULL,
  two_factor_created_at DATETIME NULL,
  two_factor_last_verified_at DATETIME NULL,
  tentativas_login INT DEFAULT 0,
  bloqueado_ate DATETIME NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS empresas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(160) NOT NULL,
  cnpj VARCHAR(20) NULL,
  ativo TINYINT DEFAULT 1,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS filiais (
  id INT AUTO_INCREMENT PRIMARY KEY,
  empresa_id INT NOT NULL,
  nome VARCHAR(160) NOT NULL,
  cnpj VARCHAR(20) NULL,
  cidade VARCHAR(100) NULL,
  ativo TINYINT DEFAULT 1,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_filiais_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS configuracoes_integracao (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ambiente ENUM('homologacao','producao') DEFAULT 'homologacao',
  tiny_versao ENUM('v2','v3') DEFAULT 'v2',
  tiny_v2_url VARCHAR(255) DEFAULT 'https://api.tiny.com.br/api2',
  tiny_v2_token TEXT NULL,
  tiny_v3_ambiente ENUM('homologacao','producao') DEFAULT 'homologacao',
  tiny_v3_url VARCHAR(255) DEFAULT 'https://api.tiny.com.br/public-api/v3',
  tiny_v3_token TEXT NULL,
  tiny_v3_auth_url VARCHAR(255) NULL,
  tiny_v3_token_url VARCHAR(255) NULL,
  tiny_v3_client_id VARCHAR(255) NULL,
  tiny_v3_client_secret TEXT NULL,
  tiny_v3_redirect_uri VARCHAR(255) NULL,
  tiny_v3_scopes TEXT NULL,
  tiny_v3_produtos_listar VARCHAR(255) NULL,
  tiny_v3_produtos_obter VARCHAR(255) NULL,
  tiny_v3_produtos_criar VARCHAR(255) NULL,
  tiny_v3_produtos_alterar VARCHAR(255) NULL,
  tiny_v3_produtos_preco VARCHAR(255) NULL,
  tiny_v3_estoque_consultar VARCHAR(255) NULL,
  tiny_v3_estoque_atualizar VARCHAR(255) NULL,
  tiny_v3_pedidos_obter VARCHAR(255) NULL,
  tiny_v3_pedidos_lancar_estoque VARCHAR(255) NULL,
  tiny_v3_notas_obter VARCHAR(255) NULL,
  tiny_v3_manual_access_token TEXT NULL,
  tiny_v3_manual_refresh_token TEXT NULL,
  vsm_url VARCHAR(255) DEFAULT 'https://conectavenda.homolog.vsm.com.br',
  vsm_token TEXT NULL,
  vsm_api_principal VARCHAR(40) DEFAULT 'pedidos-integradora',
  vsm_api_loja VARCHAR(40) DEFAULT 'desativado',
  vsm_swagger_integradora VARCHAR(255) DEFAULT 'https://conectavenda.homolog.vsm.com.br/swagger-ui/index.html?urls.primaryName=pedidos-integradora',
  vsm_swagger_loja VARCHAR(255) DEFAULT 'https://conectavenda.homolog.vsm.com.br/swagger-ui/index.html?urls.primaryName=pedidos-loja',
  vsm_waf_agressivo TINYINT DEFAULT 0,
  vsm_api_observacao TEXT NULL,
  vsm_endpoint_baixa_estoque VARCHAR(255) DEFAULT '/api/estoque/baixa',
  vsm_endpoint_produto_novo VARCHAR(255) DEFAULT '/api/produtos',
  vsm_endpoint_consulta_estoque VARCHAR(255) DEFAULT '/api/estoque/consulta',
  fluxo_tiny_vsm_estoque TINYINT DEFAULT 1,
  fluxo_vsm_tiny_produto TINYINT DEFAULT 1,
  fluxo_vsm_tiny_pedido TINYINT DEFAULT 0,
  webhook_secret TEXT NULL,
  tiny_webhook_secret TEXT NULL,
  tiny_webhook_cnpj_autorizados TEXT NULL,
  tiny_webhook_exigir_secret TINYINT DEFAULT 1,
  tiny_webhook_rate_limit INT DEFAULT 60,
  tiny_webhook_max_bytes INT DEFAULT 1048576,
  bloquear_inativo_com_estoque TINYINT DEFAULT 1,
  sync_criar_produto_tiny TINYINT DEFAULT 1,
  sync_atualizar_produto_tiny TINYINT DEFAULT 1,
  sync_atualizar_estoque_tiny TINYINT DEFAULT 1,
  sync_atualizar_status_tiny TINYINT DEFAULT 1,
  sync_atualizar_preco_tiny TINYINT DEFAULT 1,
  sync_atualizar_descricao_tiny TINYINT DEFAULT 1,
  sync_atualizar_categoria_tiny TINYINT DEFAULT 1,
  sync_atualizar_marca_tiny TINYINT DEFAULT 1,
  sync_criar_produto_se_nao_existir TINYINT DEFAULT 0,
  sync_bloquear_estoque_negativo TINYINT DEFAULT 1,
  sync_receber_pedidos_vsm TINYINT DEFAULT 0,
  sync_enviar_pedido_tiny TINYINT DEFAULT 0,
  sync_vsm_enviar_estoque_tiny TINYINT DEFAULT 1,
  sync_vsm_status_produto_tiny TINYINT DEFAULT 1,
  sync_vsm_enviar_nota_tiny TINYINT DEFAULT 1,
  sync_bloquear_produto_novo_vsm TINYINT DEFAULT 1,
  sync_permitir_produto_novo_vsm_manual TINYINT DEFAULT 0,
  sync_tiny_enviar_nota_vsm TINYINT DEFAULT 0,
  sync_tiny_enviar_pedido_vsm TINYINT DEFAULT 1,
  sync_tiny_enviar_estoque_vsm TINYINT DEFAULT 0,
  sync_tiny_status_produto_vsm TINYINT DEFAULT 0,
  sync_tiny_bloquear_produto_novo_vsm TINYINT DEFAULT 1,
  sync_exigir_mapeamento_sku TINYINT DEFAULT 1,
  sync_aprovacao_manual_produto_novo_vsm TINYINT DEFAULT 1,
  sync_exigir_categoria_mapeada_vsm TINYINT DEFAULT 1,
  sync_permitir_atualizar_produto_existente_vsm TINYINT DEFAULT 1,
  sync_permitir_estoque_vsm_tiny TINYINT DEFAULT 1,
  sync_permitir_status_vsm_tiny TINYINT DEFAULT 1,
  sync_exigir_nfe_autorizada TINYINT DEFAULT 1,
  sync_ordem_envio TEXT NULL,
  fluxo_pedido_vsm_receber TINYINT DEFAULT 0,
  fluxo_pedido_vsm_enviar_tiny TINYINT DEFAULT 0,
  fluxo_pedido_tiny_enviar_vsm TINYINT DEFAULT 1,
  fluxo_nfe_vsm_enviar_tiny TINYINT DEFAULT 1,
  fluxo_nfe_tiny_enviar_vsm TINYINT DEFAULT 0,
  fluxo_estoque_tiny_enviar_vsm TINYINT DEFAULT 0,
  fluxo_estoque_vsm_enviar_tiny TINYINT DEFAULT 1,
  fluxo_produto_status_tiny_enviar_vsm TINYINT DEFAULT 0,
  fluxo_produto_status_vsm_enviar_tiny TINYINT DEFAULT 1,
  fluxo_produto_novo_vsm_bloquear TINYINT DEFAULT 1,
  fluxo_produto_novo_tiny_bloquear TINYINT DEFAULT 1,
  sync_exigir_ean_produto_novo_vsm TINYINT DEFAULT 1,
  sync_exigir_ncm_produto_novo_vsm TINYINT DEFAULT 1,
  sync_exigir_confirmacao_forte_produto_vsm TINYINT DEFAULT 1,
  sync_bloquear_duplicidade_produto_vsm TINYINT DEFAULT 1,
  produto_novo_aprovacao_modo VARCHAR(20) DEFAULT 'manual',
  produto_novo_auto_fallback_manual TINYINT DEFAULT 1,
  produto_novo_auto_exigir_ean TINYINT DEFAULT 1,
  produto_novo_auto_exigir_ncm TINYINT DEFAULT 1,
  produto_novo_auto_exigir_categoria TINYINT DEFAULT 1,
  produto_novo_auto_bloquear_duplicidade TINYINT DEFAULT 1,
  pedido_tiny_vsm_validacao_obrigatoria TINYINT DEFAULT 1,
  pedido_tiny_vsm_aprovacao_manual TINYINT DEFAULT 1,
  pedido_tiny_vsm_auto_enviar_validos TINYINT DEFAULT 0,
  pedido_tiny_vsm_exigir_sku_mapeado TINYINT DEFAULT 1,
  pedido_tiny_vsm_exigir_cliente_documento TINYINT DEFAULT 1,
  pedido_tiny_vsm_exigir_endereco TINYINT DEFAULT 1,
  pedido_tiny_vsm_status_permitidos VARCHAR(255) DEFAULT 'aprovado,pago,faturado,pronto para envio',
  vsm_endpoint_pedido VARCHAR(255) DEFAULT '/api/pedidos',
  pedido_ciclo_vida_obrigatorio TINYINT DEFAULT 1,
  pedido_xml_validacao_obrigatoria TINYINT DEFAULT 1,
  pedido_xml_auto_enviar_tiny TINYINT DEFAULT 0,
  pedido_retorno_vsm_exigir_chave_nfe TINYINT DEFAULT 1,
  pedido_retorno_vsm_exigir_xml TINYINT DEFAULT 1,
  tiny_endpoint_xml_nfe VARCHAR(255) DEFAULT 'nota.incluir.xml.php',
  tiny_endpoint_status_pedido VARCHAR(255) DEFAULT 'pedido.alterar.situacao.php',
  tiny_v3_operacional TINYINT DEFAULT 0,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS configuracoes_historico (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NULL,
  campo VARCHAR(120) NOT NULL,
  valor_antigo TEXT NULL,
  valor_novo TEXT NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_conf_hist_campo(campo),
  INDEX idx_conf_hist_trace(trace_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS login_tentativas (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(160) NULL,
  ip VARCHAR(45) NULL,
  sucesso TINYINT DEFAULT 0,
  mensagem VARCHAR(255) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_login_email(email),
  INDEX idx_login_ip(ip),
  INDEX idx_login_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS permissoes_perfil (
  id INT AUTO_INCREMENT PRIMARY KEY,
  perfil ENUM('admin','gerente','operador') NOT NULL,
  modulo VARCHAR(80) NOT NULL,
  acao VARCHAR(80) NOT NULL,
  permitido TINYINT DEFAULT 1,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_perm(perfil,modulo,acao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS tiny_v3_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ambiente ENUM('homologacao','producao') DEFAULT 'homologacao',
  access_token TEXT NOT NULL,
  refresh_token TEXT NULL,
  expires_at DATETIME NULL,
  scope TEXT NULL,
  origem VARCHAR(30) DEFAULT 'manual',
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NULL,
  INDEX idx_tiny_v3_tokens_expira(expires_at),
  INDEX idx_tiny_v3_tokens_ambiente(ambiente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS mapper_versions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(80) NOT NULL,
  versao VARCHAR(30) NOT NULL,
  descricao TEXT NULL,
  ativo TINYINT DEFAULT 1,
  schema_exemplo LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_mapper_nome_versao(nome, versao),
  INDEX idx_mapper_ativo(ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS tiny_testes_reais (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NULL,
  sku VARCHAR(120) NOT NULL,
  acao VARCHAR(60) NOT NULL,
  modo_tiny VARCHAR(40) NULL,
  versao_efetiva VARCHAR(60) NULL,
  sucesso TINYINT DEFAULT 0,
  payload_vsm LONGTEXT NULL,
  payload_tiny LONGTEXT NULL,
  retorno_json LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tiny_testes_sku(sku),
  INDEX idx_tiny_testes_trace(trace_id),
  INDEX idx_tiny_testes_sucesso(sucesso),
  INDEX idx_tiny_testes_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS tiny_error_catalogo (
  id INT AUTO_INCREMENT PRIMARY KEY,
  versao ENUM('v2','v3') NOT NULL,
  codigo VARCHAR(80) NOT NULL,
  titulo VARCHAR(180) NOT NULL,
  causa TEXT NULL,
  acao_recomendada TEXT NULL,
  retry_policy VARCHAR(120) NULL,
  ativo TINYINT DEFAULT 1,
  UNIQUE KEY uk_tiny_error(versao,codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS homologacao_checklist (
  id INT AUTO_INCREMENT PRIMARY KEY,
  chave VARCHAR(80) NOT NULL UNIQUE,
  titulo VARCHAR(180) NOT NULL,
  descricao TEXT NULL,
  status ENUM('pendente','ok','falha','nao_aplicavel') DEFAULT 'pendente',
  resultado LONGTEXT NULL,
  trace_id VARCHAR(80) NULL,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_homologacao_status(status),
  INDEX idx_homologacao_chave(chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO homologacao_checklist(chave,titulo,descricao,status) VALUES
('tiny_token','Testar token Tiny','Executar teste de conexão Tiny V2 com SKU real ou SKU de homologação.','pendente'),
('vsm_conexao','Testar conexão VSM','Validar DNS, HTTPS, token e endpoint configurado da VSM.','pendente'),
('vsm_produto_tiny','Produto VSM para Tiny','Simular ou receber produto real da VSM e criar/atualizar no Tiny.','pendente'),
('vsm_estoque_tiny','Estoque VSM para Tiny','Atualizar saldo de SKU existente no Tiny a partir da VSM.','pendente'),
('vsm_status_tiny','Status ativo/inativo VSM para Tiny','Inativar/ativar produto pela VSM respeitando bloqueio de estoque.','pendente'),
('tiny_baixa_vsm','Baixa Tiny para VSM','Receber evento do Tiny e enviar baixa de estoque para a VSM.','pendente'),
('auditoria_trace','Auditoria e Trace ID','Conferir payload original, transformado, enviado e retorno.','pendente'),
('fila_dlq','Fila morta / DLQ','Forçar erro controlado e validar reprocessamento/fila morta.','pendente'),
('reconciliacao','Reconciliação de estoque','Comparar saldo Tiny x VSM e registrar divergência.','pendente'),
('tiny_v3_token','Tiny V3 OAuth válido','Salvar token OAuth por ambiente e validar access/refresh token.','pendente'),
('tiny_v3_refresh','Tiny V3 refresh token','Executar renovação automática e confirmar sucesso.','pendente'),
('tiny_v3_produto_sku','Tiny V3 produto por SKU','Consultar SKU real e confirmar comparação exata.','pendente'),
('tiny_v3_estoque','Tiny V3 estoque por SKU','Consultar/atualizar estoque em SKU real de homologação.','pendente'),
('tiny_v3_logs','Tiny V3 logs técnicos','Confirmar tiny_v3_endpoint_logs com request/response mascarados.','pendente'),
('vsm_estoque_consulta','VSM consulta estoque por SKU','Validar endpoint real de consulta de estoque da VSM para reconciliação.','pendente');

INSERT IGNORE INTO mapper_versions(nome,versao,descricao,ativo) VALUES
('produto_vsm_para_tiny','v1','Mapper padrão para produto novo criado na VSM e enviado ao Tiny.',1),
('baixa_tiny_para_vsm','v1','Mapper padrão para baixa/alteração de estoque originada no Tiny e enviada à VSM.',1);

-- V56: circuit_breakers pertence ao módulo fila. Seed movido para database/modules/fila.sql.

-- V57: notificacoes_config pertence ao módulo observabilidade; seed movido para observabilidade.sql.

INSERT IGNORE INTO permissoes_perfil(perfil,modulo,acao,permitido) VALUES
('admin','ficha_tecnica','visualizar',1),('admin','seguranca','visualizar',1),('admin','seguranca','gerenciar',1),('admin','auditoria','exportar',1),('admin','dashboard','visualizar',1),('admin','tiny_webhooks','visualizar',1),('admin','estoque','visualizar',1),('admin','estoque','simular',1),('admin','produtos_vsm','visualizar',1),('admin','produtos_vsm','simular',1),('admin','fluxos','editar',1),('admin','pedidos','visualizar',1),('admin','pedidos','detalhe',1),('admin','fila','visualizar',1),('admin','fila','reprocessar',1),('admin','configuracoes','editar',1),('admin','configuracoes','visualizar',1),('admin','auditoria','visualizar',1),('admin','usuarios','gerenciar',1),('admin','backup','gerar',1),('admin','backup','visualizar',1),('admin','logs','visualizar',1),('admin','logs','exportar',1),('admin','notificacoes','visualizar',1),('admin','produtos','visualizar',1),
('gerente','ficha_tecnica','visualizar',1),('gerente','seguranca','visualizar',1),('gerente','seguranca','gerenciar',0),('gerente','auditoria','exportar',1),('gerente','dashboard','visualizar',1),('gerente','tiny_webhooks','visualizar',1),('gerente','estoque','visualizar',1),('gerente','estoque','simular',1),('gerente','produtos_vsm','visualizar',1),('gerente','produtos_vsm','simular',1),('gerente','fluxos','editar',1),('gerente','pedidos','visualizar',1),('gerente','pedidos','detalhe',1),('gerente','fila','visualizar',1),('gerente','fila','reprocessar',1),('gerente','configuracoes','editar',1),('gerente','configuracoes','visualizar',1),('gerente','auditoria','visualizar',1),('gerente','usuarios','gerenciar',0),('gerente','backup','gerar',0),('gerente','backup','visualizar',1),('gerente','logs','visualizar',1),('gerente','logs','exportar',1),('gerente','notificacoes','visualizar',1),('gerente','produtos','visualizar',1),
('admin','fila_morta','visualizar',1),('admin','fila_morta','reprocessar',1),('admin','laboratorio','visualizar',1),('admin','laboratorio','executar',1),('admin','reconciliacao','visualizar',1),('admin','reconciliacao','executar',1),('admin','produtos_vsm','simular',1),('admin','metricas','visualizar',1),('admin','selftest','visualizar',1),('admin','selftest','executar',1),('gerente','fila_morta','visualizar',1),('gerente','fila_morta','reprocessar',1),('gerente','laboratorio','visualizar',1),('gerente','laboratorio','executar',1),('gerente','reconciliacao','visualizar',1),('gerente','reconciliacao','executar',1),('gerente','produtos_vsm','simular',1),('gerente','metricas','visualizar',1),('gerente','selftest','visualizar',1),('gerente','selftest','executar',1),('operador','fila_morta','visualizar',0),('operador','fila_morta','reprocessar',0),('operador','laboratorio','visualizar',0),('operador','laboratorio','executar',0),('operador','reconciliacao','visualizar',0),('operador','reconciliacao','executar',0),('operador','metricas','visualizar',0),('operador','selftest','visualizar',0),('operador','selftest','executar',0),
('operador','ficha_tecnica','visualizar',0),('operador','seguranca','visualizar',0),('operador','seguranca','gerenciar',0),('operador','auditoria','exportar',0),('operador','dashboard','visualizar',1),('operador','tiny_webhooks','visualizar',0),('operador','estoque','visualizar',1),('operador','estoque','simular',0),('operador','produtos_vsm','visualizar',1),('operador','produtos_vsm','simular',0),('operador','fluxos','editar',0),('operador','pedidos','visualizar',1),('operador','pedidos','detalhe',1),('operador','fila','visualizar',1),('operador','fila','reprocessar',0),('operador','configuracoes','editar',0),('operador','configuracoes','visualizar',0),('operador','auditoria','visualizar',0),('operador','usuarios','gerenciar',0),('operador','backup','gerar',0),('operador','backup','visualizar',0),('operador','logs','visualizar',0),('operador','logs','exportar',0),('operador','notificacoes','visualizar',1),('operador','produtos','visualizar',1);


INSERT IGNORE INTO usuarios(id,nome,email,senha,perfil,ativo) VALUES
(1,'{ADMIN_NOME}','{ADMIN_EMAIL}','{ADMIN_HASH}','admin',1)
ON DUPLICATE KEY UPDATE nome=VALUES(nome), senha=VALUES(senha), perfil='admin', ativo=1;

INSERT IGNORE INTO empresas(id,nome,cnpj) VALUES(1,'Empresa Demonstração','');


CREATE TABLE IF NOT EXISTS homologacao_automatica_relatorios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NULL,
  aprovado TINYINT DEFAULT 0,
  liberado_tiny_v3 TINYINT DEFAULT 0,
  ambiente VARCHAR(30) NULL,
  tiny_v3_ambiente VARCHAR(30) NULL,
  resumo_json LONGTEXT NULL,
  relatorio_json LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_auto_hom_trace(trace_id),
  INDEX idx_auto_hom_aprovado(aprovado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO homologacao_checklist(chave,titulo,descricao,status) VALUES
('auto_banco','Homologação automática: banco','Validação automática de conexão e estrutura mínima do banco.','pendente'),
('auto_tiny_v3_oauth','Homologação automática: OAuth Tiny V3','Validação automática de Client ID, Redirect URI, token e refresh.','pendente'),
('auto_tiny_v3_modulos','Homologação automática: módulos Tiny V3','Testes automáticos de produto, estoque, pedido e logs Tiny V3.','pendente'),
('auto_vsm','Homologação automática: VSM','Validação automática da configuração mínima da VSM.','pendente'),
('auto_fila_auditoria','Homologação automática: fila/auditoria','Validação automática de fila, DLQ e auditoria por Trace ID.','pendente');

INSERT INTO configuracoes_integracao(id, ambiente, tiny_versao, tiny_v2_url, tiny_v2_token, tiny_v3_url, tiny_v3_ambiente, tiny_v3_token, tiny_v3_auth_url, tiny_v3_token_url, tiny_v3_client_id, tiny_v3_client_secret, tiny_v3_redirect_uri, tiny_v3_scopes, tiny_v3_manual_access_token, tiny_v3_manual_refresh_token, vsm_url, vsm_token, vsm_endpoint_baixa_estoque, vsm_endpoint_produto_novo, vsm_endpoint_consulta_estoque, fluxo_tiny_vsm_estoque, fluxo_vsm_tiny_produto, fluxo_vsm_tiny_pedido, webhook_secret, tiny_webhook_secret, tiny_webhook_cnpj_autorizados, tiny_webhook_exigir_secret, tiny_webhook_rate_limit, tiny_webhook_max_bytes, bloquear_inativo_com_estoque, sync_criar_produto_tiny, sync_atualizar_produto_tiny, sync_atualizar_estoque_tiny, sync_atualizar_status_tiny, sync_atualizar_preco_tiny, sync_atualizar_descricao_tiny, sync_atualizar_categoria_tiny, sync_atualizar_marca_tiny, sync_criar_produto_se_nao_existir, sync_bloquear_estoque_negativo, tiny_v3_operacional)
VALUES(1, '{AMBIENTE}', '{TINY_VERSION}', '{TINY_V2_URL}', '{TINY_V2_TOKEN}', '{TINY_V3_URL}', 'homologacao', '{TINY_V3_TOKEN}', 'https://accounts.tiny.com.br/realms/tiny/protocol/openid-connect/auth', 'https://accounts.tiny.com.br/realms/tiny/protocol/openid-connect/token', '', '', '', '', '', '', '{VSM_URL}', '{VSM_TOKEN}', '/api/estoque/baixa', '/api/produtos', '/api/estoque/consulta', 1, 1, 0, '{WEBHOOK_SECRET}', '{WEBHOOK_SECRET}', '', 1, 60, 1048576, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 1, 0)
ON DUPLICATE KEY UPDATE
ambiente=VALUES(ambiente), tiny_versao=VALUES(tiny_versao), tiny_v2_url=VALUES(tiny_v2_url), tiny_v2_token=VALUES(tiny_v2_token), tiny_v3_url=VALUES(tiny_v3_url), tiny_v3_ambiente=VALUES(tiny_v3_ambiente), tiny_v3_token=VALUES(tiny_v3_token), tiny_v3_auth_url=VALUES(tiny_v3_auth_url), tiny_v3_token_url=VALUES(tiny_v3_token_url), tiny_v3_client_id=VALUES(tiny_v3_client_id), tiny_v3_client_secret=VALUES(tiny_v3_client_secret), tiny_v3_redirect_uri=VALUES(tiny_v3_redirect_uri), tiny_v3_scopes=VALUES(tiny_v3_scopes), tiny_v3_manual_access_token=VALUES(tiny_v3_manual_access_token), tiny_v3_manual_refresh_token=VALUES(tiny_v3_manual_refresh_token), vsm_url=VALUES(vsm_url), vsm_token=VALUES(vsm_token), vsm_endpoint_baixa_estoque=VALUES(vsm_endpoint_baixa_estoque), vsm_endpoint_produto_novo=VALUES(vsm_endpoint_produto_novo), vsm_endpoint_consulta_estoque=VALUES(vsm_endpoint_consulta_estoque), fluxo_tiny_vsm_estoque=VALUES(fluxo_tiny_vsm_estoque), fluxo_vsm_tiny_produto=VALUES(fluxo_vsm_tiny_produto), fluxo_vsm_tiny_pedido=VALUES(fluxo_vsm_tiny_pedido), webhook_secret=VALUES(webhook_secret), tiny_webhook_secret=VALUES(tiny_webhook_secret), tiny_webhook_cnpj_autorizados=VALUES(tiny_webhook_cnpj_autorizados), tiny_webhook_exigir_secret=VALUES(tiny_webhook_exigir_secret), tiny_webhook_rate_limit=VALUES(tiny_webhook_rate_limit), tiny_webhook_max_bytes=VALUES(tiny_webhook_max_bytes), bloquear_inativo_com_estoque=VALUES(bloquear_inativo_com_estoque), sync_criar_produto_tiny=VALUES(sync_criar_produto_tiny), sync_atualizar_produto_tiny=VALUES(sync_atualizar_produto_tiny), sync_atualizar_estoque_tiny=VALUES(sync_atualizar_estoque_tiny), sync_atualizar_status_tiny=VALUES(sync_atualizar_status_tiny), sync_atualizar_preco_tiny=VALUES(sync_atualizar_preco_tiny), sync_atualizar_descricao_tiny=VALUES(sync_atualizar_descricao_tiny), sync_atualizar_categoria_tiny=VALUES(sync_atualizar_categoria_tiny), sync_atualizar_marca_tiny=VALUES(sync_atualizar_marca_tiny), sync_criar_produto_se_nao_existir=VALUES(sync_criar_produto_se_nao_existir), sync_bloquear_estoque_negativo=VALUES(sync_bloquear_estoque_negativo), tiny_v3_operacional=VALUES(tiny_v3_operacional);




INSERT IGNORE INTO permissoes_perfil(perfil,modulo,acao,permitido) VALUES
('admin','produtos_vsm','visualizar',1),
('admin','reconciliacao','visualizar',1),
('gerente','produtos_vsm','visualizar',1),
('gerente','reconciliacao','visualizar',1),
('admin','homologacao','visualizar',1),
('admin','homologacao','executar',1),
('admin','homologacao','relatorio',1),
('admin','database','validar',1),
('gerente','homologacao','visualizar',1),
('gerente','homologacao','relatorio',1),
('gerente','database','validar',1);


INSERT IGNORE INTO tiny_error_catalogo(versao,codigo,titulo,causa,acao_recomendada,retry_policy) VALUES
('v2','TINY_V2_TOKEN_INVALID','Token Tiny V2 inválido','Credencial inválida, expirada ou copiada incorretamente.','Atualize o token Tiny V2 em Configurações e teste novamente.','manual'),
('v2','TINY_V2_RATE_LIMIT','Limite de API Tiny V2','Muitas chamadas em pouco tempo.','Reduza frequência do worker e aguarde a janela de limite.','5/15/30min'),
('v2','TINY_V2_PRODUTO_NAO_ENCONTRADO','Produto não encontrado no Tiny V2','SKU inexistente ou divergente.','Vincule SKU manualmente ou crie produto no Tiny.','manual'),
('v3','TINY_V3_OAUTH_EXPIRED','OAuth Tiny V3 expirado','Access token expirou e refresh falhou.','Reconectar OAuth Tiny V3 no painel.','manual'),
('v3','TINY_V3_ENDPOINT_ERROR','Endpoint Tiny V3 com falha','Endpoint retornou erro HTTP ou payload inesperado.','Abrir logs Tiny V3 e validar endpoint configurado.','1/5/15min');


-- V24 Production Ready


CREATE TABLE IF NOT EXISTS instalacao_prechecks (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  item VARCHAR(160) NOT NULL,
  status ENUM('ok','falha','atencao') DEFAULT 'atencao',
  detalhe TEXT NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_precheck_status(status),
  INDEX idx_precheck_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS upgrade_snapshots (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  versao VARCHAR(30) NOT NULL,
  tipo ENUM('pre_upgrade','post_upgrade','manual') DEFAULT 'pre_upgrade',
  snapshot_json LONGTEXT NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_upgrade_versao(versao),
  INDEX idx_upgrade_trace(trace_id),
  INDEX idx_upgrade_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS post_install_tests (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  score INT DEFAULT 0,
  resultado_json LONGTEXT NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_post_install_trace(trace_id),
  INDEX idx_post_install_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS tiny_v2_retry_policies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(80) NOT NULL UNIQUE,
  retry TINYINT DEFAULT 1,
  atrasos_minutos VARCHAR(120) DEFAULT '1,5,15',
  acao_recomendada TEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- V57: seed vsm_endpoint_catalogo movido para produtos.sql.

INSERT IGNORE INTO tiny_v2_retry_policies(codigo,retry,atrasos_minutos,acao_recomendada) VALUES
('TINY_V2_TOKEN_INVALID',0,'','Atualizar token Tiny V2 manualmente.'),
('TINY_V2_RATE_LIMIT',1,'5,15,30,60','Reduzir frequência do worker e aguardar janela de limite.'),
('TINY_V2_TIMEOUT',1,'1,5,15,30','Reprocessar com backoff progressivo.'),
('TINY_V2_HTTP_500',1,'1,5,15,30','Reprocessar e monitorar instabilidade da API.'),
('TINY_V2_PRODUTO_NAO_ENCONTRADO',0,'','Vincular SKU ou cadastrar produto antes de reprocessar.');

INSERT IGNORE INTO permissoes_perfil(perfil,modulo,acao,permitido) VALUES
('admin','ficha_tecnica','visualizar',1),('gerente','ficha_tecnica','visualizar',1),('operador','ficha_tecnica','visualizar',0),
('admin','fila_morta','visualizar',1),('gerente','fila_morta','visualizar',1),('operador','fila_morta','visualizar',0),
('admin','database','validar',1),('gerente','database','validar',1),('operador','database','validar',0);


CREATE TABLE IF NOT EXISTS schema_migrations (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  migration VARCHAR(180) NOT NULL UNIQUE,
  checksum VARCHAR(128) NULL,
  status ENUM('aplicada','falha') DEFAULT 'aplicada',
  mensagem TEXT NULL,
  aplicada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_schema_migrations_status(status),
  INDEX idx_schema_migrations_data(aplicada_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Dados iniciais do núcleo
INSERT INTO configuracoes_integracao(id, ambiente, tiny_versao, tiny_v2_url, tiny_v2_token, tiny_v3_url, tiny_v3_ambiente, tiny_v3_token, tiny_v3_auth_url, tiny_v3_token_url, tiny_v3_client_id, tiny_v3_client_secret, tiny_v3_redirect_uri, tiny_v3_scopes, tiny_v3_manual_access_token, tiny_v3_manual_refresh_token, vsm_url, vsm_token, vsm_endpoint_baixa_estoque, vsm_endpoint_produto_novo, vsm_endpoint_consulta_estoque, fluxo_tiny_vsm_estoque, fluxo_vsm_tiny_produto, fluxo_vsm_tiny_pedido, webhook_secret, tiny_webhook_secret, tiny_webhook_cnpj_autorizados, tiny_webhook_exigir_secret, tiny_webhook_rate_limit, tiny_webhook_max_bytes, bloquear_inativo_com_estoque, sync_criar_produto_tiny, sync_atualizar_produto_tiny, sync_atualizar_estoque_tiny, sync_atualizar_status_tiny, sync_atualizar_preco_tiny, sync_atualizar_descricao_tiny, sync_atualizar_categoria_tiny, sync_atualizar_marca_tiny, sync_criar_produto_se_nao_existir, sync_bloquear_estoque_negativo, tiny_v3_operacional)
VALUES(1, '{AMBIENTE}', '{TINY_VERSION}', '{TINY_V2_URL}', '{TINY_V2_TOKEN}', '{TINY_V3_URL}', 'homologacao', '{TINY_V3_TOKEN}', 'https://accounts.tiny.com.br/realms/tiny/protocol/openid-connect/auth', 'https://accounts.tiny.com.br/realms/tiny/protocol/openid-connect/token', '', '', '', '', '', '', '{VSM_URL}', '{VSM_TOKEN}', '/api/estoque/baixa', '/api/produtos', '/api/estoque/consulta', 1, 1, 0, '{WEBHOOK_SECRET}', '{WEBHOOK_SECRET}', '', 1, 60, 1048576, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 1, 0)
ON DUPLICATE KEY UPDATE
ambiente=VALUES(ambiente), tiny_versao=VALUES(tiny_versao), tiny_v2_url=VALUES(tiny_v2_url), tiny_v2_token=VALUES(tiny_v2_token), tiny_v3_url=VALUES(tiny_v3_url), tiny_v3_ambiente=VALUES(tiny_v3_ambiente), tiny_v3_token=VALUES(tiny_v3_token), tiny_v3_auth_url=VALUES(tiny_v3_auth_url), tiny_v3_token_url=VALUES(tiny_v3_token_url), tiny_v3_client_id=VALUES(tiny_v3_client_id), tiny_v3_client_secret=VALUES(tiny_v3_client_secret), tiny_v3_redirect_uri=VALUES(tiny_v3_redirect_uri), tiny_v3_scopes=VALUES(tiny_v3_scopes), tiny_v3_manual_access_token=VALUES(tiny_v3_manual_access_token), tiny_v3_manual_refresh_token=VALUES(tiny_v3_manual_refresh_token), vsm_url=VALUES(vsm_url), vsm_token=VALUES(vsm_token), vsm_endpoint_baixa_estoque=VALUES(vsm_endpoint_baixa_estoque), vsm_endpoint_produto_novo=VALUES(vsm_endpoint_produto_novo), vsm_endpoint_consulta_estoque=VALUES(vsm_endpoint_consulta_estoque), fluxo_tiny_vsm_estoque=VALUES(fluxo_tiny_vsm_estoque), fluxo_vsm_tiny_produto=VALUES(fluxo_vsm_tiny_produto), fluxo_vsm_tiny_pedido=VALUES(fluxo_vsm_tiny_pedido), webhook_secret=VALUES(webhook_secret), tiny_webhook_secret=VALUES(tiny_webhook_secret), tiny_webhook_cnpj_autorizados=VALUES(tiny_webhook_cnpj_autorizados), tiny_webhook_exigir_secret=VALUES(tiny_webhook_exigir_secret), tiny_webhook_rate_limit=VALUES(tiny_webhook_rate_limit), tiny_webhook_max_bytes=VALUES(tiny_webhook_max_bytes), bloquear_inativo_com_estoque=VALUES(bloquear_inativo_com_estoque), sync_criar_produto_tiny=VALUES(sync_criar_produto_tiny), sync_atualizar_produto_tiny=VALUES(sync_atualizar_produto_tiny), sync_atualizar_estoque_tiny=VALUES(sync_atualizar_estoque_tiny), sync_atualizar_status_tiny=VALUES(sync_atualizar_status_tiny), sync_atualizar_preco_tiny=VALUES(sync_atualizar_preco_tiny), sync_atualizar_descricao_tiny=VALUES(sync_atualizar_descricao_tiny), sync_atualizar_categoria_tiny=VALUES(sync_atualizar_categoria_tiny), sync_atualizar_marca_tiny=VALUES(sync_atualizar_marca_tiny), sync_criar_produto_se_nao_existir=VALUES(sync_criar_produto_se_nao_existir), sync_bloquear_estoque_negativo=VALUES(sync_bloquear_estoque_negativo), tiny_v3_operacional=VALUES(tiny_v3_operacional);

-- V45_SAFE_DEFAULTS: produto novo VSM bloqueado por padrão
UPDATE configuracoes_integracao SET sync_bloquear_produto_novo_vsm=1, sync_permitir_produto_novo_vsm_manual=1, sync_aprovacao_manual_produto_novo_vsm=1, sync_exigir_categoria_mapeada_vsm=1, sync_criar_produto_tiny=0, sync_criar_produto_se_nao_existir=0, sync_exigir_mapeamento_sku=1 WHERE id=1;

-- V50 - Produção segura, Tiny validado e sistema leve
CREATE TABLE IF NOT EXISTS tiny_validacoes_execucoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NULL,
  versao VARCHAR(10) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'pendente',
  score INT NOT NULL DEFAULT 0,
  ambiente VARCHAR(30) NULL,
  sku_teste VARCHAR(120) NULL,
  resumo TEXT NULL,
  resultado_json LONGTEXT NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tiny_val_versao (versao),
  INDEX idx_tiny_val_status (status),
  INDEX idx_tiny_val_criado (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS production_readiness_v50 (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NULL,
  status VARCHAR(30) NOT NULL,
  score INT NOT NULL DEFAULT 0,
  resultado_json LONGTEXT NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pr50_status(status),
  INDEX idx_pr50_criado(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- V57: defaults consolidados de produto/pedido/NF-e no módulo core.
UPDATE configuracoes_integracao
SET sync_bloquear_produto_novo_vsm=1,
    sync_permitir_produto_novo_vsm_manual=1,
    sync_aprovacao_manual_produto_novo_vsm=1,
    sync_exigir_categoria_mapeada_vsm=1,
    sync_exigir_ean_produto_novo_vsm=1,
    sync_exigir_ncm_produto_novo_vsm=1,
    sync_exigir_confirmacao_forte_produto_vsm=1,
    sync_bloquear_duplicidade_produto_vsm=1,
    produto_novo_aprovacao_modo='manual',
    produto_novo_auto_fallback_manual=1,
    produto_novo_auto_exigir_ean=1,
    produto_novo_auto_exigir_ncm=1,
    produto_novo_auto_exigir_categoria=1,
    produto_novo_auto_bloquear_duplicidade=1,
    pedido_tiny_vsm_validacao_obrigatoria=1,
    pedido_tiny_vsm_aprovacao_manual=1,
    pedido_tiny_vsm_auto_enviar_validos=0,
    pedido_tiny_vsm_exigir_sku_mapeado=1,
    pedido_tiny_vsm_exigir_cliente_documento=1,
    pedido_tiny_vsm_exigir_endereco=1,
    pedido_ciclo_vida_obrigatorio=1,
    pedido_xml_validacao_obrigatoria=1,
    pedido_xml_auto_enviar_tiny=0,
    pedido_retorno_vsm_exigir_chave_nfe=1,
    pedido_retorno_vsm_exigir_xml=1,
    sync_criar_produto_tiny=0,
    sync_criar_produto_se_nao_existir=0,
    sync_exigir_mapeamento_sku=1
WHERE id=1;

INSERT IGNORE INTO schema_migrations(migration,checksum,status,mensagem)
VALUES('v57_modular_tables_fix','v57','aplicada','Correção completa de seeds e tabelas em bancos modulares.');

-- V58: tabelas VSM configuráveis ausentes no install modular core.
CREATE TABLE IF NOT EXISTS vsm_endpoints (
  id INT AUTO_INCREMENT PRIMARY KEY,
  chave VARCHAR(100) NOT NULL UNIQUE,
  nome VARCHAR(160) NOT NULL,
  categoria VARCHAR(60) NOT NULL DEFAULT 'geral',
  metodo_http VARCHAR(10) NOT NULL DEFAULT 'GET',
  endpoint VARCHAR(255) NOT NULL,
  ativo TINYINT NOT NULL DEFAULT 1,
  timeout_segundos INT NOT NULL DEFAULT 30,
  retry_maximo INT NOT NULL DEFAULT 3,
  ordem_execucao INT NOT NULL DEFAULT 0,
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
  transformacao VARCHAR(120) NULL,
  obrigatorio TINYINT NOT NULL DEFAULT 0,
  ativo TINYINT NOT NULL DEFAULT 1,
  observacao TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_vsm_campo (categoria, campo_vsm, campo_hub),
  INDEX idx_vsm_campos_categoria (categoria),
  INDEX idx_vsm_campos_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ===== modules/estoque.sql =====
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
  UNIQUE KEY uk_baixa_referencia_sku (referencia, sku)
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
  INDEX idx_div_trace(trace_id)
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
  INDEX idx_reconc_data(criado_em)
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
  INDEX idx_reconc_item_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- V61 Estoque Enterprise
-- Estoque mestre, fila exclusiva, alertas, reconciliação automática, histórico por SKU e auditoria de estoque.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS estoque_configuracoes (
  chave VARCHAR(120) PRIMARY KEY,
  valor LONGTEXT NULL,
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO estoque_configuracoes(chave, valor) VALUES
('estoque_mestre','tiny'),
('permitir_vsm_tiny','0'),
('bloquear_loop_bidirecional','1'),
('reconciliacao_automatica','1'),
('alertar_estoque_negativo','1'),
('alertar_produto_sem_mapeamento','1'),
('retencao_dias','90'),
('retry_minutos','5,15,30,60')
ON DUPLICATE KEY UPDATE valor=VALUES(valor);

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
  INDEX idx_fila_estoque_proxima(proxima_tentativa)
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
  INDEX idx_est_alerta_trace(trace_id)
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
  INDEX idx_est_saldo_mapeado(produto_mapeado)
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
  INDEX idx_est_aud_trace(trace_id)
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
INSERT INTO estoque_configuracoes(chave, valor) VALUES
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
('retry_minutos','5,15,30,60')
ON DUPLICATE KEY UPDATE valor=VALUES(valor);

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
CREATE TABLE IF NOT EXISTS fila_fiscal (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  nota_fiscal_id BIGINT NULL,
  nfe_integracao_id BIGINT NULL,
  acao ENUM('validar_xml','enviar_tiny','enviar_vsm','reprocessar','consultar_status') DEFAULT 'reprocessar',
  prioridade ENUM('critica','alta','normal','baixa') DEFAULT 'normal',
  status ENUM('pendente','processando','sucesso','erro','falha_definitiva') DEFAULT 'pendente',
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
  INDEX idx_fila_fiscal_proxima(proxima_tentativa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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

INSERT INTO estoque_configuracoes(chave, valor) VALUES
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
('consulta_vsm_alerta_falhas_percentual','30')
ON DUPLICATE KEY UPDATE valor=VALUES(valor);


-- ===== modules/fila.sql =====
-- V42 - Estrutura modular do banco: fila
-- Execute no banco do módulo correspondente.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS fila_integracao (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tipo VARCHAR(50) NOT NULL,
  prioridade ENUM('critica','alta','normal','baixa') DEFAULT 'normal',
  categoria VARCHAR(60) NULL,
  referencia VARCHAR(120) NULL,
  payload LONGTEXT NULL,
  retorno LONGTEXT NULL,
  status ENUM('pendente','processando','sucesso','erro','falha_definitiva') DEFAULT 'pendente',
  codigo_erro VARCHAR(80) NULL,
  tentativas INT DEFAULT 0,
  proxima_tentativa DATETIME NULL,
  processando_desde DATETIME NULL,
  processado_em DATETIME NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_fila_status(status),
  INDEX idx_fila_codigo(codigo_erro),
  INDEX idx_fila_tipo(tipo),
  INDEX idx_fila_prioridade(prioridade,status)
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
  INDEX idx_fila_morta_data(criado_em)
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
('tiny','fechado');

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
  status ENUM('pendente','processando','sucesso','erro','falha_definitiva') DEFAULT 'pendente',
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
  INDEX idx_fila_fiscal_proxima(proxima_tentativa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ===== modules/fiscal.sql =====
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
  INDEX idx_nfe_trace(trace_id)
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
  INDEX idx_nfe_evento_trace(trace_id)
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
  INDEX idx_nfe_int_trace(trace_id)
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
  INDEX idx_nfe_xml_nota(nota_fiscal_id)
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
  INDEX idx_nfe_status_trace(trace_id)
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


-- ===== modules/observabilidade.sql =====
-- V42 - Estrutura modular do banco: observabilidade
-- Execute no banco do módulo correspondente.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS logs_integracao (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NULL,
  tipo VARCHAR(50) NOT NULL,
  nivel ENUM('info','alerta','erro','critico') DEFAULT 'info',
  codigo_erro VARCHAR(80) NULL,
  mensagem TEXT NOT NULL,
  payload LONGTEXT NULL,
  ip VARCHAR(45) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_logs_tipo(tipo),
  INDEX idx_logs_nivel(nivel),
  INDEX idx_logs_data(criado_em),
  INDEX idx_logs_trace(trace_id),
  INDEX idx_logs_codigo(codigo_erro)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS auditoria_eventos (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NOT NULL,
  usuario_id INT NULL,
  acao VARCHAR(120) NOT NULL,
  entidade VARCHAR(80) NULL,
  entidade_id VARCHAR(120) NULL,
  status ENUM('sucesso','erro','alerta','info') DEFAULT 'info',
  nivel ENUM('info','alerta','erro','critico') DEFAULT 'info',
  codigo_erro VARCHAR(80) NULL,
  mensagem TEXT NULL,
  causa_provavel TEXT NULL,
  acao_recomendada TEXT NULL,
  payload LONGTEXT NULL,
  retorno LONGTEXT NULL,
  contexto LONGTEXT NULL,
  ip VARCHAR(45) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_trace(trace_id),
  INDEX idx_audit_acao(acao),
  INDEX idx_audit_status(status),
  INDEX idx_audit_entidade(entidade, entidade_id),
  INDEX idx_audit_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS notificacoes (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tipo ENUM('pedido_novo','pedido_integrado','erro_integracao','fila','estoque','baixa_estoque','produto_novo','integracao_sucesso','nota_fiscal','sistema') DEFAULT 'sistema',
  titulo VARCHAR(160) NOT NULL,
  mensagem TEXT NOT NULL,
  severidade ENUM('info','sucesso','alerta','erro','critico') DEFAULT 'info',
  entidade VARCHAR(80) NULL,
  entidade_id VARCHAR(120) NULL,
  link VARCHAR(255) NULL,
  trace_id VARCHAR(80) NULL,
  payload LONGTEXT NULL,
  lida TINYINT DEFAULT 0,
  lida_em DATETIME NULL,
  criada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notif_lida(lida),
  INDEX idx_notif_tipo(tipo),
  INDEX idx_notif_sev(severidade),
  INDEX idx_notif_trace(trace_id),
  INDEX idx_notif_data(criada_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS notificacoes_config (
  id INT AUTO_INCREMENT PRIMARY KEY,
  painel TINYINT DEFAULT 1,
  som TINYINT DEFAULT 1,
  browser_push TINYINT DEFAULT 1,
  email TINYINT DEFAULT 0,
  whatsapp TINYINT DEFAULT 0,
  pedido_novo TINYINT DEFAULT 1,
  erro_integracao TINYINT DEFAULT 1,
  fila_parada TINYINT DEFAULT 1,
  estoque_alerta TINYINT DEFAULT 1,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO notificacoes_config(id) VALUES(1);


CREATE TABLE IF NOT EXISTS diagnostico_api (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  sistema VARCHAR(30) NOT NULL,
  endpoint VARCHAR(255) NULL,
  status ENUM('online','atencao','erro') DEFAULT 'atencao',
  http_code INT NULL,
  tempo_ms INT NULL,
  mensagem TEXT NULL,
  detalhes LONGTEXT NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_diag_sistema(sistema),
  INDEX idx_diag_status(status),
  INDEX idx_diag_data(criado_em),
  INDEX idx_diag_trace(trace_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS tiny_v3_endpoint_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  endpoint VARCHAR(180) NULL,
  metodo VARCHAR(10) NULL,
  http_code INT NULL,
  sucesso TINYINT DEFAULT 0,
  tempo_ms INT NULL,
  trace_id VARCHAR(80) NULL,
  request_body LONGTEXT NULL,
  response_body LONGTEXT NULL,
  erro TEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tiny_v3_endpoint_logs_data(criado_em),
  INDEX idx_tiny_v3_endpoint_logs_trace(trace_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS metricas_api (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  sistema VARCHAR(40) NOT NULL,
  endpoint VARCHAR(255) NULL,
  metodo VARCHAR(10) DEFAULT 'POST',
  http_code INT NULL,
  tempo_ms INT NULL,
  sucesso TINYINT DEFAULT 0,
  codigo_erro VARCHAR(100) NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_metricas_sistema(sistema),
  INDEX idx_metricas_sucesso(sucesso),
  INDEX idx_metricas_data(criado_em),
  INDEX idx_metricas_trace(trace_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS security_audit (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NULL,
  usuario_id INT NULL,
  evento VARCHAR(120) NOT NULL,
  nivel ENUM('info','alerta','erro','critico') DEFAULT 'info',
  ip VARCHAR(45) NULL,
  user_agent TEXT NULL,
  session_id VARCHAR(128) NULL,
  detalhes LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sec_evento(evento),
  INDEX idx_sec_nivel(nivel),
  INDEX idx_sec_trace(trace_id),
  INDEX idx_sec_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS auditoria_timeline (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NOT NULL,
  fase VARCHAR(120) NOT NULL,
  status ENUM('info','sucesso','alerta','erro','critico') DEFAULT 'info',
  entidade VARCHAR(80) NULL,
  entidade_id VARCHAR(120) NULL,
  detalhes LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_timeline_trace(trace_id),
  INDEX idx_timeline_fase(fase),
  INDEX idx_timeline_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS auditoria_detalhes (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NOT NULL,
  entidade VARCHAR(80) NULL,
  entidade_id VARCHAR(120) NULL,
  antes_json LONGTEXT NULL,
  depois_json LONGTEXT NULL,
  diff_json LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_det_trace(trace_id),
  INDEX idx_audit_det_entidade(entidade, entidade_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS tiny_v2_endpoint_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NULL,
  endpoint VARCHAR(255) NULL,
  metodo VARCHAR(10) DEFAULT 'POST',
  http_code INT NULL,
  sucesso TINYINT DEFAULT 0,
  tempo_ms INT NULL,
  request_body LONGTEXT NULL,
  response_body LONGTEXT NULL,
  erro TEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tinyv2_trace(trace_id),
  INDEX idx_tinyv2_endpoint(endpoint),
  INDEX idx_tinyv2_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS auditoria_assinaturas (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  auditoria_evento_id BIGINT NOT NULL,
  trace_id VARCHAR(80) NULL,
  hash_sha256 VARCHAR(128) NOT NULL,
  hash_anterior VARCHAR(128) NULL,
  hash_canonico LONGTEXT NULL,
  cadeia_valida TINYINT DEFAULT 1,
  algoritmo VARCHAR(30) DEFAULT 'sha256-chain',
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_auditoria_assinatura(auditoria_evento_id),
  INDEX idx_aud_sig_trace(trace_id),
  INDEX idx_aud_sig_hash(hash_sha256)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS audit_exports (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NULL,
  formato ENUM('html_pdf','csv','json') DEFAULT 'json',
  filtros LONGTEXT NULL,
  total_registros INT DEFAULT 0,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_exports_trace(trace_id),
  INDEX idx_audit_exports_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS auditoria_hash_chain (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  auditoria_id BIGINT NOT NULL,
  trace_id VARCHAR(80) NULL,
  hash_anterior CHAR(64) NOT NULL,
  hash_atual CHAR(64) NOT NULL,
  base_assinatura LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_auditoria_hash(auditoria_id),
  INDEX idx_chain_trace(trace_id),
  INDEX idx_chain_hash(hash_atual)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS hosting_checks (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  provedor VARCHAR(80) DEFAULT 'infinityfree',
  score INT DEFAULT 0,
  resultado_json LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_hosting_checks_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- V57: permissões production_ready/hosting ficam no módulo core.


-- V57: permissões de laboratório ficam no módulo core.



-- V44 - snapshots de testes do dashboard e health modular
CREATE TABLE IF NOT EXISTS dashboard_testes_execucoes (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tipo VARCHAR(60) NOT NULL DEFAULT 'dashboard',
  status ENUM('ok','erro','atencao') DEFAULT 'ok',
  total INT DEFAULT 0,
  erros INT DEFAULT 0,
  payload LONGTEXT NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_dash_test_tipo(tipo),
  INDEX idx_dash_test_trace(trace_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS module_health_snapshots (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  modulo VARCHAR(60) NOT NULL,
  status ENUM('ok','erro','atencao') DEFAULT 'ok',
  mensagem TEXT NULL,
  payload LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_module_health_modulo(modulo),
  INDEX idx_module_health_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- V58: log HTTP dos endpoints VSM no módulo observabilidade.
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
  trace_id VARCHAR(80) NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_vsm_logs_endpoint (endpoint_id),
  INDEX idx_vsm_logs_chave (chave),
  INDEX idx_vsm_logs_sucesso (sucesso),
  INDEX idx_vsm_logs_criado (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ===== modules/pedidos.sql =====
-- V42 - Estrutura modular do banco: pedidos
-- Execute no banco do módulo correspondente.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS pedidos_integracao (
  id INT AUTO_INCREMENT PRIMARY KEY,
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
  INDEX idx_pedidos_data(criado_em)
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
  fila_id INT NULL,
  pedido_hub_id INT NULL,
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
  INDEX idx_ped_val_criado (criado_em)
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
  INDEX idx_ped_hist_acao (acao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pedidos_hub (
  id INT AUTO_INCREMENT PRIMARY KEY,
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
  INDEX idx_pedido_hub_vsm (pedido_vsm_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pedidos_payloads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_hub_id INT NOT NULL,
  origem VARCHAR(40) NOT NULL,
  destino VARCHAR(40) NULL,
  tipo_payload VARCHAR(80) NOT NULL,
  payload_json LONGTEXT NULL,
  hash_payload CHAR(64) NULL,
  trace_id VARCHAR(80) NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ped_payload_hub (pedido_hub_id),
  INDEX idx_ped_payload_tipo (tipo_payload),
  INDEX idx_ped_payload_hash (hash_payload)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pedidos_status_historico (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_hub_id INT NOT NULL,
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
  INDEX idx_ped_status_trace (trace_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pedidos_nfe_xml (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_hub_id INT NOT NULL,
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
  INDEX idx_xml_status (status_xml)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ===== modules/produtos.sql =====
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
  UNIQUE KEY uk_skus(sku_tiny, sku_vsm)
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
  INDEX idx_prod_vsm_data(criado_em)
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
  INDEX idx_prod_pend_trace(trace_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS vsm_endpoint_catalogo (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(160) NOT NULL,
  tipo ENUM('produto','estoque','status','baixa','consulta','webhook','outro') DEFAULT 'outro',
  metodo ENUM('GET','POST','PUT','PATCH','DELETE') DEFAULT 'POST',
  endpoint VARCHAR(255) NOT NULL,
  ambiente ENUM('homologacao','producao') DEFAULT 'homologacao',
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
  ambiente ENUM('homologacao','producao') DEFAULT 'homologacao',
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
  INDEX idx_cat_ativo(ativo)
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
  INDEX idx_prod_pendente_categoria(categoria_vsm_id)
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
  INDEX idx_produtos_tiny_nome(nome)
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
  INDEX idx_produtos_vsm_status(status)
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
  INDEX idx_prod_aprovacao_trace(trace_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- V73 - Homologação profissional Tiny V2
CREATE TABLE IF NOT EXISTS tiny_v2_homologacao_testes (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NULL,
  sku VARCHAR(120) NOT NULL,
  pedido_teste VARCHAR(120) NULL,
  aprovado TINYINT DEFAULT 0,
  resultado_json LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tiny_v2_homolog_sku(sku),
  INDEX idx_tiny_v2_homolog_trace(trace_id),
  INDEX idx_tiny_v2_homolog_aprovado(aprovado),
  INDEX idx_tiny_v2_homolog_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- V95 segurança: permissão de purge de logs/auditoria
INSERT IGNORE INTO permissoes_perfil(perfil,modulo,acao,permitido) VALUES
('admin','logs','purgar',1),('gerente','logs','purgar',0),('operador','logs','purgar',0);
