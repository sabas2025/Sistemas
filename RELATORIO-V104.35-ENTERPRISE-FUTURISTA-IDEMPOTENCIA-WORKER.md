# RELATÓRIO V104.35 — Enterprise Futurista + Idempotência Forte + Worker Escalável

## Objetivo
Aplicar melhorias de grande porte preservando regras de negócio, Tiny, VSM, pedidos, estoque, fiscal, XML, login, 2FA e compatibilidade com bases existentes.

## Melhorias aplicadas

### 1. Idempotência forte antes da chamada externa
- Criado `EnterpriseIdempotencyGuardService`.
- `QueueService::pegarProximo()` agora calcula chave idempotente por tipo/referência/payload.
- Se o mesmo item já foi concluído, o novo item é marcado como `ignorado` e não chama Tiny/VSM novamente.
- Se o mesmo item já está reservado/processando, o duplicado também é ignorado para evitar concorrência.

### 2. Worker escalável
- `QueueService` tenta usar `FOR UPDATE SKIP LOCKED` quando o MySQL suporta.
- Fallback automático para `FOR UPDATE` em MySQL antigo/hospedagem compartilhada.
- Adicionado `locked_by` e `locked_at` para rastrear concorrência.
- `worker_enterprise.php` agora aceita limite de tempo: `php workers/worker_enterprise.php 50 300`.

### 3. Retry por categoria de erro
- Criado `QueueRetryPolicyEnterpriseService`.
- Rate limit, instabilidade de API, autenticação, dados de negócio e configuração recebem tratamento diferente.
- Erros não retryable vão para DLQ mais cedo, evitando reprocessamento perigoso.

### 4. Testes de regressão
- Criado `EnterpriseRegressionTestService`.
- Nova tela: `Central Técnica > Testes de Regressão`.
- Script CLI: `php tests/enterprise/run_regression.php`.
- Testa PHP, conexão MySQL, tabelas críticas, status `ignorado` na fila e versão PWA.

### 5. Interface futurista/profissional
- Nova camada CSS V104.35 com visual glass/enterprise, cards futuristas, status pills e dark mode via sistema.
- Nova tela: `Central Técnica > Design Futurista`.
- Melhorias em cards, botões, inputs, tabelas e topbar sem remover CSS antigo.
- Adicionado `futuristic-ui.js` com atalho `Ctrl+K` para busca rápida na Central Técnica.

### 6. Banco/migração
- Criado `database/update_v104_35_enterprise_futurista.sql`.
- Criado `database/enterprise_core_bootstrap_v104_35.sql`.
- Atualizados `install_final_current.sql`, `repair_current.sql` e `install.sql` com compatibilidade V104.35.

## Arquivos principais alterados/criados
- `app/Services/EnterpriseIdempotencyGuardService.php`
- `app/Services/QueueRetryPolicyEnterpriseService.php`
- `app/Services/EnterpriseRegressionTestService.php`
- `app/Services/QueueService.php`
- `app/Services/IntegrationEventService.php`
- `app/Services/EnterpriseObservabilityService.php`
- `app/Services/SchemaMigrationService.php`
- `app/Services/SystemVersionService.php`
- `app/Controllers/EnterpriseCoreController.php`
- `app/Services/FastRouteDispatcherService.php`
- `views/enterprise_regression_tests.php`
- `views/design_system_enterprise.php`
- `public/assets/app.css`
- `public/assets/futuristic-ui.js`
- `public/assets/pwa.js`
- `public/sw.js`
- `workers/worker_enterprise.php`
- `tests/enterprise/run_regression.php`
- `database/update_v104_35_enterprise_futurista.sql`

## Impacto
- Tiny: não altera endpoint, token, regra de pedido, estoque, XML ou NF-e.
- VSM: não altera endpoint, token, homologação/produção ou regra de envio.
- Banco: adiciona colunas/tabelas opcionais e idempotentes.
- APIs: mantém compatibilidade.
- PWA: cache atualizado para V104.35.
- Produção: reduz risco de duplicidade e melhora controle operacional.

## Rollback
1. Voltar ZIP V104.34.
2. Não é necessário remover tabelas novas; elas são opcionais.
3. Se quiser desativar a idempotência forte, ajustar `config/config.php`:
   `enterprise.idempotency_strict_mode => false`.
4. Se houver MySQL antigo sem suporte a `SKIP LOCKED`, o sistema já usa fallback automático.

## Próximas melhorias sugeridas
1. Refatorar `DashboardController.php` em módulos menores com testes cobrindo rotas.
2. Criar suíte PHPUnit real com mocks Tiny/VSM.
3. Adicionar foreign keys graduais após saneamento dos dados.
4. Criar supervisor systemd/PM2/Supervisor para workers CLI.
5. Criar SLO/alertas externos para fila, DLQ, Tiny, VSM e webhooks.
