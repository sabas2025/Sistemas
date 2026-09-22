# Documentação da API Tiny — referência para o Hub

**Data:** 2026-09-21 · **Estado:** parcial — ver aviso abaixo.

> ## ⚠️ Aviso de origem (leia primeiro)
> Este arquivo tem **duas metades** com procedência diferente, e elas **não podem ser confundidas**:
>
> - **Parte A — do CÓDIGO do Hub.** Extraída dos arquivos deste repositório (com arquivo e linha).
>   É o que o Hub **de fato faz hoje**. Evidência real, verificável no código.
> - **Parte B — da DOCUMENTAÇÃO OFICIAL da Tiny/Olist.** Os três links pedidos **não puderam ser
>   acessados** deste ambiente: a política de egresso da rede **bloqueia** `tiny.com.br` e
>   `api-docs.erp.olist.com`. Pela regra do projeto (*"nunca inventar endpoint ou configuração"*),
>   os campos oficiais ficam marcados **"Não identificado com as evidências disponíveis"** até serem
>   preenchidos a partir da fonte (print da tela, texto colado, ou liberação do domínio no proxy).
>
> **Não trate a Parte A como se fosse a documentação oficial:** ela é o comportamento do Hub, que
> pode divergir do que a Tiny publica — cruzar as duas é justamente o objetivo da validação (Fase 5).

**Fontes usadas (PDFs fornecidos pelo responsável, capturados 2026-09-21):**
- ✅ Limites da API **v2** — `https://tiny.com.br/api-docs/api2-limites-api` (§B.2)
- ✅ **v3** Criando um aplicativo — `https://api-docs.erp.olist.com/documentacao/comecando/criando-um-aplicativo` (§B.3)
- ✅ **v3** Autenticação e autorização — `https://api-docs.erp.olist.com/documentacao/comecando/autenticacao` (§B.3)
- ✅ **v3** Limites de requisição — `https://api-docs.erp.olist.com/documentacao/comecando/limites-de-consulta` (§B.4)
- ✅ **v3** Webhooks — `https://api-docs.erp.olist.com/documentacao/webhooks/webhooks` (§B.5)
- ✅ **v3** Integração de Clientes — `https://api-docs.erp.olist.com/documentacao/clientes/orientacoes` (§B.6)
- ✅ **v3** Homologação de Parceiros — `https://api-docs.erp.olist.com/documentacao/parceiros/homologacao` (§B.6)
- ⏳ **v2** Visão geral / lista de endpoints — `https://tiny.com.br/api-docs/api` *(pendente — único ainda não enviado)*
- ⏳ **v3/v2** Números exatos do rate limit por plano *(a doc v3 remete à "documentação do usuário do ERP")*

---

# PARTE A — O que o HUB implementa hoje (fonte: código deste repositório)

## A.1 — Tiny API V2

| Item | Valor no Hub | Arquivo:linha |
|---|---|---|
| Base URL (default) | `https://api.tiny.com.br/api2` | `config/config.example.php` (`tiny.v2_url`) |
| Autenticação | `token` + `formato=json` no corpo POST (`application/x-www-form-urlencoded`) | `app/Services/TinyV2Service.php:8` |
| Host allowlist | `api.tiny.com.br,accounts.tiny.com.br` · porta `443` | `config/config.example.php` |
| Timeout | 30 s total, 10 s de conexão | `TinyV2Service.php:37` |
| TLS | `SSL_VERIFYPEER=true`, `SSL_VERIFYHOST=2` | `TinyV2Service.php:37` |
| Resiliência | Circuit breaker `tiny_v2`; retry `RetryPolicyService::attempts()` (3) com backoff | `TinyV2Service.php:10,32-43` |
| Erro de negócio | `retorno.status_processamento !== 3` com `retorno.erros` | `TinyV2Service.php:60` |
| Limite de requisições | **reação**, não pré-limite: detecta `TINY_RATE_LIMIT` e recua **5/15/30 min** | `TinyErrorCatalog.php`, `TinyV2ErrorCatalogService.php:6` |

**Endpoints V2 usados** (`TinyV2Service.php:97-129`):
| Ação | Endpoint |
|---|---|
| Criar pedido | `pedido.incluir.php` |
| Consultar pedido | `pedido.obter.php` |
| Alterar situação do pedido | `pedido.alterar.situacao.php` |
| Criar produto | `produto.incluir.php` |
| Alterar produto | `produto.alterar.php` |
| Pesquisar produto (por SKU) | `produtos.pesquisa.php` |
| Consultar estoque (por ID) | `produto.obter.estoque.php` |
| Atualizar estoque | `produto.atualizar.estoque.php` |
| Incluir NF-e por XML | `nota.fiscal.incluir.xml.php` |

