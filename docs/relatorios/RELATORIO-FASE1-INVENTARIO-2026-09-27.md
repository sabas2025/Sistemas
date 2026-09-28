# Relatório — Fase 1 (Inventário)

**Data:** 2026-09-27
**Release:** `V104.49.3-R7+20260917.1` (branch `claude/security-audit-skill-9cd1qi`, sobre `main` `3e0dae3`)
**Fase (PROMPT_-_HUB / CLAUDE.md):** 1 — Inventário
**Natureza:** documento de referência, **read-only**. Nenhum código alterado.

> Consolida o mapa do sistema descoberto ao longo das auditorias (Fases 2–6). Contagens medidas na
> árvore em `3e0dae3`, não estimadas. Onde uma camada não existe, isto é dito — não inventado.

---

## 1. Visão geral

- **Stack:** PHP 8.x vanilla MVC, **sem Composer**. Autoloader próprio com classmap em
  `storage/cache/classmap.php` (**257 classes**), gerado por `scripts/build-classmap.php`.
- **Banco:** MySQL 8 / MariaDB 11.4 (matriz de CI). Sem ORM; PDO com prepares **nativos**
  (`ATTR_EMULATE_PREPARES=false`).
- **Front-end:** views PHP server-side + PWA (assets minificados conferidos por `build-assets.mjs`).
- **Distribuição:** hospedagem compartilhada; zero dependência de rede em runtime (sem webfont, sem CDN).

**Números medidos:**

| Camada / artefato | Qtd |
|---|---:|
| Controllers (`app/Controllers`) | 44 |
| Controllers legados (`app/Legacy/Controllers`) | 2 |
| Services (`app/Services`) | 196 |
| Core (`app/Core`) | 7 |
| Connectors (`app/Connectors`) | 10 |
| Models / Repositories dedicados | **0** (ver §4) |
| Workers CLI (`workers/`) | 15 |
| Views (`views/`) | 130 |
| Módulos de schema (`database/modules`) | 8 |
| Migrations (`database/migrations`) | 18 |
| Testes enterprise (`tests/enterprise`) | 80 |
| Specs E2E (`tests/e2e`) | 6 |
| Scripts de CI (`scripts/ci`) | 17 |
| Relatórios (`RELATORIO-*.md`) | 116 |

---

## 2. Mapa de diretórios (raiz)

| Pasta | Conteúdo |
|---|---|
| `app/` | Código-fonte MVC: `Core`, `Controllers`, `Services`, `Connectors`, `Legacy/Controllers` |
| `config/` | Só `config.example.php` (o `config.php` real **não** é versionado; nasce do instalador) |
| `contracts/` | Contratos OpenAPI da VSM (`pedidos-integradora`, `pedidos-loja`) — travados por `vsm-openapi-check.php` |
| `database/` | `modules/` (8 módulos), `migrations/` (18), consolidados `install.sql`/`install_final_current.sql`, `legacy/` |
| `docs/` | Documentação de arquitetura, licenciamento, relatórios históricos, `INVENTARIO-FUNCOES-*.csv` |
| `public/` | Docroot: `index.php` (front controller), `install.php`, `pwa_telemetry.php`, 12 shims `worker_*.php`, assets |
| `scripts/` | Ferramentas CLI (`build-classmap`, `rotate-secrets`, `diagnose-http-500`, `create-install-authorization`, `upgrade-r7`) + `ci/` |
| `storage/` | Runtime: `cache/classmap.php`, locks, logs, backups, sessões — **não** versionado (exceto o classmap) |
| `tests/` | `enterprise/` (80 portões PHP) + `e2e/` (Playwright) |
| `views/` | 130 templates PHP server-side |
| `workers/` | 15 workers CLI reais (a lógica; os shims públicos só delegam) |
| `node_modules/` | Dependências do build PWA/E2E (não versionado no pacote) |

---

## 3. Cadeia de entrada / bootstrap

`public/index.php` (front controller resiliente) executa, nesta ordem:

1. `register_shutdown_function` + `hub_boot_render_error` — captura fatal antes do dispatcher, com Trace ID.
2. Carrega `config/config.php`.
3. Endurece o cookie de sessão (`Secure` condicional, `HttpOnly`, `SameSite=Strict`) **antes** de abrir a sessão.
4. `app/Core/Autoload.php` → registra `DatabaseSessionHandler` (opt-in) → `session_start()`.
5. `app/Core/Helpers.php` → `App::setupErrors` → `App::enforceProductionSafety` → `App::sendSecurityHeaders`.
6. `IpBlockService::enforce` → `WafService::inspect` → `RouteRateLimiterService::enforce`.
7. `FastRouteDispatcherService::dispatch($page, $method)`.

**Risco:** Crítico. Alterar a ordem quebra sessão/segurança. Mudanças aqui exigem validação de runtime.

---

## 4. Camadas de código

