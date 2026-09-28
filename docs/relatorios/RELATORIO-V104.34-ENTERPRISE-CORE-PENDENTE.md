# RELATÓRIO V104.34 — CORREÇÃO ENTERPRISE CORE PENDENTE

## Problema informado

A tela mostrava tabelas como PENDENTE:

- `schema_migrations`
- `integration_events`
- `integration_idempotency`
- `worker_heartbeats`
- `observability_snapshots`
- `enterprise_quality_gates`
- `llm_prompts`
- `llm_audit_logs`

## Diagnóstico

Isso não é erro de regra de negócio Tiny/VSM. Significa que o código da V104.32/V104.33 foi atualizado, mas o banco existente ainda não recebeu a migração Enterprise Core. Em instalação nova, o `install_final_current.sql` já contém as estruturas. Em banco antigo, é necessário executar `Central Técnica > Enterprise Core > Aplicar Enterprise Core`.

## Correção aplicada

A V104.34 melhora o destravamento e adiciona um SQL manual consolidado:

`database/enterprise_core_bootstrap_v104_34.sql`

Esse SQL cria as tabelas pendentes com `CREATE TABLE IF NOT EXISTS`, registra as migrações em `schema_migrations` e pode ser executado novamente sem apagar dados.

## Como aplicar

1. Faça backup do banco.
2. Suba a V104.34.
3. Acesse `Central Técnica > Enterprise Core`.
4. Clique em `Aplicar Enterprise Core`.
5. Se continuar pendente por bloqueio da hospedagem, execute no phpMyAdmin o arquivo `database/enterprise_core_bootstrap_v104_34.sql`.

## Impacto

- Tiny: não altera.
- VSM: não altera.
- Pedidos/estoque/fiscal/XML: não altera.
- Banco: cria tabelas técnicas novas e registros de migração.
- LLM: continua desativado por padrão.
- Produção: seguro se feito após backup.

## Rollback

Como as tabelas são novas e técnicas, o rollback mais seguro é restaurar backup. Caso necessário, as tabelas criadas pelo Enterprise Core podem ser removidas manualmente apenas se ainda não houver uso operacional delas.
