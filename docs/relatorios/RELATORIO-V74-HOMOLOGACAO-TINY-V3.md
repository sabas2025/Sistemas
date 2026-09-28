# RELATÓRIO V74 - HOMOLOGAÇÃO TINY V3

## Aplicado somente nesta etapa

Foi adicionada a tela profissional **Tiny V3 - Homologação**, sem alterar a homologação fiscal completa anterior e sem reaplicar sugestões antigas.

## Arquivos adicionados

- `app/Services/TinyV3HomologationService.php`
- `views/tiny_v3_homologacao.php`
- `RELATORIO-V74-HOMOLOGACAO-TINY-V3.md`

## Arquivos ajustados

- `app/Controllers/DashboardController.php`
- `views/layout_top.php`
- `views/tiny_ambientes.php`

## Recursos da tela

- Status geral com progresso.
- Bloco OAuth Tiny V3: Client ID, Client Secret, Redirect URI, token salvo/manual.
- SKU de homologação.
- Pedido de teste.
- Botão `Executar Homologação Completa V3`.
- Consulta de produto por SKU no Tiny V3.
- Consulta de estoque Tiny V3 e comparação com VSM.
- Validação de pedido de teste Tiny V3.
- Validação fiscal/XML local vinculada ao pedido.
- Histórico dos últimos testes.
- Evidência JSON com Trace ID.
- Registro em auditoria: `tiny.v3.homologacao.executar`.

## Tabela criada automaticamente

- `tiny_v3_homologacao_testes`

A tabela é criada automaticamente pelo serviço quando a tela é aberta/executada.

## Regra operacional

Tiny V3 só deve ser considerado aprovado quando OAuth, produto, estoque, pedido e fiscal/XML estiverem com status OK e evidência por Trace ID.
