-- Melhoria 1 da seção 8 (relatório V104.49.3-R6) — isolamento multiempresa.
--
-- Até a R6 as colunas empresa_id existiam em duas tabelas e NENHUMA consulta filtrava por elas:
-- o sistema bloqueava rota operacional sem empresa selecionada, mas não isolava dado nenhum. Esta
-- migration cria a coluna nas 31 tabelas que guardam dados operacionais de UMA empresa, indexa e
-- faz o backfill.
--
-- BACKFILL: quando existe exatamente UMA empresa cadastrada — o caso de toda instalação atual —
-- todas as linhas são atribuídas a ela. Com duas ou mais empresas já cadastradas, o backfill é
-- PULADO de propósito: não há como adivinhar a quem o histórico pertence, e chutar seria pior do
-- que deixar explícito. Nesse caso as linhas ficam com empresa_id NULL e continuam visíveis para
-- todas as empresas (TenantScopeService::where() inclui NULL) até que alguém as classifique.
--
-- Idempotente: coluna e índice só são criados se ainda não existirem; o backfill só toca linhas
-- que ainda estão com NULL. Pode ser executada novamente com segurança.

SET @empresas := (SELECT COUNT(*) FROM empresas);
SET @empresa_unica := (SELECT CASE WHEN @empresas = 1 THEN (SELECT MIN(id) FROM empresas) ELSE NULL END);

