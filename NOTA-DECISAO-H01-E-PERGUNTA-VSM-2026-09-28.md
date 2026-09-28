# Nota de decisão — H-01 (2+ empresas) e perguntas a VSM/Tiny

**Data:** 2026-09-28 · **Base:** `main` @ `6c6dbb9` (V104.49.3-R7) · **Natureza:** documento de decisão
e comunicação. **Não altera código.**

> Companion acionável do `RELATORIO-DESENHO-H01-MULTIEMPRESA-2026-09-21.md` (a análise completa das
> Opções A/B/C está lá). Esta nota **não repete** a análise: reúne (1) o que precisa ser decidido pelo
> responsável, com opções concretas, e (2) o texto pronto para enviar aos provedores. Governança:
> nada de código antes das respostas — construir agora seria **inventar configuração** (proibido).

---

## 1. Onde estamos (uma frase)

O H-01 está **resolvido para empresa única** e **explicitamente recusado (HTTP 409) para 2+ empresas** —
o Hub para em vez de gravar `empresa_id` NULL e vazar dado entre clientes. **Decisão de produto já
tomada (2026-09-21):** quando for suportar 2+ empresas, o modelo é a **Opção A — identidade de conexão
por empresa**. Falta destravar **3 pré-requisitos**; nenhum deles é código que o Hub decida sozinho.

---

## 2. As 3 decisões que travam o código (para o responsável)

Cada uma precisa de uma resposta antes de escrever qualquer linha. Marque a escolha e devolva.

### D1 — Roteamento do webhook: como o Hub sabe de qual empresa é o evento?
Duas formas de a autenticação **carregar** a identidade da empresa:

- **[ ] (a) Segredo por empresa** *(recomendado)* — cada empresa tem seu próprio segredo/HMAC de
  webhook; a URL do webhook é a mesma. O Hub resolve a empresa por **qual segredo autenticou**.
  *Implica:* o provedor precisa conseguir assinar cada empresa com um segredo distinto (ver D2 para a VSM).
- **[ ] (b) URL por empresa** — `/webhook/tiny/{empresa}` e `/webhook/vsm/{empresa}`; a empresa vem no
  caminho. *Implica:* o provedor precisa conseguir configurar uma URL de callback distinta por empresa.
- **Por que importa:** decide se o trabalho é em autenticação (a) ou em roteamento/URL (b). (a) é mais
  seguro (o segredo prova a origem); (b) depende de o provedor suportar URL por empresa.

### D2 — A VSM consegue assinar por empresa? *(fato externo — só a VSM responde)*
- **[ ] Sim** — a VSM pode usar um segredo/chave HMAC distinto por empresa do integrador → Opção A fecha
  o lado VSM.
- **[ ] Não / assinatura global** — a VSM assina toda a integração com uma chave só → **o lado VSM não
  fecha**, e forçá-lo arriscaria **recusar webhook legítimo da VSM** (proibição inegociável). Nesse caso,
  multiempresa com VSM fica inviável até a VSM evoluir; decidir se seguimos só com Tiny multiempresa.
- **Por que importa:** o payload da VSM **não carrega empresa nenhuma** (nem CNPJ). Sem assinatura por
  empresa, não há sinal para isolar a entrada da VSM. Este é o pré-requisito de maior risco. **Pergunta
  pronta na §3.**

### D3 — Credenciais de saída (envio ao Tiny/VSM) são próprias por empresa?
- **[ ] Sim, cada cliente tem as suas** — cada empresa tem token Tiny V2/V3 e token VSM próprios →
  Opção A integral (entrada **e** saída por empresa).
- **[ ] Não, compartilhadas** — várias empresas usam as mesmas credenciais de saída → cai na Opção C
  (isola só a entrada; saída segue global). Coerente só se o negócio for realmente esse.
- **Por que importa:** decide se `configuracoes_integracao` (hoje linha única `id=1` com todas as
  credenciais) precisa virar **uma linha por empresa** ou se só o segredo de webhook se multiplica.

**Sem D1+D2+D3 respondidos, o item fica parado — corretamente.** Recusar 2+ empresas é o comportamento
certo até lá.

---

## 3. Texto pronto para enviar à VSM

> **Assunto:** Assinatura de webhook por empresa (integração Conecta Venda ↔ Hub)
>
> Olá, equipe VSM.
>
> Estamos preparando o Hub de integração para operar com **mais de uma empresa** na mesma instalação.
> Para isolar com segurança os eventos de cada empresa, precisamos confirmar um ponto do webhook de vocês:
>
> **O webhook da VSM pode assinar cada empresa do integrador com um segredo/chave HMAC distinto, ou a
> assinatura é global por integração?**
>
> Em outras palavras: quando dispararem um webhook (pedido, produto, estoque, retorno de pedido), é
> possível que a assinatura HMAC use uma chave específica **por empresa** — de modo que, ao receber, nós
> identifiquemos a empresa de origem pela chave que validou a assinatura?
>
> Se **sim**, precisaremos de: como configurar/obter a chave por empresa, e se o cabeçalho de assinatura
> muda em algo. Se a assinatura for **global** (uma chave para toda a integração), nos digam também — isso
> muda o desenho do nosso lado, já que o corpo do webhook de vocês não traz identificador de empresa.
>
> Alternativa, caso a chave por empresa não seja viável: vocês conseguem configurar uma **URL de callback
> distinta por empresa** (ex.: `.../webhook/vsm/{empresa}`)? Qualquer um dos dois resolve para nós.
>
> Obrigado.

---

## 4. Texto pronto para o lado Tiny (se D1=(b) URL por empresa)

O webhook de **pedido** do Tiny já carrega **CNPJ** (validado contra `tiny_webhook_cnpj_autorizados`),
então o lado Tiny tem sinal mesmo com segredo compartilhado. A pergunta ao Tiny só é necessária se a
decisão D1 for **URL por empresa** ou se quisermos **segredo por empresa** também no Tiny:

> **Assunto:** Webhook por empresa (integração Tiny/Olist ↔ Hub)
>
> Para operarmos múltiplas empresas, o webhook do Tiny/Olist permite configurar, **por empresa**, ou (a)
> um **segredo de header distinto** (`X-TINY-HUB-SECRET`), ou (b) uma **URL de callback distinta**
> (ex.: `.../webhook/tiny/{empresa}`)? Hoje já validamos o CNPJ no corpo do pedido; queremos confirmar o
> mecanismo para as demais notificações (estoque, produto, situação), que nem sempre trazem CNPJ.

---

## 5. O que acontece depois das respostas (esboço, não código)

Com D1+D2+D3 respondidos, a implementação segue a **Opção A** (ver §4 e §6 do relatório de desenho):
`configuracoes_integracao` deixa de ser linha única (uma linha/tabela-filha por empresa, credenciais e
segredo de webhook próprios); `TinyWebhookSecurityService`/`WebhookSecurityService` passam a **devolver**
a empresa resolvida; migração suave da linha `id=1` → empresa 1 (preservando tokens e histórico);
validação contra banco real com 2 empresas (a mesma medição que provou o isolamento de empresa única).
**Nada disso começa antes das respostas** — a forma da tabela/config depende delas.

---

## 6. Resumo de uma linha

Decisão do modelo (Opção A) já tomada; falta **D1** (segredo vs URL por empresa), **D2** (a VSM assina
por empresa? — pergunta pronta na §3) e **D3** (credenciais de saída próprias por empresa?). Enviar §3 à
VSM é o desbloqueio de maior alavancagem, porque o lado VSM é o único sem sinal de empresa hoje.
