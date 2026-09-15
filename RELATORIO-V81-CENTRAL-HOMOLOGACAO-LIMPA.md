# RELATÓRIO V81 - Central de Homologação Limpa

## Objetivo
Alinhar a homologação ao fluxo real VSM → HUB → Tiny, mantendo no Tiny apenas o que pertence à API Tiny: autenticação/OAuth, produto, estoque, pedido e retorno da API.

## Alterações aplicadas

- Removido XML/NF-e dos cards Tiny V2 e Tiny V3 na Central de Homologação.
- Mantido XML/NF-e em card separado: VSM → HUB → Tiny.
- Ajustada descrição da regra operacional para deixar claro que a VSM é a origem fiscal.
- Removidas funções mortas `validarFiscalLocal()` de `TinyV2HomologationService` e `TinyV3HomologationService`.
- Criado `XmlNfeHomologationService.php` para registrar a responsabilidade conceitual do módulo XML/NF-e.
- Corrigido `database/install_final_v72.sql` removendo comandos `SOURCE` para arquivos legados inexistentes.
- Criado `database/install_final_v81.sql` como schema consolidado atualizado.
- Atualizado `UniversalUpgradeService` para apontar instalações novas para `install_final_v81.sql`.

## Estrutura correta a partir da V81

### Tiny V2
- Token/API Key
- Produto
- Estoque Tiny x VSM
- Pedido
- Retorno Tiny

### Tiny V3
- OAuth
- Produto
- Estoque Tiny x VSM
- Pedido
- Retorno Tiny

### XML/NF-e
- Recebimento da VSM
- Validação de XML/chave
- Vinculação com pedido
- Auditoria
- Reenvio/retorno ao Tiny quando aplicável

## Melhorias futuras sugeridas

1. Criar `CentralHomologacaoController` separado do `DashboardController`.
2. Criar `TinyHomologacaoController` para V2/V3.
3. Criar `XmlNfeController` dedicado se o módulo XML/NF-e crescer.
4. Gerar relatório PDF de evidência por Trace ID.
5. Criar monitor de divergência de estoque com fila própria.
6. Criar teste agendado diário de homologação leve.
7. Adicionar botão “Copiar Trace ID” no histórico.
8. Adicionar badge claro de ambiente: produção, homologação ou simulado.

## Validação
Todos os arquivos PHP foram validados com `php -l`.