### 4.1 `app/Core` (7) — fundação
`App` (config, headers, produção), `Auth` (sessão/login/permissão de sessão), `Autoload` (classmap),
`Csrf` (token por sessão), `Database` (PDO, `tableExists`/`columnExists`, `forTable`), `Helpers`
(funções globais `cfg()`, `e()`, `redirect()`…), `RequestContext` (Trace ID).
**Risco:** Crítico — tudo depende. Classes-folha, muito chamadas, quase nada chamam.

### 4.2 `app/Controllers` (44) + `app/Legacy/Controllers` (2)
Recebem rota do `FastRouteDispatcherService`, autenticam (`Auth::requireLogin` +
`PermissionService::require`), chamam services e renderizam views. Donos de módulo declarados em
`RouteModuleRegistry`; rotas resolvidas por `$dispatchGroups`.
- **`DashboardController`** — decomposto na Fase 3 (163.608 → 33.433 B); ainda é alvo do achado
  A3-01 e vive sob teto de **160 KB** travado por `v104_48_1_architecture_test`. **Risco: Alto** —
  medir `wc -c` antes de acrescentar lógica; lógica nova de domínio entra por serviço.
- Legados `V50Controller`/`V51Controller` — mantidos por compatibilidade de rota.
**Risco geral:** Médio — derivam controller↔serviço↔view em silêncio (lição I-11); ao mexer numa
tela antiga, conferir as três camadas.

### 4.3 `app/Services` (196) — regra de negócio e infraestrutura
Maior camada. Agrupamentos principais:
- **Integração Tiny:** `TinyV2Service`, `TinyV3Service`, `TinyV3TokenService`, `TinyFactory`,
  `TinyEndpointSecurityService`, `TinyWebhookSecurityService` (auditados na Fase 5).
- **Integração VSM:** `VsmService`, `VsmTokenService`, `VsmEndpointSecurityService`,
  `VsmCredentialsConfigService` (Fase 6).
- **Fila/resiliência:** `QueueService`, `RetryPolicyService` (folha; toda a matemática de backoff —
  as três filas dependem dela), `DeadLetterQueueService`, `CircuitBreakerService`.
- **Isolamento/tenant:** `TenantScopeService` (catálogo de 32 tabelas), `TenantContextService`,
  `IntegrationTenantService`, `EmpresaCatalogService`.
- **Segurança:** `RateLimitService` (fachada única), `AtomicRateCounterService`, `RouteCatalogService`
  (fonte única de classificação de rota), `SecurityHealthService`, `CryptoService`, `SecretStrengthService`.
- **Config/observabilidade:** `IntegrationConfig`, `Audit`, `SensitiveDataService`, `MetricsService`.
**Risco:** varia. Classes-folha centrais (`RetryPolicyService`, `TenantScopeService`, `CryptoService`,
`RouteCatalogService`) são **Alto** — muitos dependentes. **Regra:** usar os serviços centrais
existentes, nunca criar paralelos (CLAUDE.md).

### 4.4 `app/Connectors` (10)
Adaptadores de saída para os provedores. `TinyClientInterface` define o contrato
(`criarPedido`, `consultarProduto`, `atualizarEstoque`…), implementado por `TinyV2Service`/`TinyV3Service`
e selecionado pela `TinyFactory`.
**Risco:** Médio — alterar assinatura pública exige mapear chamadas.

### 4.5 Observação: **não há camada Models nem Repositories dedicada**
`app/Models` e `app/Repositories` não existem (0 arquivos). O acesso a dados é feito por `Database::forTable()`
e SQL direto dentro dos services (com `TenantScopeService` para escopo). `AuthRepository` existe como
serviço avulso em `app/Services`, não numa pasta `Repositories`. **Não é defeito** — é o desenho vanilla
do Hub; registrado para quem procurar essas pastas.

---

## 5. Persistência

- **Schema modular:** 8 módulos em `database/modules/` (`core`, `pedidos`, `estoque`, `fiscal`,
  `produtos`, `fila`, `backups`, `observabilidade`).
- **Consolidado:** `install.sql` e `install_final_current.sql` (template com marcadores), gerados de
  `build-consolidated-schema.mjs` e conferidos por `--check`.
- **Migrations:** 18 em `database/migrations/`, execução **manual** (não há executor automático —
  `SchemaMigrationService::checksum()` lista só 5; as demais são de aplicação manual, por desenho).
- **Regra I-18:** todo índice/coluna entra nos **dois caminhos** (módulo + migration) no mesmo commit,
  com paridade `information_schema` remedida.
- **Sem chaves estrangeiras** nas 135+ tabelas (coerente com hospedagem compartilhada; registrado, não aplicado).
**Risco:** Alto — `20260914_012_pk_bigint` exige janela de manutenção; migrations precisam ser
idempotentes, portáveis MySQL 8 + MariaDB e reversíveis.

---

## 6. Rotas

- **`FastRouteDispatcherService`** — dispatcher central; `$directActions` (4 rotas de API pré-gate) +
  `$dispatchGroups` (controller→rotas). Fallback: `DashboardController::dispatch`.
