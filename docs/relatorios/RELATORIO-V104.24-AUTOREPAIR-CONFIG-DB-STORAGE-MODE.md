# RELATÓRIO V104.24 — AutoRepair, config.php/db_storage_mode e ambiente

## Problema analisado

No painel **Integrações > Validar Banco de Dados**, o AutoRepair executou muitos comandos com sucesso, mas registrou erro em inserts de `configuracoes_integracao`:

```text
SQLSTATE[01000]: Warning: 1265 Data truncated for column 'ambiente' at row 1
```

Em seguida, a validação continuou mostrando várias tabelas como ausentes, incluindo `usuarios`.

## Causa técnica

Foram encontradas duas causas prováveis:

1. Os SQLs oficiais ainda tinham placeholders de instalação, como:
   - `{AMBIENTE}`
   - `{TINY_VERSION}`
   - `{TINY_V2_URL}`
   - `{VSM_URL}`
   - `{WEBHOOK_SECRET}`

   Quando o `install.php` executa os SQLs, esses placeholders são substituídos. Porém, quando o **AutoRepair** executa SQLs diretamente pelo painel, eles podiam ir crus para o MySQL.

2. A coluna `configuracoes_integracao.ambiente` estava como `ENUM('homologacao','producao')`. Quando o valor `{AMBIENTE}` chegava ali, o MySQL/MariaDB gerava o Warning 1265, tratado como exceção pelo PDO.

## Correções aplicadas

### 1. AutoRepair agora substitui placeholders

Arquivo alterado:

```text
app/Services/DatabaseAutoRepairService.php
```

O AutoRepair agora substitui automaticamente:

```text
{AMBIENTE}      => homologacao
{TINY_VERSION}  => v2
{TINY_V2_URL}   => https://api.tiny.com.br/api2
{TINY_V3_URL}   => https://api.tiny.com.br/public-api/v3
{VSM_URL}       => https://conectavenda.homolog.vsm.com.br
{WEBHOOK_SECRET}=> vazio seguro
```

### 2. Ambiente e versão Tiny ficaram mais compatíveis

Nos SQLs atuais, foram ajustadas as colunas:

```sql
ambiente VARCHAR(30) DEFAULT 'homologacao'
tiny_versao VARCHAR(10) DEFAULT 'v2'
tiny_v3_ambiente VARCHAR(30) DEFAULT 'homologacao'
```

Isso evita travamento por `ENUM` quando houver banco antigo, homologação customizada ou AutoRepair fora do instalador.

### 3. Normalização automática de configuração

O AutoRepair agora tenta executar:

```sql
ALTER TABLE configuracoes_integracao MODIFY ambiente VARCHAR(30) DEFAULT 'homologacao';
ALTER TABLE configuracoes_integracao MODIFY tiny_versao VARCHAR(10) DEFAULT 'v2';
ALTER TABLE configuracoes_integracao MODIFY tiny_v3_ambiente VARCHAR(30) DEFAULT 'homologacao';
```

Também corrige valores inválidos:

```sql
ambiente LIKE '{%'
tiny_versao LIKE '{%'
tiny_v3_ambiente LIKE '{%'
```

### 4. Diagnóstico explícito de config.php/db_storage_mode

A validação agora mostra:

```text
db_storage_mode configurado
db_storage_mode efetivo
banco configurado
banco conectado de verdade
quantidade de tabelas no banco conectado
```

Isso ajuda a identificar se o `config.php` aponta para banco errado/vazio.

### 5. Pós-verificação de tabelas essenciais

Após o AutoRepair, o sistema verifica:

```text
usuarios
configuracoes_integracao
schema_migrations
backups_banco
```

Se ainda faltarem, tenta bootstrap direto pelo SQL oficial.

### 6. repair_current.sql ficou mais seguro para phpMyAdmin

O arquivo `database/repair_current.sql` não depende mais dos placeholders de configuração para o seed principal.

## Arquivos alterados

```text
app/Services/DatabaseAutoRepairService.php
app/Services/DatabaseValidationService.php
app/Services/DatabaseConfigDiagnosticService.php
app/Services/SystemVersionService.php
database/install.sql
database/install_final_current.sql
database/repair_current.sql
database/modules/core.sql
database/install_final_v104_24.sql
database/update_v104_24_autorepair_config_db_storage_mode.sql
storage/cache/classmap.php
```

## Revisão de config.php/db_storage_mode

Para hospedagem compartilhada/cPanel, o recomendado é:

```php
'db_storage_mode' => 'single',
'db_modular_strict' => false,
'db_single_database_rescue' => true,
```

E o bloco `db` precisa apontar para o banco real criado no cPanel:

```php
'db' => [
  'host' => 'localhost',
  'name' => 'ctbatop1_NOME_DO_BANCO',
  'user' => 'ctbatop1_USUARIO_MYSQL',
  'pass' => 'SENHA_FORTE_DO_MYSQL',
  'charset' => 'utf8mb4'
],
```

Em modo `single`, os `db_modules` são ignorados. Mesmo que existam no arquivo, todos os módulos usam o banco principal de `db.name`.

## Se ainda aparecer tabela usuarios ausente

Depois da V104.24, se `usuarios` continuar ausente, a causa mais provável é externa ao código:

1. `config/config.php` aponta para banco vazio ou errado;
2. usuário MySQL não tem permissão `CREATE`, `ALTER` ou `INSERT`;
3. banco escolhido no phpMyAdmin não é o mesmo de `config.php`;
4. deploy parcial deixou arquivos antigos no servidor;
5. cache `storage/cache/classmap.php` antigo não foi apagado.

## Validação local

- PHP lint executado em todos os arquivos PHP.
- 0 erro de sintaxe.
- Classmap regenerado com 209 classes/interfaces/traits.
- SQL atual sem duplicidade de `CREATE TABLE` nos arquivos principais.
- `repair_current.sql` sem placeholders `{AMBIENTE}` / `{TINY_VERSION}`.

## Versão

```text
V104.24 — AutoRepair configurável, placeholders seguros e diagnóstico db_storage_mode
```
