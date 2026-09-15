# Manual do cliente

## Objetivo do HUB
O HUB centraliza integrações entre ERP, VSM, Tiny, marketplaces, estoque, pedidos e NF-e.

## Fluxo operacional recomendado
1. Pedido nasce no Tiny.
2. HUB recebe e valida o pedido.
3. HUB envia o pedido para VSM.
4. VSM processa e retorna status, estoque ou NF-e.
5. HUB atualiza o Tiny e mantém auditoria com Trace ID.

## Operação diária
- Acompanhar Dashboard, Centro de Operações e Alertas.
- Verificar fila e divergências.
- Conferir logs com Trace ID.
- Executar backup conforme política.
- Usar telas de homologação antes de ativar produção.

## Boas práticas
- Não compartilhar senhas.
- Usar 2FA.
- Não inserir token real em ambiente demo.
- Validar produto, estoque, pedido e fiscal antes de produção.
