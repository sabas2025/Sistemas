# RELATÓRIO V78 - Correção Homologação Tiny V3 e Fiscal

## Problema informado
Trace ID: TRC-20260619-214632-3548305C

Falhas encontradas no retorno da Homologação Tiny V3:
- OAuth aparecia como OK, mas o serviço real retornava `TINY_V3_TOKEN_MISSING`.
- Produto, estoque e pedido falhavam por token V3 não utilizável.
- VSM retornava HTTP 403 na consulta de estoque.
- Fiscal falhava com SQL: coluna `n.pedido_id` inexistente em `notas_fiscais`.

## Correções aplicadas

### 1. OAuth Tiny V3 mais realista
A etapa OAuth agora só fica OK quando existe access token realmente utilizável.
Antes a tela considerava OK apenas por existir registro/token manual.

Novo campo no resultado:
- `access_token_utilizavel`
- `acao_recomendada`

### 2. Produto/Pedido Tiny V3 com pré-validação de token
Se o token não estiver utilizável, produto e pedido não tentam chamada confusa na API.
Retornam erro claro:
- `TINY_V3_TOKEN_NOT_USABLE`

### 3. Estoque Tiny V3 x VSM com ação recomendada
A etapa de estoque agora identifica melhor:
- token Tiny V3 ausente/não utilizável;
- SKU não encontrado no Tiny V3;
- VSM HTTP 403;
- divergência de saldo Tiny x VSM.

### 4. Fiscal V3 corrigido para banco variável
Removida dependência fixa da coluna `notas_fiscais.pedido_id`.
Agora o sistema procura dinamicamente colunas possíveis:
- `pedido_id`
- `pedido_tiny_id`
- `pedido_numero`
- `numero_pedido`
- `id_pedido`
- `pedido`
- `numero`
- `numero_nfe`
- `chave_acesso`
- `chave_nfe`
- `chave`

Se nenhuma existir, o sistema retorna mensagem orientativa em vez de erro fatal SQL.

### 5. Fiscal V2 recebeu a mesma proteção
A Homologação Tiny V2 também foi protegida contra erro SQL por coluna fiscal ausente.

## O que ainda precisa ser corrigido na configuração real

Pelo retorno enviado, ainda precisa conferir no painel:

1. Tiny V3
   - reconectar OAuth no ambiente selecionado;
   - confirmar se o ambiente está correto: homologação ou produção;
   - confirmar chave de criptografia igual à usada quando o token foi salvo;
   - se estiver em produção, token manual não deve ser usado como fallback.

2. SKU
   - SKU `VSMEST092955` precisa existir exatamente no Tiny V3.

3. VSM
   - HTTP 403 indica acesso negado;
   - conferir token/chave VSM, endpoint e permissão do usuário no estoque.

4. Fiscal
   - conferir estrutura da tabela `notas_fiscais`;
   - se possível, padronizar vínculo fiscal com coluna `pedido_tiny_id` ou `numero_pedido`.

## Arquivos alterados
- `app/Services/TinyV3HomologationService.php`
- `app/Services/TinyV2HomologationService.php`

## Validação
- Todos os arquivos PHP passaram no `php -l`.
- ZIP final testado.
