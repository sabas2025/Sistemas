# Relatório — Fase 5 (Tiny ERP V2 e V3)

**Data:** 2026-09-27
**Release base:** V104.49.3-R7 (branch `claude/security-audit-skill-9cd1qi`, sobre `main`)
**Fase (PROMPT_-_HUB / CLAUDE.md):** 5 — Tiny ERP, validando **V2 e V3 separadamente**
**Regra que governou o trabalho:** começar pela auditoria; não alterar código antes de apresentar
evidência e impacto. As correções aplicadas (F5-01, F5-02) foram autorizadas explicitamente pelo
responsável **após** a auditoria, e o escopo destrutivo do F5-01 (DROP das colunas) foi decisão de
produto tomada nesta sessão.

---

## 1. Escopo e método

Auditadas as **26 classes** da superfície Tiny (`app/Connectors`, `app/Controllers`,
`app/Services`), o caminho de configuração/segredos (`IntegrationConfig`, `ConfiguracaoController`,
`CryptoService`) e o schema de `configuracoes_integracao`/`tiny_v3_tokens`. Método: leitura estática
do código + comparação de contratos + **validação de schema contra MariaDB 10.11 real**. Não há
credenciais reais do Tiny neste ambiente, então o ciclo OAuth completo e qualquer chamada real ao
provedor **não** foram exercitados (ver §6).

Os dois clientes foram tratados como sistemas distintos, como exige o CLAUDE.md
("Nunca assumir que V2 e V3 se comportam igual").

---

## 2. Resultado da auditoria por eixo (V2 × V3)

| Eixo | Tiny V2 | Tiny V3 | Status |
|---|---|---|---|
| Autenticação | `token` no corpo do POST, form-encoded | `Bearer` no header, JSON; **OAuth obrigatório em produção** (`TinyV3TokenService::accessToken`) | confirmado |
| Segredo em repouso | `tiny_v2_token` cifrado AES-256-GCM | `client_secret` + access/refresh cifrados; **Token Vault** como fonte primária | confirmado |
| Simetria cifra leitura×escrita | `IntegrationConfig::get` decifra ⇄ `ConfiguracaoController` cifra | idem | confirmado |
| SSRF/saída | `TinyEndpointSecurityService`: HTTPS-only, allowlist host+porta, bloqueio de IP privado/reservado, **pin de IP via `CURLOPT_RESOLVE`**, redirects off | idem (mesma classe) | confirmado |
| Timeout/retry | 30s/10s; backoff exponencial + jitter (sem latência na 1ª tentativa); circuit breaker | idem | confirmado |
| Erro/log | G-01 e G-02 corrigidos: `sanitizeForStorage()` + `mask()` em request e resposta | `mask()` sempre (desenho de referência) | confirmado |
| Rate limit do provedor | observabilidade `x-limit-api` (T-02), sem throttle | `X-RateLimit-*` (T-05) | confirmado |
| OAuth code→token | n/a | state uso único/TTL **antes** da troca (P0-01), **PKCE S256**, identidade assinada + authz pelo estado atual do banco (B-01/B-03), permissão `configuracoes.editar`, SSRF na token URL | confirmado |
| Renovação de token | n/a | refresh sob **lock** (GET_LOCK + fallback de arquivo), reuso se outro processo já renovou | confirmado |
| Webhook de entrada | segredo compartilhado `X-TINY-HUB-SECRET` (`hash_equals`), limitador de tentativas **antes** da auth (A-09), CNPJ/IP/payload | (VSM usa HMAC — desenhos deliberadamente não copiados entre si) | confirmado |

**Conclusão da auditoria:** a superfície Tiny V2/V3 está endurecida e coerente com o histórico de
correções (G-01/02, A-02/09, B-01/03, P0-01, I-10/14, T-02/05, guarda SSRF). **Não foi identificado
defeito confirmado ou provável.** Não se declara o sistema "100% seguro" — declara-se que, com as
evidências disponíveis, nenhum defeito foi encontrado. Duas observações de qualidade foram
levantadas e corrigidas (F5-01, F5-02, §3–§4).

