-- Auditoria de capacidade 2026-09-14 — achados C-05 e C-07.
--
-- C-05: logs_integracao não tinha escopo de empresa. Com 100 clientes na mesma instalação, os logs
-- de integração de todos ficavam no mesmo balde sem filtro: quem investigasse o cliente A via
-- linhas do cliente B. auditoria_eventos e security_events continuam GLOBAIS de propósito — são a
-- trilha forense da instalação, não dados operacionais de um cliente.
--
-- C-07: tabela de sessão compartilhada, para permitir mais de um servidor atrás de balanceador.
-- Só é usada quando security.session_driver='database'; o padrão continua arquivo local.
--
-- Idempotente: coluna, índices e tabela só são criados se ainda não existirem.

-- ---------- C-05 ----------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='logs_integracao' AND COLUMN_NAME='empresa_id');
SET @sql := IF(@col=0,'ALTER TABLE logs_integracao ADD COLUMN empresa_id INT NULL','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='logs_integracao' AND INDEX_NAME='idx_logs_integracao_empresa');
SET @sql := IF(@idx=0,'CREATE INDEX idx_logs_integracao_empresa ON logs_integracao(empresa_id)','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='logs_integracao' AND INDEX_NAME='idx_logs_empresa_data');
SET @sql := IF(@idx=0,'CREATE INDEX idx_logs_empresa_data ON logs_integracao(empresa_id,criado_em)','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill com a mesma regra da migration 20260914_010: só quando existe UMA empresa cadastrada.
-- Com duas ou mais, não há como saber a quem o log histórico pertence, e chutar seria pior.
SET @empresas := (SELECT COUNT(*) FROM empresas);
SET @empresa_unica := (SELECT CASE WHEN @empresas = 1 THEN (SELECT MIN(id) FROM empresas) ELSE NULL END);
SET @sql := IF(@empresa_unica IS NOT NULL,
  CONCAT('UPDATE logs_integracao SET empresa_id = ', @empresa_unica, ' WHERE empresa_id IS NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- C-07 ----------
CREATE TABLE IF NOT EXISTS sessoes (
  id VARCHAR(128) NOT NULL PRIMARY KEY,
  dados MEDIUMTEXT NULL,
  usuario_id INT NULL,
  ip VARCHAR(64) NULL,
  ultimo_acesso INT NOT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sessoes_ultimo_acesso(ultimo_acesso),
  INDEX idx_sessoes_usuario(usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO schema_migrations(migration,version,description,status)
VALUES ('20260914_013_capacidade_logs_sessoes','V104.49.3-R7','Escopo de empresa em logs_integracao (C-05) e tabela de sessão compartilhada (C-07).','aplicada');
