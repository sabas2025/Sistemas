# V104.40 — Correção da tela Saúde VSM

## Causa
O controller atribuía o retorno de `VsmEndpointService::health()` à variável `$rows`, enquanto a view utilizava `$health`. A variável inexistente era enviada a `array_column()`, causando TypeError.

## Correções
- Padronização da variável `$health` no controller e na view.
- Validação de tipo do retorno do serviço.
- Fallback seguro para lista vazia.
- Auditoria da exceção no carregamento da saúde VSM.
- View tolerante a registros incompletos.
- Estado vazio profissional quando não há endpoints.
- Ajustes responsivos nos cards e atributos `data-label` da tabela.

## Compatibilidade
Não altera banco, endpoints, regras de integração nem contratos Tiny/VSM.
