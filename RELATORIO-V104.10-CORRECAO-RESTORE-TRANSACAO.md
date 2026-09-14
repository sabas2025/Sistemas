# RELATÓRIO V104.10 — Correção Restore Backup: There is no active transaction

## Problema informado

Ao restaurar backup pelo painel, o sistema retornava:

```text
Falha ao restaurar backup: There is no active transaction
```

## Causa técnica

O restore executava o SQL inteiro dentro de uma transação PDO:

```php
$pdo->beginTransaction();
...
$pdo->commit();
```

Porém backups completos contêm comandos estruturais como:

```sql
DROP TABLE IF EXISTS ...;
CREATE TABLE ...;
ALTER TABLE ...;
LOCK TABLES ...;
UNLOCK TABLES ...;
```

No MySQL/MariaDB, comandos DDL como `DROP`, `CREATE` e `ALTER` fazem **COMMIT implícito**. Assim, a transação iniciada pelo PDO pode ser encerrada automaticamente pelo banco antes do `commit()` final. Quando o PHP chama `commit()` ou `rollBack()` depois disso, o PDO pode lançar:

```text
There is no active transaction
```

## Correção aplicada

Arquivo principal alterado:

```text
app/Services/BackupService.php
```

Melhorias:

- Criado parser separado de statements SQL:
  - `splitSqlStatements()`
- Criada detecção de comandos que encerram transação implicitamente:
  - `containsImplicitCommitStatement()`
- Restore completo com `DROP/CREATE/ALTER` agora roda **sem transação global**.
- Restore de dados simples, sem DDL, ainda pode usar transação.
- `commit()` só é chamado se a transação ainda estiver ativa.
- `rollBack()` só é chamado se a transação ainda estiver ativa.
- Em caso de erro, o sistema tenta religar:
  - `SET FOREIGN_KEY_CHECKS=1`
- O erro original do restore é preservado, sem ser substituído por erro de rollback.

## Correções complementares

Também foram protegidos pontos de rollback em:

```text
app/Controllers/DashboardController.php
app/Services/QueueService.php
```

Agora eles verificam `inTransaction()` antes de chamar `rollBack()`.

## Segurança mantida

A correção não remove as validações de restore. Continuam ativos:

- assinatura/HMAC do backup;
- validação estrutural do SQL;
- bloqueio de comandos perigosos;
- limite de tamanho do SQL;
- permissão `backup.restaurar`;
- confirmação textual `RESTAURAR`;
- backup de segurança antes de restaurar.

## Validação feita

- 297 arquivos PHP validados com `php -l`.
- 0 erros de sintaxe PHP.
- ZIP testado com sucesso.

## Teste recomendado no servidor

Após subir esta versão:

1. Gerar um backup novo pelo painel.
2. Importar o mesmo backup em ambiente de teste.
3. Restaurar digitando `RESTAURAR`.
4. Abrir:
   - Central Técnica > Validar Banco
   - Central Técnica > Mapa do Banco
   - Central Técnica > Health de Módulos
   - Central Técnica > Backups

## Observação

Não foi executado teste real no MySQL de produção porque o ambiente de geração não possui acesso às credenciais do servidor.
