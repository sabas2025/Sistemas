# RELATÓRIO V104.21 — Correção Backups / Schema Guard

## Erro corrigido

A rota `backups` falhava em `app/Controllers/BackupController.php:18` porque a consulta usava colunas antigas/novas que não estavam garantidas no banco real:

```sql
SELECT id, nome_arquivo, caminho, tamanho_bytes, hash_sha256, hmac_sha256, trust_score, status, criado_em FROM backups_banco
```

Em instalações parcialmente atualizadas, a tabela `backups_banco` podia existir com o schema antigo (`arquivo`, `tamanho_bytes`, `mensagem`, `trace_id`) ou não existir ainda. Isso derrubava a tela antes de o usuário conseguir gerar ou restaurar backup.

## Correções aplicadas

- Criado `app/Services/BackupSchemaService.php`.
- A tela Backups agora executa reparo idempotente antes de consultar.
- `BackupController` passou a usar `BackupSchemaService::list(100)`.
- `DashboardController` legado também foi corrigido.
- `BackupService` agora garante schema antes de gerar, registrar, importar ou restaurar.
- `backups_banco` agora aceita colunas modernas:
  - `arquivo`
  - `tamanho_bytes`
  - `hash_sha256`
  - `hmac_sha256`
  - `trust_score`
  - `status`
  - `mensagem`
  - `trace_id`
  - `criado_em`
- Compatibilidade com versões antigas:
  - migra `nome_arquivo` para `arquivo`;
  - migra `caminho` para `arquivo` usando o nome final do caminho.
- Registro de backup agora grava SHA-256, HMAC e Trust Score quando disponíveis.
- SQLs oficiais atualizados.
- Criado `database/update_v104_21_backup_schema_guard.sql`.

## Validação

- PHP lint: sem erro de sintaxe.
- ZIP testado com sucesso.
- Versão centralizada atualizada para V104.21.

## Após subir

Acesse:

```text
Central Técnica > Backups
```

A tela deve abrir mesmo que a tabela `backups_banco` esteja antiga ou incompleta. Depois teste:

```text
Gerar backup ZIP agora
Baixar backup
Validar Banco
Mapa do Banco
```
