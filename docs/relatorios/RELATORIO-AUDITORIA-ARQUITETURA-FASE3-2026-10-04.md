# Relatório — Auditoria de Arquitetura (Fase 3) — 2026-10-04

> Rodada de auditoria da **Fase 3 (Arquitetura)** das 12 fases do `CLAUDE.md`.
> **Natureza:** varredura read-only. Nenhuma alteração de código foi aplicada nesta auditoria.
> **Base:** `main` em `bec3fb9` (após os PRs #83/#84/#85 — remoção do Fiscal Modelo A e limpeza do
> `DashboardController`).

## Escopo

Dimensões da fase 3 verificadas: responsabilidade misturada · controller gigante · regra em view ·
acesso direto ao banco fora da camada · serviço duplicado · classe legada · arquivo sem uso · método
ausente · chamada a método inexistente · dependência circular · código morto · acoplamento.

## Método (evidências medidas)

- Inventário por tamanho: `find app/Controllers app/Services -printf '%s %p'`.
- Classes órfãs: script que lê `storage/cache/classmap.php` (256 classes) e, para cada classe,
  procura referência por palavra inteira em `app/ workers/ public/ scripts/ tests/ views/` fora do
  próprio arquivo.
- Acesso a banco em view: `grep` por `->prepare/query/exec`, `new PDO`, `Database::*` em `views/`.
- `new PDO` fora de `app/Core`: `grep` em `app/` excluindo `app/Core/`.
- Acoplamento controller→controller: `grep` por `new [A-Z]*Controller(` fora do padrão de dispatch.
- Roteamento: leitura de `FastRouteDispatcherService` (`$directActions`, `$dispatchGroups`).

## Sinais verdes (medidos)

| Verificação | Resultado |
|---|---|
| Classes órfãs no classmap (256 classes) | **0** |
| Views com acesso direto a banco | **0** (de 126 views) |
| `new PDO` fora de `app/Core` | **0** |
| Acoplamento controller→controller escondido | **0** |
| `controller-route-check.php` (método ausente/duplicado nas rotas) | verde — 215 rotas, 254 classes |
| `DashboardController` vs teto de 160 KB | 15.037 bytes (folga ~148 KB) |

## Achados (todos baixa gravidade / informativos)

### A3-N1 — `ApiController` (61 KB): superfície grande, porém coesa
- **Evidência:** 18 métodos, todos de ingresso de API — webhooks VSM (`webhookVsmPedido/Produto/Estoque/RetornoPedido`), webhooks Tiny (`webhookTinyEvento/PedidoVsm/Estoque/Produto/NotaFiscal/SituacaoPedido`), `processarFila`, `notificacoesRecentes`, `marcarNotificacaoLidaApi`, `statusJson`. `ApiVsmWebhookController` e `ApiTinyController` **estendem** `ApiController` e apenas delegam (design "wrapper modular"; a lógica vive na base).
- **Arquivo/classe/método:** `app/Controllers/ApiController.php` — método mais pesado `processarFila()` (~236 linhas, 543→779).
- **Gravidade:** Informativa.
- **Causa raiz:** classe-base de webhooks com um método longo; **não** há responsabilidade misturada.
- **Impacto:** manutenção do método longo; nenhum risco funcional.
- **Cenário de falha:** n/a.
- **Correção recomendada:** opcional — extrair o corpo de `processarFila` para um serviço (o motor de fila já é `QueueService`). Só com motivo além de estética.
- **Risco da correção:** médio (caminho crítico de processamento de fila) — por isso **não** recomendada agora.
- **Compatibilidade:** —. **Como testar:** enterprise + E2E + medir `sistema.erro_fatal`. **Como reverter:** git revert.
- **Status:** `não identificado` como defeito.

### A3-N2 — `LegacyDatabaseUpgradeController` (47 KB): legado desligado, preservado de propósito
- **Evidência:** `dispatch()` recusa com `LEGACY_DB_UPGRADE_DISABLED` salvo liberação explícita e consciente; rota desconhecida → 404; `atualizar-v*` é interceptado por `MigrationController` **antes** dele; citado na allowlist de `SchemaRuntimePolicyService`.
- **Arquivo/classe:** `app/Controllers/LegacyDatabaseUpgradeController.php`.
- **Gravidade:** Informativa.
- **Causa raiz:** 47 KB de código legado guardado (escape hatch consciente).
- **Impacto:** peso de leitura; sem risco de execução acidental (guardado).
- **Correção recomendada:** **não remover** — proibição do `CLAUDE.md` ("excluir legado sem confirmar uso") e ele tem uso declarado. Remoção é decisão de produto.
- **Risco da correção:** alto se removido sem autorização (perde o escape hatch). **Status:** `confirmado` (legado deliberado, não defeito).

### A3-N3 — `workerCards()` duplicado em 2 serviços
- **Evidência:** `DashboardMetricsService::workerCards()` (3 workers: Estoque, Consulta VSM, Fila) e `OperationCenterService::workerCards()` (5 workers: + Backups, Notificações) repetem o laço `is_file()`+map, com **listas divergentes** e strings de detalhe diferentes; nenhuma das duas bate com os 12 workers reais de `workers/`.
- **Arquivo/classe/método:** `app/Services/DashboardMetricsService.php:169` e `app/Services/OperationCenterService.php:53`.
- **Gravidade:** Baixa.
- **Causa raiz:** duas listas de workers codificadas à mão; fonte de verdade espalhada.
- **Impacto:** manutenção — ao incluir/remover worker, é preciso lembrar dos dois pontos (foi o que aconteceu ao remover `worker_fiscal`/`worker_xml_nfe` no PR #84, que atualizou ambos).
- **Cenário de falha:** card de monitor mostrando lista defasada — sem impacto funcional.
- **Correção recomendada:** extrair o laço para um helper único, parametrizado pela lista (a divergência 3×5 pode ser intencional — o Centro de Operações mostra mais). Ganho pequeno.
- **Risco da correção:** baixo. **Compatibilidade:** total. **Como testar:** abrir dashboard e centro de operações, conferir os cards. **Como reverter:** git revert.
- **Status:** `confirmado`, não aplicado.

## Veredito

Arquitetura sólida. A campanha **Fase 3** (controllers dedicados, PRs #53–#58) somada às limpezas
recentes (#83 F1, #84 F2, #85 DashboardController) deixou o repositório **sem controller-deus vivo,
sem classe órfã, sem acesso a banco fora da camada e sem acoplamento controller→controller**. Não há
defeito de arquitetura Crítico/Alto/Médio. O único item acionável por código é o **A3-N3**
(consolidar `workerCards`), de baixo valor — e a própria regra da fase 3 ("não refatorar por
estética") desaconselha mexer sem motivo adicional.

## Pendências de arquitetura que NÃO são código (recap do `CLAUDE.md`)

- Multiempresa (2+ empresas): bloqueada em 3 perguntas à VSM/produto — não escrever código antes.
- `LegacyDatabaseUpgradeController`: remoção é decisão de produto (A3-N2).
