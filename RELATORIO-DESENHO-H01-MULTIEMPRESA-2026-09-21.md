# RELATÓRIO DE DESENHO — H-01 para 2+ empresas (isolamento multiempresa na entrada sem sessão)

**Data:** 2026-09-21 · **Base:** `main` @ `70e20e0` (V104.49.3-R7) · **Natureza:** análise de decisão de produto.
**NÃO É ALTERAÇÃO DE CÓDIGO.** Apresenta o estado real, as opções, o impacto e uma recomendação, para
que a decisão adiada possa ser tomada com evidência. Governança: *"Comece sempre pela auditoria. Não
aplique alterações antes de apresentar evidências e impacto."*

---

## 1. O estado REAL hoje (medido no código, não no resumo)

O H-01 está **resolvido para instalação de empresa única** e **explicitamente barrado — não silenciosamente
quebrado — para 2+ empresas**. Três fatos, com arquivo e linha:

| Fato | Evidência |
|---|---|
| A entrada sem sessão (webhook/worker/cron) resolve a empresa por **vínculo**, não por sessão | `TenantContextService::currentEmpresaId()` (linha 10-11): em contexto externo (`PHP_SAPI==='cli'` ou rota `api/*`) devolve `IntegrationTenantService::boundEmpresaId()` |
| `boundEmpresaId()` exige **exatamente uma empresa ativa** E o vínculo da integração a ela | `IntegrationTenantService::singleEmpresaId()` lança `TENANT_SINGLE_COMPANY_REQUIRED` se `COUNT(empresas ativas) ≠ 1`; `boundEmpresaId()` exige `configuracoes_integracao.integracao_empresa_id == singleEmpresaId` (migration 017) |
| Com 2+ empresas o Hub **recusa a operação** (não grava NULL) | `enforceRequest()` (linha 45) chama `boundEmpresaId()` em toda rota não-manutenção; a exceção vira **HTTP 409** (linha 56-60). A escrita, se chegasse, lançaria `TENANT_CONTEXT_REQUIRED` em `TenantScopeService::applyToInsert()` (linha 286) |

**Conclusão-chave:** a camada de **armazenamento** já é multi-tenant (32 tabelas com `empresa_id`, carimbadas
por `TenantScopeService`, com portão estático `tenant-scope-check.php`). O que falta para 2+ empresas **não é
storage** — é o **sinal de RESOLUÇÃO** na entrada sem sessão: *"a qual empresa pertence este evento que
acabou de chegar?"*. Hoje a resposta é "a única que existe". Com duas, não há resposta, e o sistema
corretamente **para** em vez de adivinhar.

## 2. Por que não é trivial — o gargalo estrutural

`configuracoes_integracao` é uma **linha única** (`WHERE id=1`) que guarda **todas** as credenciais de
**todas** as integrações: Tiny V2 (`tiny_v2_token`), Tiny V3 (`tiny_v3_client_id/secret/token`), VSM
(`vsm_url/token`), o segredo do webhook do Tiny (`tiny_webhook_secret`), o HMAC da VSM (`webhook_secret`) e
a allowlist de CNPJ (`tiny_webhook_cnpj_autorizados`, um **TEXT** na mesma linha). Evidência:
`database/modules/core.sql:83,133,396`.

Ou seja: **uma instalação = uma conexão Tiny + uma conexão VSM + um segredo de webhook**, tudo amarrado a
**uma** empresa por `integracao_empresa_id`. Suportar 2+ empresas exige, antes de qualquer isolamento de
dado, **credenciais e identidade de conexão por empresa** — é aí que a release deliberadamente parou.

## 3. O problema do SINAL, superfície por superfície

Para resolver a empresa no intake, cada superfície precisa **carregar** ou **estar associada a** um sinal:

