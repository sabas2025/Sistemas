-- V104.48.1 - concorrência segura da fila, lease configurável e permissões fiscais.
-- Compatível com MySQL 8.x sem depender de ADD/CREATE ... IF NOT EXISTS.
-- Não remove dados nem altera os contratos Tiny/VSM.


CREATE TABLE IF NOT EXISTS schema_migrations (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  migration VARCHAR(180) NOT NULL UNIQUE,
  version VARCHAR(40) NULL,
  description TEXT NULL,
  checksum VARCHAR(128) NULL,
  status ENUM('aplicada','falha') DEFAULT 'aplicada',
  mensagem TEXT NULL,
  aplicada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_schema_migrations_status(status),
  INDEX idx_schema_migrations_data(aplicada_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Compatibilidade com versões antigas da tabela de migrations.
SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='schema_migrations' AND COLUMN_NAME='version')=0,
  'ALTER TABLE schema_migrations ADD COLUMN version VARCHAR(40) NULL', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='schema_migrations' AND COLUMN_NAME='description')=0,
  'ALTER TABLE schema_migrations ADD COLUMN description TEXT NULL', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='schema_migrations' AND COLUMN_NAME='checksum')=0,
  'ALTER TABLE schema_migrations ADD COLUMN checksum VARCHAR(128) NULL', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='schema_migrations' AND COLUMN_NAME='status')=0,
  'ALTER TABLE schema_migrations ADD COLUMN status ENUM(''aplicada'',''falha'') DEFAULT ''aplicada''', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='schema_migrations' AND COLUMN_NAME='mensagem')=0,
  'ALTER TABLE schema_migrations ADD COLUMN mensagem TEXT NULL', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='schema_migrations' AND COLUMN_NAME='aplicada_em')=0,
  'ALTER TABLE schema_migrations ADD COLUMN aplicada_em TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

ALTER TABLE fila_integracao
  MODIFY status ENUM('pendente','processando','sucesso','erro','falha_definitiva','ignorado') DEFAULT 'pendente';

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fila_integracao' AND COLUMN_NAME='idempotency_key')=0,
  'ALTER TABLE fila_integracao ADD COLUMN idempotency_key VARCHAR(190) NULL', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fila_integracao' AND COLUMN_NAME='locked_by')=0,
  'ALTER TABLE fila_integracao ADD COLUMN locked_by VARCHAR(120) NULL', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fila_integracao' AND COLUMN_NAME='locked_at')=0,
  'ALTER TABLE fila_integracao ADD COLUMN locked_at DATETIME NULL', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fila_integracao' AND COLUMN_NAME='lease_expires_at')=0,
  'ALTER TABLE fila_integracao ADD COLUMN lease_expires_at DATETIME NULL', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fila_integracao' AND COLUMN_NAME='heartbeat_at')=0,
  'ALTER TABLE fila_integracao ADD COLUMN heartbeat_at DATETIME NULL', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fila_integracao' AND INDEX_NAME='idx_fila_idempotency_key')=0,
  'ALTER TABLE fila_integracao ADD INDEX idx_fila_idempotency_key (idempotency_key)', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fila_integracao' AND INDEX_NAME='idx_fila_status_proxima_prioridade')=0,
  'ALTER TABLE fila_integracao ADD INDEX idx_fila_status_proxima_prioridade (status,proxima_tentativa,prioridade,id)', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fila_integracao' AND INDEX_NAME='idx_fila_locked')=0,
  'ALTER TABLE fila_integracao ADD INDEX idx_fila_locked (locked_by,locked_at)', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fila_integracao' AND INDEX_NAME='idx_fila_lease')=0,
  'ALTER TABLE fila_integracao ADD INDEX idx_fila_lease (status,lease_expires_at)', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

CREATE TABLE IF NOT EXISTS fila_reprocessamento_historico (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  fila_id BIGINT NOT NULL,
  status_anterior VARCHAR(40) NULL,
  tentativas_anteriores INT DEFAULT 0,
  codigo_erro_anterior VARCHAR(120) NULL,
  retorno_anterior LONGTEXT NULL,
  locked_by_anterior VARCHAR(120) NULL,
  solicitado_por BIGINT NULL,
  motivo VARCHAR(500) NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_fila_reproc_fila(fila_id),
  INDEX idx_fila_reproc_trace(trace_id),
  INDEX idx_fila_reproc_data(criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='configuracoes_integracao' AND COLUMN_NAME='queue_processing_timeout_minutes')=0,
  'ALTER TABLE configuracoes_integracao ADD COLUMN queue_processing_timeout_minutes INT DEFAULT 30', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='configuracoes_integracao' AND COLUMN_NAME='queue_lease_minutes')=0,
  'ALTER TABLE configuracoes_integracao ADD COLUMN queue_lease_minutes INT DEFAULT 5', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='configuracoes_integracao' AND COLUMN_NAME='queue_lease_by_type_json')=0,
  'ALTER TABLE configuracoes_integracao ADD COLUMN queue_lease_by_type_json LONGTEXT NULL', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

UPDATE configuracoes_integracao
SET queue_processing_timeout_minutes=COALESCE(queue_processing_timeout_minutes,30),
    queue_lease_minutes=COALESCE(queue_lease_minutes,5)
WHERE id=1;

INSERT IGNORE INTO permissoes_perfil(perfil,modulo,acao,permitido) VALUES
('admin','fiscal','visualizar',1),('admin','fiscal','reconciliar',1),('admin','fiscal','reenviar',1),
('gerente','fiscal','visualizar',1),('gerente','fiscal','reconciliar',1),('gerente','fiscal','reenviar',1),
('operador','fiscal','visualizar',0),('operador','fiscal','reconciliar',0),('operador','fiscal','reenviar',0);

INSERT INTO schema_migrations(migration,version,description,checksum,status,mensagem,aplicada_em)
VALUES('20260712_001_queue_oauth_concurrency','V104.48.1','Concorrência OAuth/fila, lease configurável e permissões fiscais',NULL,'aplicada','Migração V104.48.1 aplicada',CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE version=VALUES(version),description=VALUES(description),status='aplicada',mensagem=VALUES(mensagem),aplicada_em=CURRENT_TIMESTAMP;
