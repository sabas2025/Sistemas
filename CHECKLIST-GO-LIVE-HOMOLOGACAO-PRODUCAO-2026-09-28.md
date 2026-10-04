# Checklist de Go-Live — Homologação → Produção

**Data:** 2026-09-28 · **Base:** `main` @ `9e59bf9` (V104.49.3-R7) · **Natureza:** documento de
referência operacional. **Não altera código.**

> Consolida num só lugar o que fazer para levar o Hub a homologação e, depois, a produção. Cada item
> aponta o mecanismo real do Hub que o cobre. Complementa (não substitui) o `SECURITY.md`, o
> `RUNBOOK-MIGRATION-012-PK-BIGINT-2026-09-28.md` e a `NOTA-DECISAO-H01-E-PERGUNTA-VSM-2026-09-28.md`.
>
> **Regra que governa este documento:** o Hub NÃO se declara "100% funcional" nem "100% seguro".
> Produção só entra depois da homologação fechar o que não pôde ser validado sem credenciais reais.

---

## 0. O que já está validado (e o que NÃO está)

**Validado contra banco real / CI** (tabela completa no `CLAUDE.md`): schema consolidado e modular
(MySQL 8 + MariaDB 11.4), E2E autenticado, todas as rotas do painel por status + texto de erro, 78
rotas de mutação (CSRF), webhooks de entrada (Tiny secret + VSM HMAC), resiliência de fila
(retry/backoff/DLQ), OAuth V3 **sem credencial** (recusas, replay, PKCE), força bruta, fixação de
sessão, carga de um dia na meta, instalação limpa e de produção com as 5 guardas, segurança por
superfície (Fase 4).

**NÃO validado — e é o objetivo da homologação:**
- **Ciclo OAuth Tiny V3 completo** com o servidor real.
- **Qualquer chamada real ao Tiny ou à VSM** (pedido, estoque, fiscal, produto ponta a ponta).

"CI verde" cobre a tabela acima, **não** o Tiny/VSM real. Por isso homologação vem antes de produção.

---

## FASE A — Homologação (pode ir agora)

Homologação é onde se fecha a lacuna acima. Ordem sugerida:

- [ ] **A1. Instalar em homologação.** `public/install.php` com `ambiente=homologacao` /
      `app_env` não-produção. Instalador validado (I-22 parser, I-23 coerência de ambiente). Preserve
      `config/config.php`.
- [ ] **A2. Conectar Tiny V3 via OAuth real.** Configurar Client ID/Secret, Token URL, Redirect URI
      (na Ficha Tiny V3), clicar **Conectar** e completar o ciclo `authorization_code` real — a parte
      que não pôde ser exercitada aqui. Confirmar token salvo por ambiente (`tiny_v3_tokens`, origem
      `oauth`).
- [ ] **A3. Configurar a VSM de homologação.** URL de homologação + credenciais (clientToken/secret/
      loja) — cifradas por `VsmCredentialsConfigService`. Confirmar `vsm_url` de homologação.
- [ ] **A4. Rodar os fluxos ponta a ponta** e conferir cada um pela trilha (Trace ID):
  - [ ] **Pedido** Tiny → Hub → VSM (validação, idempotência por `pedido_origem_id`, envio, status).
  - [ ] **Estoque** VSM → Hub → Tiny (sem loop de sincronização; saldo anterior/novo).
  - [ ] **Fiscal** VSM → Hub → Tiny (NF-e/XML, vínculo ao pedido, sem duplicação).
  - [ ] **Produto** VSM → Hub → Tiny (fluxo ativo/inativo, aprovação manual quando configurada, SKU).
- [ ] **A5. Homologação assistida do painel.** Rodar **Homologação Central**, **Tiny V2/V3
      Homologação** e **Teste Real Tiny**; resolver as pendências que o checklist V29 apontar.
- [ ] **A6. Webhooks reais.** Disparar do Tiny (segredo `X-TINY-HUB-SECRET`) e da VSM (HMAC v2);
      confirmar aceitação, CNPJ autorizado (Tiny), e a fila drenando sem `sistema.erro_fatal`.
- [ ] **A7. Observabilidade.** Pesquisar um Trace ID e confirmar que devolve origem, rota, usuário,
      requisição, resposta, causa e ação — ponta a ponta.

**Só avance para produção quando A2–A6 passarem com dados reais.**

---

## FASE B — Pré-produção (gates, antes de virar a chave)

- [ ] **B1. Backup verificado.** Painel **Backups → Gerar**; conferir o **SHA-256**. É o ponto de
      retorno de toda a virada.
