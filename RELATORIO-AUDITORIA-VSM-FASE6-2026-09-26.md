# RELATÓRIO — Fase 6 (VSM), auditoria de código

**Data:** 2026-09-26 · **Natureza:** auditoria (leitura/análise). **NADA ALTERADO.**
**Escopo:** integração VSM a partir do código — cliente, autenticação (entrada e saída), SSRF,
retry/backoff, idempotência, timeout, circuit breaker, rate limit, máscara de log, prevenção de
loop de estoque e contrato OpenAPI. Segue a fase 6 do `CLAUDE.md`.

> Complementa o `RELATORIO-AUDITORIA-VSM-DUAS-APIS-2026-09-20.md` (que tratou da separação
> gravação × consulta). Aqui o foco é a superfície de segurança e resiliência inteira.

---

## Qual API é usada em cada fluxo (exigência explícita da fase 6)

Derivado do código e travado pelo teste `tests/enterprise/v104_49_3_vsm_dual_api_test.php`.

| Fluxo | Método (arquivo:linha) | Base | Endpoint (default, configurável) | Chave de idempotência |
|---|---|---|---|---|
| **Pedido** Hub→VSM | `enviarPedido()` `app/Controllers/ApiController.php:723` | **gravação** (`vsm_url`) | `vsm_endpoint_pedido` = `/api/pedidos` | `fila_pedidos:<id>` |
| **Baixa de estoque** Hub→VSM (do pedido) | `enviarBaixaEstoque()` `app/Controllers/ApiController.php:713` | gravação | `vsm_endpoint_baixa_estoque` = `/api/estoque/baixa` | `fila_estoque_pedidos:<id>` |
| **Baixa de estoque** Hub→VSM (worker) | `enviarBaixaEstoque()` `workers/worker_estoque.php:52` | gravação | idem | `fila_estoque:<id>` |
| **Consulta de estoque** Hub→VSM | `consultarEstoque()` `app/Controllers/EstoqueController.php:152`, `EstoqueVsmSchedulerService`, `ReconciliationService` | **consulta** (`vsm_url_consulta`; vazia → cai na de gravação) | `/api/estoque/consulta` (método/payload configuráveis) | — (leitura) |
| **Recebimento de estoque** VSM→Hub | webhook → `EstoqueEnterpriseService::receberAtualizacao('vsm', …)` | — (entrada) | — | hash `origem\|referencia\|sku\|tipo` |

---

## Achados

### F6-01 · Autenticação (entrada e saída)
- **Identificação:** autenticação dos dois sentidos do canal VSM.
- **Evidência:** `WebhookSecurityService::validar()` (entrada) e `VsmService::request()` +
  `IntegrationSecurityService::vsmHmacHeaders()` (saída).
- **Arquivo/classe/método:** `app/Services/WebhookSecurityService.php`, `app/Services/VsmService.php:41-55`.
- **Gravidade:** Informativa (controle presente e correto).
- **Causa raiz / Impacto:** n/a.
- **Detalhe:** entrada usa HMAC v2 canonizando `v2:MÉTODO:rota:timestamp:nonce:hash(corpo)`,
  comparação com `hash_equals`, janela de timestamp, guarda de nonce e de replay, teto de payload
  e allowlist de IP. A assinatura v1 é concessão **documentada** (`SECURITY.md`) e **visível**
  (degrada em `SecurityHealthService`, evento `webhook.assinatura_v1`). Saída manda Bearer +
  HMAC de saída, com headers mascarados no log (`IntegrationSecurityService::maskHeaders`).
- **Cenário de falha:** nenhum identificado no código.
- **Como testar:** já coberto por suíte enterprise; validação com HMAC real do lado VSM depende de
  credencial.
- **Status:** confirmado (OK).

### F6-02 · SSRF / DNS rebinding
- **Evidência:** `VsmEndpointSecurityService::validateBaseUrl()`, `resolvePublicIps()`,
  `curlResolveEntry()`, `sanitizePath()`.
