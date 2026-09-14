# V73 - Homologação Tiny V2 Profissional

## O que foi aplicado

- Criada a tela `Tiny V2 - Homologação` em `views/tiny_v2_homologacao.php`.
- Criada a rota `index.php?page=tiny-v2-homologacao`.
- Criada a ação `index.php?page=tiny-v2-homologacao-executar`.
- Criado o serviço `TinyV2HomologationService.php`.
- Criada a tabela `tiny_v2_homologacao_testes` no `install_final_v72.sql`.
- Adicionado item no menu lateral: `Homologação Tiny V2`.
- Adicionado link rápido na tela `Tiny V2 e Tiny V3 - Ambientes`.

## Melhorias da tela

1. Status geral com progresso.
2. Validação visual de Token/API Key e URL Tiny V2.
3. Campo de SKU de homologação.
4. Campo de pedido de teste Tiny V2.
5. Botão `Executar Homologação Completa V2`.
6. Consulta de produto no Tiny V2 pelo SKU.
7. Consulta de estoque na VSM.
8. Comparação Tiny V2 x VSM.
9. Validação local de NF-e/XML vinculada ao pedido de teste.
10. Histórico dos últimos testes.
11. Evidência técnica com Trace ID e JSON mascarado.

## Observação importante

A homologação V2 agora deixa de ser apenas teste de API/token. Ela valida o fluxo operacional mínimo:

Tiny V2 → Produto → Estoque → VSM → Pedido → Fiscal/XML → Evidência.

A etapa fiscal depende de registros locais em `notas_fiscais` e `nfe_xml` vinculados ao pedido informado.