- [ ] **B2. Migration `012` (PK INT→BIGINT).** Executar na janela seguindo o
      **`RUNBOOK-MIGRATION-012-PK-BIGINT-2026-09-28.md`** (workers parados, webhooks drenados). Antes
      dela, confirmar a ordem das migrations de capacidade (`011 → 013 → 015 → cron retenção → 016 →
      012`). Quanto antes, mais barata.
- [ ] **B3. Segredos fortes.** `App::enforceProductionSafety` bloqueia produção se qualquer das 6
      chaves (`encryption_key`, `backup_signature_key`, `integration_replay_hmac_key`,
      `audit_daily_signature_key`, `token_vault_hmac_key`, `fim_manifest_hmac_key`) estiver vazia ou
      fraca (< 32 chars). Gerar/rotacionar: `php scripts/rotate-secrets.php --audit`.
- [ ] **B4. Ambiente e host.** `app_env=production`, `security.canonical_host` definido,
      `security.trusted_proxies` correto, `force_https` ligado. O redirect HTTPS é feito pelo
      `enforceProductionSafety` (não pelo `.htaccess` — decisão 3.6 do SECURITY.md); em produção,
      prefira redirecionar no virtual host (`nginx.conf.example`), descartando os `X-Forwarded-*` da
      internet.
- [ ] **B5. Endpoints coerentes com o ambiente (I-23).** O `ProductionGoLiveService` recusa go-live
      se `vsm_url`/Tiny apontarem para host de **homologação** com `ambiente=producao`. Trocar as URLs
      e credenciais para as de **produção** (a URL de produção da VSM é informada pelo provedor).
- [ ] **B6. OAuth de produção.** `ProductionGoLiveService` exige token com **origem OAuth** (não
      manual) para o ambiente de produção. Refazer o OAuth Tiny V3 apontando para produção.
- [ ] **B7. Empresa.** Opera em **empresa única** (decisão de produto). **Não** habilitar
      `commercial.tenant_scope_required` para 2+ empresas sem antes resolver o H-01 — ver
      `NOTA-DECISAO-H01-E-PERGUNTA-VSM-2026-09-28.md` (respostas da VSM pendentes).
- [ ] **B8. Cron dos workers.** Agendar `workers/worker_fila.php`, `worker_estoque.php`,
      `worker_reconciliacao.php`, `worker_retencao.php`,
      `worker_tiny_v3_refresh.php`, `worker_vsm_token_refresh.php` etc. via CLI/cron (nunca por HTTP —
      os shims públicos bloqueiam navegador). O XML/NF-e **não** tem worker: o envio ao Tiny é
      síncrono (retorno da VSM + ação manual no ciclo do pedido).
- [ ] **B9. Go-live guard verde.** Abrir **Entrada em Produção** (`ProductionGoLiveService`) e
      confirmar que **todas as checagens acendem verdes** — é o gate final do próprio Hub.

---

## FASE C — Virada e pós-produção

- [ ] **C1. Ligar a entrada.** Reativar webhooks (Tiny/VSM apontando para o Hub de produção) e os
      workers no cron.
- [ ] **C2. Fumaça em produção.** Um pedido real de ponta a ponta; conferir pela trilha (Trace ID) e
      pelo `api/status` autenticado (fila, DLQ, circuit breakers, saúde de segurança).
- [ ] **C3. Monitorar as primeiras horas.** `api/status` e a tela de Segurança mostram degradação de
      controle **antes** do incidente (`SecurityHealthService`). DLQ e `sistema.erro_fatal` na trilha
      são os sinais de alarme.
- [ ] **C4. Dívida com prazo.** Quando a VSM migrar a assinatura, ligar
      `security.webhook_signature_require_v2` (hoje a v1 é aceita como dívida — SECURITY.md 3.3).

---

## Itens que dependem de terceiros (fora do código)

| Item | Quem destrava | Referência |
|---|---|---|
| Executar a janela da `012` | operador (servidor) | RUNBOOK #63 |
| URL/credenciais de **produção** da VSM | provedor VSM | B5 |
| Ciclo OAuth Tiny V3 real | credenciais Tiny reais | A2 / B6 |
| Multiempresa (2+ empresas) | responsável + VSM (D1/D2/D3) | NOTA H-01 #64 |
| `Hub CI / gate` como *required* | admin do repositório | Settings > Branches |

---

## Resumo de uma linha

**Homologação agora** (fechar OAuth + fluxos reais Tiny/VSM, A2–A6) → **gates de pré-produção**
(backup, `012` na janela, segredos fortes, ambiente/host, endpoints de produção, OAuth de produção,
go-live guard verde) → **virada e monitoração**. Empresa única; multiempresa só após a VSM responder.
