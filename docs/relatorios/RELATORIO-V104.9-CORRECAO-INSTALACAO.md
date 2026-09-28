# RELATÓRIO V104.9 — Correção de Instalação

## Problema reportado

Erro protegido no instalador:

`Trace: INST-20260629-035224-0EDA2752`

Como o erro real fica em `storage/logs/install_errors.log`, a causa exata do servidor só aparece no log interno. Pela auditoria do pacote V104.8, os pontos mais prováveis eram incompatibilidade SQL em hospedagem MySQL/MariaDB e falta de contexto detalhado no instalador.

## Correções aplicadas

1. SQL oficial e módulos agora usam `LONGTEXT` no lugar de `JSON` para compatibilidade com MariaDB/MySQL mais antigo.
2. `SecurityEventService` também cria `contexto` como `LONGTEXT`, evitando erro runtime em banco antigo.
3. `public/install.php` recebeu parser SQL mais seguro, respeitando aspas e comentários.
4. `run_sql()` agora registra o módulo SQL que falhou.
5. Log interno do instalador agora grava prévia segura do statement, SQLSTATE e driver code.
6. Erros idempotentes de tabela/coluna/índice duplicado em tentativa de reinstalação parcial são tratados sem derrubar a instalação.
7. Criado SQL oficial `database/install_final_v104_9.sql`.
8. Criado incremental `database/update_v104_9_instalador_compatibilidade.sql`.

## Arquivos principais alterados

- `public/install.php`
- `database/modules/core.sql`
- `database/install.sql`
- `database/install_final_v104_9.sql`
- `app/Services/SecurityEventService.php`

## Após subir

Se a instalação anterior falhou antes de criar o lock, pode instalar novamente. Se criou tabelas parcialmente, o instalador V104.9 foi ajustado para ser mais tolerante a execução parcial.

Se ainda falhar, abrir:

`storage/logs/install_errors.log`

e procurar pelo Trace ID. O novo log mostrará o módulo e uma prévia segura do SQL que falhou.
