# Relatório V104.7 — Análise completa de banco e código

## Objetivo

Analisar o sistema completo para reduzir erros de banco de dados, organizar o código e deixar o schema mais seguro para produção.

## Resultado executivo

A V104.7 corrige o principal risco encontrado nas versões anteriores: desalinhamento entre **SQL oficial**, **SQL modular**, **mapa de tabelas do `Database`** e **código que cria tabelas/colunas em runtime**.

Agora o sistema possui:

- SQL oficial limpo.
- 107 tabelas únicas.
- 0 duplicidade de `CREATE TABLE` no SQL oficial.
- 0 uso de `ALTER TABLE ADD COLUMN IF NOT EXISTS` no SQL oficial.
- 0 tabela do SQL oficial fora do mapa `Database::tableModule()`.
- 0 tabela do mapa fora do SQL oficial.
- SchemaGuard idempotente para corrigir tabelas/colunas críticas sem derrubar a tela.

## Métricas analisadas

- Arquivos PHP analisados: **293**
- Linhas PHP: **19.634**
- Services: **124**
- Controllers: **30**
- Views: **104**
- Funções/métodos: **1.033**
- Tabelas no SQL oficial: **107**
- Módulos de banco: **8**

Distribuição das tabelas por módulo:

| Módulo | Tabelas |
|---|---:|
| core | 33 |
| pedidos | 12 |
| produtos | 11 |
| estoque | 16 |
| fiscal | 7 |
| fila | 7 |
| observabilidade | 19 |
| backups | 2 |

## Problemas encontrados e corrigidos

### 1. SQL oficial desatualizado em relação aos módulos

Algumas tabelas existiam em `database/modules/*.sql`, mas não estavam no `install.sql` consolidado. Isso podia causar erro em instalação manual ou em validação de banco.

Tabelas corrigidas no SQL oficial:

- `token_vault`
- `security_events`
- `ips_bloqueados`
- `rate_limit_hits`
- `audit_daily_signatures`

### 2. Tabelas runtime sem mapa no `Database`

Algumas tabelas existiam no SQL consolidado, mas não estavam em `Database::tableModule()`. Isso podia fazer o sistema procurar a tabela no banco errado, principalmente com banco modular.

Tabelas adicionadas ao mapa:

- `orquestracao_fluxos_historico`
- `production_go_live_checks`
- `security_hardening_checks`
- `system_build_info`
- `tiny_v2_homologacao_testes`
- `tiny_v3_homologacao_testes`

### 3. `ADD COLUMN IF NOT EXISTS` no SQL oficial

O `install.sql` ainda continha `ALTER TABLE ... ADD COLUMN IF NOT EXISTS`. Isso pode falhar em algumas versões de MySQL, dependendo da hospedagem.

Correção aplicada:

- `database/install.sql` foi regenerado a partir dos módulos oficiais.
- `database/install_final_v104_7.sql` foi criado como schema limpo.
- As colunas novas ficam dentro do `CREATE TABLE` ou são corrigidas pelo `SchemaGuard`.

### 4. Risco de coluna duplicada em telas de manutenção

A tela de orquestração já tinha sido corrigida na V104.6, mas a V104.7 generaliza a solução.

Correção aplicada:

- Criado `Database::addColumnIfMissing()`.
- Criado `Database::addIndexIfMissing()`.
- Criado `Database::columnExistsOn()`.
- Criado `Database::tableExistsOn()`.
- `OrquestracaoController` agora usa helper central.
- `DashboardController` agora usa helper central.

### 5. Validação de banco apenas apontava erro, mas não reparava

A validação informava tabela/coluna ausente, mas não tentava reparar automaticamente.

Correção aplicada:

- Criado `app/Services/DatabaseSchemaGuardService.php`.
- `DatabaseValidationService` agora executa o SchemaGuard antes dos checks.
- O reparo é idempotente e não destrutivo.
- Ele executa apenas `CREATE TABLE`, `ADD COLUMN` e `ADD INDEX` quando necessário.

### 6. SQL legado podia confundir

Havia vários arquivos `install_final_vXX.sql` antigos na raiz de `database/`.

Correção aplicada:

- SQLs antigos movidos para `database/legacy/`.
- SQL oficial atual mantido em:
  - `database/install.sql`
  - `database/install_final_v104_7.sql`
- Criado `database/README-SQL-V104.7.md`.

## Arquivos principais alterados

- `app/Core/Database.php`
- `app/Services/DatabaseSchemaGuardService.php`
- `app/Services/DatabaseValidationService.php`
- `app/Services/SafeDb.php`
- `app/Controllers/OrquestracaoController.php`
- `app/Controllers/DashboardController.php`
- `app/Controllers/MigrationController.php`
- `app/Controllers/DatabaseMaintenanceController.php`
- `app/Services/UniversalUpgradeService.php`
- `database/modules/core.sql`
- `database/install.sql`
- `database/install_final_v104_7.sql`
- `database/update_v104_7_schema_guard.sql`
- `database/schema_inventory_v104_7.json`
- `database/README-SQL-V104.7.md`

## Como corrigir banco já instalado

Depois de subir os arquivos da V104.7, acesse:

```text
Central Técnica > Validar Banco
```

Essa tela agora executa o `DatabaseSchemaGuardService` antes de validar. Ele cria tabelas/colunas críticas ausentes sem derrubar o sistema.

## Como instalar banco novo

Use preferencialmente:

```text
public/install.php
```

Ou manualmente:

```text
database/install_final_v104_7.sql
```

## Validações realizadas

- `php -l` em todos os arquivos PHP: **0 erro**.
- SQL oficial com 107 tabelas únicas: **OK**.
- Duplicidade de `CREATE TABLE`: **0**.
- `ADD COLUMN IF NOT EXISTS` no SQL oficial: **0**.
- Tabela no SQL sem mapa no `Database`: **0**.
- Tabela no mapa sem SQL oficial: **0**.
- JavaScript inline nas views: **0**.
- Referências diretas `Database::forTable/tableExists/columnExists` fora do mapa: **0**.

## Recomendação de uso

Para produção, use este fluxo:

1. Subir a V104.7.
2. Acessar o painel.
3. Rodar `Central Técnica > Validar Banco`.
4. Rodar `Central Técnica > Health de Módulos`.
5. Testar `Integrações > Escolher fluxos ativos`.
6. Gerar backup.
7. Testar Tiny/VSM em homologação antes de produção.

## Observação

Não foi feito teste real no MySQL de produção, Tiny ou VSM porque este ambiente não possui suas credenciais nem acesso ao banco do servidor. A validação feita aqui foi estrutural, estática, de sintaxe, organização, SQL e compatibilidade de mapa de tabelas.
