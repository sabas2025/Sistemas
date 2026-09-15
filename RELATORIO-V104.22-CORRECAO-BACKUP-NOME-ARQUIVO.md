# RELATÓRIO V104.22 — Correção definitiva Backups: `nome_arquivo` / `arquivo`

## Problema corrigido

Erro informado em produção:

```text
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'nome_arquivo' in 'SELECT'
```

## Causa

Algum trecho ativo no servidor ainda fazia consulta usando a coluna antiga `nome_arquivo`, enquanto a tabela moderna `backups_banco` passou a usar `arquivo`.

Mesmo com a correção anterior, em deploy parcial, cache de classmap, controller legado ou tabela criada por versão antiga, o painel poderia encontrar uma combinação incompatível:

- código antigo procurando `nome_arquivo`;
- tabela nova contendo apenas `arquivo`;
- ou código novo procurando `arquivo` com tabela antiga contendo apenas `nome_arquivo`.

## Correção aplicada

A partir da V104.22, a tabela `backups_banco` mantém compatibilidade permanente com:

- `arquivo` — coluna oficial atual;
- `nome_arquivo` — coluna legada compatível;
- `caminho` — coluna legada compatível.

O `BackupSchemaService` agora:

1. cria `backups_banco` quando ausente;
2. adiciona `arquivo`, `nome_arquivo` e `caminho` quando faltarem;
3. migra `nome_arquivo` para `arquivo` quando necessário;
4. migra `caminho` para `arquivo` quando necessário;
5. sincroniza `arquivo` para `nome_arquivo`;
6. preenche `caminho` como `storage/backups/<arquivo>`;
7. lista backups com as três colunas para compatibilidade.

## Arquivos alterados

- `app/Services/BackupSchemaService.php`
- `app/Services/BackupService.php`
- `app/Services/SystemVersionService.php`
- `database/install.sql`
- `database/install_final_current.sql`
- `database/repair_current.sql`
- `database/modules/backups.sql`
- `database/install_final_v104_22.sql`
- `database/update_v104_22_backup_compatibilidade_nome_arquivo.sql`

## Resultado esperado

A rota abaixo não deve mais quebrar por coluna ausente:

```text
index.php?page=backups
```

A tela Backups deve abrir mesmo se o banco tiver sido criado por versão antiga.

## Recomendação após subir

1. Subir todos os arquivos da V104.22.
2. Apagar o cache antigo, se existir:
   - `storage/cache/classmap.php`
3. Abrir:
   - `Central Técnica > Backups`
4. Executar:
   - `Central Técnica > Validar Banco`
   - `Central Técnica > Mapa do Banco`
5. Gerar um backup novo.

## Validação local

- PHP lint executado nos arquivos do sistema.
- Nenhum erro de sintaxe encontrado.
- SQL oficial validado sem duplicidade de colunas em `backups_banco`.
- Classmap regenerado.
