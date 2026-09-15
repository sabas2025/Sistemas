-- Melhoria 9 da seção 8 (relatório V104.49.3-R6) — proveniência consultável do backup.
--
-- O achado A-11 passou a gravar a proveniência (local_generated / imported_untrusted) dentro do
-- payload assinado do backup, coberta pelo HMAC. Isso resolve a decisão de segurança (o restore
-- exige confirmação reforçada para importados), mas deixou a LISTAGEM do painel dependendo do
-- prefixo "importado_" no nome do arquivo para mostrar a origem — frágil se alguém renomear.
--
-- Esta migration acrescenta a coluna consultável. A assinatura continua sendo a fonte
-- autoritativa: o restore lê a proveniência do .sig.json (protegida por HMAC), nunca desta
-- coluna, que serve para exibição, filtro e relatório.
--
-- Idempotente: a coluna e o índice só são criados se ainda não existirem, e o backfill só
-- reclassifica linhas que ainda estão no valor padrão.

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'backups_banco' AND COLUMN_NAME = 'proveniencia');
SET @sql := IF(@col = 0,
  'ALTER TABLE backups_banco ADD COLUMN proveniencia VARCHAR(32) NOT NULL DEFAULT ''local_generated'' AFTER status',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'backups_banco' AND INDEX_NAME = 'idx_backup_proveniencia');
SET @sql := IF(@idx = 0,
  'CREATE INDEX idx_backup_proveniencia ON backups_banco(proveniencia)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill conservador: o histórico anterior a esta versão não declarava proveniência, então a
-- única pista é o prefixo do nome de arquivo usado pelo importador. Na dúvida, o valor padrão
-- (local_generated) permanece — e o restore continua decidindo pela assinatura, que para
-- assinaturas v1 sem proveniência já resolve para "importado" no caso duvidoso.
UPDATE backups_banco
   SET proveniencia = 'imported_untrusted'
 WHERE proveniencia = 'local_generated'
   AND (arquivo LIKE 'importado\\_%' OR nome_arquivo LIKE 'importado\\_%');

INSERT IGNORE INTO schema_migrations(migration,version,description,status)
VALUES ('20260914_009_backup_proveniencia','V104.49.3-R6','Proveniência consultável do backup (melhoria 9 da seção 8).','aplicada');