## A.2 — Tiny API V3 (OAuth 2.0)

| Item | Valor no Hub | Arquivo:linha |
|---|---|---|
| Base URL (default) | `https://api.tiny.com.br/public-api/v3` | `TinyV3Service.php:8` |
| Autenticação | `Authorization: Bearer <access_token>` | `TinyV3Service.php:41` |
| Content-Type / Accept | `application/json` | `TinyV3Service.php:41` |
| Grant de renovação | `grant_type=refresh_token` + `client_id` + `client_secret` | `TinyV3TokenService.php:237-239` |
| Guarda do token | AES-256-GCM + Token Vault interno | `TinyV3TokenService.php:52-54,116` |
| Expiração / folga | `expires_in` (default 3600 s); considera expirado com 60 s de antecedência | `TinyV3TokenService.php:81-85,101` |
| Renovação automática | ao usar token expirado, chama `refresh()` sob **lock** anti-concorrência (GET_LOCK, fallback arquivo) | `TinyV3TokenService.php:37-51,142-179` |
| Produção | exige OAuth salvo; **não** usa token manual | `TinyV3TokenService.php:60-67` |
| Ambientes | `homologacao` / `producao` separados na tabela `tiny_v3_tokens` | `TinyV3TokenService.php:95-114` |
| Config exigida | `tiny_v3_url`, `tiny_v3_token_url`, `tiny_v3_client_id`, `tiny_v3_client_secret`, `tiny_v3_redirect_uri`, `tiny_v3_scopes` | `TinyV3TokenService.php:287-291`, `homologationReady()` |
| Timeout | 30 s total, 10 s de conexão | `TinyV3Service.php:42` |

**Endpoints V3 usados** (`TinyV3EndpointCatalog.php:4-15`):
| Ação | Caminho |
|---|---|
| Listar produtos | `/produtos` |
| Obter produto | `/produtos/{id}` |
| Criar produto | `/produtos` |
| Alterar produto | `/produtos/{id}` |
| Preço do produto | `/produtos/{id}/preco` |
| Consultar estoque | `/estoque/produtos/{id}` |
| Atualizar estoque | `/estoque/produtos/{id}` |
| Obter pedido | `/pedidos/{id}` |
| Lançar estoque do pedido | `/pedidos/{idPedido}/lancar-estoque` |
| Obter NF-e | `/notas-fiscais/{id}` |

> **Nota:** o Hub **não** usa o recurso **Categorias** da V3 — não sincroniza categorias.

## A.3 — Webhooks de ENTRADA do Tiny (não confundir com chamadas de saída acima)

| Item | Valor no Hub | Arquivo:linha |
|---|---|---|
| Autenticação | segredo compartilhado no header `X-TINY-HUB-SECRET` (`hash_equals`) | `TinyWebhookSecurityService.php` |
| Proteções adicionais | allowlist de CNPJ (`tiny_webhook_cnpj_autorizados`), allowlist de IP, teto de payload, 2 limitadores | `TinyWebhookSecurityService.php` |
| Rate limit webhook | default 60/min (`tiny_webhook_rate_limit`) | `TinyWebhookSecurityService.php:8` |

---

# PARTE B — Documentação oficial da Tiny/Olist (A PREENCHER da fonte)

> Cada campo abaixo deve ser preenchido **copiando da fonte oficial** (print/colagem) e mantendo o
> link de origem. Enquanto não preenchido, permanece **"Não identificado com as evidências
> disponíveis"** — nunca deduzir de memória.

## B.1 — Visão geral da API
**Fonte:** `https://tiny.com.br/api-docs/api`

- Base URLs oficiais: *Não identificado com as evidências disponíveis.*
- Esquema de autenticação (V2 e V3): *Não identificado com as evidências disponíveis.*
- Lista de recursos/endpoints: *Não identificado com as evidências disponíveis.*
- Formato de requisição/resposta: *Não identificado com as evidências disponíveis.*

## B.2 — Limites da API V2 ✅ PREENCHIDO
**Fonte:** `https://tiny.com.br/api-docs/api2-limites-api` (PDF fornecido pelo responsável, capturado 2026-09-21).

