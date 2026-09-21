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

**Links de origem pedidos (a preencher na Parte B):**
1. Visão geral da API — `https://tiny.com.br/api-docs/api` *(inacessível: egress bloqueado)*
2. Limites da API v2 — `https://tiny.com.br/api-docs/api2-limites-api` *(inacessível: egress bloqueado)*
3. Criando um aplicativo (API v3 / OAuth) — `https://api-docs.erp.olist.com/documentacao/comecando/criando-um-aplicativo` *(inacessível: egress bloqueado)*

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

## B.2 — Limites da API V2
**Fonte:** `https://tiny.com.br/api-docs/api2-limites-api`

- Número de requisições permitido e janela (ex.: X/min, Y/dia): *Não identificado com as evidências disponíveis.*
- O limite é por token, por conta ou por IP: *Não identificado com as evidências disponíveis.*
- Resposta/código ao exceder o limite: *Não identificado com as evidências disponíveis.*
- Tempo de espera / retry-after documentado: *Não identificado com as evidências disponíveis.*

> **Por que este é o campo mais importante:** o Hub hoje **reage** ao limite (recua 5/15/30 min),
> mas **não pré-limita** a frequência dos workers. Com o número oficial aqui, dá para confirmar se a
> frequência combinada dos workers respeita o teto na meta de 500 pedidos/min — ou se precisa de um
> pré-throttle no `RateLimitService`.

## B.3 — Criando um aplicativo (API v3 / OAuth 2.0)
**Fonte:** `https://api-docs.erp.olist.com/documentacao/comecando/criando-um-aplicativo`

- Passo a passo de criação do aplicativo: *Não identificado com as evidências disponíveis.*
- Client ID / Client Secret (onde obter): *Não identificado com as evidências disponíveis.*
- Redirect URI (exigências): *Não identificado com as evidências disponíveis.*
- Escopos disponíveis / exigidos (strings): *Não identificado com as evidências disponíveis.*
- Authorization URL: *Não identificado com as evidências disponíveis.*
- Token URL: *Não identificado com as evidências disponíveis.*
- Tempo de vida do access token: *Não identificado com as evidências disponíveis.*
- Tempo de vida do refresh token / rotaciona?: *Não identificado com as evidências disponíveis.*
- Rate limit da V3 (se houver): *Não identificado com as evidências disponíveis.*

---

## Como completar a Parte B
1. **Mandar prints** das três páginas (ou colar o texto) — eu transcrevo campo a campo, mantendo o link.
2. **Ou** liberar `tiny.com.br` e `api-docs.erp.olist.com` no proxy de egresso — aí busco direto.

Ao preencher, cruzar cada valor oficial com a Parte A e anotar divergência (ex.: limite oficial vs.
frequência dos workers; escopos exigidos vs. endpoints usados na A.2).
