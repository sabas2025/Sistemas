# RELATÓRIO — Teste de caos: queda de banco fazia webhook responder 409 (achado J-01)

**Data:** 2026-09-21 · **Release base:** V104.49.3-R7 · **Commit base:** `62a306a`
**Origem:** análise do documento `auditoria_resiliencia.md` enviado pelo responsável, seguida de
**teste de caos medido** (banco derrubado em MariaDB real). Documento de correção aplicada e validada.

## Contexto
O documento propunha réplica + circuit breaker de banco + cache-como-banco. A auditoria de código
mostrou que **nada disso existe** no Hub (um único host de banco; o CircuitBreaker protege
integrações externas e guarda estado **no próprio banco**; não há cache de dados de domínio). O Hub
é **write-first**: todo webhook de entrada escreve. A resiliência correta para esse desenho é o
**remetente reenviar** quando o Hub sinaliza indisponibilidade (5xx). O teste de caos foi feito
para verificar exatamente isso — e encontrou um defeito.

## Achado J-01 — formato do relatório

- **Identificação:** com o **banco fora**, toda rota — inclusive os webhooks de entrada de Tiny e
  VSM — respondia **HTTP 409** com o erro cru de SQL no corpo.
- **Evidência (medida, MariaDB real derrubado com `SHUTDOWN`):**

  | Rota | Banco de pé | Banco fora (ANTES) |
  |---|---|---|
  | `api/webhook/tiny/evento` e 5 outras Tiny | 401 | **409** |
  | `api/webhook/vsm/*` (4 rotas) | 401 | **409** |
  | `dashboard` | 302 | **409** |

  Corpo devolvido ao chamador: **`SQLSTATE[HY000] [2002] Connection refused`**.
- **Arquivo/método:** `IntegrationTenantService::enforceRequest()`, chamado a cada requisição por
  `FastRouteDispatcherService.php:73`. A resolução de tenant (`singleEmpresaId()`, linha 5) toca o
  banco logo de início; na queda, o **`PDOException`** — que **é** subclasse de `RuntimeException`
  (confirmado por `is_subclass_of`) — caía no `catch (RuntimeException){ http_response_code(409);
  echo $e->getMessage(); exit; }`.
- **Causa raiz:** `catch` largo demais. Não distinguia **erro de negócio** (tenant não vinculado →
  409 é o correto) de **falha de infraestrutura** (banco fora → deve ser 5xx).
- **Gravidade:** **Alta** — quebra de fluxo principal + vazamento de informação.
- **Impacto:**
  1. **Perda silenciosa de pedido.** 409 (Conflict) é lido por Tiny/VSM como duplicado/definitivo:
     eles **não reenviam**. Numa queda de banco, pedido/estoque/fiscal de entrada seriam
     **descartados sem retry** — o oposto da resiliência desejada.
  2. **Vazamento** do `SQLSTATE...` ao chamador — contra a regra "nunca devolver erro cru".
  3. A tela **503 de Recuperação** (correta) era **contornada**: o `enforceRequest` fazia `exit`
     antes de o tratador global de `public/index.php` mapear a queda de conexão para 503.
- **Cenário de falha:** banco cai 2 min; Tiny dispara webhook de pedido; recebe 409; marca como
  entregue/duplicado; o pedido nunca chega ao Hub.

## Correção aplicada
Em `IntegrationTenantService::enforceRequest()`, capturar `PDOException` **antes** do
`catch (RuntimeException)` e **deixá-la propagar** (`throw $e`) para o tratador global, que já
mapeia a queda de conexão para **503 + tela de Recuperação** (sem vazar mensagem). O `409` legítimo
para tenant real (`TENANT_*`) permanece intacto. Padrão idêntico ao que `MyOuroController` **já**
seguia corretamente (PDOException → 503 antes do 409).

- **Risco:** baixo — apenas estreita o `catch`; nenhum código HTTP de negócio foi alterado.
- **Compatibilidade:** PHP 8.x, MySQL/MariaDB, hospedagem compartilhada — inalterada.
- **Como reverter:** `git revert` do commit.

## Validação (medida DEPOIS)

| Verificação | Resultado |
|---|---|
| Webhooks Tiny/VSM + dashboard, **banco fora** | **HTTP 503** (era 409) ✅ |
| Corpo da resposta, banco fora | tela de **Recuperação** + Trace ID; **sem `SQLSTATE`** ✅ |
| Teste enterprise `v104_49_3_db_down_5xx_test.php` (novo) | passa; **reprova sobre o código antigo** (3 falhas) ✅ |
| Suíte enterprise | **52 testes** verdes (era 51) |
| Portões: lint, controller-route, tenant-scope, vsm-openapi, secret-hygiene, schema-runtime-ddl, classmap, cli-smoke | verdes |

## Resposta à pergunta original ("segunda opção quando o banco cai — é viável?")
Réplica/cache em PHP **não** é recomendável para este Hub (write-first, hospedagem compartilhada, e
os mecanismos morreriam junto com o banco). A resiliência viável é o **remetente reenviar** — que
agora funciona: banco fora → 503 → Tiny/VSM reenviam → pedido não se perde. Alta disponibilidade de
banco real é decisão de **infraestrutura** (MySQL gerenciado com failover), não de código.

## Ressalvas honestas
- Medição em MariaDB **10.11** local, não em produção. O que se prova aqui é o **código HTTP** e a
  **não-exposição** — o reenvio efetivo depende do comportamento de retry de Tiny/VSM em 5xx, que é
  do lado deles.
- Não foi introduzida réplica, cache de fallback nem circuit breaker de banco (decisão registrada,
  não aplicada).
