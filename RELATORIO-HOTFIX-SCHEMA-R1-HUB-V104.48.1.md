# Relatório técnico — Hotfix Schema Recovery R1

## Incidente validado

A tela `mapa-banco` registrava erro de licenciamento comercial e apresentava como ausentes as seguintes tabelas:

- comercial_clientes_licencas
- comercial_conectores_catalogo
- comercial_cobranca_faturas
- comercial_demo_ambientes
- comercial_suporte_chamados
- comercial_sla_eventos
- enterprise_quality_gates
- enterprise_regression_runs
- enterprise_ui_preferences
- integration_events
- integration_idempotency
- llm_approval_queue
- llm_audit_logs
- llm_policy_settings
- llm_prompts
- llm_usage_daily
- observability_snapshots
- worker_heartbeats

## Causa raiz

As migrations `20260712_001` e `20260712_002` adicionavam colunas, índices e estruturas complementares, mas não criavam todas as 18 tabelas novas em bancos existentes. A mensagem operacional indicava apenas essas migrations, portanto a orientação não resolvia o problema apresentado.

Além disso:

1. `Validar Banco > Reparar Schema Manualmente` não chamava `SchemaMigrationService::applyEnterpriseCore()`.
2. `Mapa do Banco` era somente leitura e não possuía ação direta para aplicar as tabelas pendentes.
3. O licenciamento consultava o schema comercial antes de liberar as rotas técnicas de recuperação, gerando alerta durante a própria manutenção.
4. A verificação Enterprise Core não incluía as seis tabelas comerciais principais.

## Correções aplicadas

### Migration 003

Criado o arquivo:

`database/migrations/20260712_003_missing_core_tables.sql`

Características:

- `CREATE TABLE IF NOT EXISTS` para as 18 tabelas reportadas;
- definições idênticas ao `database/install_final_current.sql`;
- compatibilidade com versões antigas de `schema_migrations`;
- registro idempotente da migration;
- nenhum `DROP`, `TRUNCATE` ou `DELETE`;
- não altera contratos Tiny ou VSM.

### Enterprise Core

`SchemaMigrationService` agora:

- inclui as seis tabelas comerciais principais;
- mantém as tabelas enterprise existentes;
- limpa cache de metadados depois da criação;
- verifica todas as tabelas após o reparo;
- registra explicitamente tabelas ainda pendentes;
- calcula checksum incluindo a migration 003.

### Validar Banco

O reparo manual agora executa, nesta ordem:

1. Enterprise Core / migration 003;
2. AutoRepair oficial;
3. SchemaGuard;
4. validação final de tabelas, colunas e índices.

### Mapa do Banco

Adicionado botão POST protegido por:

- autenticação;
- permissão `database.validar`;
- CSRF;
- confirmação do operador;
- rate limit sensível;
- WAF de painel.

Botão:

`Aplicar tabelas e migrations pendentes`

A tela apresenta aplicados, ignorados, erros, Trace ID e pendências após o reparo.

### Licenciamento

As rotas técnicas abaixo são liberadas antes da consulta ao schema comercial:

- central-tecnica
- validar-banco
- mapa-banco
- enterprise-core
- enterprise-core-aplicar
- migracoes-seguras
- migracao-aplicar
- backups

Isso evita que a recuperação seja bloqueada ou gere alerta enganoso por falta das próprias tabelas que serão criadas.

## Validação executada

- PHP lint: **419/419 arquivos aprovados**.
- Testes enterprise: **21/21 aprovados**.
- Playwright/PWA: **5/5 aprovados**.
- Schema oficial: **134 tabelas, nenhuma duplicidade**.
- Rotas: **96 mapeamentos válidos, sem duplicidade**.
- DDL runtime: restrito a **10 arquivos autorizados**.
- Migration 003 comparada com schema oficial: **18/18 definições idênticas**.
- Comandos destrutivos na migration 003: **0**.
- `npm audit`: **0 vulnerabilidades conhecidas**.

## Limitação externa

A execução real da migration em MySQL não foi possível no ambiente de auditoria porque o PHP disponível não possui o driver `pdo_mysql`. O executor destrutivo isolado está atualizado para aplicar as migrations 001/002/003 duas vezes e validar idempotência quando executado na homologação com MySQL.

## Procedimento de aplicação

1. Gerar backup dos arquivos e banco.
2. Extrair o hotfix na raiz do HUB.
3. Não substituir `config/config.php`.
4. Abrir `public/index.php?page=mapa-banco`.
5. Clicar em **Aplicar tabelas e migrations pendentes**.
6. Recarregar o mapa e confirmar que as 18 tabelas aparecem como OK.
7. Abrir `public/index.php?page=validar-banco` e executar validação em modo leitura.
8. Regenerar a baseline em **Segurança > Integridade de Arquivos — FIM**.

Fallback via phpMyAdmin:

1. `20260712_001_queue_oauth_concurrency.sql`
2. `20260712_002_schema_runtime_vsm_contract.sql`
3. `20260712_003_missing_core_tables.sql`

Aplicar nessa ordem. Todos são idempotentes.
