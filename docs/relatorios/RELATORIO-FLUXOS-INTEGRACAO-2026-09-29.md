# Relatório — Fluxos de Integração Tiny ⇄ VSM (referência)

**Data:** 2026-09-29 · **Base:** branch `claude/security-audit-skill-9cd1qi` (contém a PR #73 da tela fiscal).
**Natureza:** auditoria de fluxos + referência de operação. **Não altera código** (documentação).
**Método:** leitura do código com evidência `arquivo:linha`. Onde a memória do projeto (`CLAUDE.md`)
já mediu contra banco real, está citado.

---

## 0. Resumo executivo — quem controla o quê

Autoridade por domínio (fonte de verdade), **codificada** em
`app/Services/IntegrationSourceOfTruthService.php:8-12`:

| Domínio | Fonte de verdade | Direção | Política de conflito |
|---|---|---|---|
| **Pedidos** | **Tiny** | Tiny → Hub → VSM | revisão manual |
| **Estoque** | **VSM** | VSM → Hub → Tiny | **VSM vence após validação** |
| **Fiscal (NF-e/XML)** | **VSM** | VSM → Hub → Tiny | documento autorizado vence |
| **Produto novo** | **VSM** | VSM → Hub → Tiny | **aprovação manual** |
| **Status integração** | Hub | interno | append-only (auditoria) |

**→ O estoque é controlado pela VSM** (fonte autoritativa). Confirmado em três pontos independentes:
- `app/Services/EstoqueEnterpriseService.php:5` → `'estoque_mestre'=>'vsm'`
- `app/Services/EstoqueEnterpriseService.php:8` → `'estrategia_estoque'=>'vsm_fonte_real'`
- `app/Services/EstoqueVsmSchedulerService.php:4-5` → "consultar o estoque real na VSM … mantendo a
  VSM como fonte autoritativa".

---

## 1. Defaults dos fluxos — o que vem LIGADO de fábrica

Fonte autoritativa: `app/Services/IntegrationOrchestratorService.php` — `$defaults` (linhas 7-33) +
`catalogoFluxos()` (35-92). O default efetivo é montado em `keys()` (95-99) e `all()` (101-108):
um fluxo roda apenas se `fluxo_<id>` **e** a macro `regra` (`sync_*`) estiverem ligados.

**Ligados por padrão (modelo de produção recomendado):**

| Fluxo | Direção | `padrao` | macro (regra) | Evidência |
|---|---|---|---|---|
| `pedido_tiny_enviar_vsm` | Tiny → Hub → VSM | 1 | `sync_tiny_enviar_pedido_vsm`=1 | `:24, :47-51` |
| `nfe_vsm_enviar_tiny` | VSM → Hub → Tiny | 1 | `sync_vsm_enviar_nota_tiny`=1 | `:13, :52-56` |
| `estoque_vsm_enviar_tiny` | VSM → Hub → Tiny | 1 | `sync_vsm_enviar_estoque_tiny`=1 | `:11, :67-71` |
| `produto_status_vsm_enviar_tiny` | VSM → Hub → Tiny | 1 | `sync_vsm_status_produto_tiny`=1 | `:12, :77-81` |
| `produto_novo_vsm_bloquear` | VSM → Hub | 1 (**bloquear**) | `sync_bloquear_produto_novo_vsm`=1 | `:14, :82-86` |
| `produto_novo_tiny_bloquear` | Tiny → Hub | 1 (**bloquear**) | `sync_tiny_bloquear_produto_novo_vsm`=1 | `:27, :87-91` |

**Desligados por padrão (opcionais/reversos) — e POR QUÊ:** cada um traz uma nota de `risco` no
próprio catálogo dizendo para deixar desligado, para não duplicar pedido nem criar loop de estoque.

| Fluxo | Direção | `padrao` | Motivo de vir desligado (código) | Evidência |
|---|---|---|---|---|
| `pedido_vsm_receber` | VSM → Hub | 0 | "Deixe desligado quando o pedido nasce no Tiny para evitar duplicidade." | `:37-41` |
| `pedido_vsm_enviar_tiny` | Hub → Tiny | 0 | "Deixe desligado no modelo Tiny→HUB→VSM para não criar pedido duplicado." | `:42-46` |
| `nfe_tiny_enviar_vsm` | Tiny → VSM | 0 | fluxo fiscal reverso, opcional | `:57-61` |
| `estoque_tiny_enviar_vsm` | Tiny → VSM | 0 | "Evitar loop de estoque quando VSM também envia estoque para Tiny." | `:62-66` |
| `produto_status_tiny_enviar_vsm` | Tiny → VSM | 0 | "Não criar produto novo automaticamente se SKU não existir." | `:72-76` |

**Conclusão sobre "ligar os fluxos por padrão":** os fluxos do **modelo de produção já vêm
ligados**. Os que faltam são os **reversos**, desligados **de propósito** — ligá-los por padrão
recria a duplicidade de pedido e o loop de estoque que o Hub foi feito para evitar. Recomendação:
**manter os defaults**. Um operador que precise de um fluxo reverso específico o liga por instalação
na tela **Orquestração** (a fonte oficial de ligar/desligar — `views/*orquestracao*`,
`OrquestracaoController`). **Correção de relato anterior:** as chaves `fluxo_tiny_vsm_estoque=0`,
`fluxo_vsm_tiny_produto=0`, `fluxo_vsm_tiny_pedido=0` em `IntegrationConfig` são macros **legadas** e
não representam os defaults efetivos, que vêm do catálogo acima.

---

## 2. Fluxo de Pedidos — Tiny → Hub → VSM → Hub → Tiny

Serviço central: `app/Services/PedidoCicloVidaService.php`. Tabelas: `pedidos_hub`,
`pedidos_nfe_xml`, `pedidos_status_historico`, `pedidos_payloads` (+ `pedidos_validacao`).

1. **Tiny gera o pedido** → webhook `api/tiny/webhook/pedido`
   (`app/Services/FastRouteDispatcherService.php:19`, `ApiTinyController`). Autenticação por segredo
   compartilhado no header `X-TINY-HUB-SECRET` (ver §7).
2. **Validação + gravação** → `PedidoTinyVsmValidationService.php:54` chama
   `PedidoCicloVidaService::fromTinyPedido()` (`:13`), gravando `pedidos_hub` com status
   `recebido_tiny` e a cópia original em `pedidos_payloads`.
3. **Hub envia à VSM** → na drenagem da fila, `ApiController.php:730` chama
   `marcarEnviadoVsmPorTinyId()` (`:53`) → status `enviado_vsm`.
4. **VSM emite a NF-e e retorna** → `ApiController.php:369` chama `receberRetornoVsm()` (`:94`) →
   status `recebido_vsm` → `xml_validado` (ver §4).
5. **Hub devolve ao Tiny** → `ApiController.php:372` chama `enviarXmlParaTiny()` (`:137`) → status
   `enviado_tiny` → `concluido`.
- **Idempotência medida:** 3 reenvios do mesmo pedido = 1 linha em `pedidos_validacao`
  (`CLAUDE.md`, tabela de validação real; achado I-14). Tela: `page=pedido-ciclo-vida`
  (`V51Controller`).
- **Gap conhecido (etapa 5):** o webhook `api/tiny/webhook/situacao-pedido` só registra/audita
  (`ApiController.php:520`); **não** atualiza `pedidos_hub`. O ciclo marca `concluido` no **envio** ao
  Tiny, não na **confirmação** do Tiny. Fechar isso é decisão de produto (pendência no `CLAUDE.md`).

---

## 3. Fluxo de Estoque — VSM manda; Tiny alimenta a venda

Serviços: `EstoqueEnterpriseService`, `EstoqueVsmSchedulerService`, `EstoqueMapper`. Tabelas:
`estoque_movimentos`, `estoque_eventos_sincronizacao`, `estoque_auditoria_sku`, `estoque_alertas`,
`fila_estoque`, `estoque_configuracoes`.

- **Principal (VSM → Hub → Tiny):** `EstoqueVsmSchedulerService` (worker `worker_consulta_estoque_vsm`)
  consulta a VSM e propaga o saldo real ao Tiny (`saldo_autoritativo`).
- **Reverso (Tiny → Hub → VSM):** webhook `api/tiny/webhook/estoque` → `receberAtualizacao('tiny', …)`
  (`EstoqueEnterpriseService.php:72`) — baixa de venda no Tiny.
- **Entrada única:** `receberAtualizacao(origem, …)` identifica a origem e o destino oposto (`:79`).
- **Anti-loop (3 camadas):**
  1. **Idempotência por evento** — hash `origem|referência|sku|tipo` inserido em
     `estoque_eventos_sincronizacao` com UNIQUE; duplicado é ignorado (`:60-70, :82-84`).
  2. **Bloqueio bidirecional** — barra VSM→Tiny se o mestre for Tiny (`:37-40`).
  3. **Janela de retorno espelhado** — `ignorar_retorno_espelhado_minutos=10` (`:9`).
- **Políticas** (`config()`, `:3-22`): `permitir_vsm_tiny=1`, `permitir_tiny_vsm=1`,
  `estoque_mestre=vsm`, `alertar_estoque_negativo=1`, `reconciliacao_automatica=1`.
- **Histórico / saldo anterior e novo:** `estoque_movimentos` (INSERT IGNORE, `:86`),
  `estoque_auditoria_sku` (`auditarSku`, `:92`).
- **Concorrência/fila:** `fila_estoque` com retry escalonado + jitter aditivo
  (`proximaTentativa`, `:108-114`; G-03).

---

## 4. Fluxo Fiscal / NF-e / XML — VSM → Hub → Tiny

É a etapa da NF-e dentro do ciclo do pedido (§2, passos 4-5), em `PedidoCicloVidaService`.
- **Recepção do XML da VSM:** `receberRetornoVsm()` (`:94`) — casa o pedido, grava `pedidos_nfe_xml`.
- **Validação:** XML bem formado (`parseXmlInfo`, `:62-79`) + **chave NF-e 44 dígitos com dígito
  verificador módulo-11** (`XmlNfeHomologationService::validarChaveNfe`, chamado em `:118-121`, achado
  M-01). Sem XML/chave exigidos por config → bloqueia (`:110-112`).
- **Envio ao Tiny:** `enviarXmlParaTiny()` (`:137`) via `enviarNfeXmlPedido` — existe em
  `TinyV3Service.php:165` e `TinyV2Service.php:132` (não há `TINY_XML_METHOD_MISSING` real).
- **Tela:** `page=fiscal` foi **reapontada** para este fluxo real (PR #73). O subsistema antigo
  (`notas_fiscais`/`nfe_integracao`, sentido Hub→VSM, `FiscalIntegrationService`) **não é alimentado
  por nenhum fluxo** — é código morto, pendência de remoção registrada no `CLAUDE.md`.

---

## 5. Produto novo — VSM → Hub, **retido para aprovação manual**

Serviços: `ProdutoVsmGovernanceService`, `ProdutoVsmApprovalGuardService`, `ProdutoMapper`,
`ProdutoTinyPreflightService`. Tabelas: `produtos_pendentes_integracao`, `produto_pendencias`,
`produtos_mapeamento`, `categorias_mapeamento`, `produtos_aprovacao_historico`.

- **Chegada do produto novo (VSM):** vira **pendência**, não cria no Tiny sozinho —
  `ProdutoVsmGovernanceService::createPending()` (`:53-79`) grava `produtos_pendentes_integracao`
  (status `pendente`) + `produto_pendencias`.
- **Bloqueio por padrão:** `isAutoCreateAllowed()` (`:44-48`) exige
  `produto_novo_aprovacao_modo != 'manual'` **e** `sync_bloquear_produto_novo_vsm` desligado — e o
  padrão é `manual` + bloqueio ligado (§1). Logo, **produto novo não entra no Tiny automaticamente**.
- **Aprovação manual:** `ProdutoVsmApprovalGuardService::checklist()` (`:64-82`) — detecta
  duplicidade (EAN/NCM/SKU, `:28-45`), exige categoria mapeada, lista bloqueios; só permite aprovar
  sem bloqueios (`pode_aprovar`). Histórico em `produtos_aprovacao_historico`
  (`registrarHistorico`, `:85-88`).
- **Vínculo por SKU:** `produtos_mapeamento` (`sku_vsm` ↔ `sku_tiny`), consultado em
  `ProdutoVsmGovernanceService.php:14`.

---

## 6. Produto ativo/inativo — VSM → Hub → Tiny (status)

- **Tradução do status:** `ProdutoMapper::situacaoTiny()` (`:21-22`) mapeia o status VSM para `A`/`I`
  do Tiny; `ProdutoMapper.php:16-17` lista os sinônimos aceitos
  (ativo/inativo/desativado/bloqueado…).
- **Classificação do evento:** `ProdutoMapper.php:40-50` decide entre `produto_vsm_status_para_tiny`
  (mudança de status) e `produto_vsm_para_tiny` (produto novo) pelo conteúdo do payload.
- **Fluxo ligado por padrão:** `produto_status_vsm_enviar_tiny` (`padrao=1`, §1) — VSM manda o
  ativo/inativo para o Tiny. Nota de risco no código: **"Inativação deve respeitar pedido pendente e
  estoque positivo"** (`IntegrationOrchestratorService.php:80`).
- **Reverso (Tiny → VSM status):** existe (`produto_status_tiny_enviar_vsm`) mas vem **desligado**
  (`padrao=0`, `:72-76`).

---

## 7. Segurança de entrada (webhooks) — resumo

- **Tiny:** segredo compartilhado no header `X-TINY-HUB-SECRET` comparado com `hash_equals`
  (`TinyWebhookSecurityService`); + lista de CNPJ autorizado, lista de IP, teto de payload e dois
  limitadores (o de tentativas roda ANTES da autenticação, achado A-09). Medido: sem segredo → 401,
  errado → 401, correto → aceito (`CLAUDE.md`).
- **VSM:** assinatura **HMAC** (`v2:MÉTODO:rota:timestamp:nonce:hash`, `WebhookSecurityService`) —
  `ApiVsmWebhookController`. **Não** é o mesmo desenho do Tiny.

---

## 8. Análise / achados

**Sólido (nenhuma ação necessária):**
- Contrato de autoridade explícito e coerente (`IntegrationSourceOfTruthService`).
- Anti-loop de estoque robusto (3 camadas independentes).
- Pedido e NF-e com idempotência medida; produto novo retido para aprovação (correto para
  "VSM manda, humano confirma").
- **Defaults dos fluxos já corretos:** produção ligada, reversos desligados por segurança.

**Pendências (registradas no `CLAUDE.md`, aguardam decisão/autorização):**
1. **Fiscal Modelo A (código morto)** — remoção pendente de autorização (sem `DROP`).
2. **Etapa 5 do pedido** (Tiny → Hub "finalizado") não fecha o ciclo — decisão de produto.

**Recomendação sobre "ligar todos os fluxos por padrão":** **não fazer.** Os fluxos de produção já
vêm ligados; os reversos vêm desligados de propósito (duplicidade/loop). A ativação de um fluxo
reverso específico é decisão por instalação, na tela Orquestração.

---

## Como reproduzir esta auditoria
Todos os pontos são leitura estática; os arquivos e linhas estão citados acima. Para os fluxos em
runtime, use a reprodução E2E documentada no `CLAUDE.md` (MariaDB local + provisionamento).
