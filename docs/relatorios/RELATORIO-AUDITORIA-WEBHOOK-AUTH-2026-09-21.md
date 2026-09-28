# RELATÓRIO — Auditoria runtime de autenticação de webhook (Tiny + VSM)

**Data:** 2026-09-21 · **Release base:** V104.49.3-R7 · **Commit base:** `10c3f88` (main)
**Método:** ambiente provisionado em **MariaDB 10.11 real**; webhooks exercitados por **HTTP** com
requisições assinadas (HMAC v2 calculado no teste). Documento informativo. **Sem alteração de código —
nenhum defeito.**

> As duas autenticações são **diferentes por desenho** e foram medidas separadamente: o **Tiny** usa
> um **segredo compartilhado no header** (`X-TINY-HUB-SECRET`), a **VSM** usa **HMAC v2** assinado.

## 1. Tiny — segredo compartilhado (`TinyWebhookSecurityService`)

| Cenário | HTTP | Veredito |
|---|---|---|
| Sem segredo | **401** | bloqueado ✅ |
| Segredo errado | **401** | bloqueado (`hash_equals`) ✅ |
| Segredo correto | 422 | passa a auth (bloqueio adiante é de validação) ✅ |
| CNPJ autorizado (allowlist ligada) | 422 | passa ✅ |
| CNPJ não autorizado | **401** | bloqueado (`TINY_WEBHOOK_CNPJ_NOT_ALLOWED`) ✅ |
| CNPJ ausente com allowlist ligada | **401** | bloqueado ✅ |

## 2. VSM — HMAC v2 assinado (`WebhookSecurityService`)

Base canônica `v2:MÉTODO:/rota:timestamp:nonce:sha256(corpo)`, assinatura `sha256=HMAC`.

| Cenário | HTTP | Veredito |
|---|---|---|
| Sem nenhuma autenticação | **401** | bloqueado ✅ |
| HMAC v2 válido | 200 | aceito ✅ |
| Assinatura adulterada | **401** | bloqueado ✅ |
| Timestamp fora da janela | **401** | bloqueado ✅ |
| **Anti-replay por nonce** (mesmo nonce, corpo diferente) | **401** | bloqueado — "nonce já utilizado" ✅ |
| **Anti-replay por corpo** (nonce novo, mesmo corpo) | **401** | bloqueado — `IntegrationReplayGuardService` (defesa em profundidade) ✅ |
| Secret simples `X-Hub-Secret` correto (só local/homolog) | 200 | aceito ✅ |
| Secret simples errado | **401** | bloqueado ✅ |

## 3. Trilha
Todos os bloqueios são auditados: `webhook_requisicoes` registra `aceito`/`bloqueado`, e
`auditoria_eventos` grava os eventos de bloqueio de webhook. Medido: aceites e bloqueios contabilizados
corretamente em ambas as tabelas.

## Veredito
A autenticação de webhook está **sólida** nas duas superfícies: o Tiny recusa por segredo e por CNPJ;
a VSM recusa por assinatura, janela de tempo e **replay** (nonce **e** corpo), com o secret simples
aceito apenas em ambiente local/homologação. **Nada a corrigir.**

## Ressalva honesta
Em ambiente local/homologação o HMAC v2 **não é obrigatório** (`require_hmac_in_production` só exige em
produção/host público) — por isso o secret simples é aceito aqui. Em produção, ligar
`security.webhook_signature_require_v2` quando a VSM migrar torna o v2 obrigatório (pendência já
registrada no CLAUDE.md). O teste enviou HMAC v2 válido e provou que o caminho assinado e o anti-replay
funcionam independentemente dessa exigência.

---

Com este relatório, encerram-se as pendências de auditoria anotadas nesta linha de trabalho.