| Superfície de entrada | Sinal disponível hoje | Resolve empresa? |
|---|---|---|
| Webhook de **PEDIDO do Tiny** | **CNPJ** no payload (já validado contra `tiny_webhook_cnpj_autorizados`) | **Sim, se** houver mapa `CNPJ → empresa` |
| Webhook de **estoque/outros do Tiny** | segredo compartilhado no header; sem CNPJ garantido | Só via qual **segredo/rota** autenticou |
| Webhook da **VSM** | HMAC v2 (`WebhookSecurityService`); **nenhum campo de empresa no corpo** | Só via qual **segredo/rota** autenticou |
| **Workers / cron** | processam a fila; sem evento externo | Herdam de `empresa_id` **já carimbado no item** no intake |

Dois fatos que decidem o desenho:
1. **A VSM não manda empresa nenhuma** — nem CNPJ. Qualquer solução que dependa do *corpo* do webhook
   resolve o Tiny e **deixa a VSM sem resposta**.
2. **A fila já carrega `empresa_id`** (carimbado no intake — a correção de empresa única). Se o intake
   resolver a empresa certa, **workers e cron herdam de graça**. O ponto único a resolver é o **intake**.

## 4. Opções (com impacto, risco e compatibilidade)

### Opção A — Identidade de conexão por empresa (RECOMENDADA)
Cada empresa tem **sua própria conexão de integração**: `configuracoes_integracao` deixa de ser linha única
e passa a ter uma linha por empresa (credenciais Tiny/VSM, endpoints, **segredo de webhook próprio** e
allowlist de CNPJ próprios). No intake, a empresa é resolvida por **qual segredo/rota autenticou** — a
autenticação que já existe passa a **carregar** a identidade da empresa. Cobre **as duas superfícies**
(Tiny e VSM), porque cada lado assina/manda o segredo daquela empresa.
- **Impacto:** schema (`configuracoes_integracao` multi-linha ou tabela filha por empresa), roteamento de
  webhook (URL ou segredo por empresa), `TinyWebhookSecurityService`/`WebhookSecurityService` passam a
  **devolver** a empresa resolvida, tela de configurações por empresa.
- **Risco:** **alto** — mexe no ponto mais sensível (credenciais + autenticação de webhook). Toda mudança
  aqui arrisca **recusar chamada legítima** de Tiny/VSM (proibição inegociável do CLAUDE.md).
- **Compatibilidade:** a instalação de empresa única continua sendo o caso `N=1` desse modelo — migração
  suave (a linha atual vira a linha da empresa 1).
- **Fecha o H-01?** **Sim, integralmente**, para ambas as superfícies.

### Opção B — Mapa CNPJ → empresa (PARCIAL)
Tabela de mapeamento `cnpj → empresa_id`; o webhook de **pedido do Tiny** resolve a empresa pelo CNPJ que
já valida.
- **Impacto:** pequeno (uma tabela + uso no intake do pedido Tiny).
- **Risco:** baixo no que cobre.
- **Fecha o H-01?** **Não.** Resolve só o pedido do Tiny. **Estoque do Tiny sem CNPJ garantido e toda a
  VSM ficam sem sinal.** Deixa metade do H-01 aberta — pior que o estado atual, porque cria a *ilusão* de
  multiempresa enquanto a VSM continua sem isolamento.

### Opção C — Segredo de webhook por empresa, credenciais ainda globais (INTERMEDIÁRIA)
Só a **autenticação** vira por empresa (cada empresa tem seu `tiny_webhook_secret` e seu HMAC VSM); as
credenciais de **saída** (envio ao Tiny/VSM) seguem globais.
- **Impacto:** menor que A (não fatia todas as credenciais), mas ainda mexe na autenticação de webhook.
- **Risco:** médio — mesmo ponto sensível de A, em menor superfície.
- **Fecha o H-01?** **Sim para a ENTRADA** (que é o escopo do H-01). Mas deixa a **saída** (envio ao
  provedor) sem identidade por empresa — inconsistente se o produto quiser, de fato, N clientes com
  credenciais distintas. É meio-caminho: resolve o isolamento de entrada, adia o de saída.

