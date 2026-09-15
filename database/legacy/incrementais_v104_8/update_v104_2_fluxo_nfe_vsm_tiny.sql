-- V104.2 - Fluxo fiscal VSM -> Hub -> Tiny
-- Adiciona o checkbox: Integrações > Escolher fluxos ativos > Notas fiscais > Enviar NF-e autorizada do VSM para a Tiny.
-- Seguro para bases existentes: o salvamento da tela também cria essas colunas caso ainda não existam.

ALTER TABLE configuracoes_integracao
  ADD COLUMN IF NOT EXISTS sync_vsm_enviar_nota_tiny TINYINT DEFAULT 1 AFTER sync_vsm_status_produto_tiny;

ALTER TABLE configuracoes_integracao
  ADD COLUMN IF NOT EXISTS fluxo_nfe_vsm_enviar_tiny TINYINT DEFAULT 1 AFTER fluxo_pedido_tiny_enviar_vsm;

UPDATE configuracoes_integracao
SET sync_vsm_enviar_nota_tiny = 1,
    fluxo_nfe_vsm_enviar_tiny = 1,
    sync_exigir_nfe_autorizada = 1,
    sync_ordem_envio = CASE
      WHEN sync_ordem_envio IS NULL OR sync_ordem_envio = '' THEN 'pedido_vsm_receber,pedido_vsm_enviar_tiny,nfe_vsm_enviar_tiny,nfe_tiny_enviar_vsm,estoque_tiny_enviar_vsm,estoque_vsm_enviar_tiny,produto_status_tiny_enviar_vsm,produto_status_vsm_enviar_tiny'
      WHEN sync_ordem_envio NOT LIKE '%nfe_vsm_enviar_tiny%' THEN REPLACE(sync_ordem_envio, 'nfe_tiny_enviar_vsm', 'nfe_vsm_enviar_tiny,nfe_tiny_enviar_vsm')
      ELSE sync_ordem_envio
    END
WHERE id = 1;
