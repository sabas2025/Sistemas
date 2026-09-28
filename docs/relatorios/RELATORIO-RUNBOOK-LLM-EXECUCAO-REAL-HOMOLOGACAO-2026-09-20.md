# RELATÓRIO — Runbook da 1ª execução real de LLM em homologação

**Data:** 2026-09-20 · **Release base:** V104.49.3-R7 · **Commit base:** `84a9cad`
**Escopo:** procedimento operacional para ligar, com segurança, a **primeira chamada real** ao
provedor LLM (Anthropic) em **homologação**, usando o subsistema já presente no Hub
(Fases A + B1 + B3 + B2+B5). **A execução real permanece desligada por padrão** — este documento
descreve os passos que o operador executa; não altera comportamento.

> Regra que governa: nenhuma chamada real sai para a rede sem, em conjunto, `enabled` **e**
> `allow_external_calls` **e** chave ativa no Token Vault **e** `environment=homologacao` **e** uma
> aprovação humana `aprovado` não expirada e coerente **e** o texto conferir com o `input_hash`
> aprovado (anti-troca) **e** custo dentro dos limites **e** circuit breaker fechado. Produção é
> deliberadamente fora de escopo nesta fase.

## Peças envolvidas (código real, sem invenção)

| Peça | Arquivo/rota | Papel |
|---|---|---|
| Tela de governança | rota `llm-governance` → `EnterpriseCoreController::llm()` · `views/llm_governance.php` | painel de política, chave, aprovação e execução |
| Política e gates | `LlmPolicyService::policy()` / `savePolicy()` · tabela `llm_policy_settings` | libera/bloqueia |
| Cofre da chave | `TokenVaultService::store()/getActive()` · tabela `token_vault` | chave cifrada; nunca em log/tela/corpo |
| Aprovação humana | `LlmApprovalService` · tabela `llm_approval_queue` (`input_hash`, `status`, `expires_at`) | consentimento + anti-troca |
| Execução real | `LlmRealExecutionService::executeHomologacao()` (rota `execute_homologacao`) | orquestra os gates e a chamada |
| Cliente HTTP | `LlmHttpClientService::anthropicMessages()` | Messages API: `x-api-key`, `anthropic-version: 2023-06-01` |
| Custo | `LlmCostGuardService` | estimativa + limites + registro (`llm_usage_daily`) |
| Trilha | `llm_audit_logs` (ENUM `permitido/erro/bloqueado`, `real_execution_allowed`, `output_hash`) | auditoria |

## Modelos e preços (conferidos com a referência Anthropic, cache 2026-06-24)

| Modelo | ID | Entrada US$/1M | Saída US$/1M |
|---|---|---|---|
| Haiku 4.5 | `claude-haiku-4-5` | 1,00 | 5,00 |
| Sonnet 5 | `claude-sonnet-5` | 2,00 | 10,00 |
| Opus 5 | `claude-opus-5` | 5,00 | 25,00 |

Recomendação para o **primeiro teste**: `claude-haiku-4-5` (mais barato; a chamada de smoke custa
centavos de centavo). Modelo fora desta tabela cai numa estimativa genérica conservadora no
`LlmCostGuardService`, sem deixar de proteger.

## Pré-requisitos

1. Chave de API da Anthropic (`sk-ant-…`) criada em `console.anthropic.com`, com crédito.
2. Login no Hub como **admin** (perfil que administra a Governança LLM).

## Passo a passo (tela **Governança LLM**, `index.php?page=llm-governance`)

1. **Salvar política** (card *Política LLM*):
   - **Ambiente:** `Homologação` (obrigatório; produção é recusada).
   - **Provider:** `Anthropic` · **Modelo:** `claude-haiku-4-5`.
   - **LLM habilitado:** ligado · **Chamadas externas:** ligado · **Exigir aprovação humana:** ligado.
   - **Limites conservadores para o 1º teste:** por requisição `0.10`, diário `1.00`, mensal `5.00`;
     `max_tokens` `256`; `temperature` `0.2`.
