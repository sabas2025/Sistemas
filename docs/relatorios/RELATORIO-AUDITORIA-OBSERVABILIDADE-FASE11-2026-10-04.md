# Relatório — Auditoria de Observabilidade (Fase 11) — 2026-10-04

> Rodada de auditoria da **Fase 11 (Observabilidade — Trace ID)** das 12 fases do `CLAUDE.md`.
> **Natureza:** varredura read-only estática. Nenhuma alteração de código foi aplicada.
> **Base:** `main` em `8ce9ac8`.

## Escopo

Trace ID único e correlacionado entre telas, logs, filas e integrações. Pesquisar um Trace ID deve
devolver origem, rota, usuário, operação, serviço, requisição, resposta, causa provável e ação
recomendada.

## Método (evidências medidas)

- Leitura do núcleo: `app/Core/RequestContext.php` (geração do trace), `app/Services/Audit.php`
  (gravação em `auditoria_eventos`, incluindo `causa_provavel`/`acao_recomendada`).
- Leitura da busca por trace: `app/Controllers/AuditoriaController.php` (`auditoria-detalhe`,
  `auditoria`, exportações CSV/JSON).
- Rastreamento do trace atravessando o limite da fila: `QueueService::pegarProximo`/`marcarResultado`,
  `IntegrationEventService::onQueueClaimed/onQueueFinished`, `ApiController::processarFila`,
  `workers/worker_fila.php`.
- `grep` de escritores/leitores de `evento_correlacao` em `app/` e `workers/`; leitura do schema em
  `database/modules/pedidos.sql`.

## Sinais verdes (medidos)

| Dimensão | Evidência | Veredito |
|---|---|---|
| Geração do Trace ID | `RequestContext::id()` → `TRC-YYYYMMDD-HHMMSS-XXXXXXXX` (4 bytes aleatórios = 32 bits); à meta de 500 ped/min (~8,3/s) a colisão de nascimento é desprezível | OK |
| Causa provável / ação recomendada | `Audit::event()` deriva `causa_provavel`/`acao_recomendada` de `ErrorCatalog::explain($codigo)` quando há `codigo_erro` e grava em `auditoria_eventos` (evento de sucesso não tem causa, por desenho) | OK |
| Busca por Trace ID | `AuditoriaController` `auditoria-detalhe` reconstrói a linha do tempo inteira por `trace_id`, aceitando `?id=` e `?trace_id=` (I-17 corrigido) | OK |
| Correlação por `fila_id` | `integration_events` e `PayloadSnapshotService` preservam o `trace_id` de origem **e** o `fila_id` do item — a correlação é alcançável por `fila_id` | OK (parcial — ver O11-2) |
| Fluxo **síncrono** (pedido Tiny inline, XML→Tiny) | trace único ponta a ponta dentro da mesma requisição | OK |

## Achados

### O11-1 — `evento_correlacao` nunca é escrita: tela sempre vazia + prontidão inflada
- **Identificação:** a tabela desenhada para correlacionar trace↔webhook↔fila↔pedido↔sku↔nf não é
  populada por nenhum componente.
- **Evidência:** zero `INSERT`/gravação em `evento_correlacao` em `app/` e `workers/` (varrido). As
  **4 únicas** referências são leitura/estrutura:
  - `app/Controllers/SecurityController.php:114` — `SELECT * FROM evento_correlacao ORDER BY id DESC LIMIT 80` (exibido numa tela);
  - `app/Core/Database.php:141` — mapa de tenant (`'evento_correlacao'=>'pedidos'`);
  - `app/Services/ModuleDatabaseService.php:48` — agrupamento de módulo para export/schema;
  - `app/Services/ProductionReadinessV24Service.php:34` — **+1 ponto** no score de prontidão por a tabela existir.
- **Arquivo/tabela:** `evento_correlacao` (schema em `database/modules/pedidos.sql:124`, com
  `trace_id`, `origem`, `tipo_evento`, `pedido_id`, `produto_sku`, `nf_chave`, `webhook_id`, `fila_id`).
- **Gravidade:** Média (funcional/operacional/manutenção).
- **Causa raiz:** feature de correlação com schema pronto, porém sem nenhum escritor na árvore atual
  (provável feature inacabada ou com escritor removido em limpeza anterior).
- **Impacto:** o painel de correlação renderiza **sempre vazio**; o score de prontidão premia uma
  tabela que não faz trabalho — um "indicador que mente" no sentido exato do `CLAUDE.md`
  ("indicador que mente é pior que indicador ausente").
- **Cenário de falha:** operador abre a tela de correlação de segurança para investigar um incidente
  e vê sempre "nenhuma correlação", concluindo erroneamente que não há atividade a correlacionar.
- **Correção recomendada (decisão de produto):** ou **(a)** ligar a escrita nos pontos naturais
  (recepção do webhook, enfileiramento e cada etapa do worker), ou **(b)** aposentar honestamente o
  painel e o ponto de score, se a correlação oficial for por `fila_id` via `integration_events`.
