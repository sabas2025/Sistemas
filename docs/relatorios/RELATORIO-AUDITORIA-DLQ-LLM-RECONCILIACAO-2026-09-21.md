# RELATÓRIO — Auditoria runtime: resiliência de fila/DLQ, execução real do LLM e reconciliação de estoque

**Data:** 2026-09-21 · **Release base:** V104.49.3-R7 · **Commit base:** `a82efcc` (main)
**Método:** ambiente provisionado em **MariaDB 10.11 real**; serviços reais exercitados por harness CLI
e leitura do banco. Documento informativo.

> **Ressalva:** sem conectividade real com Tiny/VSM/Anthropic (sem credenciais; anti-SSRF barra hosts
> fictícios). Exercita-se a **lógica interna**; a chamada externa efetiva falha honestamente (DNS /
> token ausente), o que é o comportamento correto sem ambiente conectado.

## 1. Resiliência de fila e fila morta (DLQ) — SÓLIDO

| Cenário | Resultado |
|---|---|
| Falha temporária → retry + backoff | `marcarResultado(false)` incrementa tentativas e agenda `proxima_tentativa` no **futuro** (não reentrega antes da hora) ✅ |
| Esgotar tentativas → fila morta | linha em `fila_morta` criada **carimbada com `empresa_id=1`** (I-13 ida) ✅ |
| Reprocessar da DLQ → volta à fila | **+1 linha, sem duplicar**; nova `fila_integracao` **herda `empresa_id=1`** (I-13 volta) ✅ |
| Worker morto → item preso | `liberarTravados()` solta (status→pendente, lock limpo, empresa preservada) ✅ |
| Isolamento | 4/4 `fila_integracao` e 1/1 `fila_morta` com empresa, **zero nulos** ✅ |

**Nota de método:** tentar simular "empresa 2" via sessão em contexto CLI passa — **não é defeito**:
em contexto externo (CLI/worker/webhook) `currentEmpresaId()` resolve pela empresa **vinculada**
(`boundEmpresaId`), ignorando a sessão de propósito (sessão é forjável). Isolamento cross-empresa real
depende de 2+ empresas (item **H-01**, aberto por decisão de produto).

## 2. Execução real do LLM (B2+B5) — SÓLIDO, secure-by-default

`LlmRealExecutionService::planExecution()` — os 7 portões **bloqueiam** corretamente:

| Portão | Verificado |
|---|---|
| Ambiente ≠ `homologacao` | bloqueia ✅ |
| Provider ≠ `anthropic` | bloqueia ✅ |
| Anti-swap: hash do prompt ≠ hash aprovado | bloqueia ✅ |
| Sem chave de API (Token Vault) | bloqueia ✅ |
| Sem aprovação humana | bloqueia ✅ |
| Custo estimado acima do limite | bloqueia ✅ |
| Caminho feliz (transporte fake) | libera e retorna texto ✅ |

**Auditoria** (`llm_audit_logs`, medida): sucesso → `status='permitido'`, `real_execution_allowed=1`,
e grava o **HASH da saída (`output_hash`), nunca o texto cru** (o texto confidencial não aparece em
nenhuma coluna do registro); bloqueio → `status='bloqueado'`, `real_execution_allowed=0`.
**Sem validação apenas** a chamada real à Anthropic (depende de chave real).

## 3. Reconciliação de estoque — funcional, com 1 achado

| Cenário | Resultado |
|---|---|
| `registrarManual` estoque igual | `status='ok'`, diferença 0 ✅ |
| `registrarManual` estoque diferente | `status='divergente'`, diferença correta, **notificação criada** ✅ |
| `resumo()` | total e divergentes corretos ✅ |
| Isolamento | todas as linhas de `estoque_reconciliacao` com `empresa_id=1`, zero nulos ✅ |
| `reconciliarSku()` sem rede | **propaga exceção** de DNS do VSM — ver **L-01** |

### Achado L-01 — `reconciliarSku()` não degrada quando o VSM/Tiny está indisponível

- **Gravidade:** Média (robustez/UX; sem perda de dado).
- **Evidência:** com o VSM inalcançável, `ReconciliationService::reconciliarSku()` **lança**
  `Host VSM não pôde ser resolvido por DNS` — `VsmService::consultarEstoque()` lança, enquanto o lado
  Tiny (`consultarProduto`) devolve **array de erro**. Comportamentos inconsistentes.
- **Arquivo:** `DashboardController::reconciliacaoExecutar()` (linha ~1872) chama
  `ReconciliationService::reconciliarSku($sku)` **sem `try/catch`**.
- **Impacto:** o operador que clica *Reconciliar* (sem informar quantidades) com o VSM
  temporariamente fora recebe a **tela de Recuperação genérica** em vez de uma mensagem amigável
  ("VSM indisponível, tente depois"), e **nenhum registro** de reconciliação é criado. Sem
  vazamento (o tratador global não expõe stack trace) e sem corrupção — é robustez/UX.
- **Correção recomendada:** envolver a consulta externa (no serviço ou no controller) para que uma
  falha de provedor **registre a tentativa/audite** e redirecione com mensagem amigável
  (`?erro=provedor_indisponivel`), consistente com o padrão do `MyOuroController` (que já captura e
  responde amigável). Não altera o caminho de sucesso nem regra de negócio.
- **Risco da correção:** baixo. **Como testar:** exercitar `reconciliacao-executar` com o VSM
  inalcançável e conferir redirect amigável + evento de auditoria, sem tela de Recuperação.
- **Status:** confirmado (registrado; correção proposta, a aplicar sob autorização).

## Veredito
DLQ/resiliência e execução real do LLM estão **sólidos** e casam com o design documentado. A
reconciliação funciona (comparação, divergência, notificação, isolamento), com **um achado de
robustez (L-01)** na reconciliação *real* quando o provedor externo está fora. Continua sem validação
apenas a chamada real a Tiny/VSM/Anthropic (credenciais/ambiente de produção).
