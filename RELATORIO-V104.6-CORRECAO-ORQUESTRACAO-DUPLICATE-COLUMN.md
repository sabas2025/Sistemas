# RELATÓRIO V104.6 — Correção Orquestração Duplicate Column

## Erro informado

Ao acessar:

`index.php?page=orquestracao-integracoes`

O sistema registrava erro fatal:

`SQLSTATE[42S21]: Column already exists: 1060 Duplicate column name 'sync_receber_pedidos_vsm'`

Arquivo/linha informados:

`app/Controllers/OrquestracaoController.php:99`

## Causa raiz

A tela de orquestração chama `ensureSchema()` para garantir que as colunas de fluxo existam na tabela `configuracoes_integracao`.

Na versão anterior, a função `safeAddColumn()` dependia apenas de `Database::columnExists()` antes de executar:

`ALTER TABLE configuracoes_integracao ADD COLUMN ...`

Em alguns ambientes de hospedagem/banco modular, a verificação retornava falso mesmo quando a coluna já existia. Com isso, o sistema tentava adicionar novamente a coluna e o MySQL retornava erro `1060 Duplicate column name`.

## Correção aplicada

### 1. OrquestracaoController

Arquivo alterado:

`app/Controllers/OrquestracaoController.php`

Melhorias:

- `ensureSchema()` agora garante a existência da tabela `configuracoes_integracao` antes de adicionar colunas.
- `safeAddColumn()` ficou idempotente.
- Valida identificadores SQL antes de montar `ALTER TABLE`.
- Usa `INFORMATION_SCHEMA.COLUMNS` com `DATABASE()` para verificar a coluna na conexão correta.
- Usa fallback com `SHOW COLUMNS`.
- Trata `SQLSTATE 42S21`, erro `1060` e mensagem `duplicate column` como coluna já existente.
- Não derruba mais a tela se a coluna já existir.

### 2. DashboardController

Arquivo alterado:

`app/Controllers/DashboardController.php`

Melhorias:

- `safeAddColumn()` legado também ficou idempotente.
- Usa `Database::forTable($table)` para respeitar banco modular.
- Trata duplicidade de coluna como aviso, não erro fatal.

### 3. Database

Arquivo alterado:

`app/Core/Database.php`

Melhorias:

- `Database::columnExists()` agora usa primeiro `INFORMATION_SCHEMA.COLUMNS`.
- Usa `DATABASE()` para verificar na conexão atual.
- Mantém fallback com `SHOW COLUMNS`.
- Valida nome da tabela e coluna para evitar identificador inválido.

## Resultado esperado

Após subir a V104.6, acessar:

`Integrações > Escolher fluxos ativos`

não deve mais gerar erro fatal por coluna duplicada.

Quando a coluna já existir, o sistema registra internamente:

`IGNORADO: configuracoes_integracao.sync_receber_pedidos_vsm já existe.`

## Arquivo SQL auxiliar

Criado:

`database/update_v104_6_orquestracao_colunas_idempotentes.sql`

Ele não precisa criar colunas. Serve apenas para registrar a migração quando a tabela `schema_migrations` existir.

## Validação feita

- Sintaxe PHP validada nos arquivos alterados.
- Sintaxe PHP validada no pacote completo.
- ZIP testado com sucesso.

## Observação

Não foi executado teste real no MySQL de produção porque este ambiente não possui acesso às credenciais do servidor.
