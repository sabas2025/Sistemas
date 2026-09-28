# Relatório V97 — Blindagem de Segurança, Código e Banco

Data: 2026-06-23
Base: v96 consolidação código/banco.

## Objetivo
Aplicar a etapa de blindagem final recomendada na auditoria v96, reforçando o sistema contra ataque, invasão, abuso administrativo, SQL dinâmico inseguro, restore perigoso, configuração fraca de produção e inconsistência de schema.

## Melhorias aplicadas

### 1. Produção segura por padrão
- `config/config.php` agora usa `app_env => production` por padrão.
- `App::enforceProductionSafety()` bloqueia execução quando `app_env=local` estiver em host público.
- `public/index.php` chama a trava de segurança logo após `App::setupErrors()`.
- Em host público com ambiente local, o sistema retorna 503 e não expõe erro técnico.

### 2. CSP reforçada
- Removido `style-src 'unsafe-inline'` da política padrão.
- CSP agora usa nonce também para `style-src`.
- Mantido `script-src` com nonce por request.

### 3. Backup/restore com permissões separadas
Criadas permissões granulares:
- `backup.visualizar`
- `backup.gerar`
- `backup.baixar`
- `backup.importar`
- `backup.restaurar`
- `backup.excluir`

Admin recebe todas. Gerente pode visualizar/baixar. Operador não acessa backup.

### 4. Restore endurecido
- `BackupService::restaurarPorId()` exige `backup.restaurar`.
- Importação exige `backup.importar`.
- Download exige `backup.baixar`.
- Exclusão exige `backup.excluir`.
- Restore registra SHA-256 do arquivo restaurado.
- Restore bloqueia comandos perigosos:
  - `DROP DATABASE`
  - `CREATE USER`
  - `ALTER USER`
  - `GRANT`
  - `REVOKE`
  - `LOAD DATA`
  - `OUTFILE`
  - `INFILE`
  - `TRIGGER`
  - `EVENT`
  - `PROCEDURE`
  - `FUNCTION`
  - `INSTALL PLUGIN`
- Restore limita SQL a 80MB.
- Restore aceita somente statements em lista permitida.

### 5. SafeDb centralizado
Criado `app/Services/SafeDb.php` com:
- `assertIdentifier()`
- `assertTable()`
- `assertColumn()`
- `quoteIdent()`
- `selectAll()`
- `countWhere()`
- `updateAllowed()`

Objetivo: centralizar validação de nomes de tabela/coluna e reduzir SQL dinâmico espalhado.

### 6. Backup usa identificadores validados
- `BackupService::buildSql()` agora valida tabela com `SafeDb::assertTable()`.
- `SHOW CREATE TABLE` e `SELECT *` usam `SafeDb::quoteIdent()`.

### 7. Webhook Tiny mais rígido em produção
- `TinyWebhookSecurityService` exige secret automaticamente quando `App::isProduction()` for verdadeiro.
- Mesmo que a configuração esteja relaxada, produção força secret.

### 8. Instalador grava configuração completa
- `public/install.php` passou a gerar bloco `security` completo.
- Gera automaticamente:
  - `encryption_key`
  - `webhook_secret`
  - `tiny_webhook_secret`
- Instalação padrão agora privilegia produção segura.

### 9. Schema final v97
- Criado `database/install_final_v97.sql`.
- Adicionada tabela `schema_inventory`.
- Adicionadas permissões granulares de backup.

### 10. Inventário de tabelas
- Criado `database/schema_inventory.json` com classificação inicial:
  - `EM_USO`
  - `LEGADO`
  - `FUTURO`
- Tabelas LEGADO/FUTURO foram mantidas por compatibilidade e não removidas automaticamente.

## Observações importantes
- Ainda existem `CREATE TABLE IF NOT EXISTS` em services antigos por compatibilidade com upgrades incrementais. Na v97, a recomendação passa a ser: produção deve usar `database/install_final_v97.sql` e services não devem criar schema durante uso normal.
- A remoção completa desses creates exige refatoração maior de fluxo de atualização/migration para não quebrar instalações antigas.
- O restore foi endurecido, mas o melhor cenário de produção continua sendo restaurar em ambiente separado/staging antes de aplicar no banco real.

## Status final
Nota técnica estimada após v97: 9,1/10.

A v97 está mais robusta contra:
- abuso de backup/restore;
- execução SQL perigosa;
- configuração local publicada sem querer;
- webhook Tiny sem secret em produção;
- CSP fraca por scripts/styles inline;
- permissões administrativas amplas demais;
- uso futuro de tabelas/colunas dinâmicas sem validação.
