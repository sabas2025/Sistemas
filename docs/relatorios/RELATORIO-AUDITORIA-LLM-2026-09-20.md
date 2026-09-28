# Relatório de auditoria da plataforma LLM — `main` `V104.49.3-R7+20260917.1`

Data: 20/09/2026
Branch de origem: `main` @ `51a2386`
Base metodológica: prompt de auditoria LLM enterprise fornecido pelo responsável.
Natureza: **auditoria read-only** — nenhuma alteração de código, schema, rota ou comportamento.

## Regra que governou esta auditoria

> Comece sempre pela auditoria. Não altere modelo, prompt, agente, ferramenta,
> MCP, memória ou RAG antes de apresentar evidências, impacto e plano.

Cada afirmação distingue **fato · evidência · hipótese**. Onde não há evidência,
consta literalmente **"Não identificado com as evidências disponíveis."**

## 1. Inventário — o que existe de fato

| Componente | Arquivo | Responsabilidade |
|---|---|---|
| Política central | `app/Services/LlmPolicyService.php` | acesso por perfil, cofre de chave, redação, limites, guardrails |
| Gateway | `app/Services/LlmGatewayService.php` | valida prompt, injeção, custo — **simula, nunca executa IA real** |
| Guarda de custo | `app/Services/LlmCostGuardService.php` | estimativa de token/custo; teto por requisição/dia/mês |
| Aprovação humana | `app/Services/LlmApprovalService.php` | fila `pendente → aprovado/rejeitado`, expira em 24h |
| UI/rota | `app/Controllers/EnterpriseCoreController::llm()` + `views/llm_governance.php` | `page=llm-governance` (admin/supervisor, POST com CSRF) |
| Schema | `database/update_v104_33_llm_governance_secure.sql` | `llm_policy_settings`, `llm_approval_queue`, `llm_usage_daily`, `llm_audit_logs` |
| Config | `config/config.example.php` (bloco `llm`) | `enabled=false`, `provider=none`, limites, flags de segurança |

## 2. Veredito geral (fato, medido)

**O subsistema LLM do Hub é uma camada de governança e simulação, segura por
padrão — e está correta.** Evidências:

- `enabled=false`, `provider=none`, `allow_external_calls=false` no
  `config.example.php`.
- **Nenhum cliente HTTP de LLM real existe no código.** Medido: grep por
  `api.openai.com`, `api.anthropic.com`, `generativelanguage.googleapis`,
  `/v1/messages`, `/v1/chat/completions` → **zero ocorrências**.
- `real_execution_allowed = false` fixo no gateway
  (`LlmGatewayService::validatePrompt`, comentário "nunca executa IA real
  automaticamente").
- `allow_automatic_actions` forçado a `false` no `savePolicy()` **e** fora de
  produção (`LlmPolicyService::policy()`) — guardrail duplo.
- Chaves só no **Token Vault** (`storeApiKey`), nunca exibidas; redação de
  token/CPF/CNPJ/e-mail/telefone/CEP aplicada ao contexto, **inclusive em texto
  livre** (`SensitiveDataService::maskJson` trata o caso não-JSON); contexto
  truncado por `max_input_chars`.
- A rota `llm-governance` foi validada na auditoria de rotas
  (`RELATORIO-AUDITORIA-ROTAS-2026-09-20.md`): **200 para admin, 403/CSRF nas
  mutações**.

**Não há nada quebrado nem inseguro para corrigir.** As sugestões abaixo são
todas **aditivas e opcionais**, sem tocar em código ou rota que funciona hoje.

## 3. Achados e sugestões (nenhum quebra código ou rota)

### A-LLM-01 · Tabela de preços genérica · Gravidade: Informativa · Status: confirmado
- **Evidência:** `LlmCostGuardService::estimateCost()` usa `$0.005/$0.015` por 1k
  fixos, com heurística por substring (`mini`/`flash`/`llama`). O próprio
  comentário reconhece: "substituir por tabela de preços atualizada por modelo".
- **Impacto no custo hoje:** **nulo** — nenhuma execução real gera custo; é
  estimativa de teto defensivo.
- **Correção sugerida (aditiva):** quando/se ligarem execução real, uma tabela
  `provider+model → preço` vinda de config, sem hardcode. Não altera assinatura
  de método nem rota.
- **Rollback:** trivial (a estimativa genérica continua como fallback).

### A-LLM-02 · Estimador de tokens por bytes/4 · Gravidade: Baixa · Status: confirmado
- **Evidência:** `estimateTokens()` = `strlen/4`. Em pt-BR (UTF-8 acentuado)
  **superestima** tokens — conservador para o teto de custo, portanto seguro.
- **Sugestão:** registrar; trocar por um tokenizer aproximado só se precisão de
  custo virar requisito. Não aplicar agora.

### A-LLM-03 · Guarda de prompt injection por regex (6 padrões) · Gravidade: Baixa · Status: confirmado
- **Evidência:** `LlmGatewayService::validatePrompt()` cobre "ignorar
  instruções", pedido de segredo, SQL destrutivo, ação operacional sensível e
  tentativa de burlar governança, aplicados ao texto **original** (correto para
  detecção). Escala de risco `medio/alto/critico`.
- **Impacto:** baixo — sem execução real, a injeção não alcança modelo; a
  separação dados/instrução já existe (`minimizeContext`).
- **Sugestão:** se ligarem execução real, complementar (base64, homoglyphs,
  injeção indireta) — não antes.

## 4. O que NÃO existe (honestidade)

O prompt pede auditar **roteamento, fallback, circuit breaker LLM, RAG,
embeddings, banco vetorial, agentes, MCP, function calling, structured output,
streaming, memória de longo prazo**. **Não identificado com as evidências
disponíveis** — nada disso está implementado no Hub, e isso é **decisão de
projeto** (V104.33: IA desativada, sem chamadas externas), não defeito. Nenhum
desses componentes foi inventado neste relatório.

## 5. Recomendação

**Não mexer no LLM agora.** Está desligado, correto e sem risco. As três
sugestões são para o dia em que decidirem **ligar execução real** — e, na ordem
do próprio prompt: preço-por-modelo (A-LLM-01), homologação com aprovação humana
(já existe), e só então roteamento/fallback. Cada uma é aditiva e reversível.

## 6. Escopo do que foi provado

Cobre o inventário do subsistema LLM, a leitura integral dos quatro serviços, do
controller, do schema e da config, e a medição por grep de ausência de cliente
HTTP de LLM. **Não** cobre comportamento sob execução real (que não existe) nem
integração com provedor externo (nenhuma configurada). Conforme a regra do
projeto, a auditoria **não** declara o sistema "100% seguro".

## 7. Ação

Auditoria **read-only** — nenhuma alteração de código, schema ou comportamento.
Este documento apenas registra o resultado.
