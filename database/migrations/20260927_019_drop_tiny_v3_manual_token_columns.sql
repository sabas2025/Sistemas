-- Achado F5-01 (auditoria Fase 5 — Tiny ERP, 2026-09-27) — colunas mortas do Tiny V3 manual.
--
-- `tiny_v3_manual_access_token` e `tiny_v3_manual_refresh_token` nasceram TEXT NULL, foram
-- semeadas vazias pelo instalador e NUNCA gravadas por caminho nenhum (o formulário de
-- Configurações não as escreve; o access token real do V3 é `tiny_v3_token`/OAuth). No código,
-- eram lidas apenas como `!empty(...)` em três serviços (ProductionReadinessV50Service,
-- OperationCenterService, ProductionGoLiveService), sempre em OR com o token vivo — como a coluna
-- é sempre '', o termo avaliava sempre false e não mudava o resultado. Sinal morto que sugeria um
-- caminho de token manual inexistente. As leituras foram removidas no mesmo commit; esta migration
-- remove as colunas para instalações já existentes.
--
-- DECISÃO DE PRODUTO (2026-09-27): dropar (escopo escolhido pelo responsável). Difere do precedente
-- das classes órfãs (que manteve as tabelas) porque aqui não há NENHUM escritor nem leitor real.
--
-- Idempotente e portável MySQL 8 + MariaDB: cada coluna só é dropada se AINDA existir (não usa
-- `DROP COLUMN IF EXISTS`, que o MySQL 8 não aceita). Pode reexecutar com segurança. Espelha a
-- remoção feita em database/modules/core.sql (paridade instalação nova × atualização — lição I-18).
--
-- REVERSÃO:
--   ALTER TABLE configuracoes_integracao
--     ADD COLUMN tiny_v3_manual_access_token TEXT NULL,
--     ADD COLUMN tiny_v3_manual_refresh_token TEXT NULL;
-- (nenhuma delas volta a ser lida por código; recriá-las apenas restaura o schema anterior.)

SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_integracao');

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_integracao' AND COLUMN_NAME = 'tiny_v3_manual_access_token');
SET @sql := IF(@tbl = 1 AND @col = 1, 'ALTER TABLE configuracoes_integracao DROP COLUMN tiny_v3_manual_access_token', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_integracao' AND COLUMN_NAME = 'tiny_v3_manual_refresh_token');
SET @sql := IF(@tbl = 1 AND @col = 1, 'ALTER TABLE configuracoes_integracao DROP COLUMN tiny_v3_manual_refresh_token', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