- **Arquivo:** `app/Services/VsmEndpointSecurityService.php`.
- **Gravidade:** Informativa (controle presente e correto).
- **Detalhe:** allowlist de scheme/host/porta; recusa host local e IP privado/reservado
  (`FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE`); fixa o IP validado na requisição
  (`CURLOPT_RESOLVE`) para fechar a janela de DNS rebinding; `sanitizePath` bloqueia `..`, URL
  absoluta, `\`, nulo e caracteres de controle (com dupla decodificação). HTTPS obrigatório em
  produção; allowlist de host **obrigatória** em produção.
- **Status:** confirmado (OK).

### F6-03 · Resiliência (retry, backoff, timeout, circuit breaker, idempotência)
- **Evidência:** `VsmService::request()` (laço de tentativas) + `RetryPolicyService` +
  `CircuitBreakerService`.
- **Arquivo:** `app/Services/VsmService.php:58-90`, `app/Services/RetryPolicyService.php`.
- **Gravidade:** Informativa (controle presente e correto).
- **Detalhe:** backoff exponencial com jitter; `RetryPolicyService::sleep(1)` **retorna na hora**
  (sem latência na 1ª tentativa — verificado); retry só em erro transitório
  (408/409/425/429/5xx e erro de cURL); timeout limitado a 5–120 s; circuit breaker por provedor
  (`vsm`); idempotência de mutação por `Idempotency-Key` (SHA-256 de chave estável por item de
  fila — achado P0-09, já fechado), então reprocessar não duplica pedido nem baixa de estoque.
- **Status:** confirmado (OK).

### F6-04 · Loop de sincronização de estoque
- **Evidência:** `EstoqueEnterpriseService::receberAtualizacao()`, `enfileirarEstoque()`,
  `eventoJaProcessado()`.
- **Arquivo:** `app/Services/EstoqueEnterpriseService.php:35-89`.
- **Gravidade:** Informativa (controle presente e correto).
- **Detalhe:** `bloquear_loop_bidirecional`, identificação de origem (`vsm`/`tiny`),
  `ignorar_retorno_espelhado_minutos`, idempotência por hash de evento
  (`origem|referencia|sku|tipo`, insert único). Reprocessar não duplica movimento de estoque.
- **Status:** confirmado (OK).

### F6-06 · Host de STAGE da VSM não reconhecido como ambiente de teste — **CORRIGIDO E VALIDADO**
- **Identificação:** o vocabulário de "host de teste" do Hub tinha `homolog`/`staging`, mas não
  `stage` — o rótulo real do ambiente de validação da VSM.
- **Evidência (fato oficial, 2026-09-26):** Stage = `https://conectavenda.stage.vsm.com.br`;
  Produção = `https://conectavenda.vsm.com.br`.
- **Arquivo/classe/método:** `VsmEnvironmentService::isHomologUrl()`;
  `ProductionGoLiveService::hostDeHomologacao()`.
- **Gravidade:** Alta.
- **Causa raiz:** rótulo `stage` ausente das duas listas de detecção.
- **Impacto:** o Stage era classificado como **produção** no dashboard; e o go-live (checagem
  I-23) **não acusava** um Hub de produção apontado para Stage — pedido/estoque/NF-e reais iriam
  para o ambiente de validação sem alarme.
- **Cenário de falha:** instalação em produção com `vsm_url = conectavenda.stage.vsm.com.br` →
  go-live "apto" → pedidos de cliente enviados ao ambiente de teste.
- **Correção aplicada:** rótulo `stage` acrescentado às duas listas, comparado por **rótulo de
  DNS** (nunca substring — armadilha B-06). O host de produção real não tem rótulo de teste e
  segue classificado como produção.
- **Risco da correção:** baixo — só amplia a detecção de host de teste; não afeta o host de
  produção.
- **Como testar:** `tests/enterprise/v104_49_3_vsm_stage_environment_test.php` (comportamental,
  chama os métodos reais e o privado por reflexão). Conferido que **reprova sobre o código
  antigo** (5 falhas) e passa sobre o corrigido; suíte enterprise 57/57 verde.
- **Como reverter:** remover `stage` das duas listas.
- **Status:** corrigido e validado.

### F6-07 · Auth e endpoints do Hub divergem da VSM real — **CONFIRMADO (contrato em mãos; correção pendente de aprovação)**
Contrato oficial recebido em 2026-09-26 e salvo em `contracts/vsm/pedidos-integradora.openapi.json`
(OpenAPI 3.1.0, 4 operações; gate `vsm-openapi-check` verde). O delta ficou **confirmado**:

| # | VSM real (contrato) | Hub hoje | Impacto |
|---|---|---|---|
| **a** | `POST /v1/auth/token` body `{clientToken,clientSecret}` → JWT `{accessToken, expiration, expiresIn≈7200s}` (Bearer JWT) | `vsm_token` **estático** como Bearer; **sem** token-exchange (`VsmService.php:19,42`) | Não autentica contra a VSM real |
| **b** | Credenciais: **Integradora** (`clientToken`+`clientSecret`) e **Loja** (`clientTokenLoja`); `clientTokenIntegradora` | schema só tem `vsm_token` | Sem lugar para guardar as credenciais reais (precisa de colunas cifradas + migration nos 2 caminhos, lição I-18) |
| **c** | Pedido: `POST /v1/pedido/integradora?clientTokenLoja=…&clientTokenIntegradora=…` (query **obrigatória**) + Bearer | `POST /api/pedidos` (default), sem os query tokens | Endpoint e query não batem |
| **d** | Corpo `PedidoCadastroDTO` (cliente, pedidoEntrega, pedidoPagamento, pedidoItem[], tipo, valorFinal, dataAprovacao) com enums 0..5 | `PedidoMapper` monta outro formato | Mapeamento precisa ser reescrito para o DTO |
| **e** | `409` = **falha de validação permanente** ("Quantidade dos itens deve ser maior que 0") | `RetryPolicyService::shouldRetry` **retenta 409** | Retentaria erro permanente — desperdício e DLQ atrasada. Correção deve ser **específica da VSM** (o 409 global é compartilhado com o Tiny) |
| **f** | **Sem HMAC** | envia `vsmHmacHeaders` na saída (`:53`) | Peso morto (o servidor ignora); o HMAC de ENTRADA (VSM→Hub) é outro e permanece |
| **g** | Os dois `clientToken*` viajam na **query string** | `VsmService::request` audita `'url'=>$url` **cru** (`:55-56`) | Se a query levar os tokens, o log grava segredo em claro — a gravação precisa usar `redactUrlForLog` |

