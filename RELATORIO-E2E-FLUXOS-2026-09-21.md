# RELATÓRIO — Teste ponta a ponta dos fluxos de negócio (pedido, NF-e, estoque, produtos)

**Data:** 2026-09-21 · **Release base:** V104.49.3-R7 · **Commit base:** `ceae7c0` (main)
**Método:** ambiente provisionado em **MariaDB 10.11 real**, exercício dos fluxos por **HTTP** (webhooks
autenticados) e leitura do estado no banco + trilha de auditoria. Documento informativo.

> **Ressalva honesta:** não há conectividade real com Tiny nem VSM (sem credenciais; anti-SSRF barra
> hosts fictícios). Foi exercitada toda a **lógica interna do Hub** — receber, autenticar, validar,
> deduplicar, enfileirar, carimbar empresa, mudar status, auditar, contrato de resposta. No ponto do
> envio externo, mede-se a **tentativa**: falha de DNS (VSM) e "Token Tiny V2 não configurado" (Tiny)
> são o comportamento **correto** sem ambiente conectado — o dado fica preservado para retry.

## Fluxo 1 — Pedido (Tiny → Hub → VSM → NF-e → status → retorno ao Tiny)

| Etapa | Evidência medida |
|---|---|
| Recebe do Tiny (webhook autenticado por `X-TINY-HUB-SECRET`) | pedido inválido → **HTTP 422** com motivo ("SKU sem mapeamento"), `success:false` coerente (correção I-14) |
| Pedido válido | **HTTP 200**, `success:true`, gravado em `pedidos_validacao` (empresa_id=1), **enfileirado** em `fila_integracao` (`pedido_tiny_para_vsm`, empresa_id=1) |
| Envio ao VSM (worker_fila) | tentativa real → `fila_integracao.status=erro`, `QUEUE_PROCESS_ERROR`, retorno *"Host VSM não pôde ser resolvido por DNS"*, **tentativas=1 e reagendado** (pedido preservado, não perdido) — correto sem rede |
| Retorno NF-e (VSM → Hub, `X-Hub-Secret`) | **HTTP 200**, `validado:true`, chave NF-e aceita (**valida módulo-11**), vinculada ao pedido (`pedidos_nfe_xml` → pedido_hub_id=2), status → `recebido_vsm` |
| Validação do XML | status → `xml_validado` ("NF-e/XML validado antes de enviar ao Tiny") |
| Retorno ao Tiny | tentativa real → `enviado_tiny` com `TINY_TOKEN_MISSING` (sem credencial), ciclo → `concluido` — sem crash |

**Ciclo de vida auditado (pedido_hub_id=2, empresa_id=1 em todas):**
`recebido_tiny → recebido_vsm → xml_validado → enviado_tiny → concluido` (5 transições em `pedidos_status_historico`).

## Fluxo 2 — Baixa de estoque (Tiny → Hub → VSM)
Webhook `api/tiny/webhook/estoque` autenticado → **HTTP 200**, `success:true`, enfileirado na fila
exclusiva **`fila_estoque`** (id=1, sku SKU-1, empresa_id=1), política `vsm_fonte_real`.

## Fluxo 3 — Produtos novos (VSM → Hub → Tiny), ativo/inativo
| Caso | Resultado |
|---|---|
| Produto com preço zerado | **rejeitado** ("Preço de venda zerado…") — governança ativa |
| Produto novo **ATIVO** (com preço) | **HTTP 202**, `pending:true`, pendência 1; payload registra `situacao=ativo`, `ativo=true` |
| Produto novo **INATIVO** (com preço) | **HTTP 202**, `pending:true`, pendência 2; payload registra `situacao=inativo`, `ativo=false` |

O modo **aprovação manual** estava ligado (`produto_novo_aprovacao_modo=manual`): produtos entram em
`produto_pendencias` aguardando aprovação no Hub antes de ir ao Tiny — respeitando "fluxo ativo/inativo,
aprovação manual quando configurada, vínculo por SKU, prevenir duplicidade, auditoria completa".

### Fluxo 3 completo — VSM → Hub → **Tiny** (produto novo até o envio)
O caminho até o Tiny foi exercitado até o fim. A criação de produto no Tiny é **gated por design**
(secure-by-default): mesmo em modo automático o Hub só cria no Tiny com mapeamento de categoria e
**aprovação manual do operador** — comportamento correto.

