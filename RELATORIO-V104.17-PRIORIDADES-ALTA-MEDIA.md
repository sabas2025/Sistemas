# RELATÓRIO V104.17 — Prioridades Alta e Média Aplicadas

## Objetivo

Aplicar as melhorias solicitadas a partir da análise técnica da V104.16, priorizando primeiro os itens de **prioridade alta** e depois os itens de **prioridade média**, com foco em produção comercial, estabilidade operacional, banco em hospedagem compartilhada, licenciamento e testes.

## Prioridade alta aplicada

### 1. Banco único absoluto

Corrigido `Database::moduleConfig()` para que, em `db_storage_mode = single`, o sistema ignore completamente `db_modules`, inclusive `db_modules['core']`.

Antes ainda havia risco de um `config.php` antigo apontar `db_modules['core']` para um banco vazio. Agora, em banco único, todos os módulos usam somente `config['db']`.

Arquivos:

- `app/Core/Database.php`

### 2. Diagnóstico de configuração real

Criada tela para mostrar o banco realmente conectado por módulo.

Nova rota:

- `index.php?page=diagnostico-config-real`

Novos arquivos:

- `app/Services/DatabaseConfigDiagnosticService.php`
- `app/Controllers/ConfigDiagnosticController.php`
- `views/config_diagnostic.php`

A tela mostra:

- modo configurado;
- banco principal;
- banco conectado por módulo;
- quantidade de tabelas no banco conectado;
- tabelas oficiais ausentes;
- riscos detectados;
- ação recomendada.

### 3. Divisão progressiva do DashboardController

A versão V104.17 não removeu todo o `DashboardController`, pois isso exigiria uma refatoração grande e arriscada em muitas rotas antigas. Porém, a divisão foi avançada com mais responsabilidades fora dele:

- diagnóstico real em controller próprio;
- camada comercial em controller próprio;
- produção comercial em controller próprio;
- segurança assistida em controller próprio;
- manutenção de banco em controller próprio;
- orquestração em controller próprio;
- backups em controller próprio.

Recomendação futura: continuar extraindo `logs`, `auditoria`, `configurações`, `usuários` e rotas legadas.

### 4. Redução de SELECT * em listagens pesadas

Criado `HeavyQueryOptimizerService` com colunas leves para tabelas que têm payloads grandes.

Arquivo:

- `app/Services/HeavyQueryOptimizerService.php`

O método genérico `DashboardController::tableRows()` agora usa colunas leves quando a tabela possui payload pesado.

Tabelas priorizadas:

- `auditoria_eventos`
- `logs_integracao`
- `fila_integracao`
- `pedidos_integracao`
- `tiny_webhooks`
- `webhook_requisicoes`

### 5. Workers reais reforçados

Criado bloqueio central:

- `app/Services/WorkerCliGuardService.php`

Todos os workers reais em `/workers` passaram a chamar o guard central, além dos wrappers públicos já bloquearem navegador.

### 6. Base para homologação real

Adicionados testes Playwright e carga leve não invasiva.

Arquivos:

- `tests/e2e/package.json`
- `tests/e2e/playwright.config.js`
- `tests/e2e/specs/public-security.spec.js`
- `tests/e2e/specs/authenticated-smoke.spec.js`
- `tests/load/light-smoke.sh`
- `docs/testing/PLAYWRIGHT-E2E.md`

## Prioridade média aplicada

### 1. Licença comercial assinada com HMAC

`LicenseEnforcementService` agora valida assinatura HMAC da licença.

Melhorias:

- assinatura por `license_key_hash`, cliente, documento, plano, status, ambiente, limites e expiração;
- status `licenca_sem_assinatura`;
- status `licenca_assinatura_invalida`;
- `ultimo_check_em` atualizado no check válido;
- campos sensíveis mascarados.

Arquivo:

- `app/Services/LicenseEnforcementService.php`

### 2. Portal self-service do cliente

Criado portal interno para cliente/operador acompanhar:

- status da licença;
- faturas;
- conectores;
- chamados/SLA;
- links para áreas comerciais.

Nova rota:

- `index.php?page=cliente-portal`

Nova view:

- `views/commercial_client_portal.php`

### 3. Conectores plugáveis completos por classe

Foram criadas classes reais para conectores planejados, substituindo parte dos genéricos:

- `BlingConnector`
- `OmieConnector`
- `MercadoLivreConnector`
- `ShopeeConnector`
- `AmazonConnector`
- `TikTokShopConnector`

Todos implementam capabilities e requirements.

### 4. Ambiente demo com reset automático

Adicionado reset seguro do ambiente demo sem dados reais.

Nova rota:

- `index.php?page=ambiente-demo-reset`

Ação:

- remove faturas/chamados demonstrativos;
- recria ambiente demo seguro;
- registra auditoria.

### 5. Painel de SLA e suporte

Criado painel comercial de chamados e SLA.

Rotas:

- `index.php?page=suporte-sla`
- `index.php?page=suporte-sla-demo`

Tabelas novas:

- `comercial_suporte_chamados`
- `comercial_sla_eventos`

### 6. Testes E2E com Playwright

Adicionada suíte inicial segura:

- tela pública não expõe erro técnico;
- `install.php` bloqueado;
- workers públicos bloqueados;
- smoke autenticado opcional com usuário temporário.

### 7. Teste de carga leve

Adicionado script não invasivo para smoke de disponibilidade com baixa concorrência.

## Banco e SQL

Tabelas oficiais agora: **113**.

Novas tabelas:

- `comercial_suporte_chamados`
- `comercial_sla_eventos`

SQLs atualizados:

- `database/install.sql`
- `database/install_final_current.sql`
- `database/repair_current.sql`
- `database/modules/core.sql`
- `database/update_v104_17_prioridades_alta_media.sql`

## Validação executada

- PHP lint em todos os arquivos: **sem erro de sintaxe**.
- Classmap regenerado: **191 classes/interfaces**.
- Conectores carregam via `ConnectorRegistryService`.
- Versão centralizada atualizada para **V104.17**.
- SQL oficial sem duplicidade de `CREATE TABLE`.
- Inventário atual do schema com **113 tabelas**.

## Análise técnica após aplicação

### Nota atual estimada

**8,6 / 10**

Evoluiu de 8,2 para 8,6 porque os principais riscos de produção comercial foram reduzidos:

- banco único absoluto;
- diagnóstico real de conexão;
- licença com assinatura;
- workers reforçados;
- testes E2E/carga leve;
- SLA/portal cliente;
- conectores mais plugáveis.

## Melhorias futuras recomendadas

1. Extrair definitivamente o restante do `DashboardController` em controllers menores.
2. Implementar integração real de portal do cliente com autenticação por cliente, não apenas painel interno.
3. Integrar cobrança com Mercado Pago, banco ou gateway real.
4. Criar assinatura remota de licença com servidor licenciador.
5. Implementar conectores Bling/Omie/Mercado Livre com endpoints reais.
6. Rodar Playwright contra ambiente real com usuário temporário.
7. Rodar teste de carga controlado em homologação.
8. Criar pipeline CI/CD para validar PHP lint, SQL, Playwright e zip.

## Conclusão

A V104.17 fica mais próxima de produção comercial e reduz o risco de erros de banco em hospedagem compartilhada. A principal etapa restante para chamar de produção final continua sendo homologação real com:

- MySQL real;
- Tiny real/homologação;
- VSM pedidos-integradora;
- workers via cron;
- backup/restore;
- Segurança Assistida sem críticos;
- Playwright smoke passando.
