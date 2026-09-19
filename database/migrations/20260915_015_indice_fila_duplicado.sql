-- Achado I-18 (auditoria de 2026-09-15) — remove um índice DUPLICADO em fila_integracao.
--
-- Como apareceu: comparando, num MariaDB real, o schema que a INSTALAÇÃO NOVA produz
-- (database/install_final_current.sql) com o que a ATUALIZAÇÃO produz (módulos + as 13 migrations),
-- que é a paridade que a fase 2 do CLAUDE.md exige. As 1.550 colunas batem exatamente. Os índices
-- divergiam em um só:
--
--   idx_fila_ready                     (status, proxima_tentativa, prioridade, id)
--   idx_fila_status_proxima_prioridade (status, proxima_tentativa, prioridade, id)
--
-- Mesmas colunas, mesma ordem, os dois NÃO-únicos: são o mesmo índice com dois nomes. A migration
-- 20260710_001 criou o primeiro; dois dias depois a 20260712_001 criou o segundo, e foi ESTE que
-- entrou em database/modules/fila.sql e, por consequência, no consolidado. O de 10 de julho ficou
-- para trás — some da instalação nova e sobrevive em toda instalação que rodou a cadeia.
--
-- Por que remover em vez de levar o ausente para os módulos: `fila_integracao` é a tabela de
-- escrita mais quente do Hub (a meta é 500 pedidos por minuto), e todo INSERT/UPDATE nela mantinha
-- a MESMA árvore B duas vezes, sem nenhum ganho de leitura. Medido com EXPLAIN nos dois bancos: o
-- otimizador escolhe `idx_fila_status` para a consulta de reserva da fila e não usa nenhum dos
-- dois compostos, então remover o duplicado não tira caminho de acesso nenhum.
--
-- SEGURANÇA: o DROP só acontece se o índice canônico existir. Se por qualquer razão
-- `idx_fila_status_proxima_prioridade` não estiver presente, esta migration não faz nada e a
-- instalação continua com `idx_fila_ready` — nunca fica sem o caminho de acesso.
--
-- Idempotente: reexecutar não faz efeito, porque o DROP é condicionado à existência do duplicado.
--
-- ROLLBACK, se algum dia for preciso:
--   CREATE INDEX idx_fila_ready ON fila_integracao(status, proxima_tentativa, prioridade, id);
--
-- NÃO tratados aqui, de propósito: `homologacao_checklist.idx_homologacao_chave` e
-- `llm_policy_settings.idx_llm_policy_key` também são redundantes (duplicam uma UNIQUE da mesma
-- coluna), mas existem nos DOIS caminhos, logo não são divergência de paridade, e as duas tabelas
-- são frias — mexer nelas sem evidência de gargalo seria a otimização prematura que o CLAUDE.md
-- proíbe. Ficam registradas para que a próxima auditoria não as "descubra" de novo.

SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fila_integracao');
SET @dup := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fila_integracao' AND INDEX_NAME = 'idx_fila_ready');
SET @canonico := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fila_integracao' AND INDEX_NAME = 'idx_fila_status_proxima_prioridade');

SET @hub_sql := IF(@tbl = 1 AND @dup > 0 AND @canonico > 0,
  'DROP INDEX idx_fila_ready ON fila_integracao',
  'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

INSERT IGNORE INTO schema_migrations(migration,version,description,status)
VALUES ('20260915_015_indice_fila_duplicado','V104.49.3-R7','Remove idx_fila_ready, duplicata exata de idx_fila_status_proxima_prioridade (achado I-18).','aplicada');
