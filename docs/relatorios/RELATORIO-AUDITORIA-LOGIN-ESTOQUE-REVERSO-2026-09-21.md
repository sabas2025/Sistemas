# RELATÓRIO — Auditoria runtime: login/sessão (fase 8) e fluxo reverso de estoque (VSM→Tiny)

**Data:** 2026-09-21 · **Release base:** V104.49.3-R7 · **Commit base:** `59d6cfb` (main)
**Método:** ambiente provisionado em **MariaDB 10.11 real**; login por HTTP com os **limites reais do
código** (o provisionamento E2E afrouxa ~100×; restaurados 20/5 por IP e 10/3 por usuário) e serviços
de estoque exercitados por harness CLI. Documento informativo. **Sem alteração de código — nenhum defeito.**

## 1. Login / sessão (fase 8) — SÓLIDO

| Controle | Evidência medida |
|---|---|
| Flags de cookie de sessão | **HttpOnly + SameSite=Strict** (Secure condicional a HTTPS) ✅ |
| Fixação de sessão | `session_regenerate_id(true)` no login → **id muda** após autenticar ✅ |
| Mensagem de login | **genérica** ("usuário ou senha inválidos, ou bloqueado") — não revela usuário nem estado ✅ |
| Força bruta (limites reais) | bloqueia da ~4ª tentativa; a **senha correta é recusada** durante o bloqueio — confirmado na trilha `login_tentativas` ("IP/usuário temporariamente limitado"), não no texto da tela ✅ |
| Revogação por `session_version` | bump no banco → próxima requisição autenticada **302 → login?expired=1** ✅ |
| `deve_trocar_senha` | marca ligada (capturada no login) → **302 → trocar-senha** em toda rota autenticada, antes de qualquer dispatch de ação (gate P1-10) ✅ |

**Nota de método:** o texto da tela de login é genérico de propósito; o estado real (bloqueado vs.
inválido) só é confiável em `login_tentativas.mensagem` / trilha. A marca `deve_trocar_senha` é lida
da **sessão** (capturada no login) — para revogação imediata de sessão ativa usa-se `session_version`.
Ambos funcionam; a combinação cobre o caso de forçar troca em sessão já aberta.

## 2. Fluxo reverso de estoque (VSM → Hub → Tiny aplicando saldo) — SÓLIDO

| Verificação | Resultado |
|---|---|
| VSM → Hub | `receberAtualizacao('vsm')` → destino **tiny**, enfileirado em `fila_estoque`, movimento gravado ✅ |
| Idempotência / **anti-loop** | evento duplicado ignorado — hash único `origem\|referência\|sku\|tipo` em `estoque_eventos_sincronizacao` ✅ |
| Saldo auditado | `estoque_auditoria_sku` registra o saldo por evento ✅ |
| Worker aplica no Tiny | sem rede → **reagenda** (status pendente, tentativas=1), saldo não se perde ✅ |
| Isolamento | `fila_estoque` todas com `empresa_id`, zero nulos ✅ |
| Estoque negativo | recebido → **alerta** registrado (`estoque_alertas`) ✅ |
| Direção / política | `permitir_vsm_tiny` gateia a direção; `estoque_mestre=vsm` (VSM é a fonte autoritativa) ✅ |

**Ressalva honesta:** sem conectividade com Tiny/VSM, o envio efetivo ao Tiny falha (DNS/token) e o
item reagenda — comportamento correto. A prevenção de **loop entre provedores** ponta a ponta (Tiny
ecoando de volta o saldo que o Hub acabou de gravar) depende de ambas as conexões reais; o mecanismo
existe (dedup por hash de origem + política de mestre), mas o loop completo só é observável com os
dois provedores conectados.

## Veredito
Login/sessão e fluxo reverso de estoque estão **sólidos** e casam com o design documentado. **Nada a
corrigir.** Continua sem validação apenas a chamada real a Tiny/VSM (credenciais/rede).

## Pendência anotada para a próxima sessão
- **Autenticação de webhook ponta a ponta** (segredo compartilhado do Tiny `X-TINY-HUB-SECRET` + HMAC
  v2 da VSM: recusa por segredo/assinatura/CNPJ/IP, anti-replay) — foi parcialmente exercitada nos
  fluxos; convém uma bateria dedicada.
