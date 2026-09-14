-- Auditoria de capacidade 2026-09-14 (achado C-02) — chaves primárias INT -> BIGINT.
--
-- PROBLEMA: quatro tabelas de alto volume usavam INT na chave primária (teto 2.147.483.647). No
-- volume alvo de 500 pedidos por minuto:
--     logs_integracao     ~2.000 linhas/min  -> estoura em ~2,0 anos
--     fila_integracao     ~1.500 linhas/min  -> estoura em ~2,7 anos
--     pedidos_integracao    500 linhas/min   -> estoura em ~8,2 anos
--     pedidos_hub           500 linhas/min   -> estoura em ~8,2 anos
-- AUTO_INCREMENT não reaproveita id de linha apagada, então retenção não adia o estouro. Quando
-- acontece, o INSERT falha com "Duplicate entry '2147483647' for key 'PRIMARY'" e a entrada de
-- trabalho para por inteiro.
--
-- ============================ LEIA ANTES DE EXECUTAR ============================
--
-- ESTA MIGRATION BLOQUEIA AS TABELAS. ALTER TABLE ... MODIFY de chave primária reconstrói a
-- tabela e os índices. O tempo é proporcional ao volume:
--     tabela vazia ou pequena (< 1M linhas)   segundos
--     ~10M linhas                             minutos
--     ~100M linhas                            horas
--
-- Por isso o momento certo de executar é AGORA, com as tabelas pequenas — o custo só cresce.
--
-- JANELA: execute em janela de manutenção, com os workers parados (worker_fila, worker_estoque,
-- worker_fiscal, worker_reconciliacao) e a entrada de webhooks suspensa ou drenada. Um INSERT
-- concorrente durante o ALTER fica bloqueado até o fim.
--
-- ANTES: gere um backup completo pelo painel (Backups > Gerar) e confirme o SHA-256. O rollback
-- deste arquivo é a restauração desse backup: reverter BIGINT -> INT só é seguro se nenhum id já
-- tiver ultrapassado 2.147.483.647, e não há como garantir isso depois de voltar a operar.
--
-- MySQL 8 e MariaDB 10.5+ executam estes ALTER com ALGORITHM=INPLACE quando possível, mas a
-- mudança de tipo de coluna geralmente exige COPY. Não confie em execução online: assuma bloqueio.
--
-- ORDEM: as colunas que REFERENCIAM as chaves são migradas ANTES das próprias chaves, para que em
-- nenhum instante exista referência estreita apontando para chave larga.
--
-- IDEMPOTENTE: cada ALTER só roda se o tipo atual ainda for INT.
-- ===============================================================================

-- ---------- 1. Colunas que referenciam pedidos_hub.id e fila_integracao.id ----------
-- Dez das doze colunas fila_id do schema JÁ eram BIGINT; pedidos_validacao.fila_id era a exceção.

SET @sql := (SELECT IF(DATA_TYPE = 'int',
  'ALTER TABLE pedidos_validacao MODIFY fila_id BIGINT NULL', 'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pedidos_validacao' AND COLUMN_NAME='fila_id');
SET @sql := IFNULL(@sql,'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(DATA_TYPE = 'int',
  'ALTER TABLE pedidos_validacao MODIFY pedido_hub_id BIGINT NULL', 'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pedidos_validacao' AND COLUMN_NAME='pedido_hub_id');
SET @sql := IFNULL(@sql,'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(DATA_TYPE = 'int',
  'ALTER TABLE pedidos_payloads MODIFY pedido_hub_id BIGINT NOT NULL', 'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pedidos_payloads' AND COLUMN_NAME='pedido_hub_id');
SET @sql := IFNULL(@sql,'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(DATA_TYPE = 'int',
  'ALTER TABLE pedidos_status_historico MODIFY pedido_hub_id BIGINT NOT NULL', 'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pedidos_status_historico' AND COLUMN_NAME='pedido_hub_id');
SET @sql := IFNULL(@sql,'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(DATA_TYPE = 'int',
  'ALTER TABLE pedidos_nfe_xml MODIFY pedido_hub_id BIGINT NOT NULL', 'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pedidos_nfe_xml' AND COLUMN_NAME='pedido_hub_id');
SET @sql := IFNULL(@sql,'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- 2. As chaves primárias ----------

SET @sql := (SELECT IF(DATA_TYPE = 'int',
  'ALTER TABLE fila_integracao MODIFY id BIGINT NOT NULL AUTO_INCREMENT', 'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fila_integracao' AND COLUMN_NAME='id');
SET @sql := IFNULL(@sql,'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(DATA_TYPE = 'int',
  'ALTER TABLE pedidos_integracao MODIFY id BIGINT NOT NULL AUTO_INCREMENT', 'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pedidos_integracao' AND COLUMN_NAME='id');
SET @sql := IFNULL(@sql,'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(DATA_TYPE = 'int',
  'ALTER TABLE pedidos_hub MODIFY id BIGINT NOT NULL AUTO_INCREMENT', 'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pedidos_hub' AND COLUMN_NAME='id');
SET @sql := IFNULL(@sql,'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(DATA_TYPE = 'int',
  'ALTER TABLE logs_integracao MODIFY id BIGINT NOT NULL AUTO_INCREMENT', 'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='logs_integracao' AND COLUMN_NAME='id');
SET @sql := IFNULL(@sql,'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO schema_migrations(migration,version,description,status)
VALUES ('20260914_012_pk_bigint_capacidade','V104.49.3-R7','Chaves primárias INT->BIGINT nas tabelas de alto volume (achado C-02).','aplicada');
