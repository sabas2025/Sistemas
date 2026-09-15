# RELATÓRIO V104.12 — Correção Banco Único/Modular + SchemaGuard Rescue

## Problema recebido

Na validação do banco apareceram erros em massa como:

- `Tabela usuarios ERRO Tabela não existe no módulo core`
- `Tabela pedidos_integracao ERRO Tabela não existe no módulo pedidos`
- `Tabela logs_integracao ERRO Tabela não existe no módulo observabilidade`
- várias colunas críticas ausentes.

Quando praticamente todas as tabelas aparecem como ausentes, o problema normalmente não é uma tabela específica. É desalinhamento entre:

1. banco onde o sistema está procurando as tabelas;
2. banco onde a instalação/restauração criou as tabelas;
3. modo modular versus banco único em hospedagem compartilhada.

## Causa provável

O sistema estava usando o mapa modular oficial:

- core;
- pedidos;
- produtos;
- estoque;
- fiscal;
- fila;
- observabilidade;
- backups.

Se o `config.php` aponta para bancos modulares vazios, mas a hospedagem usa banco único, o painel acusa todas as tabelas como inexistentes no módulo correto.

## Correções aplicadas

### 1. Resolução inteligente de tabela

Arquivo alterado:

- `app/Core/Database.php`

Foi criado:

- `Database::resolveTableModule()`
- `Database::officialTableModule()`
- `Database::clearTableResolutionCache()`
- `Database::singleDatabaseRescueEnabled()`

Agora, antes de assumir que uma tabela está ausente no módulo oficial, o sistema:

1. procura no módulo oficial;
2. procura em outros módulos configurados;
3. se não encontrar e o modo rescue estiver ativo, usa o banco `core` como banco único de recuperação.

Isso evita falso erro em massa quando a base foi instalada/restaurada em banco único.

### 2. SchemaGuard com rescue banco único/modular

Arquivo alterado:

- `app/Services/DatabaseSchemaGuardService.php`

Agora o SchemaGuard:

- limpa cache de resolução antes/depois do reparo;
- cria tabelas oficiais no banco resolvido;
- informa banco/módulo alvo de cada tabela;
- registra migração `v104_12_schema_guard_rescue`.

### 3. Validar Banco melhorado

Arquivo alterado:

- `app/Services/DatabaseValidationService.php`

Agora a validação:

- executa SchemaGuard V104.12 antes de validar;
- mostra módulo oficial e módulo real usado;
- usa `Database::forTable()` para permissões;
- recomenda V104.12 em vez de updates antigos.

### 4. Mapa do Banco com reparo preventivo

Arquivo alterado:

- `app/Services/DatabaseMapService.php`

Agora o Mapa do Banco tenta executar o SchemaGuard antes de listar erros, evitando mostrar 100+ erros sem tentar reparar.

### 5. Instalador atualizado

Arquivo alterado:

- `public/install.php`

Agora o instalador salva:

- `db_storage_mode = single|modular`
- `db_single_database_rescue = true|false`

Em hospedagem compartilhada, o recomendado é:

- `db_storage_mode = single`
- todos os módulos apontando para o mesmo banco.

### 6. Config padrão atualizado

Arquivo alterado:

- `config/config.php`

O padrão agora deixa claro:

- banco único é recomendado em cPanel/DirectAdmin;
- modular é apenas para VPS/servidor com bancos separados.

### 7. SQL auxiliar criado

Novo arquivo:

- `database/update_v104_12_schema_rescue.sql`

Novo SQL oficial:

- `database/install_final_v104_12.sql`

## Validação feita

- 298 arquivos PHP validados com `php -l`.
- 0 erros de sintaxe.
- ZIP testado com sucesso.

## Como testar no servidor

Depois de subir a V104.12:

1. Acesse `Central Técnica > Validar Banco`.
2. Aguarde o SchemaGuard criar/reparar as tabelas.
3. Acesse `Central Técnica > Mapa do Banco`.
4. Acesse `Central Técnica > Health de Módulos`.

Se ainda aparecerem 100+ tabelas ausentes, confira `config/config.php`:

- em hospedagem compartilhada, use `db_storage_mode => 'single'`;
- todos os itens em `db_modules` devem apontar para o mesmo `name` do banco criado no cPanel.

## Observação importante

Se o banco estiver totalmente vazio, o SchemaGuard cria as tabelas, mas não consegue recriar a senha original do administrador. Para instalação limpa, o mais seguro é usar `public/install.php` novamente com banco único e senha forte.
