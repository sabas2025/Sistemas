# Relatório — Auditoria de Filas e Resiliência (Fase 7) — 2026-10-04

> Rodada de auditoria da **Fase 7 (Filas e resiliência)** das 12 fases do `CLAUDE.md`.
> **Natureza:** varredura read-only estática + testes de regressão. Nenhuma alteração de código.
> **Base:** `main` em `c47e0b9`.

## Escopo

Tabela · estados · locks · tentativas · retry · backoff exponencial · jitter · timeout ·
idempotência · prioridade · concorrência · DLQ · reprocessamento · poison message · jobs presos ·
circuit breaker · reconciliação. **Reprocessar não pode duplicar pedido, produto, nota ou
movimentação de estoque.**

## Método

- Inventário dos serviços de fila/resiliência (`app/Services/`) e das tabelas de fila no schema.
- Leitura do núcleo de concorrência do `QueueService` (`pegarProximo`, `marcarResultado`,
  `liberarTravados`, `heartbeat`, `reprocessar`) e dos invariantes de `RetryPolicyService`.
- Conferência do wiring de `CircuitBreakerService` no outbound e do carimbo de empresa na DLQ.
- Execução dos testes enterprise de resiliência (`v104_49_3_r7_backoff_jitter_test`,
  `v104_49_3_r7_vazao_fila_test`).

## Invariantes verificados

| Dimensão | Evidência | Veredito |
|---|---|---|
| Concorrência / lock | `pegarProximo` transacional: `SELECT … FOR UPDATE [SKIP LOCKED]` + `UPDATE … WHERE id=? AND status='pendente'`; `rowCount()!==1` → rollback | OK |
| Tentativas / backoff / jitter | `RetryPolicyService::attempts()`=3 (máx 5); jitter **aditivo** (teto 10%, piso 30s, limite 300s — G-03). Teste: 500 falhas no mesmo segundo → 31 instantes distintos, pico ≤10% | OK |
| Pontos de reagendamento | **3** (QueueService→RetryPolicy, EnterpriseIdempotencyGuardService, EstoqueEnterpriseService). O 4º (FiscalEnterpriseService) saiu com a remoção do Modelo A (#84); teste de jitter re-apontado, verde | OK |
| Posse / resultado obsoleto | `marcarResultado` exige `status='processando'` + `hash_equals(locked_by,$owner)`; resultado de worker que perdeu o item é descartado (`stale_worker_result`) | OK |
| Jobs presos | `liberarTravados` solta item por `lease_expires_at`/`heartbeat_at` expirado ou timeout de processamento | OK |
| DLQ | só em `falha_definitiva`; `DeadLetterQueueService::enviar` com `assertRow` de tenant, carimba `empresa_id` (I-13 intacto) e `ON DUPLICATE KEY (fila_id)` → insert idempotente (não duplica linha morta) | OK |
| Reprocessar sem duplicar | `reprocessar` com `FOR UPDATE` + `assertRow`; bloqueia item em processamento ativo (lease/timeout vivo), registra `fila_reprocessamento_historico`, reseta a `pendente tentativas=0`; o guard de idempotência no re-pickup impede efeito colateral duplicado | OK |
| Poison message | claim do `EnterpriseIdempotencyGuardService` após a reserva; não-permitido → marca guard-failure/ignored e recursa (cap `depth`=5) | OK |
| Prioridade | `ORDER BY FIELD(prioridade,'critica','alta','normal','baixa'), id ASC` | OK |
| Circuit breaker | `CircuitBreakerService` wired nos clientes outbound reais: `TinyV2Service`, `TinyV3Service`, `VsmService` | OK |
| `fila_fiscal` órfã? | sem worker drenando (removido no #84), mas o único alimentador também era morto → fila sempre vazia, sem itens presos; leitores de métrica mostram 0 | OK |

**Testes enterprise de resiliência:** `r7_backoff_jitter` e `r7_vazao_fila` — verdes.

## Veredito

Filas e resiliência sólidas. Nenhum achado Crítico/Alto/Médio; **nada acionável**. A remoção do
Fiscal Modelo A (#84) está corretamente refletida (3 pontos de reagendamento, não 4) sem deixar fila
órfã com itens presos.

## Não medido nesta rodada (precisa de banco vivo)

Os 5 cenários de runtime — retry reagenda para o futuro e incrementa tentativas; item não é
reentregue antes da hora; esgotar o limite manda para `falha_definitiva` + DLQ carimbada; item preso
por worker morto é solto; dois reprocessamentos não duplicam — foram medidos contra MariaDB real na
rodada anterior (ver tabela de validação no `CLAUDE.md`). Estaticamente nada regrediu. A remedição
contra MariaDB pode ser feita sob demanda (receita no `CLAUDE.md`).
