-- Auditoria de capacidade 2026-09-14 — índices para 100 clientes / 500 pedidos por minuto.
--
-- C-03: pedidos_integracao era a ÚNICA das 31 tabelas com escopo de empresa sem índice em
-- empresa_id. A migration 20260914_010 a pulou porque a coluna já existia desde antes da R7, e o
-- índice foi junto no pulo. Com 100 clientes na mesma instalação, toda listagem de pedidos lia as
-- linhas de todos eles para depois descartar.
--
-- Os índices compostos existem porque o filtro real nunca é só a empresa: as telas filtram
-- "empresa + status" e "empresa + período". Um índice só de empresa_id ainda obrigaria a ler todas
-- as linhas daquela empresa para aplicar o segundo predicado.
--
-- Idempotente: cada índice só é criado se ainda não existir.

SET @t := 'pedidos_integracao';

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@t AND INDEX_NAME='idx_pedidos_integracao_empresa');
SET @sql := IF(@idx=0,'CREATE INDEX idx_pedidos_integracao_empresa ON pedidos_integracao(empresa_id)','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@t AND INDEX_NAME='idx_pedidos_empresa_status');
SET @sql := IF(@idx=0,'CREATE INDEX idx_pedidos_empresa_status ON pedidos_integracao(empresa_id,status)','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@t AND INDEX_NAME='idx_pedidos_empresa_data');
SET @sql := IF(@idx=0,'CREATE INDEX idx_pedidos_empresa_data ON pedidos_integracao(empresa_id,criado_em)','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO schema_migrations(migration,version,description,status)
VALUES ('20260914_011_indices_capacidade','V104.49.3-R7','Índices de empresa em pedidos_integracao para 100 clientes (achado C-03).','aplicada');