**O limite é por EMPRESA e varia conforme o plano contratado.** A cada requisição, o Tiny retorna no
header **`x-limit-api`** a quantidade de chamadas permitidas por minuto para aquela empresa.

**Limites de chamadas (por minuto):**
| Plano | Chamadas/min | Chamadas de serviços em lote/min |
|---|---|---|
| Começar | **0** | **0** |
| Crescer | **30** | **5** |
| Evoluir | **60** | **5** |
| Potencializar | **120** | **5** |
| Descontinuados (Free, Teen, Premium, Profissional) | **20** | **5** |

- **Requisições concorrentes:** considerar como **1/4** do limite total.
- **Serviços SEMPRE contabilizados como chamada em lote** (indiferente da quantidade de registros):
  Incluir Contato, Alterar Contato, Incluir/Alterar Grupo de Tag, Incluir/Alterar Tag,
  **Incluir Produto**, **Alterar Produto**.
- **Limites de registros:** 20 registros por lote de envio; **100 registros resultantes por chamada**.
- Resposta/código HTTP ao exceder o limite: *Não identificado — a página fornecida não descreve a resposta de estouro.*
- Retry-after documentado: *Não identificado — não consta na página fornecida.*

> **Cruzamento com o Hub → ver "Achado T-01" abaixo.** O ponto crítico é que `Incluir Produto` e
> `Alterar Produto` — que o Hub chama no fluxo de produto (VSM→Tiny) — contam como **lote**, cujo
> teto é **5/min**, muito abaixo do limite geral (30/60/120). E o Hub **não lê `x-limit-api`**.

## B.3 — Criando um aplicativo + Autenticação (API v3 / OAuth 2.0) ✅ PREENCHIDO
**Fontes:** `https://api-docs.erp.olist.com/documentacao/comecando/criando-um-aplicativo` e
`.../comecando/autenticacao` (PDFs fornecidos 2026-09-21).

- **Tipo de aplicativo:** na v3, **apenas aplicativo Privado** está disponível (Público "em breve").
- **Uso próprio (conta própria):** **não** exige homologação; gera as chaves (`client_id`/`client_secret`) direto no painel do ERP.
- **Comercialização para terceiros:** homologação **obrigatória** (ver B.6). Cada vendedor instala o app na própria conta; o parceiro gere as credenciais.
- **Permissões:** marcadas no login do app; marcar o **mínimo** necessário; se mudar depois, o usuário refaz o login.
- **URL de redirecionamento:** informada no painel do app (campo *URL de redirecionamento*).

**OAuth 2 / OpenID Connect (Keycloak `realms/tiny`):**
| Item | Valor oficial |
|---|---|
| Authorization URL | `https://accounts.tiny.com.br/realms/tiny/protocol/openid-connect/auth` (`?client_id=&redirect_uri=&scope=openid&response_type=code`) |
| Token URL | `https://accounts.tiny.com.br/realms/tiny/protocol/openid-connect/token` |
| Grant inicial | `authorization_code` (+ `client_id`, `client_secret`, `redirect_uri`, `code`) |
| Grant de renovação | `refresh_token` (+ `client_id`, `client_secret`, `refresh_token`) |
| Header de uso | `Authorization: Bearer {access_token}` |
| **Access token — validade** | **4 horas** |
| **Refresh token — validade** | **1 dia** |
| Escopo | inclui `openid` |

## B.4 — Limites de requisição V3 ✅ PREENCHIDO
**Fonte:** `https://api-docs.erp.olist.com/documentacao/comecando/limites-de-consulta` (PDF fornecido).

- Limite **por minuto**, **por CONTA — não por aplicativo** (vários apps ativos **compartilham** o mesmo limite).
- **Diferenciado leitura vs escrita:** mais chamadas em **leitura** (`GET`) do que em **escrita** (`POST`/`PUT`/`DELETE`).
- Ao exceder: retorna **erro**; deve-se aguardar a liberação.
- Cabeçalhos de controle retornados:
  - `X-RateLimit-Limit` — limite total por minuto (exemplo mostrado: 120)
  - `X-RateLimit-Remaining` — disponível no minuto atual
  - `X-RateLimit-Reset` — segundos até o reset
- **Números exatos por plano:** *Não identificado — a página remete à "documentação do usuário do ERP, sessão Limites disponíveis", não incluída nos PDFs.*