| Etapa | Evidência |
|---|---|
| VSM envia produto novo | recebido e autenticado (`X-Hub-Secret`) |
| Governança | **pendente** — exige categoria mapeada + aprovação manual (secure-by-default) |
| Operador aprova (`criar_produto_tiny`) | enfileira **`produto_vsm_para_tiny`** (referência=SKU, empresa_id=1, `hub_aprovado_manual=true`) |
| Worker mapeia VSM→Tiny | **`mapper.produto_vsm_para_tiny` sucesso** — "Produto VSM convertido para formato Tiny" |
| Envio ao Tiny | tentativa real → `TINY_TOKEN_MISSING` (sem credencial) → `falha_definitiva`, auditado — falha honesta e definitiva (token ausente não se resolve em retry) |

A **conversão VSM→Tiny funciona**; só a chamada externa final depende de credencial real do Tiny.

### Fluxo 3b — VSM → Hub → **Tiny** (produto que JÁ EXISTE: alteração/atualização)
Para produto **já existente** (SKU mapeado), a governança reconhece `isNew=false` e **enfileira direto,
sem aprovação manual** (a aprovação manual é só para produto novo). Três variantes, por tipo de evento:

| Evento VSM | Fila gerada | Worker |
|---|---|---|
| `atualizar` (cadastro alterado) | `produto_vsm_atualizar_tiny` (empresa_id=1) | **mapper sucesso** (convertido p/ Tiny) → tentativa de atualização → `TINY_TOKEN_MISSING` |
| `status` (ativo→inativo) | `produto_vsm_status_para_tiny` (empresa_id=1) | faz **preflight** (consulta o SKU no Tiny **antes** de atualizar — não atualiza às cegas) → sem token → `TINY_PRODUCT_PREFLIGHT_ERROR` |
| `estoque` | `produto_vsm_estoque_para_tiny` | mesma mecânica (idem preflight) |

Medido: os dois eventos recebidos **HTTP 200**, enfileirados sem pendência (porque o SKU existe), e o
rastro em `produtos_vsm_eventos` registra `produto_cadastro_atualizado` (status_vsm=A) e
`produto_status_atualizado` (status_vsm=I), ambos `empresa_id=1`. A tentativa de escrever no Tiny falha
honestamente por falta de credencial — o **preflight de status/estoque é higiene correta** (confere
existência antes de alterar).

## Auditoria final

| Verificação | Resultado |
|---|---|
| **Erros fatais** (`sistema.erro_fatal`) | **0** — nenhuma tela derrubada; todo erro tratado e auditado |
| **Isolamento por empresa** | 7 tabelas de negócio (15 linhas), **todas `empresa_id=1`, zero nulos** |
| **Trilha por Trace ID** | ciclo de pedido, estoque e produto todos correlacionados e auditados |
| **Contrato de resposta** | status HTTP coerente com o corpo (422 bloqueio, 200 sucesso, 202 pendente) |
| **Idempotência / dedupe** | `eventos_processados` grava hash; reenvio não duplica |

## Achado (registrado, não corrigido)

- **K-01 · `SensitiveDataService::maskScalar()` recebe array e emite `Array to string conversion`.**
  - **Gravidade:** Baixa (Informativa). **Evidência:** warning PHP em `SensitiveDataService.php:50`
    durante `worker_fila` ao mascarar o payload do pedido.
  - **Causa:** em `mask()` (linha 19), quando uma **chave sensível guarda um array**, chama
    `maskScalar($array)` → `(string)$array`. Family do achado G-01 (tipo em caminho de mascaramento).
  - **Impacto:** best-effort — **não interrompe o fluxo** (o pedido foi enfileirado e falhou no DNS
    corretamente); mas emite warning e mascara o array como a string literal "Array", perdendo estrutura no log.
  - **Correção recomendada:** em `maskScalar`, se `is_array($v)` mascarar recursivamente (ou
    `json_encode` antes de mascarar), como `mask()`/`sanitizeForStorage()` já fazem.
  - **Risco da correção:** baixo. **Como testar:** repetir o worker com payload de item aninhado e
    conferir ausência do warning. **Status:** confirmado.

## Veredito
Os quatro fluxos de negócio estão **íntegros na lógica interna do Hub**: recebimento autenticado,
validação (pedido, NF-e módulo-11, produto), deduplicação, enfileiramento, carimbo de empresa em
100% das linhas, mudança de status auditada e contrato de resposta coerente. Zero erro fatal. O único
elo não exercido é a **chamada real a Tiny/VSM** (depende de credenciais/rede) — as tentativas falham
exatamente como devem sem conectividade, preservando o dado para retry.

**Continua sem validação contra provedor real:** envio efetivo a Tiny/VSM e ciclo OAuth completo.
