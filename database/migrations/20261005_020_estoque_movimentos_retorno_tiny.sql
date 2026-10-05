-- Achado D-02 (auditoria durante a gravação do vídeo-demo, 2026-10-05).
--
-- workers/worker_estoque.php (ramo destino='tiny', fluxo PRINCIPAL de estoque VSM -> Tiny) grava
-- `UPDATE estoque_movimentos SET status=?, retorno_tiny=? WHERE trace_id=? AND sku=?`. A coluna
-- `retorno_tiny` NÃO existia em `estoque_movimentos` (só `retorno_vsm`), então o UPDATE lançava
-- `Unknown column 'retorno_tiny'`. O try/catch do worker rebaixava o erro a "falha do item":
-- o movimento nunca virava 'sucesso' nesse fluxo E o item era re-tentado — com risco de reescrever
-- o saldo no Tiny a cada retry (a linha 37, $tiny->atualizarEstoque(), já pode ter tido sucesso
-- antes do UPDATE estourar). Medido contra MariaDB: o UPDATE do ramo destino=tiny falhava.
--
-- Esta migration cria a coluna, espelhando `retorno_vsm` do ramo irmão (destino='vsm', que já
-- funciona). Mesma coluna foi adicionada ao módulo database/modules/estoque.sql e ao consolidado
-- (lição I-18: paridade instalação nova × atualização no MESMO commit).
--
-- Idempotente: só adiciona se ainda não existir. Pode reexecutar com segurança.
-- REVERSÃO: ALTER TABLE estoque_movimentos DROP COLUMN retorno_tiny;
--           (a coluna só guarda a resposta do Tiny para auditoria; nada a lê para decidir fluxo.)

SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estoque_movimentos');
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estoque_movimentos' AND COLUMN_NAME = 'retorno_tiny');
SET @sql := IF(@tbl = 1 AND @col = 0,
  'ALTER TABLE estoque_movimentos ADD COLUMN retorno_tiny LONGTEXT NULL AFTER retorno_vsm', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
