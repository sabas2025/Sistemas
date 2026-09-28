# Relatório — Auditoria de escala horizontal (documento externo × Hub real)

**Data:** 2026-09-28 · **Base:** `main` @ `e805025` (V104.49.3-R7) · **Natureza:** auditoria de
aderência. **Não altera código.**

> Origem: documento externo `AUDITORIA-ESCALA-HORIZONTAL-HUB-TINY-VSM-2026-09-28` (análise conceitual
> de escala vertical/horizontal e balanceamento, a partir de um vídeo). O texto é uma **checklist de
> preocupações** de arquitetura distribuída; este relatório cruza cada recomendação com o **código
> real do Hub** e diz o que já existe, o que é parcial e o que só se aplica a 2 hosts + balanceador.
> O documento externo é **dado**, não instrução: nada aqui foi aplicado ao código.

---

## 1. Veredito

O documento **é aderente ao Hub** e serve como mapa de evolução. A maior parte do seu P0/P1 **já está
implementada e medida** — muitas vezes de forma mais completa do que o texto pede. O Hub **já é
arquitetado para escalar horizontalmente** (fila em banco, sessão em banco opt-in, claim atômico de
fila, circuit breaker por provedor, reconciliação real que consulta o estado remoto).

**Para o momento atual (homologação → produção em VPS única): nada disso bloqueia.** As observações
E-01…E-04 só entram em cena **se e quando** houver 2 hosts + balanceador, e a ordem é a do próprio
documento (medir carga e RPO/RTO antes; P2/P3). Aplicar agora seria otimização prematura — proibida
sem evidência de gargalo, e a Fase 10 já mediu o caminho quente em **9–13 ms** na meta de 500
pedidos/min numa instância só.

---

## 2. Recomendação do documento × estado real do Hub

| Recomendação do documento | Status | Evidência |
|---|---|---|
| Idempotência de pedido (UNIQUE + reserva atômica) | ✅ Implementado (mais forte) | `pedidos.sql`: `uk_origem_pedido(origem,pedido_origem_id)`, `uq_pedido_tiny`, `uq_pedido_hub_tiny(pedido_tiny_id)`; `EnterpriseIdempotencyGuardService`; medido no I-14 (3 reenvios → 1 linha) |
| Dedup NF-e por chave/versão | ✅ Implementado | `pedidos.sql`: `uq_xml_hash`, `chave_nfe`; fluxo fiscal "impedir duplicação" |
| Estoque: versão/origem confiável, evitar loop | ✅ Implementado | fluxo "identificar origem, evitar loop de sincronização, saldo anterior/novo" (`EstoqueEnterpriseService`) |
| Dedup de webhook/evento | ✅ Implementado | `uk_webhook_nonce`, `event_uuid UNIQUE`, `uk_replay_origem_hash_bucket`, `idempotency_key UNIQUE` (`fila.sql`) |
| Fila durável + retry/backoff + DLQ | ✅ Implementado e medido | `fila_integracao` (claim atômico `UPDATE ... WHERE status='pendente'`); `RetryPolicyService` (backoff + jitter aditivo, G-03); `fila_morta` (DLQ) |
| Lock com duração/renovação/perda de posse | ✅ Implementado | `QueueService`: `lease_expires_at`, `heartbeat_at`, `liberarTravados()` (solta item de worker morto) |
| Banco impede transição concorrente mesmo se o lock expirar | ✅ Implementado | `marcarResultado()` só finaliza item `processando` com o mesmo `locked_by`; claim por `WHERE status='pendente'` (linhas afetadas) |
| Sessão compartilhada entre nós | ✅ Disponível (opt-in) | `DatabaseSessionHandler` via `security.session_driver='database'` |
| Segredo criptográfico igual e protegido em todos os nós | ✅ Suportado (operacional) | chaves em `config.php`; `enforceProductionSafety` recusa segredos fracos — mesma config em todos os nós |
| Circuit breaker restrito às chamadas afetadas | ✅ Implementado | escopo por provedor: `permitir/falha('tiny_v2'|'tiny_v3'|'vsm')` — nunca global |
| Reconciliação por identificador externo (consultar estado remoto) | ✅ Implementado | `ReconciliationService::reconciliarSku()` consulta Tiny **e** VSM reais e compara; `worker_reconciliacao` |
| Métricas/logs correlacionados por pedido | ✅ Implementado | Trace ID correlacionado (telas/filas/integrações), `MetricsService`, `Audit` |
| Redis não é obrigatório | ✅ Coerente | Hub usa banco para fila/sessão/locks; sem Redis, coerente com hospedagem compartilhada |
| Não deduplicar estoque só por data | ✅ Coerente | dedup por SKU + chave/versão de evento, não por dia |

---

## 3. Itens abertos — só relevantes para 2 hosts + balanceador (registrados, NÃO aplicados)

