-- Achado H-01 (validação de runtime do isolamento multiempresa, 2026-09-15).
--
-- O isolamento estava INERTE: TenantScopeService filtra corretamente, e os 162 pontos que roteiam
-- consultas por ele estavam certos — mas nada no aplicativo selecionava a empresa ativa, porque
-- TenantContextService::set() não tinha chamador e `usuarios` não tinha vínculo com empresa.
-- Medido por HTTP, com duas empresas povoadas, a tela de pedidos mostrava as linhas das duas.
--
-- Esta migration cria o vínculo. O login passa a carimbar a sessão a partir dele
-- (app/Core/Auth.php::finalizeLogin).
--
-- POR QUE A COLUNA NASCE NULL: usuário sem empresa continua enxergando tudo, que é exatamente o
-- comportamento de hoje. Assim aplicar a migration NÃO esvazia tela de ninguém, e o isolamento
-- entra em vigor por usuário, conforme as empresas forem atribuídas. Ligar isolamento de uma vez
-- para todo mundo seria a mudança arriscada; esta é incremental e reversível.
--
-- BACKFILL: quando existe exatamente UMA empresa cadastrada — o caso de toda instalação atual —
-- todos os usuários são atribuídos a ela, mesma regra e mesma justificativa da migration
-- 20260914_010. Com duas ou mais empresas já cadastradas o backfill é PULADO de propósito: não há
-- como adivinhar a que empresa cada usuário pertence, e chutar seria pior do que deixar explícito.
--
-- Idempotente: coluna e índice só são criados se ainda não existirem, e o backfill só toca linhas
-- que ainda estão com NULL. Pode ser executada novamente com segurança.
--
-- REVERSÃO: ALTER TABLE usuarios DROP COLUMN empresa_id;  (a coluna não é lida por nada além do
-- login; sem ela, currentEmpresaId() volta a devolver null e o comportamento é o anterior).

SET @empresas := (SELECT COUNT(*) FROM empresas);
SET @empresa_unica := (SELECT CASE WHEN @empresas = 1 THEN (SELECT MIN(id) FROM empresas) ELSE NULL END);

-- ---------- usuarios.empresa_id ----------
SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios');
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'empresa_id');
SET @sql := IF(@tbl = 1 AND @col = 0, 'ALTER TABLE usuarios ADD COLUMN empresa_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND INDEX_NAME = 'idx_usuarios_empresa');
SET @sql := IF(@tbl = 1 AND @idx = 0, 'CREATE INDEX idx_usuarios_empresa ON usuarios(empresa_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@tbl = 1 AND @empresa_unica IS NOT NULL,
  CONCAT('UPDATE usuarios SET empresa_id = ', @empresa_unica, ' WHERE empresa_id IS NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