2. **Guardar a chave** (card *API key*): Provider `Anthropic`, Ambiente `Homologação`, cole a
   `sk-ant-…`. É cifrada no Token Vault; só vai ao header `x-api-key` na hora da chamada.
3. **Criar solicitação de aprovação** (card de teste → *Criar solicitação de aprovação*): digite um
   prompt curto, ex. `Responda apenas: homologacao OK`. Gera uma aprovação **pendente** com o hash
   exato do texto.
4. **Aprovar** (lista *Aprovações pendentes/recentes* → **Aprovar**): anote o **ID**. Expira em 24h.
5. **Executar em homologação** (card *Execução real em homologação*): informe o **ID** + **cole
   exatamente o mesmo texto** aprovado → confirme. Hash diferente → bloqueio, sem tocar a rede.
6. **Resultado:** a tela mostra a resposta do modelo, tokens (in/out) e custo (~US$); a trilha grava
   `llm_audit_logs.status=permitido`, `real_execution_allowed=1`, `output_hash`; o uso soma em
   `llm_usage_daily`.

## Guardas de proteção (todas verificadas em banco real)

- **Produção bloqueada** nesta fase; só homologação.
- **Custo:** estimativa acima de qualquer limite (requisição/dia/mês) → bloqueia **antes** de chamar.
- **Circuit breaker:** falhas em sequência do provedor abrem o breaker e pausam.
- **Chave** só no header `x-api-key`, cifrada no cofre — nunca no corpo nem em log.
- **Anti-troca:** o texto tem de conferir com o `input_hash` aprovado.

## Diagnóstico rápido (a tela lista o motivo exato)

- *"texto não confere com o prompt aprovado"* → cole o mesmo texto da aprovação.
- *"Sem chave no Token Vault"* → refaça o passo 2 (Ambiente **Homologação**).
- *"Chamadas externas bloqueadas" / "LLM desabilitado"* → revise o passo 1.
- Erro do provedor (ex. `401`) → chave inválida/sem crédito no console da Anthropic.

## Evidência do ensaio (2026-09-20, MariaDB descartável, **sem chave real**)

Ensaio ponta a ponta reproduzindo os 6 passos. Passos 1–5 pelas **rotas HTTP reais**; passo 6 em
duas frentes (bloqueio pela tela real; sucesso por harness com **transporte falso** e o resto real
no banco).

| Passo | Via | Resultado medido |
|---|---|---|
| 1–4 | HTTP | política com gates ON; chave (dummy) cifrada no cofre; aprovação `#1 aprovado` |
| 5 | HTTP | card *Execução real em homologação* renderiza para o admin |
| 6a — texto errado | HTTP (tela real) | **bloqueado**: "O texto informado não confere com o prompt aprovado (hash)"; tokens 0/0; **sem rede** |
| 6b — texto correto | harness (transporte falso) | **ok=true**; resposta de teste; tokens 9/3; custo ~US$0,000024; chave **no header, não no corpo** |

Trilha gravada:

```
llm_audit_logs:  #1 simulado  ·  #2 bloqueado  ·  #3 permitido (real_execution_allowed=1, 9/3, output_hash)
llm_usage_daily: requests=2  blocked=0  custo 0.000024   (o bloqueio não conta como requisição)
```

**Única diferença na execução real:** no passo 6b, em vez da resposta do transporte falso, virá a
**resposta real da Anthropic** — e o custo real (com haiku, centavos).

## Ressalvas honestas

- **Nenhuma chamada real à Anthropic foi feita** nesta sessão — o transporte do ensaio foi falso de
  propósito. A 1ª chamada real depende da sua chave e da sua ação nos 6 passos acima.
- **Produção real de LLM** seria uma fase futura própria, com as guardas de go-live (coerência
  ambiente × endpoint, achado I-23), fora do escopo deste runbook.

## Como reverter

Este documento é informativo; não altera código. Para desligar a execução real a qualquer momento,
desligue **LLM habilitado** ou **Chamadas externas** na política (card *Política LLM*), ou remova a
chave do Token Vault — qualquer um dos três já barra toda chamada.
