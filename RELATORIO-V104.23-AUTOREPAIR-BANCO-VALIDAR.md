# RELATÓRIO V104.23 — AutoRepair forte para Validar Banco / SchemaGuard

## Problema corrigido

Na tela **Integrações / Central Técnica > Validar Banco de Dados**, o sistema podia listar praticamente todas as tabelas como ausentes, mesmo após versões com banco único e SchemaGuard.

Exemplo do erro real:

- `Tabela usuarios — ERRO — Tabela não existe no módulo resolvido core`
- `Tabela pedidos_integracao — ERRO — Tabela não existe no módulo resolvido core`
- `Tabela backups_banco — ERRO — Tabela não existe no módulo resolvido core`
- dezenas de colunas críticas ausentes.

Esse padrão indica instalação parcial, banco vazio, restore feito em outro banco, permissão incompleta ou SchemaGuard sem execução forte do SQL oficial.

## Correção aplicada

### 1. Criado AutoRepair forte

Novo arquivo:

```text
app/Services/DatabaseAutoRepairService.php
```

Ele executa os SQLs oficiais idempotentes diretamente no banco principal conectado:

- `database/modules/*.sql`
- `database/repair_current.sql`

Com proteção para hospedagem compartilhada:

- ignora `CREATE DATABASE`;
- ignora `USE banco`;
- não depende de permissão administrativa;
- executa `CREATE TABLE IF NOT EXISTS`;
- executa `INSERT IGNORE` e inserts idempotentes;
- registra erros reais de permissão MySQL.

### 2. Validar Banco agora tenta reparar antes de validar

Arquivo alterado:

```text
app/Services/DatabaseValidationService.php
```

Agora a sequência é:

```text
AutoRepair SQL oficial
↓
SchemaGuard
↓
Validar tabelas
↓
Validar colunas críticas
↓
Validar índices
```

### 3. Mapa do Banco também tenta reparar antes de mapear

Arquivo alterado:

```text
app/Services/DatabaseMapService.php
```

### 4. Versão centralizada atualizada

Arquivo alterado:

```text
app/Services/SystemVersionService.php
```

Nova versão:

```text
V104.23 — AutoRepair forte do banco e SchemaGuard antes da validação
```

### 5. SQLs atualizados

Arquivos criados/atualizados:

```text
database/update_v104_23_autorepair_banco_validar.sql
database/install_final_v104_23.sql
database/install.sql
database/install_final_current.sql
database/repair_current.sql
```

## Como testar depois de subir

1. Subir todos os arquivos da V104.23.
2. Apagar cache se existir:

```text
storage/cache/classmap.php
```

3. Entrar no painel.
4. Rodar:

```text
Central Técnica > Validar Banco
Central Técnica > Mapa do Banco
```

## Se ainda aparecer erro em massa

Se ainda aparecerem todas as tabelas ausentes, o problema não será mais o código: será uma destas causas externas:

1. O `config/config.php` aponta para outro banco vazio.
2. O usuário MySQL não tem permissão `CREATE`, `ALTER` ou `INSERT`.
3. O banco selecionado no cPanel/phpMyAdmin não é o mesmo do `config.php`.
4. O deploy foi parcial e arquivos antigos ficaram no servidor.

Nesse caso, conferir no `config/config.php`:

```php
'db_storage_mode' => 'single',
'db_modular_strict' => false,
'db_single_database_rescue' => true,
```

E confirmar banco, usuário e senha no cPanel.
