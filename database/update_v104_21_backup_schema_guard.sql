-- V104.21 - Correção tela Backups/backups_banco idempotente
INSERT INTO schema_migrations(migration, checksum, status, mensagem)
VALUES('v104_21_backup_schema_guard', SHA2('v104_21_backup_schema_guard',256), 'aplicada', 'Backups com schema guard idempotente e compatibilidade de colunas antigas.')
ON DUPLICATE KEY UPDATE status=VALUES(status), mensagem=VALUES(mensagem);
