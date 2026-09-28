# RELATÓRIO — Auditoria da mudança VSM (duas APIs: consulta e gravação)

**Data:** 2026-09-20 · **Release base:** V104.49.3-R7 · **Commit base:** `277102a`
**Escopo:** validar que a mudança da VSM (PR #20) foi realmente aplicada, que nenhuma rota quebrou,
e que não há código inativo nem erro introduzido. Auditoria pós-merge; documento informativo.

## Contexto da mudança
As duas APIs VSM passam a ficar ativas ao mesmo tempo, sem principal/secundária:
- **Gravação** (`vsm_url`) → `enviarPedido()` + `enviarBaixaEstoque()`.
- **Consulta** (`vsm_url_consulta`, novo) → `consultarEstoque()`.
- `vsm_url_consulta` vazio → usa a base de gravação (retrocompatível).

## 1. Foi realmente alterado? — SIM (fato)
Commit `847107a` na história da `main` (merge `277102a`), **12 arquivos**. Diff conferido linha a linha:
- `app/Services/VsmService.php`: propriedade `$urlConsulta`; helper puro `baseConsulta()`;
  `request()` com parâmetro `?string $baseUrl=null` e `$base` selecionado por operação;
  `consultarEstoque()` injeta `$this->urlConsulta`; `curlResolveEntry($base)`.
- Coluna `configuracoes_integracao.vsm_url_consulta VARCHAR(255) DEFAULT ''` no módulo `core.sql`,
  no consolidado (`install.sql`/`install_final_current.sql`), e nos dois serviços de schema
  (`SchemaMigrationService`, `DatabaseSchemaGuardService`).
- `IntegrationConfig` (default), `DashboardController` (load/save; UPDATE dinâmico compatível),
  `views/configuracoes.php` (rótulos "API de gravação" e "API de consulta").
- Manifesto FIM: **846/846** conferido, zero falhas.

## 2. Rotas quebradas? — NENHUMA (medido em MariaDB real)
Varredura de **232 rotas GET** autenticado:
- **Zero 5xx · zero texto de erro** (`Fatal error`/`Uncaught`/`PDOException`/`SQLSTATE[`).
- Tela **Configurações → 200**; **dashboard 200** ao fim (sessão sobreviveu).
- Distribuição: 130×200, 25×302, 64×403, 6×404 (rotas de detalhe sem parâmetro), 7×405 (POST-only)
  — perfil idêntico à auditoria de rotas anterior; nada regrediu.

## 3. Código inativo? — NÃO
- `baseConsulta()` é consumido (construtor de `VsmService` + teste enterprise).
- `$this->urlConsulta` é consumido (`consultarEstoque`).
- `vsm_url_consulta` está fiado nos 5 arquivos certos (config, controller, serviço, 2 de schema).
- Nenhuma referência à base antiga sobrou dentro de `request()` — todas viraram `$base`. Sem trecho
  morto introduzido.

## 4. Erro? — NENHUM
- **Lint limpo** nos 6 arquivos VSM.
- **Suíte enterprise (51 testes) + 9 portões estáticos** verdes (route-check, parity, schema-static,
  consolidated `--check`, schema-runtime-ddl, vsm-openapi, tenant-scope, secret-hygiene).
- **Runtime:** salvar pela tela persiste `vsm_url` e `vsm_url_consulta`; `IntegrationConfig` relê
  ambos; `VsmService` resolve as duas bases; a URL de consulta passa pela **mesma validação
  anti-SSRF** da de gravação (`VsmEndpointSecurityService::validateBaseUrl`).
- A CI da PR #20 fechou verde nos 6 jobs, incluindo a matriz **MySQL 8.0 + MariaDB 11.4** (exercita
  a coluna nova em runtime) e o E2E.

## Veredito
A mudança está **aplicada de verdade, funcional, sem rota quebrada, sem código inativo e sem erro**.

## Ressalvas honestas
- **Não foi feita chamada real à VSM.** O host de teste é fictício e o anti-SSRF barra por DNS
  (comportamento correto). A prova de que consulta e gravação batem em URLs distintas **em
  produção** depende de configurar as URLs reais em Configurações → VSM.
- A separação de circuit breaker por API não foi introduzida: consulta e gravação compartilham o
  breaker `vsm` (comportamento anterior, sem regressão). Registrado como possível evolução futura,
  não aplicado.

## Como reverter
`git revert` do commit `847107a`. A coluna é opcional/idempotente; `vsm_url_consulta` vazio já
equivale ao comportamento anterior.