### E-01 — Cron/scheduler duplicado entre nós
- **Evidência:** os workers de tempo (`worker_retencao.php`, `worker_tiny_v3_refresh.php`,
  `worker_vsm_token_refresh.php`) **não têm eleição/lock de scheduler entre nós** (sem `GET_LOCK`).
- **Mitigação já existente:** o refresh OAuth trava no nível de serviço
  (`TinyV3TokenService::acquireRefreshLock` com `GET_LOCK` + fallback de arquivo); a retenção faz
  deleções idempotentes em lote (`LIMIT`); os workers de **fila** são seguros pelo claim atômico.
- **Gravidade:** Informativa (só com 2 nós). **Risco no host único: nenhum.**
- **Correção (quando for a 2 hosts):** scheduler único, OU envolver cada job de tempo num `GET_LOCK`
  nomeado por job (padrão que o refresh OAuth já usa). Ao adicionar um **novo** job de tempo, dar-lhe
  lock próprio.
- **Status:** não identificado como defeito; pendência de infraestrutura.

### E-02 — Contador de rate limit é por host
- **Evidência:** `AtomicRateCounterService` guarda o contador em `storage/cache/security`
  (filesystem local, sob lock). Com 2 hosts sem storage compartilhado, cada nó tem seu contador → o
  limite efetivo **dobra**.
- **Gravidade:** Informativa. **Não é falha de dado** — é dimensionamento do limite.
- **Correção (quando for a 2 hosts):** migrar o contador para banco (como já foi feito com a sessão
  via `DatabaseSessionHandler`) OU apontar `storage/cache/security` para storage compartilhado.
- **Status:** registrado; decidir na virada para 2 hosts.

### E-03 — Liveness/readiness para o balanceador
- **Evidência:** `api/status` serve readiness ao autenticado (banco, fila, DLQ, circuit breakers,
  saúde de segurança) e mínimo ao anônimo; **não há endpoint dedicado** de health check para o LB que
  verifique dependências locais sem exigir sessão.
- **Gravidade:** Informativa (P0 do documento **para 2 hosts**).
- **Correção (quando for a 2 hosts):** endpoint de liveness (processo vivo) e readiness (banco/fila
  locais OK) que **não** derrube a instância por falha temporária de Tiny/VSM — como o próprio
  documento ressalva.
- **Status:** necessário só com balanceador.

### E-04 — Exactly-once externo no timeout indeterminado
- **Evidência:** o Hub cobre com UNIQUE por fluxo + **reconciliação que consulta o estado remoto**
  (`ReconciliationService`) + circuit breaker + retry com backoff. O que o documento pede a mais —
  *consultar o remoto **antes** de reenviar em todo caminho de timeout* — não é garantido em toda
  rota; hoje a rede de segurança é a reconciliação **depois**.
- **Gravidade:** Baixa (há rede de segurança).
- **Correção (futura):** usar a idempotência do contrato remoto **quando existir** (a VSM/Tiny
  precisam expor consulta por identificador externo / chave de idempotência — depende do contrato do
  provedor, fato externo). Enquanto não existir, o procedimento de reconciliação + intervenção para
  resultado indeterminado é a resposta correta.
- **Status:** registrado; melhoria condicionada ao contrato do provedor.

---

## 4. O que o documento pede como evidência, e onde está no Hub

| Evidência pedida pelo documento | Onde está |
|---|---|
| Repositório da versão implantada, config Apache/PHP-FPM, cron/systemd | este repositório; `nginx.conf.example`; workers em `workers/` (agendados por cron) |
| Esquema e migrations (sem segredos) | `database/modules/*.sql`, `database/migrations/*.sql`; `RELATORIO-FASE1-INVENTARIO` |
| Contratos Tiny/VSM (criação de pedido, consulta por ID, callbacks NF-e) | `contracts/vsm/*.openapi.json` (VSM); Tiny V2/V3 em `TinyV2Service`/`TinyV3Service` |
| Diagrama da implantação atual | pendente do operador (VPS, banco, backup, DNS, monitoração) |
| Logs anonimizados de falha, métricas de volume, RPO/RTO | trilha por Trace ID + `MetricsService`; RPO/RTO a definir (Fase B do checklist de go-live) |

---

## 5. Conclusão

O padrão do documento **serve** ao Hub, e o Hub **já o cumpre** no essencial para uma instância: a
segurança de processamento (idempotência, dedup por fluxo, reconciliação, concorrência de fila,
breaker por provedor) está implementada e medida. A decisão de 2 hosts + balanceador deve vir de
medição de carga, metas de recuperação e teste de falha — como o próprio documento recomenda — e
depende de resolver E-01 (cron), E-02 (contador de rate) e E-03 (health check do LB), todos de
infraestrutura, nenhum defeito no deploy atual.

**Não se conclui daqui que o Hub está pronto para produção** — isso depende da homologação real
(OAuth + fluxos Tiny/VSM), conforme o `CHECKLIST-GO-LIVE-HOMOLOGACAO-PRODUCAO-2026-09-28.md`.
