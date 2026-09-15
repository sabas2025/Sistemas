-- V104.38 - Enterprise Core Auto Repair
-- Não apaga dados. Faça backup antes de executar em produção.
SET NAMES utf8mb4;


-- INICIO: modules/core.sql
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
  ambiente VARCHAR(30) DEFAULT 'homologacao',
  tiny_versao VARCHAR(10) DEFAULT 'v2',
  tiny_v2_url VARCHAR(255) DEFAULT 'https://api.tiny.com.br/api2',
  tiny_v2_token TEXT NULL,
  tiny_v3_ambiente VARCHAR(30) DEFAULT 'homologacao',
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
  ambiente VARCHAR(30) DEFAULT 'homologacao',
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



-- V95 segurança: permissão de purge de logs/auditoria
INSERT IGNORE INTO permissoes_perfil(perfil,modulo,acao,permitido) VALUES
('admin','logs','purgar',1),('gerente','logs','purgar',0),('operador','logs','purgar',0);


-- V102 - Token Vault interno para rotação/auditoria de tokens
CREATE TABLE IF NOT EXISTS token_vault (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  provider VARCHAR(40) NOT NULL,
  ambiente VARCHAR(30) NOT NULL DEFAULT 'homologacao',
  token_type VARCHAR(40) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  ciphertext LONGTEXT NOT NULL,
  version INT NOT NULL DEFAULT 1,
  expires_at DATETIME NULL,
  rotated_at DATETIME NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  trace_id VARCHAR(80) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_vault_provider (provider, ambiente, token_type, active),
  INDEX idx_vault_exp (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO permissoes_perfil(perfil,modulo,acao,permitido) VALUES
('admin','seguranca','gerenciar',1),('admin','seguranca','visualizar',1),('gerente','seguranca','visualizar',1),('operador','seguranca','visualizar',0);


-- V102 - Tabelas SOC no core para bloqueio antes de carregar módulos
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


-- V104.7: tabelas runtime/core incluídas no instalador modular para evitar criação tardia em tela.
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

CREATE TABLE IF NOT EXISTS production_go_live_checks (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NULL,
  status VARCHAR(30) NOT NULL,
  score INT NOT NULL DEFAULT 0,
  bloqueios INT NOT NULL DEFAULT 0,
  alertas INT NOT NULL DEFAULT 0,
  resultado_json LONGTEXT NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_golive_trace(trace_id),
  INDEX idx_golive_status(status),
  INDEX idx_golive_criado(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orquestracao_fluxos_historico (
  id INT AUTO_INCREMENT PRIMARY KEY,
  acao VARCHAR(80) NOT NULL,
  status VARCHAR(30) NOT NULL,
  mensagem TEXT NULL,
  contexto_json LONGTEXT NULL,
  usuario_id INT NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_orq_hist_trace (trace_id),
  INDEX idx_orq_hist_acao (acao),
  INDEX idx_orq_hist_criado (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS security_hardening_checks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  chave VARCHAR(120) NOT NULL,
  status VARCHAR(30) NOT NULL,
  score INT DEFAULT 0,
  detalhes LONGTEXT NULL,
  trace_id VARCHAR(60) NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_chave (chave),
  INDEX idx_criado_em (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tiny_v3_homologacao_testes (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NULL,
  sku VARCHAR(120) NOT NULL,
  pedido_teste VARCHAR(120) NULL,
  aprovado TINYINT DEFAULT 0,
  resultado_json LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tiny_v3_homolog_sku(sku),
  INDEX idx_tiny_v3_homolog_trace(trace_id),
  INDEX idx_tiny_v3_homolog_aprovado(aprovado),
  INDEX idx_tiny_v3_homolog_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS system_build_info (
  id INT AUTO_INCREMENT PRIMARY KEY,
  chave VARCHAR(80) NOT NULL UNIQUE,
  valor VARCHAR(255) NOT NULL,
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- V104.16 - Camada comercial final, licenças, conectores plugáveis, cobrança e demo
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
  licenca_origem VARCHAR(40) NOT NULL DEFAULT 'manual',
  ultimo_check_em TIMESTAMP NULL DEFAULT NULL,
  bloquear_ao_vencer TINYINT(1) NOT NULL DEFAULT 0,
  assinatura_hmac VARCHAR(128) NULL,
  observacoes TEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_comercial_cliente_documento (documento),
  INDEX idx_comercial_licenca_status_expira (status,data_expiracao)
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

-- V104.17 - suporte/SLA comercial

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

-- V104.18 - Fechamento prioridade alta/média comercial
CREATE TABLE IF NOT EXISTS comercial_license_checks (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  status VARCHAR(40) NOT NULL,
  mensagem TEXT NULL,
  contexto_json LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_license_checks_status (status),
  INDEX idx_license_checks_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS comercial_billing_gateway_events (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tipo VARCHAR(80) NOT NULL,
  status VARCHAR(40) NOT NULL,
  mensagem TEXT NULL,
  contexto_json LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_billing_gateway_tipo (tipo),
  INDEX idx_billing_gateway_status (status),
  INDEX idx_billing_gateway_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tenant_scope_audit_snapshots (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  status VARCHAR(40) NOT NULL,
  resumo TEXT NULL,
  snapshot_json LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tenant_scope_status (status),
  INDEX idx_tenant_scope_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO schema_migrations(migration, checksum, status, mensagem)
VALUES('v104_18_prioridades_alta_media_final', SHA2('v104_18_prioridades_alta_media_final',256), 'aplicada', 'Fechamento prioridades alta/média, licença remota, billing, tenant auditável e CI/CD.');

-- V104.19 - Alta prioridade técnica + média prioridade comercial reforçadas
CREATE TABLE IF NOT EXISTS comercial_license_remote_cache (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  status VARCHAR(40) NOT NULL,
  mensagem TEXT NULL,
  payload_hash CHAR(64) NULL,
  payload_json LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_license_remote_status (status),
  INDEX idx_license_remote_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS comercial_billing_provider_events (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  provider VARCHAR(60) NOT NULL DEFAULT 'manual',
  evento VARCHAR(100) NOT NULL,
  status VARCHAR(40) NOT NULL,
  referencia VARCHAR(120) NULL,
  payload_hash CHAR(64) NULL,
  payload_json LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_billing_provider (provider),
  INDEX idx_billing_provider_status (status),
  INDEX idx_billing_provider_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS comercial_demo_reset_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  status VARCHAR(40) NOT NULL,
  mensagem TEXT NULL,
  contexto_json LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_demo_reset_status (status),
  INDEX idx_demo_reset_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS connector_operational_checks (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  total INT NOT NULL DEFAULT 0,
  ok INT NOT NULL DEFAULT 0,
  alerta INT NOT NULL DEFAULT 0,
  erro INT NOT NULL DEFAULT 0,
  snapshot_json LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_connector_check_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS system_release_checks (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  version VARCHAR(40) NOT NULL,
  status VARCHAR(40) NOT NULL,
  score INT NOT NULL DEFAULT 0,
  snapshot_json LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_release_checks_version (version),
  INDEX idx_release_checks_status (status),
  INDEX idx_release_checks_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO schema_migrations(migration, checksum, status, mensagem)
VALUES('v104_19_refatoracao_comercial_tecnica', SHA2('v104_19_refatoracao_comercial_tecnica',256), 'aplicada', 'Refatoração técnica e maturidade comercial V104.19 aplicada.');

-- V104.20 - Instalador seguro MySQL / credenciais produção guiadas
INSERT INTO schema_migrations (migration, checksum, status, mensagem)
VALUES('v104_20_instalador_seguro_mysql', SHA2('v104_20_instalador_seguro_mysql',256), 'aplicada', 'Instalador com mensagem amigável para root sem senha e modo local confirmado.')
ON DUPLICATE KEY UPDATE status='aplicada', mensagem=VALUES(mensagem);
-- V104.24 - Correção AutoRepair/config.php/db_storage_mode
-- Objetivo: evitar Warning 1265 em configuracoes_integracao.ambiente e registrar migração.

-- schema_migrations já criada acima; bloco duplicado removido na V104.24.

ALTER TABLE configuracoes_integracao MODIFY ambiente VARCHAR(30) DEFAULT 'homologacao';
ALTER TABLE configuracoes_integracao MODIFY tiny_versao VARCHAR(10) DEFAULT 'v2';
ALTER TABLE configuracoes_integracao MODIFY tiny_v3_ambiente VARCHAR(30) DEFAULT 'homologacao';

UPDATE configuracoes_integracao
SET ambiente = 'homologacao'
WHERE ambiente IS NULL OR ambiente = '' OR ambiente LIKE '{%';

UPDATE configuracoes_integracao
SET tiny_versao = 'v2'
WHERE tiny_versao IS NULL OR tiny_versao = '' OR tiny_versao LIKE '{%';

UPDATE configuracoes_integracao
SET tiny_v3_ambiente = 'homologacao'
WHERE tiny_v3_ambiente IS NULL OR tiny_v3_ambiente = '' OR tiny_v3_ambiente LIKE '{%';

INSERT INTO schema_migrations(migration, checksum, status, mensagem)
VALUES('v104_24_autorepair_config_db_storage_mode', SHA2('v104_24_autorepair_config_db_storage_mode', 256), 'aplicada', 'AutoRepair substitui placeholders, diagnostica db_storage_mode e normaliza ambiente/tiny_versao.')
ON DUPLICATE KEY UPDATE status='aplicada', mensagem=VALUES(mensagem), aplicada_em=CURRENT_TIMESTAMP;

-- V104.25 - Hardening Enterprise Tiny/VSM
INSERT IGNORE INTO schema_migrations(migration, checksum, status, mensagem)
VALUES('v104_25_enterprise_hardening_tiny_vsm', SHA2('v104_25_enterprise_hardening_tiny_vsm',256), 'aplicada', 'Hardening Tiny webhook legado, HMAC público, anti-replay sem fallback, SSRF VSM e SQL repair sem placeholders.');

-- FIM: modules/core.sql


-- INICIO: modules/fila.sql
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
('tiny_v2','fechado'),
('tiny_v3','fechado'),
('fiscal','fechado');

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




-- V104.8 - Anti replay com janela de tempo para integrações VSM/Tiny
CREATE TABLE IF NOT EXISTS integration_replay_guard (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  origem VARCHAR(40) NOT NULL,
  rota VARCHAR(180) NULL,
  request_hash CHAR(64) NOT NULL,
  payload_hash CHAR(64) NULL,
  time_bucket BIGINT UNSIGNED NOT NULL DEFAULT 0,
  request_time DATETIME NOT NULL,
  hmac_validated_at DATETIME NULL,
  trace_id VARCHAR(80) NULL,
  ip VARCHAR(80) NULL,
  status ENUM('aceito','bloqueado') DEFAULT 'aceito',
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_replay_origem_hash_bucket (origem, request_hash, time_bucket),
  INDEX idx_replay_hash_time (origem, request_hash, request_time),
  INDEX idx_replay_time (request_time),
  INDEX idx_replay_trace (trace_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- FIM: modules/fila.sql


-- INICIO: enterprise_core_bootstrap_v104_35.sql
-- V104.35 - Enterprise Futurista + Idempotência Forte + Worker Escalável
-- Seguro/idempotente: não apaga dados e pode ser executado novamente.

CREATE TABLE IF NOT EXISTS schema_migrations (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  migration VARCHAR(160) NOT NULL UNIQUE,
  version VARCHAR(40) NULL,
  description TEXT NULL,
  checksum VARCHAR(128) NULL,
  applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE fila_integracao MODIFY status ENUM('pendente','processando','sucesso','erro','falha_definitiva','ignorado') DEFAULT 'pendente';
ALTER TABLE fila_integracao ADD COLUMN IF NOT EXISTS idempotency_key VARCHAR(190) NULL;
ALTER TABLE fila_integracao ADD COLUMN IF NOT EXISTS locked_by VARCHAR(120) NULL;
ALTER TABLE fila_integracao ADD COLUMN IF NOT EXISTS locked_at DATETIME NULL;
CREATE INDEX IF NOT EXISTS idx_fila_idempotency_key ON fila_integracao(idempotency_key);
CREATE INDEX IF NOT EXISTS idx_fila_status_proxima_prioridade ON fila_integracao(status, proxima_tentativa, prioridade, id);
CREATE INDEX IF NOT EXISTS idx_fila_locked ON fila_integracao(locked_by, locked_at);

CREATE TABLE IF NOT EXISTS integration_events (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  event_uuid VARCHAR(80) NOT NULL UNIQUE,
  fila_id BIGINT NULL,
  idempotency_key VARCHAR(190) NULL,
  source_system VARCHAR(40) NOT NULL,
  target_system VARCHAR(40) NOT NULL,
  entity_type VARCHAR(60) NOT NULL,
  entity_id VARCHAR(160) NULL,
  operation VARCHAR(80) NOT NULL,
  status ENUM('recebido','processando','sucesso','erro','ignorado','reprocessado') DEFAULT 'recebido',
  payload_hash VARCHAR(128) NULL,
  response_hash VARCHAR(128) NULL,
  error_code VARCHAR(120) NULL,
  error_message TEXT NULL,
  attempts INT DEFAULT 0,
  trace_id VARCHAR(80) NULL,
  metadata_json LONGTEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  finished_at DATETIME NULL,
  INDEX idx_ie_status_created(status, created_at),
  INDEX idx_ie_entity(entity_type, entity_id),
  INDEX idx_ie_trace(trace_id),
  INDEX idx_ie_idempotency(idempotency_key),
  INDEX idx_ie_source_target(source_system, target_system)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS integration_idempotency (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  idempotency_key VARCHAR(190) NOT NULL UNIQUE,
  entity_type VARCHAR(60) NULL,
  entity_id VARCHAR(160) NULL,
  operation VARCHAR(80) NULL,
  request_hash VARCHAR(128) NULL,
  response_hash VARCHAR(128) NULL,
  status ENUM('claimed','completed','failed','expired') DEFAULT 'claimed',
  trace_id VARCHAR(80) NULL,
  expires_at DATETIME NULL,
  metadata_json LONGTEXT NULL,
  claim_count INT DEFAULT 0,
  last_duplicate_at DATETIME NULL,
  original_fila_id BIGINT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_idemp_status(status),
  INDEX idx_idemp_entity(entity_type, entity_id),
  INDEX idx_idemp_expires(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE integration_idempotency ADD COLUMN IF NOT EXISTS claim_count INT DEFAULT 0;
ALTER TABLE integration_idempotency ADD COLUMN IF NOT EXISTS last_duplicate_at DATETIME NULL;
ALTER TABLE integration_idempotency ADD COLUMN IF NOT EXISTS original_fila_id BIGINT NULL;

CREATE TABLE IF NOT EXISTS enterprise_regression_runs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  trace_id VARCHAR(80) NULL,
  score INT DEFAULT 0,
  total INT DEFAULT 0,
  ok_count INT DEFAULT 0,
  error_count INT DEFAULT 0,
  results_json LONGTEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_regression_trace(trace_id),
  INDEX idx_regression_created(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS enterprise_ui_preferences (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NULL,
  profile_key VARCHAR(80) NOT NULL DEFAULT 'default',
  density ENUM('compact','comfortable','spacious') DEFAULT 'comfortable',
  theme ENUM('system','light','dark','futurista') DEFAULT 'futurista',
  settings_json LONGTEXT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_ui_user_profile(user_id, profile_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations(migration, version, description, checksum)
VALUES('v104_35_enterprise_futurista_idempotencia_worker','V104.35','Enterprise Futurista, idempotência forte, worker escalável e testes de regressão', SHA2('v104_35_enterprise_futurista_idempotencia_worker',256))
ON DUPLICATE KEY UPDATE version=VALUES(version), description=VALUES(description), checksum=VALUES(checksum);

-- FIM: enterprise_core_bootstrap_v104_35.sql


-- INICIO: migrations/20260710_001_idempotency_queue_lease.sql
-- V104.36 - idempotência atômica, propriedade de reserva e lease da fila.
ALTER TABLE integration_idempotency ADD COLUMN IF NOT EXISTS owner_token CHAR(64) NULL AFTER original_fila_id;
ALTER TABLE integration_idempotency ADD COLUMN IF NOT EXISTS locked_until DATETIME NULL AFTER owner_token;
CREATE INDEX IF NOT EXISTS idx_idemp_lock ON integration_idempotency(status, locked_until);

ALTER TABLE fila_integracao ADD COLUMN IF NOT EXISTS lease_expires_at DATETIME NULL AFTER locked_at;
ALTER TABLE fila_integracao ADD COLUMN IF NOT EXISTS heartbeat_at DATETIME NULL AFTER lease_expires_at;
CREATE INDEX IF NOT EXISTS idx_fila_lease ON fila_integracao(status, lease_expires_at);
CREATE INDEX IF NOT EXISTS idx_fila_ready ON fila_integracao(status, proxima_tentativa, prioridade, id);

-- FIM: migrations/20260710_001_idempotency_queue_lease.sql


ALTER TABLE schema_migrations ADD COLUMN IF NOT EXISTS version VARCHAR(40) NULL;
ALTER TABLE schema_migrations ADD COLUMN IF NOT EXISTS description TEXT NULL;
ALTER TABLE schema_migrations ADD COLUMN IF NOT EXISTS checksum VARCHAR(128) NULL;
ALTER TABLE schema_migrations ADD COLUMN IF NOT EXISTS status ENUM('aplicada','falha') DEFAULT 'aplicada';
ALTER TABLE schema_migrations ADD COLUMN IF NOT EXISTS mensagem TEXT NULL;
ALTER TABLE schema_migrations ADD COLUMN IF NOT EXISTS aplicada_em TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE fila_integracao MODIFY status ENUM('pendente','processando','sucesso','erro','falha_definitiva','ignorado') DEFAULT 'pendente';

INSERT INTO schema_migrations(migration, version, description, checksum, status, mensagem)
VALUES('v104_38_enterprise_core_autorepair','V104.38','Reparação segura das tabelas-base, Enterprise Core e fila',SHA2('v104_38_enterprise_core_autorepair',256),'aplicada','Migração aplicada com sucesso')
ON DUPLICATE KEY UPDATE version=VALUES(version), description=VALUES(description), checksum=VALUES(checksum), status='aplicada', mensagem=VALUES(mensagem);