## B.5 — Webhooks V3 (Olist → integrador) ✅ PREENCHIDO
**Fonte:** `https://api-docs.erp.olist.com/documentacao/webhooks/webhooks` (PDF fornecido).

- Instala-se o app **"Webhooks"** (planos específicos) e configuram-se as URLs em
  *Configurações → Aba Geral → Outras configurações → Webhooks*.
- **Confirmação:** o endpoint deve retornar **HTTP 200**; se o integrado não retornar o status, **o payload é reenviado**.
- **Reenvio:** até **10 vezes**, com atraso **progressivo, +5 min a cada tentativa**.
- **Não** é possível criar webhooks específicos por aplicativo (no momento).
- **Webhooks disponíveis:** venda criada/alterada; pedido enviado (status "enviado"); lançamento de estoque; nota fiscal autorizada.

## B.6 — Homologação de parceiros ✅ PREENCHIDO
**Fonte:** `https://api-docs.erp.olist.com/documentacao/parceiros/homologacao` + `.../clientes/orientacoes` (PDFs).

- **Só precisa homologar** quem vai **comercializar/distribuir** a solução a terceiros. Uso na própria conta: **não** precisa.
- 4 etapas: identidade visual · agendar reunião · **testes de autenticação (OAuth2, incluindo renovação de token)** · testes de rotas (endpoints, tratamento de erro).
- Contato técnico: `erp.partners@olist.com` (seg–sex, 09h–18h Brasília).

---

# PARTE C — Cruzamento com o Hub (achados da Fase 5)

## Esclarecimento sobre a meta de 500 pedidos/min
O fluxo de **Pedido é Tiny → Hub → VSM**: o Hub **recebe** o pedido por webhook e o envia à **VSM**,
não ao Tiny. Ele **não chama** `pedido.incluir.php` no caminho do pedido. Portanto **a meta de 500
pedidos/min NÃO é limitada pelo teto por-minuto do Tiny V2** — esse teto pesa sobre as chamadas de
**saída** ao Tiny (sincronização de produto, estoque, NF-e e mudança de status).

## Achado T-01 — Hub não lê `x-limit-api` e trata `Incluir/Alterar Produto` como chamada comum

- **Identificação:** rate limit do Tiny V2 é tratado de forma **reativa** e **sem distinguir chamadas de lote**.
- **Evidência:**
  - Doc oficial (B.2): `Incluir Produto` e `Alterar Produto` **sempre** contam como **lote**, teto **5/min**; o Tiny informa o teto real no header **`x-limit-api`**.
  - Código: o Hub **não lê `x-limit-api`** em lugar nenhum (`app/`, `workers/` — busca vazia).
  - Código: `ApiController.php:693` chama `criarProduto()`/`atualizarProduto()` → `TinyV2Service.php:98-99` → `produto.incluir.php`/`produto.alterar.php` (as duas chamadas de **lote**).
- **Arquivo/método:** `TinyV2Service::criarProduto/atualizarProduto`; consumidor `ApiController.php:693`; **ausência** de leitor de `x-limit-api`.
- **Gravidade:** **Média** (afeta throughput do fluxo de PRODUTO; **não** há perda de dado — o Hub recua ao receber o erro).
- **Causa raiz:** o controle de limite é reativo (`TINY_RATE_LIMIT` → recuo 5/15/30 min) e não modela o teto de **lote** (5/min), muito menor que o geral (30/60/120).
- **Impacto:** a sincronização de produto (VSM→Tiny) fica efetivamente limitada a **~5 criações/alterações por minuto**; acima disso o Tiny recusa e o Hub recua — a sincronização atrasa. No plano **Começar** o limite de lote é **0**: nenhum produto sincroniza.
- **Cenário de falha:** carga inicial ou lote grande de produtos novos/alterados da VSM enfileira >5/min → estouro do limite de lote → recuo repetido → fila de produto represada.
- **Correção recomendada (NÃO aplicar sem evidência de volume — fase 10):**
  (a) ler `x-limit-api` e **pré-throttlar** proativamente pela fachada `RateLimitService`;
  (b) modelar `produto.incluir/alterar` como classe **lote** com teto próprio de 5/min no agendamento do worker de produto;
  (c) avaliar o **serviço de lote** do Tiny (envio de até 20 registros por chamada) para reduzir nº de chamadas.
