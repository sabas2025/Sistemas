# Relatório — Auditoria de Testes (Fase 12) — 2026-10-04

> Rodada de auditoria da **Fase 12 (Testes)** das 12 fases do `CLAUDE.md`.
> **Natureza:** varredura read-only estática. Correção aplicada: só o drift do `CLAUDE.md` (T12-N1).
> **Base:** `main` em `25afa56`.

## Escopo

Lint · análise estática · unitário · integração · banco · authn · permissões · webhook · OAuth ·
filas · retry · timeout · circuit breaker · DLQ · E2E · carga · regressão · instalação limpa ·
atualização · rollback.

## Método (evidências medidas)

- Leitura do runner `scripts/ci/enterprise-tests.sh` e do wrapper `scripts/ci/run-strict-test.php`.
- Inventário de `tests/enterprise/` (81 arquivos) e contagem efetiva de testes executados.
- Leitura dos dois workflows (`.github/workflows/hub-ci.yml`, `pwa-quality.yml`): jobs, portões,
  matriz de runtime, job E2E e agregador `gate`.
- Varredura de padrões de skip/early-exit mascarantes nos testes.

## Sinais verdes (medidos)

| Dimensão | Evidência | Veredito |
|---|---|---|
| Suíte enterprise | **79 testes** executados (81 arquivos − `_helpers.php`/`run_regression.php`, que não casam `*_test.php`) | OK |
| Rigor do runner | `run-strict-test.php` registra **qualquer** diagnóstico E_ALL via `set_error_handler`+`register_shutdown_function` e falha o teste; valida o caminho e exige `*_test.php` | OK |
| Sem masking do `set -e` | cada teste roda dentro de `if` (correção 2026-09-14); a suíte só falha no fim, com a lista completa; guarda `count==0` | OK |
| Sem falso verde | nenhum `markTestSkipped`/`exit(0)` mascarante — os 2 matches são asserções `str_contains($src,'exit(0)')` sobre código-fonte | OK |
| E2E | 6 specs / 23 testes; **skip = falha de gate** (A-03), **0 coletados = falha**, `HUB_BASE_URL` com barra servindo de `public/` | OK |
| Matriz de runtime | MySQL 8 + MariaDB 11.4; schema consolidado **e** modular; `r7-upgrade-runtime` single + modular (instalação nova **e** atualização) | OK |
| 14 portões estáticos | todos no `hub-ci.yml`; `build-classmap.php --check` presente; usa `check:pwa` (confere), **não** `build:pwa` (que reescrevia e mascarava) | OK |
| `cli-scripts-smoke` | roda **2×** (job estático + após provisionamento do E2E) — único jeito de pegar o I-21 | OK |
| `gate` agregador | único check obrigatório; falha se `static-enterprise`, `mysql-runtime` ou `e2e-authenticated` ≠ success | OK |

## Achado — T12-N1 (Informativa / drift de documentação)

### A contagem de testes enterprise no `CLAUDE.md` estava desatualizada
- **Evidência:** `CLAUDE.md` (seção "Portões de CI") dizia `enterprise-tests.sh` (**46 testes**); a
  suíte reporta `Enterprise regression OK: 79 testes`.
- **Gravidade:** Informativa (drift de documentação, não defeito de teste).
- **Impacto:** indicador desatualizado na memória do projeto (classe B2-N1).
- **Correção aplicada nesta rodada:** `46 testes` → `79 testes` no `CLAUDE.md` (linha do manifesto FIM
  regenerada). Nenhuma mudança em teste ou portão.
- **Status:** `corrigido e validado`.

## Lacunas honestas de cobertura (NÃO são defeito — já documentadas)

- **Ciclo OAuth Tiny V3 completo** e **chamadas reais ao Tiny/VSM**: dependem de credenciais reais;
  fora da CI por desenho.
- **Carga**: a evidência de gargalo (I-19, volume de um dia) foi medida **localmente**, não na CI —
  teste de carga em CI é caro e não está automatizado. Há cobertura estática/sintética em
  `v104_49_3_r7_capacidade_test` e `v104_49_3_r7_vazao_fila_test`.
- **Rollback de migration**: não há teste automatizado de rollback; as migrations documentam o
  rollback e são de execução **manual** por desenho. O `r7-upgrade-runtime` cobre a **atualização**,
  não a reversão.

"CI verde" cobre a tabela de validação do `CLAUDE.md`, não o resto.

## Veredito

Infraestrutura de testes robusta e honesta: 79 testes enterprise sob runner estrito (sem masking, sem
skip silencioso, sem falso verde sobre conjunto vazio), E2E que trata skip como falha, matriz de
runtime cobrindo os dois caminhos de schema, 14 portões estáticos e um agregador como único required.
**Nenhum achado Crítico/Alto/Médio.** Único item é o drift de contagem (T12-N1), corrigido. As lacunas
de cobertura são conhecidas e por desenho.