---

## 3. Achado F5-01 — colunas mortas do Tiny V3 manual

- **Identificação:** `tiny_v3_manual_access_token` e `tiny_v3_manual_refresh_token` são colunas
  mortas em `configuracoes_integracao`.
- **Evidência:** decifradas em `IntegrationConfig.php:7`; lidas apenas como `!empty()` em
  `ProductionReadinessV50Service.php:48`, `OperationCenterService.php:102` e `:191`,
  `ProductionGoLiveService.php:163`, **sempre em `OR` com o token vivo** (`tiny_v3_token`/OAuth);
  nunca gravadas por caminho nenhum (o formulário de `ConfiguracaoController` não as escreve).
  Semeadas `''` no instalador. `accessToken()` usa `tiny_v3_token`, não estas.
- **Arquivo/classe/método/tabela:** `configuracoes_integracao` (colunas); `IntegrationConfig::get`;
  os três serviços acima; `LegacyDatabaseUpgradeController::atualizarV17TinyV3` (mapa que as
  recriava); `database/modules/core.sql`.
- **Gravidade:** Informativa.
- **Causa raiz:** colunas previstas para um caminho de "token manual" que nunca foi implementado; o
  access token manual real acabou em `tiny_v3_token`. As colunas ficaram como leitura morta.
- **Impacto:** nenhum em runtime (`CryptoService::decrypt` tolera vazio/texto-puro e `encrypt` é
  idempotente). Risco só de **leitura enganosa**: sugerem um caminho de token manual inexistente e
  poluem o schema.
- **Cenário de falha:** não há falha funcional; o risco é um mantenedor futuro assumir que existe um
  fluxo de token manual V3 e ramificar lógica sobre um sinal que é sempre falso.
- **Correção recomendada / aplicada:** remover as leituras (comportamento idêntico, pois o termo
  removido sempre avaliava `false`) e **dropar as colunas nos três caminhos de schema no mesmo
  commit** (lição I-18): `database/modules/core.sql`, consolidado regenerado
  (`install.sql` + `install_final_current.sql`) e migration `database/migrations/20260927_019_drop_tiny_v3_manual_token_columns.sql`.
  A migration usa `information_schema.COLUMNS` + `PREPARE`/`IF` (idempotente e **portável MySQL 8 +
  MariaDB**, sem `DROP COLUMN IF EXISTS`). Retiradas também do mapa do upgrade legado V17, que as
  recriaria e quebraria a paridade.
- **Risco da correção:** baixo. Nenhum código volta a lê-las; sem regra de negócio alterada. O único
  risco previsto (upgrade V17 recriar as colunas) foi eliminado no mesmo commit.
- **Compatibilidade:** preservada. Instalação existente que porventura tivesse valor nessas colunas
  o perde — mas o valor nunca foi lido nem gravado por caminho vivo. Sem impacto em PHP, MySQL,
  MariaDB, hospedagem compartilhada, Tiny ou VSM.
- **Como testar:** `tests/enterprise/v104_49_3_tiny_dead_columns_removal_test.php` (reprova sobre o
  código antigo); paridade em MariaDB (§5).
- **Como reverter:** reverter o commit e recriar as colunas —
  `ALTER TABLE configuracoes_integracao ADD COLUMN tiny_v3_manual_access_token TEXT NULL, ADD COLUMN tiny_v3_manual_refresh_token TEXT NULL;`
- **Status:** corrigido e validado.

---

## 4. Achado F5-02 — sonda de tabela por chamada no log do V2

