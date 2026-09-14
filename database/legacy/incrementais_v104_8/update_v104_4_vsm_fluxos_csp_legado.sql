-- V104.4 - VSM profile, modelo oficial Tiny -> VSM e correções CSP/legado
ALTER TABLE configuracoes_integracao ADD COLUMN IF NOT EXISTS vsm_api_principal VARCHAR(40) DEFAULT 'pedidos-integradora';
ALTER TABLE configuracoes_integracao ADD COLUMN IF NOT EXISTS vsm_api_loja VARCHAR(40) DEFAULT 'desativado';
ALTER TABLE configuracoes_integracao ADD COLUMN IF NOT EXISTS vsm_swagger_integradora VARCHAR(255) DEFAULT 'https://conectavenda.homolog.vsm.com.br/swagger-ui/index.html?urls.primaryName=pedidos-integradora';
ALTER TABLE configuracoes_integracao ADD COLUMN IF NOT EXISTS vsm_swagger_loja VARCHAR(255) DEFAULT 'https://conectavenda.homolog.vsm.com.br/swagger-ui/index.html?urls.primaryName=pedidos-loja';
ALTER TABLE configuracoes_integracao ADD COLUMN IF NOT EXISTS vsm_waf_agressivo TINYINT DEFAULT 0;
ALTER TABLE configuracoes_integracao ADD COLUMN IF NOT EXISTS vsm_api_observacao TEXT NULL;

UPDATE configuracoes_integracao
SET vsm_api_principal='pedidos-integradora',
    vsm_api_loja=COALESCE(NULLIF(vsm_api_loja,''),'desativado'),
    vsm_waf_agressivo=0,
    sync_receber_pedidos_vsm=0,
    sync_enviar_pedido_tiny=0,
    sync_tiny_enviar_pedido_vsm=1,
    sync_vsm_enviar_nota_tiny=1,
    sync_tiny_enviar_nota_vsm=0,
    sync_vsm_enviar_estoque_tiny=1,
    sync_tiny_enviar_estoque_vsm=0,
    sync_vsm_status_produto_tiny=1,
    sync_tiny_status_produto_vsm=0,
    fluxo_pedido_vsm_receber=0,
    fluxo_pedido_vsm_enviar_tiny=0,
    fluxo_pedido_tiny_enviar_vsm=1,
    fluxo_nfe_vsm_enviar_tiny=1,
    fluxo_nfe_tiny_enviar_vsm=0,
    fluxo_estoque_vsm_enviar_tiny=1,
    fluxo_estoque_tiny_enviar_vsm=0,
    fluxo_produto_status_vsm_enviar_tiny=1,
    fluxo_produto_status_tiny_enviar_vsm=0,
    fluxo_produto_novo_vsm_bloquear=1,
    fluxo_produto_novo_tiny_bloquear=1,
    sync_ordem_envio='pedido_tiny_enviar_vsm,nfe_vsm_enviar_tiny,estoque_vsm_enviar_tiny,produto_status_vsm_enviar_tiny,produto_novo_vsm_bloquear,produto_novo_tiny_bloquear'
WHERE id=1;