- **`RouteModuleRegistry`** — declara o dono de cada módulo/rota.
- **`RouteCatalogService`** — fonte única de **classificação de segurança** de rota (igualdade exata,
  nunca substring — lição B-06).
**Risco:** Alto — classificar rota por substring/prefixo já causou B-06 e C-01. Sempre por igualdade.

---

## 7. Views e assets
130 templates em `views/`. Escape por `e()` (htmlspecialchars) + CSP com nonce. Assets PWA minificados
(`public/assets/*.min.*`) conferidos contra a fonte por `build-assets.mjs --check` (`npm run check:pwa`).
**Risco:** Médio — editar `.css`/`.js` sem estar na lista do build não chega à tela; regenerar com `build:pwa`.

---

## 8. Workers e crons
15 workers CLI em `workers/` (fila, estoque, fiscal, xml_nfe, backup, notificações, reconciliação,
selftest, homologação, retenção, refresh de token Tiny V3 e VSM, consulta de estoque VSM, enterprise).
Os 12 shims em `public/worker_*.php` bloqueiam acesso web (`PHP_SAPI !== 'cli'` → 403). Agendados por cron.
**Risco:** Médio — reprocessar não pode duplicar; `worker_retencao` faz expurgo fora do caminho de
requisição (C-04). `worker_consulta_estoque_vsm` sai com código 1 sem rede (correto).

---

## 9. Instalador e atualizador
- **`public/install.php`** — instalação limpa; template com marcadores; exige código de autorização
  de uso único com TTL + binding de navegador (parser real travado por `install_sql_contract_test`, I-22).
- **`MigrationController` / `LegacyDatabaseUpgradeController`** — atualização assistida de schema
  (o legado está depreciado, DDL em runtime; desligado por padrão — ver `SECURITY.md`).
- **`scripts/upgrade-r7.php`, `create-install-authorization.php`, `rotate-secrets.php`.**
**Risco:** Crítico — preservar `install.php` e as atualizações; nunca perder dados/tokens/config.

---

## 10. Testes e portões de CI
- **80 testes enterprise** (`tests/enterprise/`), rodados por `enterprise-tests.sh`.
- **6 specs E2E** (Playwright) contra MariaDB provisionado.
- **17 scripts em `scripts/ci/`** — os 14 portões estáticos (php-lint, classmap, controller-route,
  tenant-scope, secret-hygiene, vsm-openapi, sql-inventory, mysql-schema-static, mysql-module-parity,
  build-consolidated, build-assets, schema-runtime-ddl, cli-scripts-smoke) + regressão de runtime +
  provisionamento E2E, agregados pelo job `gate`.
**Risco:** Baixo — mexer num portão pede testá-lo contra o defeito original (lição I-20/I-21).

---

## 11. Documentação e contratos
- **`CLAUDE.md`** — memória do projeto e regras de trabalho (autoritativa).
- **`SECURITY.md`** — modelo de ameaças e 7 decisões deliberadas.
- **116 `RELATORIO-*.md`** — histórico de auditorias e correções (incluindo Fases 4 e 5 desta sessão).
- **`contracts/vsm/*.openapi.json`** — contratos VSM travados por CI.
- **`docs/`** — arquitetura de conectores, licenciamento, plano de refactor, inventário de funções (CSV).
**Risco:** Baixo, com uma armadilha: editar `CLAUDE.md` invalida sua linha no manifesto FIM
(`CHECKSUMS-SHA256.txt`) — regenerar a entrada ao mexer nele.

---

## 12. Risco de alteração — consolidado

| Área | Risco | Porquê |
|---|---|---|
| `app/Core`, bootstrap, instalador | **Crítico** | Fundação; falha derruba tudo ou perde dados |
| Serviços-folha centrais (`RetryPolicy`, `TenantScope`, `Crypto`, `RouteCatalog`) | **Alto** | Muitos dependentes; erro se propaga em silêncio |
| `DashboardController` | **Alto** | Teto de 160 KB; ainda a decompor (A3-01) |
| Schema / migrations | **Alto** | Paridade I-18; PK BIGINT precisa de janela |
| Rotas / classificação de segurança | **Alto** | Substring já causou B-06/C-01 |
| Controllers/views antigos | **Médio** | Derivam nas 3 camadas em silêncio |
| Workers, assets PWA | **Médio** | Duplicação de fila; assets fora do build |
| Portões de CI, documentação | **Baixo** | Testar o portão contra o defeito; regenerar manifesto |

---

## 13. Limites

Este inventário é **estrutural e estático** (contagens e responsabilidades medidas na árvore
`3e0dae3`). Não é uma auditoria de comportamento — cada fase específica (2–12) faz isso. Não substitui
`CLAUDE.md` nem `SECURITY.md`: complementa, dando o mapa único de onde cada coisa vive e o que arriscar
ao tocá-la.