- **Risco da correção:** baixo-médio — pré-throttle mal calibrado atrasa sincronização legítima; nunca deve **bloquear** chamada legítima (regra inegociável).
- **Compatibilidade:** aditiva; preserva o comportamento reativo atual como rede de segurança.
- **Como testar:** exercitar o worker de produto com N>5 itens/min contra Tiny (real ou mock) e confirmar respeito ao 5/min sem estouro.
- **Como reverter:** remover o pré-throttle; volta ao comportamento reativo atual.
- **Status:** **confirmado** que o gap existe no código; **impacto sob a operação real: A CONFIRMAR** — depende da frequência/volume real do worker de produto (dado pendente do responsável).

## Ponto informativo T-02 — o Hub reage, mas "às cegas" quanto ao plano — OBSERVABILIDADE ADICIONADA (Fase 1.5)
Sem ler `x-limit-api`, o Hub não sabia o teto da empresa (varia por plano: 0/20/30/60/120). **Corrigido
(observabilidade):** `TinyRateLimitObserverService` captura `x-limit-api` via `CURLOPT_HEADERFUNCTION` e
o `TinyV2Service` registra o teto na trilha (`tiny.v2.response.retorno.limite_api`) e no retorno.
**Só leitura — não impõe throttle.** O pré-limite ativo (T-01) segue pendente de evidência de volume,
mas agora essa evidência é **medida pelo próprio Hub** em vez de estimada.

## Achado T-03 — refresh token V3 dura 1 DIA e o Hub não o renova proativamente

- **Identificação:** o token de atualização (refresh) da V3 expira em **1 dia** (B.3); a renovação do Hub é **sob demanda**, não agendada.
- **Evidência:**
  - Doc oficial (B.3): access token **4 h**, refresh token **1 dia**.
  - Código: `TinyV3TokenService::refresh()` só é chamado (a) automaticamente dentro de `accessToken()` quando o token está expirado **e há uma chamada acontecendo**, e (b) manualmente pelo botão em `DashboardController.php:1243` (`refresh(true)`).
  - Código: **não existe worker/cron de refresh** (`ls workers/ | grep token` → nenhum).
- **Gravidade:** **Média** — quebra o fluxo V3 após ociosidade; sem perda de dado, mas exige **reconexão OAuth manual**.
- **Causa raiz:** a renovação depende de tráfego de saída V3. Se o Hub ficar **> 1 dia sem chamar a V3** (fim de semana quieto, instalação que usa V2 como operacional, período de baixa), o refresh token morre; a próxima renovação falha e só o OAuth manual reconecta.
- **Escopo:** afeta instalações que usam a **V3 como caminho operacional**. O default do Hub é `tiny.version='v2'` — onde a V3 não opera, o ponto é inócuo.
- **Correção APLICADA (Fase 1, 2026-09-21):** worker `workers/worker_tiny_v3_refresh.php` — renova o
  token dentro da janela de 1 dia (`refresh(true)`), reusando o **lock** do serviço. **Aditivo e
  guardado:** se a V3 não estiver configurada ou sem token salvo, **não faz nada** (exit 0); nunca
  bloqueia chamada. Agendar no cron a cada 6 h **apenas quando a V3 for o caminho operacional**.
- **Risco:** baixo — uma chamada de refresh a mais por janela; o lock evita concorrência; sem config é no-op.
- **Validação (MariaDB 10.11 real, 4 ramos):**
  (1) V3 não configurada → **no-op, exit 0**;
  (2) configurada + access token expirado → **atravessa as guardas e renova** (sem rede: exit 1, correto);
  (3) token saudável (renovado agora) → **no-op, exit 0** (não martela);
  (4) preventivo (não expirado, última renovação há 15 h > 12 h) → **renova** (sem rede: exit 1).
  Portões: php-lint, classmap (250) e 55 testes enterprise **verdes**.
- **Como reverter:** desagendar/apagar o worker (o comportamento sob demanda atual permanece intacto).
- **Status:** **corrigido e validado.** Resta ação do operador: **agendar o cron** quando a V3 for operacional
  (o worker é inócuo até lá).

## Achado T-04 — códigos HTTP do webhook de pedido vs. a exigência de 200 da Olist

> **Correção de relato (2026-09-21):** uma versão anterior desta linha dizia "o pedido aceito responde
> 202". **Impreciso.** Ao reler `ApiController::webhookTinyPedido` e `responderTiny` (default **200**),
> o quadro real é o abaixo — o **caminho feliz já responde 200 e cumpre a doc**. (O `202` da
> `ApiController.php:170` é de OUTRO fluxo, VSM→Tiny produto pendente, não deste webhook.)

