-- Achado F6-07 (auditoria Fase 6, 2026-09-26) — credenciais reais da VSM Conecta Venda.
--
-- O contrato oficial (contracts/vsm/pedidos-integradora.openapi.json) mostra que a VSM autentica
-- por POST /v1/auth/token com {clientToken, clientSecret} -> JWT (Bearer, expiresIn ~7200s), e o
-- pedido exige clientTokenLoja e clientTokenIntegradora na query. O Hub só tinha `vsm_token`
-- (um Bearer estático), que não serve para esse fluxo.
--
-- Esta migration acrescenta as colunas para guardar as credenciais (cifradas em runtime pelo
-- CryptoService, como tiny_v3_client_secret/vsm_token) e para CACHEAR o JWT emitido:
--   vsm_client_token            -> clientToken da INTEGRADORA (usado no /v1/auth/token e como
--                                  clientTokenIntegradora na query do pedido)
--   vsm_client_secret           -> clientSecret da INTEGRADORA (só no /v1/auth/token)
--   vsm_client_token_loja       -> clientToken da LOJA (clientTokenLoja na query do pedido)
--   vsm_access_token            -> JWT emitido pelo /v1/auth/token (cache, cifrado)
--   vsm_access_token_expira_em  -> expiração do JWT (para renovar antes de vencer)
--
-- `vsm_token` legado é PRESERVADO (não é removido): instalação que ainda usa Bearer estático
-- continua igual até migrar para as credenciais novas.
--
-- Idempotente: cada coluna só é criada se ainda não existir; pode reexecutar com segurança.
-- Espelha exatamente as colunas de database/modules/core.sql (paridade instalação nova ×
-- atualização — lição do achado I-18).
--
-- REVERSÃO:
--   ALTER TABLE configuracoes_integracao
--     DROP COLUMN vsm_client_token, DROP COLUMN vsm_client_secret,
--     DROP COLUMN vsm_client_token_loja, DROP COLUMN vsm_access_token,
--     DROP COLUMN vsm_access_token_expira_em;
-- (nenhuma delas é lida por código legado; sem elas, o VsmService volta ao Bearer estático.)

SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_integracao');

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_integracao' AND COLUMN_NAME = 'vsm_client_token');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE configuracoes_integracao ADD COLUMN vsm_client_token TEXT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_integracao' AND COLUMN_NAME = 'vsm_client_secret');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE configuracoes_integracao ADD COLUMN vsm_client_secret TEXT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_integracao' AND COLUMN_NAME = 'vsm_client_token_loja');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE configuracoes_integracao ADD COLUMN vsm_client_token_loja TEXT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_integracao' AND COLUMN_NAME = 'vsm_access_token');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE configuracoes_integracao ADD COLUMN vsm_access_token TEXT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_integracao' AND COLUMN_NAME = 'vsm_access_token_expira_em');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE configuracoes_integracao ADD COLUMN vsm_access_token_expira_em DATETIME NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
