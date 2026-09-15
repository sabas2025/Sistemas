-- V104.48.1-002 - elimina DDL em fluxos operacionais e governa contratos VSM.
-- Idempotente, não remove dados e não desativa endpoints existentes.
-- Novos modelos VSM passam a ser inseridos desativados pelo serviço até validação manual/OpenAPI.

CREATE TABLE IF NOT EXISTS schema_migrations (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  migration VARCHAR(180) NOT NULL UNIQUE,
  version VARCHAR(40) NULL,
  description TEXT NULL,
  checksum VARCHAR(128) NULL,
  status ENUM('aplicada','falha') DEFAULT 'aplicada',
  mensagem TEXT NULL,
  aplicada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='schema_migrations' AND COLUMN_NAME='version')=0,
  'ALTER TABLE schema_migrations ADD COLUMN `version` VARCHAR(40) NULL', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='schema_migrations' AND COLUMN_NAME='description')=0,
  'ALTER TABLE schema_migrations ADD COLUMN `description` TEXT NULL', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='schema_migrations' AND COLUMN_NAME='checksum')=0,
  'ALTER TABLE schema_migrations ADD COLUMN `checksum` VARCHAR(128) NULL', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='schema_migrations' AND COLUMN_NAME='status')=0,
  'ALTER TABLE schema_migrations ADD COLUMN `status` ENUM(''aplicada'',''falha'') DEFAULT ''aplicada''', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='schema_migrations' AND COLUMN_NAME='mensagem')=0,
  'ALTER TABLE schema_migrations ADD COLUMN `mensagem` TEXT NULL', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='schema_migrations' AND COLUMN_NAME='aplicada_em')=0,
  'ALTER TABLE schema_migrations ADD COLUMN `aplicada_em` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

