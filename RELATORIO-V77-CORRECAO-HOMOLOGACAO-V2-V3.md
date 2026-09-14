# RELATÓRIO V77 — Correção Homologação V2/V3

## Correções aplicadas

### 1. Erro fatal Tiny V3
Corrigido o erro:

`Call to undefined method TinyV3TokenService::statusResumo()`

Foi adicionado o método `TinyV3TokenService::statusResumo()` para compatibilidade com `TinyV3HomologationService`.

### 2. Homologação Completa Tiny V2
A execução da homologação V2 foi ampliada para incluir de forma mais clara:

- autenticação Token/URL;
- produto por SKU;
- estoque Tiny V2 x VSM;
- pedido de teste;
- XML/NF-e fiscal por pedido ou chave NF-e;
- evidências com Trace ID.

### 3. Estoque Tiny V2
Foi criado o método `TinyV2Service::consultarEstoquePorSku()`.

Fluxo:

1. consulta produto por SKU;
2. tenta obter ID do produto;
3. consulta `produto.obter.estoque.php` quando houver ID;
4. usa fallback do retorno de produto quando o estoque já vier em `produtos.pesquisa.php`.

### 4. XML/NF-e na tela V2
A tela `Tiny V2 - Homologação` agora possui campo específico:

- `Chave NF-e / XML`.

A validação fiscal local procura XML/NF-e por:

- pedido informado;
- número da NF-e;
- chave NF-e de 44 dígitos.

### 5. Melhorias visuais na tela
A tela V2 agora mostra blocos separados para:

- autenticação;
- estoque Tiny V2 x VSM;
- XML/NF-e fiscal;
- histórico;
- evidências completas.

## Validação técnica

Todos os arquivos PHP foram validados com `php -l` sem erro de sintaxe.

## Observação

A homologação V2 só será aprovada quando todas as etapas retornarem OK:

- auth;
- produto;
- estoque;
- pedido;
- fiscal/XML.
