# RELATÓRIO V104.8 — Melhorias obrigatórias e importantes aplicadas

Data: 2026-06-29 03:44:03

## Objetivo

Aplicar as melhorias levantadas na análise da V104.7, principalmente banco de dados, migrações, SchemaGuard, performance de listagens, retenção de dados, rotas legadas e mapa do banco.

## Escopo aplicado

### Prioridade obrigatória

1. **Corrigir `integration_replay_guard` no SQL oficial**
   - O índice antigo `uk_replay_origem_hash (origem, request_hash)` foi removido do SQL oficial.
   - O SQL oficial agora usa `uk_replay_origem_hash_bucket (origem, request_hash, time_bucket)`.
   - Foram adicionadas/garantidas as colunas `payload_hash`, `time_bucket` e `hmac_validated_at`.
   - O SchemaGuard remove o índice legado quando ele existir no banco antigo.

2. **Corrigir `database/modules/fila.sql`**
   - O módulo `fila.sql` recebeu o mesmo schema corrigido de `integration_replay_guard`.
   - A instalação modular agora fica alinhada com `install_final_v104_8.sql`.

3. **Remover/mover SQLs incrementais antigos da raiz**
   - SQLs incrementais antigos foram movidos para `database/legacy/incrementais_v104_8/`.
   - Na raiz ficou apenas o SQL incremental oficial `update_v104_8_schema_guard_completo.sql`.
   - Isso reduz risco de executar SQL antigo com `ADD COLUMN IF NOT EXISTS` em hospedagem incompatível.

4. **Padronizar `schema_migrations`**
   - Padrão oficial mantido: `migration`, `checksum`, `status`, `mensagem`, `aplicada_em`.
   - Código ativo que registrava `version/descricao` foi migrado para `Database::recordMigration()`.
   - Criado helper `Database::ensureSchemaMigrations()`.

5. **Trocar `ProductApprovalPolicyService` para `Database::addColumnIfMissing()`**
   - Removido `ALTER TABLE ADD COLUMN IF NOT EXISTS` do serviço.
   - Agora o serviço usa helper idempotente compatível com MySQL/MariaDB de hospedagem.

6. **SchemaGuard completo**
   - `DatabaseSchemaGuardService` agora lê os SQLs oficiais dos módulos em `database/modules/*.sql`.
   - Ele valida/cria as **107 tabelas oficiais** conforme o mapa `Database::tableModule()`.
   - Também valida colunas críticas e índices críticos.

### Prioridade importante

7. **Separação/organização de responsabilidade**
   - Criado/fortalecido `DatabaseMaintenanceController` com:
     - `validar-banco`
     - `health-modulos`
     - `mapa-banco`
   - Isso reduz dependência do `DashboardController` para tarefas de banco.

8. **Redução de `SELECT *` em listagens pesadas**
   - Listagens principais de `logs_integracao`, `auditoria_eventos` e `fila_integracao` passaram a selecionar apenas colunas usadas na tela.
   - Detalhes continuam usando carga completa quando precisam de payload/contexto.

9. **Limpeza automática de dados operacionais**
   - Criado `DataRetentionService`.
   - Limpeza probabilística para:
     - `rate_limit_hits`
     - `integration_replay_guard`
     - `security_events`
     - `logs_integracao`
     - `auditoria_eventos`
     - `module_health_snapshots`

10. **Rotas legadas V50/V51 isoladas**
    - Criado `LegacyRouteGuardService`.
    - As rotas V50/V51 agora geram auditoria de acesso como rota legada controlada.
    - Existe suporte para bloqueio por configuração `security.disable_legacy_routes`.

11. **Painel Mapa do Banco**
    - Criado `DatabaseMapService`.
    - Criada view `views/mapa_banco.php`.
    - Nova rota: `index.php?page=mapa-banco`.
    - Mostra tabela, módulo, banco, status e colunas ausentes.

## Arquivos principais alterados/criados

- `app/Core/Database.php`
- `app/Services/DatabaseSchemaGuardService.php`
- `app/Services/DatabaseMapService.php`
- `app/Services/DataRetentionService.php`
- `app/Services/LegacyRouteGuardService.php`
- `app/Services/FastRouteDispatcherService.php`
- `app/Services/RouteRateLimiterService.php`
- `app/Services/ProductApprovalPolicyService.php`
- `app/Services/DatabaseValidationService.php`
- `app/Controllers/DatabaseMaintenanceController.php`
- `app/Controllers/DashboardController.php`
- `views/mapa_banco.php`
- `views/central_tecnica.php`
- `views/configuracoes.php`
- `database/install.sql`
- `database/install_final_v104_8.sql`
- `database/modules/fila.sql`
- `database/update_v104_8_schema_guard_completo.sql`
- `database/schema_inventory_v104_8.json`
- `docs/ROUTE_MANIFEST_V104_8.json`
- `docs/VALIDACAO-V104.8.json`

## Validação estática

- Arquivos PHP: **297**
- Controllers: **30**
- Services: **127**
- Views: **105**
- Linhas PHP aproximadas: **19775**
- Classes: **163**
- Funções/métodos: **1048**
- Tabelas no `install_final_v104_8.sql`: **107**
- Tabelas únicas no `install_final_v104_8.sql`: **107**
- Tabelas no mapa `Database::knownTables()`: **107**
- Duplicidade `CREATE TABLE` no SQL oficial: **0**
- Tabela fora do mapa de módulos: **0**
- Tabela mapeada fora do SQL oficial: **0**
- JavaScript inline nas views/app/public: **0**
- Padrões SQL inseguros na raiz/módulos (`ADD COLUMN IF NOT EXISTS`, `schema_migrations(version/versao)`, índice replay antigo): **0**
- `php -l`: **0 erros de sintaxe**

## Como usar após subir no servidor

1. Subir os arquivos da V104.8.
2. Acessar:
   - `Central Técnica > Validar Banco`
3. Depois acessar:
   - `Central Técnica > Mapa do Banco`
   - `Central Técnica > Health de Módulos`
   - `Integrações > Escolher fluxos ativos`
   - `Central Técnica > Backups > Gerar Backup`

## Observação

A refatoração total do `DashboardController` foi reduzida de risco: movi banco/manutenção para controller dedicado e criei serviços novos, mas não quebrei todas as telas em controllers menores porque isso exigiria teste real de navegação no servidor. O ponto crítico de banco/migração/schema foi aplicado.
