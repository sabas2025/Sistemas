# RELATÓRIO V104.1 — Correção Backup SafeDb

## Problema corrigido

Ao gerar backup pelo painel, o sistema podia registrar erro fatal:

```text
Tabela não permitida ou inexistente: audit_daily_signatures
Arquivo: app/Services/SafeDb.php
Rota: backup
```

## Causa raiz

`BackupService::buildSql()` listava as tabelas reais do banco usando `SHOW TABLES`, mas depois validava cada tabela com `SafeDb::assertTable()`.

`SafeDb::assertTable()` consulta o mapa modular em `Database::tableModule()`. Em ambientes com banco único, banco parcialmente modular ou tabelas técnicas criadas fora do módulo esperado, uma tabela que existe no banco atual podia ser recusada porque o mapa mandava consultar outro módulo.

Exemplo:

- `SHOW TABLES` encontrou `audit_daily_signatures` no banco usado pelo backup.
- `Database::tableModule('audit_daily_signatures')` apontou para `observabilidade`.
- Se a tabela não existia no banco de `observabilidade`, o backup parava com erro fatal.

## Correção aplicada

### 1. `BackupService::buildSql()`

Agora o backup:

- usa a mesma conexão PDO em todo o dump;
- valida somente o identificador da tabela com `SafeDb::assertIdentifier()`;
- não usa mais `SafeDb::assertTable()` dentro da geração do backup;
- valida também nomes de colunas antes de montar os `INSERTs`;
- evita `fetchAll()` para os dados das tabelas, processando linha por linha.

Isso mantém proteção contra identifier injection, mas não quebra quando o banco real não bate 100% com o mapa modular.

### 2. `BackupController`

A listagem, download e exclusão de backups agora usam:

```php
Database::forTable('backups_banco')
```

em vez de forçar:

```php
Database::connection('core')
```

Assim o controller respeita o módulo correto da tabela `backups_banco`.

### 3. Rotas antigas no `DashboardController`

Os métodos antigos de backup dentro do `DashboardController` também foram ajustados para usar `Database::forTable('backups_banco')`.

### 4. Migração auxiliar

Criado arquivo:

```text
database/update_v104_1_backup_safe_db.sql
```

Ele garante a criação de:

- `audit_daily_signatures`
- `backups_banco`

caso algum ambiente tenha ficado com migração incompleta.

## Arquivos alterados

- `app/Services/BackupService.php`
- `app/Controllers/BackupController.php`
- `app/Controllers/DashboardController.php`
- `database/update_v104_1_backup_safe_db.sql`

## Validação

- 288 arquivos PHP validados com `php -l`.
- 0 erros de sintaxe encontrados.
- Removida chamada real a `SafeDb::assertTable()` no fluxo de geração do backup.

## Observação de produção

Depois de subir esta versão, gere um backup novamente em:

```text
Central Técnica > Backups > Gerar Backup
```

Se o banco de produção estiver com módulos separados, confirme também em `config/config.php` se `db_modules` aponta para os bancos corretos.
