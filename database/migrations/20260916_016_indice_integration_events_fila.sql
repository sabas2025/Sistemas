-- Achado I-19 (auditoria de 2026-09-15) — índice para o único gargalo MEDIDO desta auditoria.
--
-- A fase 10 do CLAUDE.md exige evidência antes de otimizar, não suspeita. A evidência veio de
-- carregar um MariaDB real com o volume de um dia na meta de capacidade (500 pedidos por minuto =
-- 720 mil/dia): 300 mil linhas em fila_integracao, 500 mil em auditoria_eventos, 150 mil em
-- integration_events, 150 mil em evento_correlacao, 200 mil em pedidos_integracao.
--
-- Todo o caminho quente do painel passou bem: reservar o próximo item da fila leva 13 ms usando
-- idx_fila_status_proxima_prioridade, as telas respondem em 9–11 ms, e a busca por Trace ID em
-- 9 ms com idx_audit_trace. UMA coisa destoava.
--
-- `IntegrationEventService::onQueueFinished()` roda DUAS sentenças filtrando integration_events
-- por fila_id — um UPDATE e um SELECT, os dois com ORDER BY id DESC LIMIT 1 — e é chamada pelo
-- `QueueService::marcarResultado()`, ou seja, **a cada item de fila concluído**. A coluna não tinha
-- índice nenhum, então as duas varriam a tabela inteira.
--
-- Medido, antes e depois, com 150 mil linhas:
--   SELECT por fila_id   43 ms -> 9 ms
--   UPDATE por fila_id   87 ms -> 9 ms
--   plano: type=index varrendo PRIMARY -> type=ref, rows=1
-- (os ~9 ms que sobram são custo de conexão do cliente, não da consulta)
--
-- Na meta de 500 pedidos por minuto isso somava cerca de 65 segundos de trabalho de banco para
-- cada minuto de tráfego — mais de um minuto de banco por minuto de relógio, ou seja, o ponto de
-- saturação. E piorava sozinho, porque a tabela só cresce.
--
-- O `id` como segunda coluna atende ao `ORDER BY id DESC LIMIT 1` sem ordenação extra.
--
-- Criar o índice custou 0,133 s sobre 150 mil linhas: é barato e NÃO precisa de janela.
--
-- PARIDADE (lição do I-18): o índice foi acrescentado TAMBÉM em database/modules/fila.sql, e o
-- consolidado foi regenerado. Instalação nova e instalação atualizada continuam idênticas.
--
-- NÃO indexado, de propósito: `evento_correlacao.fila_id` também varre a tabela (57 ms), mas
-- NENHUMA consulta do Hub filtra aquela tabela por fila_id — índice ali seria custo de escrita sem
-- leitura que o justifique. Fica registrado, não aplicado.
--
-- Idempotente: só cria se ainda não existir.
--
-- ROLLBACK:  DROP INDEX idx_ie_fila ON integration_events;

SET @tbl := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'integration_events');
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'integration_events' AND COLUMN_NAME = 'fila_id');
SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'integration_events' AND INDEX_NAME = 'idx_ie_fila');

SET @hub_sql := IF(@tbl = 1 AND @col = 1 AND @idx = 0,
  'CREATE INDEX idx_ie_fila ON integration_events(fila_id, id)',
  'SELECT 1');
PREPARE hub_stmt FROM @hub_sql; EXECUTE hub_stmt; DEALLOCATE PREPARE hub_stmt;

INSERT IGNORE INTO schema_migrations(migration,version,description,status)
VALUES ('20260916_016_indice_integration_events_fila','V104.49.3-R7','Índice (fila_id,id) em integration_events: onQueueFinished varria a tabela a cada item concluído (achado I-19).','aplicada');