- **Risco da correção:** (a) baixo, aditivo (escrita best-effort em `catch(Throwable)`, como os
  demais pontos de observabilidade); (b) baixo, remoção de leitura morta. Ambos reversíveis.
- **Compatibilidade:** sem impacto em PHP/MySQL/hospedagem/Tiny/VSM; a tabela permanece (sem `DROP`).
- **Como testar:** (a) processar um fluxo e conferir linha em `evento_correlacao` com `trace_id`+`fila_id`;
  (b) conferir que a tela deixou de prometer um dado que não existe e o score não conta a tabela.
- **Como reverter:** `git revert` do commit; nenhuma migration de dados envolvida.
- **Status:** `confirmado`.

### O11-2 — `$traceOriginalFila` capturado e nunca usado; correlação atravessa a fila só por `fila_id`
- **Identificação:** o trace de origem do item é capturado no worker mas não é usado para
  correlacionar os eventos de processamento com a origem.
- **Evidência:** `app/Controllers/ApiController.php:578` atribui
  `$traceOriginalFila = !empty($item['trace_id']) ? (string)$item['trace_id'] : null;` com o
  comentário *"Não sobrescreve o Trace ID interno da requisição"* — e a variável **nunca mais é
  lida** (grep: 1 ocorrência no arquivo). `workers/worker_fila.php:33` chama `RequestContext::id()`
  (gera trace novo); `RequestContext` não expõe setter (lido o arquivo inteiro, 49 linhas).
- **Arquivo/classe/método:** `ApiController::processarFila`, `RequestContext::id`, `worker_fila.php`.
- **Gravidade:** Baixa.
- **Causa raiz:** o worker roda num processo distinto do webhook, com trace próprio; a ponte para o
  trace de origem foi prevista (variável capturada) mas não concluída.
- **Impacto:** em fluxo **assíncrono** (estoque/produto VSM→Tiny), buscar o Trace ID de **origem**
  (o do webhook) na tela de Auditoria **não** devolve os eventos de envio do worker — eles ficam
  sob o trace do worker. A correlação ponta a ponta existe, mas por `fila_id` em
  `integration_events`/snapshots, não por um único Trace ID.
- **Cenário de falha:** investigando um pedido que falhou no envio ao destino, o operador busca o
  Trace ID que apareceu na resposta do webhook e não encontra o evento de erro do worker.
- **Correção recomendada:** usar `$traceOriginalFila` para gravar um vínculo em `evento_correlacao`
  (resolve junto com O11-1), ou remover a variável morta se a correlação oficial for por `fila_id`.
- **Risco da correção:** baixo; não alterar o trace interno da requisição do worker (manter o
  desenho atual), apenas registrar o vínculo. Reversível.
- **Compatibilidade:** sem impacto externo.
- **Como testar:** enfileirar via webhook (trace T1), processar pelo worker (trace T2) e conferir que
  há vínculo T1↔T2↔`fila_id` recuperável por busca.
- **Como reverter:** `git revert`.
- **Status:** `confirmado`.

### O11-3 — mesma linha de fila gravada sob dois traces diferentes
- **Identificação:** snapshots de uma mesma `fila_id` carregam `trace_id` distintos conforme a camada
  que gravou.
- **Evidência:** `PayloadSnapshotService::registrar` chamado de `ApiController::processarFila` usa o
  `$trace` do worker (linhas 614, 646, 666, 686, 688); já `QueueService::marcarResultado` (linha 193)
  e `IntegrationEventService` usam `$item['trace_id']` (origem). Então `pedidos_payloads` de um mesmo
  `fila_id` pode carregar dois `trace_id`.
- **Arquivo/classe:** `ApiController::processarFila`, `QueueService::marcarResultado`,
  `IntegrationEventService`, `PayloadSnapshotService`.
- **Gravidade:** Baixa/Informativa.
- **Causa raiz:** consequência direta do O11-2 — a ausência de um trace único para o item na fase
  assíncrona.
- **Impacto:** correlação por `fila_id` continua íntegra; por Trace ID fica partida.
- **Como testar / reverter:** idem O11-2.
- **Status:** `confirmado`.

## Veredito

Núcleo de observabilidade sólido para o caminho **síncrono** e para **erros** (causa/ação
preenchidas pelo `ErrorCatalog`, busca por trace reconstrói a linha do tempo). O ponto fraco é a
**correlação ponta a ponta no fluxo assíncrono**: a tabela feita exatamente para isso
(`evento_correlacao`) está morta, e o trace de origem capturado no worker não é usado. Nenhum achado
Crítico/Alto; um Média (O11-1) e dois Baixa (O11-2, O11-3), todos com a mesma raiz e correção de
baixo risco — pendente de **decisão de produto** entre ligar a correlação ou aposentar o painel/score.

## Não medido nesta rodada (precisa de banco vivo)

A reconstrução de um trace assíncrono ponta a ponta contra MariaDB real (enfileirar por webhook,
processar pelo worker, conferir o que a busca por Trace ID devolve de cada metade) — a receita está
no `CLAUDE.md` e pode ser feita sob demanda. A auditoria aqui é estática: as gravações de trace por
camada foram lidas no código, não exercidas.
