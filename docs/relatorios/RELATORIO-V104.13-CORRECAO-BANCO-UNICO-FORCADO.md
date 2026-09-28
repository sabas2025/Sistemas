# RELATÓRIO V104.13 — Correção Banco Único Forçado / SchemaGuard

## Problema identificado

O relatório real do servidor mostrou erro em massa para praticamente todas as tabelas:

- `usuarios`
- `configuracoes_integracao`
- `pedidos_integracao`
- `produto_pendencias`
- `estoque_movimentos`
- `fila_integracao`
- `logs_integracao`
- `backups`
- e demais tabelas oficiais.

O padrão do erro indicava que o sistema ainda estava tentando resolver tabelas por módulos (`core`, `pedidos`, `produtos`, `estoque`, `fila`, `observabilidade`, `backups`), mas a hospedagem estava trabalhando como banco único ou com módulos vazios.

## Causa raiz

Na V104.12 o sistema já tinha `db_storage_mode='single'`, mas a função `Database::moduleConfig()` ainda podia considerar `db_modules` antigos quando o `config.php` tinha bancos separados.

Com isso, mesmo em hospedagem compartilhada, o sistema podia tentar abrir bancos como:

- `*_pedidos`
- `*_produtos`
- `*_estoque`
- `*_fila`
- `*_observabilidade`
- `*_backups`

Se esses bancos existissem vazios ou fossem diferentes do banco principal, o painel mostrava erro em massa.

## Correção aplicada

### 1. Banco único agora é respeitado de verdade

Arquivo alterado:

- `app/Core/Database.php`

Quando `db_storage_mode='single'`, todos os módulos usam obrigatoriamente o banco principal (`db.name`).

O campo `db_modules` fica apenas para compatibilidade/documentação e não define conexão em modo banco único.

### 2. Modular antigo agora entra em rescue automático

Se o `config.php` antigo estiver assim:

```php
'db_storage_mode' => 'modular'
```

mas não tiver:

```php
'db_modular_strict' => true
```

então a V104.13 trata como banco único para evitar erro em massa em hospedagem compartilhada.

Para usar VPS com bancos separados de verdade, configurar explicitamente:

```php
'db_storage_mode' => 'modular',
'db_modular_strict' => true,
```

### 3. Resolução de tabela corrigida

Em banco único:

- `usuarios` resolve para `core`
- `pedidos_integracao` resolve para `core`
- `produtos_vsm` resolve para `core`
- `estoque_movimentos` resolve para `core`
- `fila_integracao` resolve para `core`
- `logs_integracao` resolve para `core`
- `backups_banco` resolve para `core`

Isso evita procurar tabelas em bancos modulares vazios.

### 4. SQL emergencial criado

Criado arquivo:

- `database/repair_v104_13_banco_unico.sql`

Ele cria as 107 tabelas oficiais no banco principal, sem apagar dados existentes.

Use apenas se o painel **Validar Banco** não conseguir criar as tabelas automaticamente.

### 5. SQL oficial atualizado

Criado:

- `database/install_final_v104_13.sql`

## Como corrigir no servidor

1. Subir a V104.13 no servidor.
2. Conferir o `config/config.php`.
3. Para hospedagem compartilhada, deixar:

```php
'db_storage_mode' => 'single',
'db_modular_strict' => false,
'db_single_database_rescue' => true,
```

4. Todos os módulos devem apontar para o mesmo banco, ou podem ficar como estão porque a V104.13 ignora `db_modules` em modo single.
5. Entrar no painel e executar:

```text
Central Técnica > Validar Banco
Central Técnica > Mapa do Banco
Central Técnica > Health de Módulos
```

## Se ainda aparecer erro de tabela ausente

Executar no phpMyAdmin, dentro do banco principal:

```text
database/repair_v104_13_banco_unico.sql
```

Depois voltar no painel e rodar:

```text
Central Técnica > Validar Banco
```

## Validação técnica

- Todos os arquivos PHP foram validados com `php -l`.
- Nenhum erro de sintaxe encontrado.
- O SQL emergencial contém 107 `CREATE TABLE IF NOT EXISTS`.
- O pacote ZIP foi testado com sucesso.

