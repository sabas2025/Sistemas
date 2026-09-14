# Hub de Integração Enterprise

Documentação operacional limpa.

## Objetivo
Integrar VSM e Tiny com validação, auditoria, filas, estoque, fiscal e pedidos.

## Estoque
A VSM é a fonte real. O HUB consulta a VSM e envia atualizações ao Tiny quando necessário.

## Fiscal
O HUB recebe retorno fiscal da VSM, valida XML/NF-e e envia ao Tiny.

## Pedidos
O HUB guarda cópia do pedido, valida, envia para VSM e registra todo o ciclo.

Desenvolvido por Sabas.
