-- ============================================================================
-- Análise de consumo × teto da API Tiny (achado T-01) — rodar num PICO real.
-- Uso:  mysql -u <user> -p <seu_banco> < analise-limite-tiny.sql   (ou cole no phpMyAdmin)
-- Depois: me mande a saída das 4 consultas.
--
-- Fonte dos dados (gravados automaticamente pelo Hub desde a PR #37):
--   metricas_api           -> uma linha por chamada ao Tiny (sistema='tiny' V2, 'tiny_v3' V3)
--   auditoria_eventos      -> evento 'tiny.v2.response'/'tiny.v3.response' com o TETO nos headers
--                             (retorno JSON: .limite_api.limite_por_minuto  /  .rate_limit.*)
--
-- A janela padrão é as ÚLTIMAS 24h. Para cravar um pico específico, troque
--   criado_em >= NOW() - INTERVAL 1 DAY
-- por, ex.:  criado_em BETWEEN '2026-09-22 14:00:00' AND '2026-09-22 14:30:00'
-- ============================================================================

-- ----------------------------------------------------------------------------
-- (1) CONSUMO: chamadas ao Tiny por MINUTO — mostra os minutos de maior movimento.
--     Compare 'chamadas' com o teto do plano (consulta 3). 'estouros_limite' > 0
--     significa que o Tiny JÁ recusou por limite naquele minuto.
-- ----------------------------------------------------------------------------
SELECT DATE_FORMAT(criado_em, '%Y-%m-%d %H:%i') AS minuto,
       sistema,
       COUNT(*)                                                         AS chamadas,
       SUM(sucesso = 0)                                                 AS falhas,
       SUM(codigo_erro LIKE '%RATE%' OR codigo_erro LIKE '%LIMIT%')     AS estouros_limite
FROM   metricas_api
WHERE  sistema IN ('tiny', 'tiny_v3')
  AND  criado_em >= NOW() - INTERVAL 1 DAY
GROUP  BY minuto, sistema
ORDER  BY chamadas DESC
LIMIT  30;

-- ----------------------------------------------------------------------------
-- (2) CONSUMO DE LOTE: 'produto.incluir.php' e 'produto.alterar.php' contam como
--     chamada EM LOTE no Tiny V2, cujo teto é apenas 5/min (0 no plano Começar).
--     Qualquer minuto com chamadas_lote > 5 é o gargalo do T-01 em carne e osso.
-- ----------------------------------------------------------------------------
SELECT DATE_FORMAT(criado_em, '%Y-%m-%d %H:%i') AS minuto,
       COUNT(*) AS chamadas_lote
FROM   metricas_api
WHERE  sistema = 'tiny'
  AND  endpoint IN ('produto.incluir.php', 'produto.alterar.php')
  AND  criado_em >= NOW() - INTERVAL 1 DAY
GROUP  BY minuto
ORDER  BY chamadas_lote DESC
LIMIT  20;

-- ----------------------------------------------------------------------------
-- (3) TETO V2 (header x-limit-api): quantas chamadas/min o Tiny permite p/ a conta.
--     (só aparece se houve chamada V2 com o header presente na janela)
-- ----------------------------------------------------------------------------
SELECT DATE_FORMAT(criado_em, '%Y-%m-%d %H:%i')                         AS momento,
       JSON_EXTRACT(retorno, '$.limite_api.limite_por_minuto')          AS teto_por_minuto_v2
FROM   auditoria_eventos
WHERE  acao = 'tiny.v2.response'
  AND  JSON_EXTRACT(retorno, '$.limite_api.limite_por_minuto') IS NOT NULL
  AND  criado_em >= NOW() - INTERVAL 1 DAY
ORDER  BY criado_em DESC
LIMIT  5;

-- ----------------------------------------------------------------------------
-- (4) ORÇAMENTO V3 (headers X-RateLimit-*): teto, e o MENOR 'restante' observado
--     na janela — quanto mais perto de 0, mais perto de estourar.
-- ----------------------------------------------------------------------------
SELECT MAX(CAST(JSON_EXTRACT(retorno, '$.rate_limit.limite')   AS UNSIGNED)) AS teto_v3,
       MIN(CAST(JSON_EXTRACT(retorno, '$.rate_limit.restante') AS UNSIGNED)) AS menor_restante_v3,
       COUNT(*)                                                              AS chamadas_v3_com_header
FROM   auditoria_eventos
WHERE  acao = 'tiny.v3.response'
  AND  JSON_EXTRACT(retorno, '$.rate_limit.restante') IS NOT NULL
  AND  criado_em >= NOW() - INTERVAL 1 DAY;
