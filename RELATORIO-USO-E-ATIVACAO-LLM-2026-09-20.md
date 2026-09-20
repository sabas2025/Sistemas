# RELATÓRIO — Uso do LLM no Hub e como configurar/ativar

**Data:** 2026-09-20 · **Release base:** V104.49.3-R7 · **Commit base:** `03a9164`
**Escopo:** explicar, com evidência de código, **como o LLM é usado no Hub hoje** e **como
configurá-lo e ativá-lo com segurança**. Documento informativo — não altera comportamento.

> Complementa o `RELATORIO-RUNBOOK-LLM-EXECUCAO-REAL-HOMOLOGACAO-2026-09-20.md` (o passo a passo
> operacional). Aqui o foco é o **modelo mental**: o que faz, quem chama, o que fica bloqueado.

## 1. A verdade de hoje: onde o LLM é (e não é) usado

**Fato verificado (grep em `app/`, `workers/`, `crons/`, `scripts/`):** os serviços LLM são
consumidos por **apenas dois pontos**:

- `app/Controllers/EnterpriseCoreController.php` → a tela **Governança LLM** (operada por humano);
- `app/Services/EnterpriseQualityGateService.php` → apenas `LlmGatewayService::readiness()`, uma
  leitura de status (não faz chamada).

**Nenhum worker, cron, webhook ou fluxo de negócio** (pedidos, estoque, fiscal, produtos) chama LLM.
E `allow_automatic_actions` é **forçado a `false`** em dois lugares de `LlmPolicyService` (não é
possível ligar pela tela). Conclusão: **a IA não age sozinha em lugar nenhum**; o LLM é uma
**facilidade de assistência auditável**, sempre disparada por uma pessoa na tela de Governança.

O que a tela oferece:

| Recurso | O que faz | Chama a rede? |
|---|---|---|
| Validador de prompt | detecta prompt-injection e estima custo, em **simulação** | Não |
| Fila de aprovação | cria solicitação → admin aprova/rejeita | Não |
| Execução real em homologação (B2+B5) | chamada real à Anthropic, **manual**, sob todos os gates | Sim (só aqui) |

Peças de resiliência **prontas, porém não fiadas** em fluxo automático: structured output (A1),
fallback + circuit breaker (A2), cliente HTTP (B1), tabela de preço por modelo (B3).

## 2. Arquitetura em camadas (o caminho de uma chamada real)

```
Admin na tela Governança LLM
  → [Política]   LlmPolicyService        (llm_policy_settings)   decide se pode
  → [Aprovação]  LlmApprovalService      (llm_approval_queue)    consentimento humano + hash do prompt
  → [Guardas]    LlmRealExecutionService.planExecution()         ambiente, custo, breaker, anti-troca
  → [Chave]      TokenVaultService        (token_vault, cifrada) só no header x-api-key
  → [Cliente]    LlmHttpClientService     (Messages API, SSRF-hardened)
  → [Custo+Trilha] LlmCostGuardService (llm_usage_daily) + llm_audit_logs
```

## 3. Campos de configuração (tela *Política LLM*)

Defaults **secure-by-default** (fonte: `LlmPolicyService::defaultPolicy()`):

| Campo | Default | Função |
|---|---|---|
| `enabled` | desligado | liga/desliga o subsistema |
| `allow_external_calls` | desligado | permite chamada real à rede (senão, só simulação) |
| `environment` | `homologacao` | homologação ou produção (produção é bloqueada para execução real nesta fase) |
| `provider` | `none` | provedor (cliente real implementado: **anthropic**) |
| `model` | vazio | ex.: `claude-haiku-4-5` |
| `max_tokens` | 2048 (cap 256–32768) | teto de saída |
| `temperature` | 0.20 (0–1) | criatividade |
| `require_human_approval` | ligado | exige aprovação antes de qualquer uso real |
| `allow_automatic_actions` | false (travado) | IA nunca altera Tiny/VSM/banco automaticamente |
| `prompt_injection_guard` | ligado | heurística anti-injeção |
| `redact_sensitive_data` | ligado | mascara CPF/CNPJ/tokens/e-mail em contexto e logs |
| `context_minimization` | ligado | trunca o contexto ao mínimo |
| `allowed_roles` | `admin,supervisor` | quem pode usar |
| `per_request_cost_limit` | US$ 1,00 | teto por chamada |
| `daily_cost_limit` | US$ 10,00 | teto por dia |
| `monthly_cost_limit` | US$ 100,00 | teto por mês |
| `max_input_chars` | 12000 (cap 1000–50000) | tamanho máximo do contexto |
| `audit_retention_days` | 180 | retenção da trilha |

## 4. Como configurar e ativar (resumo; passo a passo detalhado no runbook)

Tela **Governança LLM** (`index.php?page=llm-governance`), como **admin**:

1. **Política:** ligar `enabled` e `allow_external_calls`; `Homologação`, `anthropic`,
   `claude-haiku-4-5`; manter `require_human_approval`; limites de custo conservadores no 1º teste.
2. **Chave:** guardar a `sk-ant-…` (Ambiente Homologação) — fica cifrada no Token Vault.
3. **Aprovação:** criar solicitação com um prompt curto → **aprovar** (anotar o ID).
4. **Executar em homologação:** ID + **o mesmo texto** aprovado → confirmar.
5. **Resultado:** resposta + tokens + custo; trilha em `llm_audit_logs`/`llm_usage_daily`.

## 5. O que continua bloqueado mesmo com tudo ligado

- IA **não** cria/edita pedido, estoque, nota, produto, config ou log automaticamente.
- **Produção real** de LLM está fora de escopo desta fase (exige guardas de go-live, achado I-23).
- Toda chamada real é **manual, aprovada, custo-limitada e auditada**.

## 6. Como desligar / reverter

Basta **um** destes na política: desligar `enabled`, **ou** desligar `allow_external_calls`,
**ou** remover a chave do Token Vault. Qualquer um barra toda chamada real (volta à simulação).

## 7. Onde acompanhar

- **Prontidão:** os *checks* no topo da tela Governança LLM (e um tile no Enterprise Core).
- **Trilha:** `llm_audit_logs` (`simulado`/`bloqueado`/`permitido`, hash de entrada/saída, tokens, custo).
- **Custo acumulado:** `llm_usage_daily` (por dia/provedor/modelo/ambiente).

## 8. Ressalvas honestas

- **Nenhuma chamada real à Anthropic** foi feita ao produzir este documento; os ensaios usaram
  transporte falso. A 1ª chamada real depende da chave do operador e da ação manual nos passos acima.
- Este relatório descreve o estado em `03a9164`; **fiar** a IA a um fluxo de negócio (ex.: "explicar
  erro de integração") seria uma fase futura própria, mantendo a mesma governança.
