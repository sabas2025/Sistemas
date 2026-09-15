# SQL oficial V104.8

## Arquivos oficiais para usar

- `database/install.sql`
- `database/install_final_v104_8.sql`
- `database/modules/*.sql`
- `database/update_v104_7_schema_guard.sql`

## O que mudou

A V104.8 consolidou o banco para evitar erros recorrentes de produção:

- `install.sql` foi regenerado a partir dos módulos oficiais.
- O SQL consolidado tem 107 tabelas únicas e 0 duplicidade de `CREATE TABLE`.
- O SQL oficial não depende de `ALTER TABLE ADD COLUMN IF NOT EXISTS`, que pode falhar em algumas versões de MySQL.
- As tabelas runtime críticas agora estão alinhadas com o mapa `Database::tableModule()`.
- O `SchemaGuard` repara colunas/tabelas críticas sem derrubar a tela.

## Banco novo

Use `public/install.php` ou execute `database/install_final_v104_8.sql` em banco novo.

## Banco existente

Depois de subir os arquivos, acesse:

`Central Técnica > Validar Banco`

A validação executa o `DatabaseSchemaGuardService` e cria somente o que estiver ausente.

## Observação

Não execute SQL legado `install_final_v82.sql`, `install_final_v85.sql`, etc. Eles foram mantidos apenas por rastreabilidade histórica.