-- ---------- categorias_mapeamento ----------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categorias_mapeamento' AND COLUMN_NAME = 'empresa_id');
SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categorias_mapeamento');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE categorias_mapeamento ADD COLUMN empresa_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categorias_mapeamento' AND INDEX_NAME = 'idx_categorias_mapeamento_empresa');
SET @sql := IF(@tbl = 1 AND @idx = 0, 'CREATE INDEX idx_categorias_mapeamento_empresa ON categorias_mapeamento(empresa_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl = 1 AND @empresa_unica IS NOT NULL,
  CONCAT('UPDATE categorias_mapeamento SET empresa_id = ', @empresa_unica, ' WHERE empresa_id IS NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- estoque_alertas ----------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estoque_alertas' AND COLUMN_NAME = 'empresa_id');
SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estoque_alertas');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE estoque_alertas ADD COLUMN empresa_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estoque_alertas' AND INDEX_NAME = 'idx_estoque_alertas_empresa');
SET @sql := IF(@tbl = 1 AND @idx = 0, 'CREATE INDEX idx_estoque_alertas_empresa ON estoque_alertas(empresa_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl = 1 AND @empresa_unica IS NOT NULL,
  CONCAT('UPDATE estoque_alertas SET empresa_id = ', @empresa_unica, ' WHERE empresa_id IS NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- estoque_auditoria_sku ----------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estoque_auditoria_sku' AND COLUMN_NAME = 'empresa_id');
SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estoque_auditoria_sku');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE estoque_auditoria_sku ADD COLUMN empresa_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estoque_auditoria_sku' AND INDEX_NAME = 'idx_estoque_auditoria_sku_empresa');
SET @sql := IF(@tbl = 1 AND @idx = 0, 'CREATE INDEX idx_estoque_auditoria_sku_empresa ON estoque_auditoria_sku(empresa_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl = 1 AND @empresa_unica IS NOT NULL,
  CONCAT('UPDATE estoque_auditoria_sku SET empresa_id = ', @empresa_unica, ' WHERE empresa_id IS NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- estoque_divergencias ----------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estoque_divergencias' AND COLUMN_NAME = 'empresa_id');
SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estoque_divergencias');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE estoque_divergencias ADD COLUMN empresa_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estoque_divergencias' AND INDEX_NAME = 'idx_estoque_divergencias_empresa');
SET @sql := IF(@tbl = 1 AND @idx = 0, 'CREATE INDEX idx_estoque_divergencias_empresa ON estoque_divergencias(empresa_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl = 1 AND @empresa_unica IS NOT NULL,
  CONCAT('UPDATE estoque_divergencias SET empresa_id = ', @empresa_unica, ' WHERE empresa_id IS NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- estoque_movimentos ----------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estoque_movimentos' AND COLUMN_NAME = 'empresa_id');
SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estoque_movimentos');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE estoque_movimentos ADD COLUMN empresa_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estoque_movimentos' AND INDEX_NAME = 'idx_estoque_movimentos_empresa');
SET @sql := IF(@tbl = 1 AND @idx = 0, 'CREATE INDEX idx_estoque_movimentos_empresa ON estoque_movimentos(empresa_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl = 1 AND @empresa_unica IS NOT NULL,
  CONCAT('UPDATE estoque_movimentos SET empresa_id = ', @empresa_unica, ' WHERE empresa_id IS NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- estoque_reconciliacao ----------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estoque_reconciliacao' AND COLUMN_NAME = 'empresa_id');
SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estoque_reconciliacao');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE estoque_reconciliacao ADD COLUMN empresa_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estoque_reconciliacao' AND INDEX_NAME = 'idx_estoque_reconciliacao_empresa');
SET @sql := IF(@tbl = 1 AND @idx = 0, 'CREATE INDEX idx_estoque_reconciliacao_empresa ON estoque_reconciliacao(empresa_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl = 1 AND @empresa_unica IS NOT NULL,
  CONCAT('UPDATE estoque_reconciliacao SET empresa_id = ', @empresa_unica, ' WHERE empresa_id IS NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- estoque_saldos_cache ----------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estoque_saldos_cache' AND COLUMN_NAME = 'empresa_id');
SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estoque_saldos_cache');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE estoque_saldos_cache ADD COLUMN empresa_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estoque_saldos_cache' AND INDEX_NAME = 'idx_estoque_saldos_cache_empresa');
SET @sql := IF(@tbl = 1 AND @idx = 0, 'CREATE INDEX idx_estoque_saldos_cache_empresa ON estoque_saldos_cache(empresa_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl = 1 AND @empresa_unica IS NOT NULL,
  CONCAT('UPDATE estoque_saldos_cache SET empresa_id = ', @empresa_unica, ' WHERE empresa_id IS NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- fila_estoque ----------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fila_estoque' AND COLUMN_NAME = 'empresa_id');
SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fila_estoque');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE fila_estoque ADD COLUMN empresa_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fila_estoque' AND INDEX_NAME = 'idx_fila_estoque_empresa');
SET @sql := IF(@tbl = 1 AND @idx = 0, 'CREATE INDEX idx_fila_estoque_empresa ON fila_estoque(empresa_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl = 1 AND @empresa_unica IS NOT NULL,
  CONCAT('UPDATE fila_estoque SET empresa_id = ', @empresa_unica, ' WHERE empresa_id IS NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- fila_fiscal ----------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fila_fiscal' AND COLUMN_NAME = 'empresa_id');
SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fila_fiscal');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE fila_fiscal ADD COLUMN empresa_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fila_fiscal' AND INDEX_NAME = 'idx_fila_fiscal_empresa');
SET @sql := IF(@tbl = 1 AND @idx = 0, 'CREATE INDEX idx_fila_fiscal_empresa ON fila_fiscal(empresa_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl = 1 AND @empresa_unica IS NOT NULL,
  CONCAT('UPDATE fila_fiscal SET empresa_id = ', @empresa_unica, ' WHERE empresa_id IS NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- fila_integracao ----------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fila_integracao' AND COLUMN_NAME = 'empresa_id');
SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fila_integracao');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE fila_integracao ADD COLUMN empresa_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fila_integracao' AND INDEX_NAME = 'idx_fila_integracao_empresa');
SET @sql := IF(@tbl = 1 AND @idx = 0, 'CREATE INDEX idx_fila_integracao_empresa ON fila_integracao(empresa_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl = 1 AND @empresa_unica IS NOT NULL,
  CONCAT('UPDATE fila_integracao SET empresa_id = ', @empresa_unica, ' WHERE empresa_id IS NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- fila_morta ----------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fila_morta' AND COLUMN_NAME = 'empresa_id');
SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fila_morta');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE fila_morta ADD COLUMN empresa_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fila_morta' AND INDEX_NAME = 'idx_fila_morta_empresa');
SET @sql := IF(@tbl = 1 AND @idx = 0, 'CREATE INDEX idx_fila_morta_empresa ON fila_morta(empresa_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl = 1 AND @empresa_unica IS NOT NULL,
  CONCAT('UPDATE fila_morta SET empresa_id = ', @empresa_unica, ' WHERE empresa_id IS NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- nfe_integracao ----------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nfe_integracao' AND COLUMN_NAME = 'empresa_id');
SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nfe_integracao');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE nfe_integracao ADD COLUMN empresa_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nfe_integracao' AND INDEX_NAME = 'idx_nfe_integracao_empresa');
SET @sql := IF(@tbl = 1 AND @idx = 0, 'CREATE INDEX idx_nfe_integracao_empresa ON nfe_integracao(empresa_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl = 1 AND @empresa_unica IS NOT NULL,
  CONCAT('UPDATE nfe_integracao SET empresa_id = ', @empresa_unica, ' WHERE empresa_id IS NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- nfe_status_historico ----------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nfe_status_historico' AND COLUMN_NAME = 'empresa_id');
SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nfe_status_historico');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE nfe_status_historico ADD COLUMN empresa_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nfe_status_historico' AND INDEX_NAME = 'idx_nfe_status_historico_empresa');
SET @sql := IF(@tbl = 1 AND @idx = 0, 'CREATE INDEX idx_nfe_status_historico_empresa ON nfe_status_historico(empresa_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl = 1 AND @empresa_unica IS NOT NULL,
  CONCAT('UPDATE nfe_status_historico SET empresa_id = ', @empresa_unica, ' WHERE empresa_id IS NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- nfe_xml ----------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nfe_xml' AND COLUMN_NAME = 'empresa_id');
SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nfe_xml');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE nfe_xml ADD COLUMN empresa_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nfe_xml' AND INDEX_NAME = 'idx_nfe_xml_empresa');
SET @sql := IF(@tbl = 1 AND @idx = 0, 'CREATE INDEX idx_nfe_xml_empresa ON nfe_xml(empresa_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl = 1 AND @empresa_unica IS NOT NULL,
  CONCAT('UPDATE nfe_xml SET empresa_id = ', @empresa_unica, ' WHERE empresa_id IS NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- notas_fiscais ----------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notas_fiscais' AND COLUMN_NAME = 'empresa_id');
SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notas_fiscais');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE notas_fiscais ADD COLUMN empresa_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notas_fiscais' AND INDEX_NAME = 'idx_notas_fiscais_empresa');
SET @sql := IF(@tbl = 1 AND @idx = 0, 'CREATE INDEX idx_notas_fiscais_empresa ON notas_fiscais(empresa_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl = 1 AND @empresa_unica IS NOT NULL,
  CONCAT('UPDATE notas_fiscais SET empresa_id = ', @empresa_unica, ' WHERE empresa_id IS NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- notas_fiscais_eventos ----------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notas_fiscais_eventos' AND COLUMN_NAME = 'empresa_id');
SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notas_fiscais_eventos');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE notas_fiscais_eventos ADD COLUMN empresa_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notas_fiscais_eventos' AND INDEX_NAME = 'idx_notas_fiscais_eventos_empresa');
SET @sql := IF(@tbl = 1 AND @idx = 0, 'CREATE INDEX idx_notas_fiscais_eventos_empresa ON notas_fiscais_eventos(empresa_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl = 1 AND @empresa_unica IS NOT NULL,
  CONCAT('UPDATE notas_fiscais_eventos SET empresa_id = ', @empresa_unica, ' WHERE empresa_id IS NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- pedidos_hub ----------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos_hub' AND COLUMN_NAME = 'empresa_id');
SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos_hub');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE pedidos_hub ADD COLUMN empresa_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos_hub' AND INDEX_NAME = 'idx_pedidos_hub_empresa');
SET @sql := IF(@tbl = 1 AND @idx = 0, 'CREATE INDEX idx_pedidos_hub_empresa ON pedidos_hub(empresa_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl = 1 AND @empresa_unica IS NOT NULL,
  CONCAT('UPDATE pedidos_hub SET empresa_id = ', @empresa_unica, ' WHERE empresa_id IS NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- pedidos_integracao ----------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos_integracao' AND COLUMN_NAME = 'empresa_id');
SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos_integracao');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE pedidos_integracao ADD COLUMN empresa_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos_integracao' AND INDEX_NAME = 'idx_pedidos_integracao_empresa');
SET @sql := IF(@tbl = 1 AND @idx = 0, 'CREATE INDEX idx_pedidos_integracao_empresa ON pedidos_integracao(empresa_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl = 1 AND @empresa_unica IS NOT NULL,
  CONCAT('UPDATE pedidos_integracao SET empresa_id = ', @empresa_unica, ' WHERE empresa_id IS NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- pedidos_nfe_xml ----------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos_nfe_xml' AND COLUMN_NAME = 'empresa_id');
SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos_nfe_xml');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE pedidos_nfe_xml ADD COLUMN empresa_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos_nfe_xml' AND INDEX_NAME = 'idx_pedidos_nfe_xml_empresa');
SET @sql := IF(@tbl = 1 AND @idx = 0, 'CREATE INDEX idx_pedidos_nfe_xml_empresa ON pedidos_nfe_xml(empresa_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl = 1 AND @empresa_unica IS NOT NULL,
  CONCAT('UPDATE pedidos_nfe_xml SET empresa_id = ', @empresa_unica, ' WHERE empresa_id IS NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- pedidos_payloads ----------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos_payloads' AND COLUMN_NAME = 'empresa_id');
SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos_payloads');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE pedidos_payloads ADD COLUMN empresa_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos_payloads' AND INDEX_NAME = 'idx_pedidos_payloads_empresa');
SET @sql := IF(@tbl = 1 AND @idx = 0, 'CREATE INDEX idx_pedidos_payloads_empresa ON pedidos_payloads(empresa_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl = 1 AND @empresa_unica IS NOT NULL,
  CONCAT('UPDATE pedidos_payloads SET empresa_id = ', @empresa_unica, ' WHERE empresa_id IS NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- pedidos_status_historico ----------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos_status_historico' AND COLUMN_NAME = 'empresa_id');
SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos_status_historico');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE pedidos_status_historico ADD COLUMN empresa_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos_status_historico' AND INDEX_NAME = 'idx_pedidos_status_historico_empresa');
SET @sql := IF(@tbl = 1 AND @idx = 0, 'CREATE INDEX idx_pedidos_status_historico_empresa ON pedidos_status_historico(empresa_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl = 1 AND @empresa_unica IS NOT NULL,
  CONCAT('UPDATE pedidos_status_historico SET empresa_id = ', @empresa_unica, ' WHERE empresa_id IS NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- pedidos_validacao ----------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos_validacao' AND COLUMN_NAME = 'empresa_id');
SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos_validacao');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE pedidos_validacao ADD COLUMN empresa_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos_validacao' AND INDEX_NAME = 'idx_pedidos_validacao_empresa');
SET @sql := IF(@tbl = 1 AND @idx = 0, 'CREATE INDEX idx_pedidos_validacao_empresa ON pedidos_validacao(empresa_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl = 1 AND @empresa_unica IS NOT NULL,
  CONCAT('UPDATE pedidos_validacao SET empresa_id = ', @empresa_unica, ' WHERE empresa_id IS NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- pedidos_validacao_historico ----------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos_validacao_historico' AND COLUMN_NAME = 'empresa_id');
SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos_validacao_historico');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE pedidos_validacao_historico ADD COLUMN empresa_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos_validacao_historico' AND INDEX_NAME = 'idx_pedidos_validacao_historico_empresa');
SET @sql := IF(@tbl = 1 AND @idx = 0, 'CREATE INDEX idx_pedidos_validacao_historico_empresa ON pedidos_validacao_historico(empresa_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl = 1 AND @empresa_unica IS NOT NULL,
  CONCAT('UPDATE pedidos_validacao_historico SET empresa_id = ', @empresa_unica, ' WHERE empresa_id IS NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- produto_pendencias ----------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produto_pendencias' AND COLUMN_NAME = 'empresa_id');
SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produto_pendencias');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE produto_pendencias ADD COLUMN empresa_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produto_pendencias' AND INDEX_NAME = 'idx_produto_pendencias_empresa');
SET @sql := IF(@tbl = 1 AND @idx = 0, 'CREATE INDEX idx_produto_pendencias_empresa ON produto_pendencias(empresa_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl = 1 AND @empresa_unica IS NOT NULL,
  CONCAT('UPDATE produto_pendencias SET empresa_id = ', @empresa_unica, ' WHERE empresa_id IS NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- produtos_aprovacao_historico ----------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produtos_aprovacao_historico' AND COLUMN_NAME = 'empresa_id');
SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produtos_aprovacao_historico');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE produtos_aprovacao_historico ADD COLUMN empresa_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produtos_aprovacao_historico' AND INDEX_NAME = 'idx_produtos_aprovacao_historico_empresa');
SET @sql := IF(@tbl = 1 AND @idx = 0, 'CREATE INDEX idx_produtos_aprovacao_historico_empresa ON produtos_aprovacao_historico(empresa_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl = 1 AND @empresa_unica IS NOT NULL,
  CONCAT('UPDATE produtos_aprovacao_historico SET empresa_id = ', @empresa_unica, ' WHERE empresa_id IS NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- produtos_mapeamento ----------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produtos_mapeamento' AND COLUMN_NAME = 'empresa_id');
SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produtos_mapeamento');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE produtos_mapeamento ADD COLUMN empresa_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produtos_mapeamento' AND INDEX_NAME = 'idx_produtos_mapeamento_empresa');
SET @sql := IF(@tbl = 1 AND @idx = 0, 'CREATE INDEX idx_produtos_mapeamento_empresa ON produtos_mapeamento(empresa_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl = 1 AND @empresa_unica IS NOT NULL,
  CONCAT('UPDATE produtos_mapeamento SET empresa_id = ', @empresa_unica, ' WHERE empresa_id IS NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- produtos_pendentes_integracao ----------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produtos_pendentes_integracao' AND COLUMN_NAME = 'empresa_id');
SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produtos_pendentes_integracao');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE produtos_pendentes_integracao ADD COLUMN empresa_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produtos_pendentes_integracao' AND INDEX_NAME = 'idx_produtos_pendentes_integracao_empresa');
SET @sql := IF(@tbl = 1 AND @idx = 0, 'CREATE INDEX idx_produtos_pendentes_integracao_empresa ON produtos_pendentes_integracao(empresa_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl = 1 AND @empresa_unica IS NOT NULL,
  CONCAT('UPDATE produtos_pendentes_integracao SET empresa_id = ', @empresa_unica, ' WHERE empresa_id IS NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- produtos_tiny ----------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produtos_tiny' AND COLUMN_NAME = 'empresa_id');
SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produtos_tiny');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE produtos_tiny ADD COLUMN empresa_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produtos_tiny' AND INDEX_NAME = 'idx_produtos_tiny_empresa');
SET @sql := IF(@tbl = 1 AND @idx = 0, 'CREATE INDEX idx_produtos_tiny_empresa ON produtos_tiny(empresa_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl = 1 AND @empresa_unica IS NOT NULL,
  CONCAT('UPDATE produtos_tiny SET empresa_id = ', @empresa_unica, ' WHERE empresa_id IS NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- produtos_vsm ----------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produtos_vsm' AND COLUMN_NAME = 'empresa_id');
SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produtos_vsm');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE produtos_vsm ADD COLUMN empresa_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produtos_vsm' AND INDEX_NAME = 'idx_produtos_vsm_empresa');
SET @sql := IF(@tbl = 1 AND @idx = 0, 'CREATE INDEX idx_produtos_vsm_empresa ON produtos_vsm(empresa_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl = 1 AND @empresa_unica IS NOT NULL,
  CONCAT('UPDATE produtos_vsm SET empresa_id = ', @empresa_unica, ' WHERE empresa_id IS NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- produtos_vsm_eventos ----------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produtos_vsm_eventos' AND COLUMN_NAME = 'empresa_id');
SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produtos_vsm_eventos');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE produtos_vsm_eventos ADD COLUMN empresa_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produtos_vsm_eventos' AND INDEX_NAME = 'idx_produtos_vsm_eventos_empresa');
SET @sql := IF(@tbl = 1 AND @idx = 0, 'CREATE INDEX idx_produtos_vsm_eventos_empresa ON produtos_vsm_eventos(empresa_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl = 1 AND @empresa_unica IS NOT NULL,
  CONCAT('UPDATE produtos_vsm_eventos SET empresa_id = ', @empresa_unica, ' WHERE empresa_id IS NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- reconciliacao_itens ----------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reconciliacao_itens' AND COLUMN_NAME = 'empresa_id');
SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reconciliacao_itens');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE reconciliacao_itens ADD COLUMN empresa_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reconciliacao_itens' AND INDEX_NAME = 'idx_reconciliacao_itens_empresa');
SET @sql := IF(@tbl = 1 AND @idx = 0, 'CREATE INDEX idx_reconciliacao_itens_empresa ON reconciliacao_itens(empresa_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl = 1 AND @empresa_unica IS NOT NULL,
  CONCAT('UPDATE reconciliacao_itens SET empresa_id = ', @empresa_unica, ' WHERE empresa_id IS NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO schema_migrations(migration,version,description,status)
VALUES ('20260914_010_tenant_isolation','V104.49.3-R6','Isolamento multiempresa: empresa_id nas tabelas operacionais + backfill (melhoria 1 da seção 8).','aplicada');