- **Identificação:** `TinyV2Service::logEndpoint()` sondava a existência da tabela a cada chamada.
- **Evidência:** `TinyV2Service.php` fazia
  `SHOW TABLES LIKE 'tiny_v2_endpoint_logs'` + `if(!$st->fetch()) return;` antes do `INSERT`. O
  `TinyV3Service::logEndpoint()` **nunca** sondou — tenta o `INSERT` e deixa o `catch(Throwable)`
  tratar tabela ausente como best-effort.
- **Arquivo/classe/método/tabela:** `TinyV2Service::logEndpoint`; tabela `tiny_v2_endpoint_logs`.
- **Gravidade:** Informativa.
- **Causa raiz:** divergência de desenho entre V2 e V3 herdada antes das correções G-01/G-02.
- **Impacto:** um round-trip local extra por chamada Tiny V2 — desprezível frente à latência de rede
  (timeout de 30s). Não é o defeito do I-10 (aqui o `LIKE` é literal, válido com prepares nativos).
- **Cenário de falha:** nenhum funcional; apenas custo e inconsistência de desenho.
- **Correção recomendada / aplicada:** remover a sonda; confiar no `catch(Throwable)` já existente,
  como o V3. Se a tabela faltar, o `INSERT` falha e é rebaixado a `BestEffortLogService::warning` —
  mesmo resultado líquido (sem log), com visibilidade igual à do V3.
- **Risco da correção:** baixo. O `INSERT` e o `catch` best-effort foram preservados.
- **Compatibilidade:** preservada.
- **Como testar:** `tests/enterprise/v104_49_3_tiny_dead_columns_removal_test.php` (asserção
  ancorada no código executável, não no comentário — armadilha da varredura acusar a própria doc).
- **Como reverter:** reintroduzir a sonda `SHOW TABLES LIKE 'tiny_v2_endpoint_logs'` no início do
  `try` de `logEndpoint`.
- **Status:** corrigido e validado.

---

## 5. Validação em banco real (MariaDB 10.11)

| Validação | Resultado |
|---|---|
| Instalação nova (módulos atuais) tem as colunas manual? | **não** — 0 colunas |
| Instalação que as tinha + migration `019` | **2 → 0** colunas |
| Reexecução da migration `019` (idempotência) | no-op, exit 0 |
| Migration usa `DROP COLUMN IF EXISTS`? | **não** — portável MySQL 8 + MariaDB |
| Paridade instalação nova × atualização em `configuracoes_integracao` | **idêntica** — 131 colunas de cada lado, diff vazio (N impresso dos dois lados) |

Portões estáticos (todos verdes): **78 testes enterprise** (o novo reprova sobre o código antigo),
`php-lint`, `build-classmap --check` (257 classes), `controller-route-check` (220 mapeamentos),
`tenant-scope-check`, `secret-hygiene-check`, `vsm-openapi-check`, `sql-inventory-check`
(136 tabelas), `mysql-schema-static-check`, `mysql-module-parity-check`,
`build-consolidated-schema --check`, `schema-runtime-ddl-check`. Manifesto FIM regenerado por
último.

**Entregue em:** PR #59, mergeado em `main` como `c019cce`.

---

## 6. Sem validação (limites honestos)

- **Ciclo OAuth Tiny V3 completo** com o servidor real — depende de credenciais reais (pendência já
  registrada no CLAUDE.md).
- **Qualquer chamada real ao Tiny** (V2 ou V3) — idem.

A auditoria acima é estática + validação de schema. "A CI está verde" **não** equivale a "o Hub está
validado contra o Tiny real".

---

## 7. Observações que permanecem (não são defeito)

- A superfície de webhook do Tiny (segredo compartilhado) e a da VSM (HMAC) **não** são simétricas,
  por decisão de produto — não copiar o desenho de um lado no outro (já registrado no CLAUDE.md).
- O webhook de **pedido** do Tiny carrega CNPJ; o da VSM não carrega empresa — isso decide a metade
  aberta do H-01 (2+ empresas), que permanece bloqueado em pré-requisitos externos.