## 5. Recomendação

**Se e quando o produto decidir suportar 2+ empresas, seguir a Opção A** — identidade de conexão por
empresa — porque é a única que fecha o H-01 **nas duas superfícies** (Tiny **e** VSM) e é coerente com um
SaaS multicliente real. A Opção B é **insuficiente** (não cobre a VSM) e deve ser, no máximo, uma
**validação adicional** dentro da A, não a solução. A Opção C é um meio-termo defensável apenas se a
premissa for "várias empresas compartilhando as mesmas credenciais de saída" — o que raramente é o caso.

**Enquanto a decisão não é tomada, o comportamento atual é o CORRETO e deve ser preservado:** recusar 2+
empresas com erro explícito (`TENANT_SINGLE_COMPANY_REQUIRED`) é infinitamente melhor que gravar `empresa_id`
NULL e vazar dado entre clientes. **Não há bug a corrigir aqui** — há uma capacidade a decidir.

## 6. Pré-requisitos que a decisão precisa resolver ANTES do código
1. **Roteamento de webhook:** URL por empresa (`/webhook/tiny/{empresa}`) **ou** segredo por empresa que a
   autenticação resolve? (decisão de contrato com Tiny/VSM — envolve o outro lado).
2. **A VSM aceita assinar com segredo por empresa?** Sem isso, a VSM não tem como se identificar, e nenhuma
   opção fecha o lado VSM.
3. **Credenciais de saída por empresa** (Tiny V2/V3 token, VSM token) — confirmar que cada cliente tem as
   suas.
4. **Migração da linha única** `configuracoes_integracao` id=1 → linha da empresa 1, sem perder tokens
   nem histórico (regra: preservar dados, tokens, configurações).

## 7. Formato de auditoria

- **Identificação:** H-01, metade em aberto — isolamento na escrita de entrada sem sessão com 2+ empresas.
- **Evidência:** `IntegrationTenantService` (linhas 4-22, 45), `TenantContextService::currentEmpresaId()`
  (linhas 9-14), `TenantScopeService::applyToInsert()` (linhas 281-307), `configuracoes_integracao` linha
  única (`core.sql:83,133,396`), payload VSM sem empresa (fase 6).
- **Gravidade:** Informativa (capacidade futura) — **não** é vulnerabilidade no estado atual, porque o
  sistema recusa a operação em vez de isolar mal.
- **Causa raiz:** modelo de conexão único por instalação (credenciais globais bound a uma empresa).
- **Impacto:** sem a decisão, o Hub não opera com 2+ empresas (por desenho).
- **Correção recomendada:** Opção A, condicionada à decisão de produto e aos pré-requisitos da seção 6.
- **Risco da correção:** alto (autenticação de webhook + credenciais); mitigável por `N=1` como caso da
  Opção A e por medição contra banco real antes de liberar.
- **Compatibilidade:** preserva a instalação de empresa única como caso particular.
- **Como testar (quando implementar):** povoar 2 empresas, 2 conexões, disparar webhook autenticado de
  cada empresa e medir `empresa_id` gravado na fila e nas tabelas de negócio (a mesma medição que provou o
  isolamento de empresa única).
- **Como reverter:** enquanto não implementado, nada a reverter; a decisão é um documento.
- **Status:** **não identificado como defeito** — pendência de decisão de produto, com desenho pronto.

## Veredito
O H-01 não é um bug em aberto: é uma **capacidade adiada** com um bloqueio estrutural claro (conexão única
por instalação) e um problema de sinal assimétrico (Tiny tem CNPJ, VSM não tem nada). A recomendação —
**identidade de conexão por empresa (Opção A)** — está desenhada e à espera da decisão de produto e das
respostas da seção 6. Até lá, o comportamento atual (recusar 2+ empresas explicitamente) é o correto e fica
preservado.