- **Gravidade:** Alta.
- **Correção recomendada:** serviço de token VSM (troca `/v1/auth/token`, cache do JWT até
  `expiration`/`expiresIn`, refresh proativo — desenho do `TinyV3TokenService`); colunas cifradas
  para as credenciais (migration idempotente nos 2 caminhos); endpoint/`query` corretos; mapper
  para `PedidoCadastroDTO`; retry VSM que **não** retenta 409; log com `redactUrlForLog`.
- **Risco da correção:** médio-alto — mexe no fluxo de pedido e no schema. Exige autorização
  explícita e será desenhada no formato "antes de alterar" antes de aplicar.
- **Como testar:** contra MariaDB real (schema/migration, mapper, retry) + teste que reprova sobre
  o código antigo; a **chamada real à VSM** ainda depende de liberar o host na rede e das
  credenciais de Stage.
- **Status:** confirmado (correção pendente de aprovação e do spec `pedidos-loja`, se houver mais).

### F6-05 · Contrato OpenAPI da VSM — **integradora IMPORTADA; loja pendente**
- **Resolvido:** `contracts/vsm/pedidos-integradora.openapi.json` importado; o gate
  `scripts/ci/vsm-openapi-check.php` agora **valida** o contrato (antes era no-op).
- **Pendente:** o spec `pedidos-loja` (se a VSM o expõe) ainda não foi importado; e a comparação
  automática endpoint↔contrato (`VsmOpenApiContractService::compareCatalog()`) ainda não é
  chamada pelo gate — entra junto com a implementação do F6-07.
- **Nota histórica (o texto abaixo era o estado ANTERIOR, mantido para rastreabilidade):**
- **Identificação:** o portão de contrato roda em modo no-op por falta do Swagger oficial.
- **Evidência:** `contracts/vsm/` contém apenas `README.md`;
  `scripts/ci/vsm-openapi-check.php:6-9` sai `[OK]` com a mensagem
  *"Nenhum contrato VSM foi empacotado; catálogo permanece não verificado até a importação de um
  OpenAPI oficial."*
- **Arquivo/classe:** `scripts/ci/vsm-openapi-check.php`, `app/Services/VsmOpenApiContractService.php`.
- **Gravidade:** Informativa.
- **Causa raiz:** o Swagger oficial da VSM (`pedidos-integradora`, `pedidos-loja`) nunca foi
  exportado para o repositório, e o `CLAUDE.md`/`README` **proíbem inventar** a especificação.
- **Impacto:** `VsmOpenApiContractService::compareCatalog()` (endpoint que o Hub chama × contrato
  publicado da VSM) **nunca é exercido** no CI — não há prova automatizada de que os endpoints
  usados existem no contrato da VSM.
- **Cenário de falha:** se a VSM renomear/remover um endpoint, o Hub só descobre em runtime.
- **Por que NÃO é defeito:** o portão é **honesto** — nomeia explicitamente o que não prova, não
  pinta um verde mentiroso; e inventar a spec violaria regra inegociável.
- **Correção recomendada:** o responsável exporta os OpenAPI oficiais da VSM para
  `contracts/vsm/*.json` (nomes recomendados no README). O portão passa a validar e comparar
  sozinho, sem alteração de código.
- **Risco da correção:** nulo (somente leitura; não executa operação).
- **Como reverter:** remover os `.json` volta ao no-op atual.
- **Status:** confirmado (aberto — depende do responsável).

---

## Conclusão

Os controles verificáveis por código estavam bem endurecidos (F6-01 a F6-04). Os fatos reais da
VSM informados pelo responsável em 2026-09-26 revelaram dois deltas:

- **F6-06 (corrigido e validado):** o Hub passou a reconhecer o host de **Stage**
  (`conectavenda.stage.vsm.com.br`) como ambiente de teste, fechando um furo em que produção
  apontada para Stage passaria pelo go-live sem alarme.
- **F6-07 (provável, aberto):** a autenticação e os endpoints do Hub divergem da VSM real
  (token-exchange `/v1/auth/token`, `/v1/pedido/integradora`, modelo integradora/loja). Precisa do
  **Swagger oficial** para desenhar a correção sem inventar contrato.

**Próximos passos gated no responsável:** anexar o Swagger (JSON/YAML) para (a) popular
`contracts/vsm/` e ligar a comparação endpoint↔contrato no CI (F6-05) e (b) desenhar/implementar a
reescrita de auth do `VsmService` (F6-07). As credenciais de Stage não são guardadas no
repositório.