CREATE TABLE IF NOT EXISTS comercial_demo_reset_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  status VARCHAR(40) NOT NULL,
  mensagem TEXT NULL,
  contexto_json LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_demo_reset_status (status),
  INDEX idx_demo_reset_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS tenant_scope_audit_snapshots (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  status VARCHAR(40) NOT NULL,
  resumo TEXT NULL,
  snapshot_json LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tenant_scope_status (status),
  INDEX idx_tenant_scope_data (criado_em)
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

CREATE TABLE IF NOT EXISTS comercial_license_checks (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  status VARCHAR(40) NOT NULL,
  mensagem TEXT NULL,
  contexto_json LONGTEXT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_license_checks_status (status),
  INDEX idx_license_checks_data (criado_em)
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

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vsm_endpoints' AND COLUMN_NAME='modo_teste_seguro')=0,
  'ALTER TABLE vsm_endpoints ADD COLUMN `modo_teste_seguro` TINYINT NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vsm_endpoints' AND COLUMN_NAME='origem')=0,
  'ALTER TABLE vsm_endpoints ADD COLUMN `origem` VARCHAR(40) NOT NULL DEFAULT ''manual''', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vsm_endpoints' AND COLUMN_NAME='contract_verified')=0,
  'ALTER TABLE vsm_endpoints ADD COLUMN `contract_verified` TINYINT NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vsm_endpoints' AND COLUMN_NAME='contract_source')=0,
  'ALTER TABLE vsm_endpoints ADD COLUMN `contract_source` VARCHAR(255) NULL', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vsm_endpoints' AND COLUMN_NAME='contract_checked_at')=0,
  'ALTER TABLE vsm_endpoints ADD COLUMN `contract_checked_at` DATETIME NULL', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vsm_endpoints' AND COLUMN_NAME='is_template')=0,
  'ALTER TABLE vsm_endpoints ADD COLUMN `is_template` TINYINT NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vsm_campos_mapeamento' AND COLUMN_NAME='tipo_dado')=0,
  'ALTER TABLE vsm_campos_mapeamento ADD COLUMN `tipo_dado` VARCHAR(50) NULL', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vsm_campos_mapeamento' AND COLUMN_NAME='valor_padrao')=0,
  'ALTER TABLE vsm_campos_mapeamento ADD COLUMN `valor_padrao` VARCHAR(255) NULL', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vsm_campos_mapeamento' AND COLUMN_NAME='regra_validacao')=0,
  'ALTER TABLE vsm_campos_mapeamento ADD COLUMN `regra_validacao` VARCHAR(255) NULL', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vsm_campos_mapeamento' AND COLUMN_NAME='exemplo_payload')=0,
  'ALTER TABLE vsm_campos_mapeamento ADD COLUMN `exemplo_payload` TEXT NULL', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vsm_campos_mapeamento' AND COLUMN_NAME='origem')=0,
  'ALTER TABLE vsm_campos_mapeamento ADD COLUMN `origem` VARCHAR(40) NOT NULL DEFAULT ''manual''', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vsm_campos_mapeamento' AND COLUMN_NAME='contract_verified')=0,
  'ALTER TABLE vsm_campos_mapeamento ADD COLUMN `contract_verified` TINYINT NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vsm_campos_mapeamento' AND COLUMN_NAME='contract_source')=0,
  'ALTER TABLE vsm_campos_mapeamento ADD COLUMN `contract_source` VARCHAR(255) NULL', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vsm_campos_mapeamento' AND COLUMN_NAME='is_template')=0,
  'ALTER TABLE vsm_campos_mapeamento ADD COLUMN `is_template` TINYINT NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vsm_endpoint_logs' AND COLUMN_NAME='modo_teste_seguro')=0,
  'ALTER TABLE vsm_endpoint_logs ADD COLUMN `modo_teste_seguro` TINYINT NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='configuracoes_integracao' AND COLUMN_NAME='vsm_ambiente')=0,
  'ALTER TABLE configuracoes_integracao ADD COLUMN `vsm_ambiente` VARCHAR(30) DEFAULT ''homologacao''', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='configuracoes_integracao' AND COLUMN_NAME='vsm_producao_liberada')=0,
  'ALTER TABLE configuracoes_integracao ADD COLUMN `vsm_producao_liberada` TINYINT DEFAULT 0', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='configuracoes_integracao' AND COLUMN_NAME='vsm_ultimo_teste_ok')=0,
  'ALTER TABLE configuracoes_integracao ADD COLUMN `vsm_ultimo_teste_ok` TINYINT DEFAULT 0', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='configuracoes_integracao' AND COLUMN_NAME='vsm_ultimo_teste_em')=0,
  'ALTER TABLE configuracoes_integracao ADD COLUMN `vsm_ultimo_teste_em` DATETIME NULL', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='configuracoes_integracao' AND COLUMN_NAME='vsm_host_producao_liberado')=0,
  'ALTER TABLE configuracoes_integracao ADD COLUMN `vsm_host_producao_liberado` VARCHAR(255) NULL', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='configuracoes_integracao' AND COLUMN_NAME='vsm_producao_liberada_em')=0,
  'ALTER TABLE configuracoes_integracao ADD COLUMN `vsm_producao_liberada_em` DATETIME NULL', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='configuracoes_integracao' AND COLUMN_NAME='vsm_producao_liberada_por')=0,
  'ALTER TABLE configuracoes_integracao ADD COLUMN `vsm_producao_liberada_por` INT NULL', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='configuracoes_integracao' AND COLUMN_NAME='produto_novo_aprovacao_modo')=0,
  'ALTER TABLE configuracoes_integracao ADD COLUMN `produto_novo_aprovacao_modo` VARCHAR(20) DEFAULT ''manual''', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='configuracoes_integracao' AND COLUMN_NAME='produto_novo_auto_fallback_manual')=0,
  'ALTER TABLE configuracoes_integracao ADD COLUMN `produto_novo_auto_fallback_manual` TINYINT DEFAULT 1', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='configuracoes_integracao' AND COLUMN_NAME='produto_novo_auto_exigir_ean')=0,
  'ALTER TABLE configuracoes_integracao ADD COLUMN `produto_novo_auto_exigir_ean` TINYINT DEFAULT 1', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='configuracoes_integracao' AND COLUMN_NAME='produto_novo_auto_exigir_ncm')=0,
  'ALTER TABLE configuracoes_integracao ADD COLUMN `produto_novo_auto_exigir_ncm` TINYINT DEFAULT 1', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='configuracoes_integracao' AND COLUMN_NAME='produto_novo_auto_exigir_categoria')=0,
  'ALTER TABLE configuracoes_integracao ADD COLUMN `produto_novo_auto_exigir_categoria` TINYINT DEFAULT 1', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='configuracoes_integracao' AND COLUMN_NAME='produto_novo_auto_bloquear_duplicidade')=0,
  'ALTER TABLE configuracoes_integracao ADD COLUMN `produto_novo_auto_bloquear_duplicidade` TINYINT DEFAULT 1', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='configuracoes_integracao' AND COLUMN_NAME='pedido_tiny_vsm_validacao_obrigatoria')=0,
  'ALTER TABLE configuracoes_integracao ADD COLUMN `pedido_tiny_vsm_validacao_obrigatoria` TINYINT DEFAULT 1', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='configuracoes_integracao' AND COLUMN_NAME='pedido_tiny_vsm_aprovacao_manual')=0,
  'ALTER TABLE configuracoes_integracao ADD COLUMN `pedido_tiny_vsm_aprovacao_manual` TINYINT DEFAULT 1', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='configuracoes_integracao' AND COLUMN_NAME='pedido_tiny_vsm_auto_enviar_validos')=0,
  'ALTER TABLE configuracoes_integracao ADD COLUMN `pedido_tiny_vsm_auto_enviar_validos` TINYINT DEFAULT 0', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='configuracoes_integracao' AND COLUMN_NAME='pedido_tiny_vsm_exigir_sku_mapeado')=0,
  'ALTER TABLE configuracoes_integracao ADD COLUMN `pedido_tiny_vsm_exigir_sku_mapeado` TINYINT DEFAULT 1', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='configuracoes_integracao' AND COLUMN_NAME='pedido_tiny_vsm_exigir_cliente_documento')=0,
  'ALTER TABLE configuracoes_integracao ADD COLUMN `pedido_tiny_vsm_exigir_cliente_documento` TINYINT DEFAULT 1', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='configuracoes_integracao' AND COLUMN_NAME='pedido_tiny_vsm_exigir_endereco')=0,
  'ALTER TABLE configuracoes_integracao ADD COLUMN `pedido_tiny_vsm_exigir_endereco` TINYINT DEFAULT 1', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='configuracoes_integracao' AND COLUMN_NAME='pedido_tiny_vsm_status_permitidos')=0,
  'ALTER TABLE configuracoes_integracao ADD COLUMN `pedido_tiny_vsm_status_permitidos` VARCHAR(255) DEFAULT ''aprovado,pago,faturado,pronto para envio''', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='configuracoes_integracao' AND COLUMN_NAME='vsm_endpoint_pedido')=0,
  'ALTER TABLE configuracoes_integracao ADD COLUMN `vsm_endpoint_pedido` VARCHAR(255) DEFAULT ''/api/pedidos''', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

INSERT IGNORE INTO configuracoes_integracao(id) VALUES(1);
UPDATE configuracoes_integracao SET
  vsm_ambiente=COALESCE(NULLIF(vsm_ambiente,''),'homologacao'),
  vsm_producao_liberada=COALESCE(vsm_producao_liberada,0),
  vsm_ultimo_teste_ok=COALESCE(vsm_ultimo_teste_ok,0),
  produto_novo_aprovacao_modo=COALESCE(NULLIF(produto_novo_aprovacao_modo,''),'manual')
WHERE id=1;

INSERT INTO schema_migrations(migration,version,description,checksum,status,mensagem,aplicada_em)
VALUES('20260712_002_schema_runtime_vsm_contract','V104.48.1','Remove DDL operacional, completa schema VSM e governa modelos não verificados',NULL,'aplicada','Migração V104.48.1-002 aplicada',CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE version=VALUES(version),description=VALUES(description),status='aplicada',mensagem=VALUES(mensagem),aplicada_em=CURRENT_TIMESTAMP;