- **Evidência da doc (B.5):** *"o webhook deverá retornar o status HTTP 200"*; se não retornar, a Olist
  **reenvia até 10×, +5 min progressivo**.
- **Códigos reais do webhook de pedido do Tiny** (`ApiController::webhookTinyPedido`):
  | Situação | Código | Linha | Conforme (200)? |
  |---|---|---|---|
  | Aceito e **auto-enfileirado** para a VSM (caminho feliz padrão) | **200** | `:294` (`responderTiny(true,…)`, default 200) | ✅ já conforme |
  | Validado, **aguardando aprovação manual** | **202** | `:303-310` (`…?202:422`) | ⚠️ só nessa config |
  | **Bloqueado** por validação | **422** | `:303-310` | reenvia (dedup) |
  | Segurança / payload / enfileiramento | 401 / 400 / 500 | `:282,283,295` | reenvia (bom p/ 500) |
- **Gravidade:** **Baixa / Informativa** — o caminho principal já cumpre a doc; o desvio existe só na
  config de aprovação manual (202) e no bloqueio (422).
- **Impacto (se a Olist exigir 200 exato):** nas duas situações de desvio, a Olist reenviaria até 10×; a
  idempotência (`pedido_origem_id`, 1 linha para N reenvios) **impede duplicação**, mas há reprocessamento.
- **O que NÃO é testável aqui:** a reação da Olist ao 202/422 roda **no servidor da Olist** — sem rede
  nem conta de teste, não há como observá-la. A doc é a única fonte, e ela diz 200.
- **Decisão de produto (do responsável), explicitamente marcada no código (`ApiController.php:297-301`)
  e no CLAUDE.md — *"mudar o código HTTP altera quando o Tiny reenvia"*:**
  (a) pedido **pendente de aprovação** → manter `202` ou devolver `200` (ack, sem reenvio)?
  (b) pedido **bloqueado** → manter `422` (Olist reenvia) ou `200` (ack, para de reenviar um bloqueio)?
- **Se decidida:** a mudança é pequena e localizada; o lado do Hub (código devolvido + idempotência no
  reenvio) é validável contra banco real. A reação da Olist continua fora de alcance.
- **Status:** **não é defeito** — caminho feliz conforme; o resto é decisão de produto pendente.

## Achado T-05 — limite V3 é por CONTA e o Hub não lia `X-RateLimit-*` — OBSERVABILIDADE ADICIONADA (Fase 1.5)
O limite V3 é **por conta, compartilhado entre apps**, e diferencia leitura/escrita. **Corrigido
(observabilidade):** `TinyRateLimitObserverService::v3()` normaliza `X-RateLimit-Limit/Remaining/Reset`
e o `TinyV3Service` registra o orçamento na trilha (`tiny.v3.response.retorno.rate_limit`) e no retorno.
**Só leitura — não impõe throttle.** Enforcement segue como T-01 (pendente de evidência de volume).

## Confirmações POSITIVAS do cruzamento (o Hub bate com a doc)
- **URLs de OAuth idênticas à doc:** o seed do instalador já grava
  `accounts.tiny.com.br/realms/tiny/protocol/openid-connect/auth` e `/token` (`core.sql:397`). ✅
- **Host `accounts.tiny.com.br` na allowlist** (`tiny_allowed_hosts`). ✅
- **Grants corretos:** `authorization_code` na entrada e `refresh_token` na renovação, com `client_id`/`client_secret`. ✅
- **`Authorization: Bearer` + guarda de token cifrada + refresh automático sob demanda.** ✅
- **Validade do access token:** o Hub honra o `expires_in` que a Tiny devolver (4 h); o default 3600 é só **fallback** conservador (renova antes). ✅
- **Reenvio de webhook (10×, +5 min):** casa com a idempotência do Hub por `pedido_origem_id` (I-14) — reenvio não duplica. ✅

---

## Como completar a Parte B
1. **Mandar prints** das três páginas (ou colar o texto) — eu transcrevo campo a campo, mantendo o link.
2. **Ou** liberar `tiny.com.br` e `api-docs.erp.olist.com` no proxy de egresso — aí busco direto.

Ao preencher, cruzar cada valor oficial com a Parte A e anotar divergência (ex.: limite oficial vs.
frequência dos workers; escopos exigidos vs. endpoints usados na A.2).
