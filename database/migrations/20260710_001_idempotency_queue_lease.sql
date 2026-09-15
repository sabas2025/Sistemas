-- V104.36 - idempotência atômica, propriedade de reserva e lease da fila.
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='integration_idempotency' AND COLUMN_NAME='owner_token')=0, 'ALTER TABLE integration_idempotency ADD COLUMN owner_token CHAR(64) NULL AFTER original_fila_id', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='integration_idempotency' AND COLUMN_NAME='locked_until')=0, 'ALTER TABLE integration_idempotency ADD COLUMN locked_until DATETIME NULL AFTER owner_token', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='integration_idempotency' AND INDEX_NAME='idx_idemp_lock')=0, 'CREATE INDEX idx_idemp_lock ON integration_idempotency(status, locked_until)', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fila_integracao' AND COLUMN_NAME='lease_expires_at')=0, 'ALTER TABLE fila_integracao ADD COLUMN lease_expires_at DATETIME NULL AFTER locked_at', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fila_integracao' AND COLUMN_NAME='heartbeat_at')=0, 'ALTER TABLE fila_integracao ADD COLUMN heartbeat_at DATETIME NULL AFTER lease_expires_at', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fila_integracao' AND INDEX_NAME='idx_fila_lease')=0, 'CREATE INDEX idx_fila_lease ON fila_integracao(status, lease_expires_at)', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
SET @hub_sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fila_integracao' AND INDEX_NAME='idx_fila_ready')=0, 'CREATE INDEX idx_fila_ready ON fila_integracao(status, proxima_tentativa, prioridade, id)', 'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;
