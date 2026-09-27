# Relatório — Fase 4 (Segurança), varredura por superfície

**Data:** 2026-09-27
**Release base:** V104.49.3-R7 (branch `claude/security-audit-skill-9cd1qi`, sobre `main` `e2efe29`)
**Fase (PROMPT_-_HUB / CLAUDE.md):** 4 — Segurança, com **política separada por superfície**
(painel · login · API · webhook · OAuth callback · worker · cron · instalação).
**Regra que governou o trabalho:** começar pela auditoria; não alterar código antes de apresentar
evidência e impacto. Esta passagem **não alterou código** — não há defeito confirmado ou provável a
corrigir.

---

## 1. Escopo e método

Auditadas as superfícies de entrada do Hub e os controles centrais de segurança
(`app/Core/App.php`, `public/index.php`, `FastRouteDispatcherService`, `ApiController`,
`BackupController`/`BackupService`, `CryptoService`, `TinyEndpointSecurityService`,
`TinyWebhookSecurityService`, `Csrf`, `public/install.php`, os 12 `public/worker_*.php`,
`public/pwa_telemetry.php`). Método: leitura estática do código + conferência das ordens de gate.

**Primeiro passo obrigatório:** leitura do `SECURITY.md`, que documenta 7 **decisões deliberadas**
contraintuitivas. Nenhuma foi tratada como defeito (§4).

Esta passagem é estática e se apoia na validação de runtime já registrada no `CLAUDE.md` (login,
força bruta, sessão, rotas de mutação, webhooks — todos medidos contra MariaDB em sessões
anteriores). Não se reexecutou a matriz de runtime aqui.

---

## 2. Resultado por superfície

| Superfície | Controles verificados | Arquivo/método | Status |
|---|---|---|---|
| **Bootstrap/Sessão** | cookie `Secure` (condicional a proxy confiável), `HttpOnly`, `SameSite=Strict`, `use_strict_mode`, `use_only_cookies`, `gc_maxlifetime` 8h — aplicados **antes** do `session_start()` | `public/index.php:86-108` | confirmado |
| **Headers/CSP** | CSP com **nonce** (sem `unsafe-inline`), `default-src 'self'`, `object-src 'none'`, `frame-ancestors 'self'`, `form-action 'self'`, `upgrade-insecure-requests`; XFO SAMEORIGIN, nosniff, Referrer-Policy no-referrer, Permissions-Policy travada, COOP/CORP same-origin, X-Permitted-Cross-Domain none; `no-store` em telas autenticadas; HSTS em produção | `App::sendSecurityHeaders` (89-119) | confirmado |
| **Exposição de erro** | `display_errors=0` fora de local; toda resposta de erro devolve só o Trace ID; nunca stack trace ao usuário final | `App::setupErrors` (120-129); `public/index.php` fallback | confirmado |
| **Produção (fail-safe)** | bloqueia `app_env=local` em host público; **host allowlist** via `TrustedProxyService` (P0-08); redirect HTTPS sem confiar em `Host`/`X-Forwarded` do cliente; **recusa segredos vazios/fracos** (6 chaves, ≥32 chars) | `App::enforceProductionSafety` (32-88) | confirmado |
| **Painel** | `Auth::requireLogin()` + `PermissionService::require(...)` por controller; CSRF em toda mutação; 404 real para rota inexistente (3.7) | controllers; `FastRouteDispatcherService` | confirmado |
| **Login** | rate limit real (bloqueia da 4ª tentativa, recusa até a senha correta durante o bloqueio); regeneração de sessão; revogação por `session_version`/`deve_trocar_senha`; mensagem genérica proposital | validado em MariaDB (tasks anteriores) | confirmado |
| **API** | `api/status` público em modo **minimal** (não expõe fila/DLQ/Tiny/VSM/CB a anônimo); `api/processar-fila` exige login + `PermissionService::require('fila','reprocessar')` + POST + CSRF; `notificacao/lida` idem; **sem CORS** (same-origin) | `ApiController::statusJson` (796), `processarFila` (543) | confirmado |
| **Webhook** | Tiny = segredo `X-TINY-HUB-SECRET` (`hash_equals`); VSM = HMAC v2; limitador de **tentativas antes** da autenticação (A-09); listas de IP/CNPJ; teto de payload | `TinyWebhookSecurityService`, `WebhookSecurityService` | confirmado |
| **OAuth callback** | fora do gate de sessão **por design** (3.2), mas: state assinado uso único/TTL consumido **antes** da troca (P0-01), **PKCE S256**, identidade da transação + autorização pelo estado atual do banco (B-01/B-03), permissão `configuracoes.editar`, guarda SSRF na token URL, allowlist de IP admin roda antes | `DashboardController::dispatchOAuthCallback` | confirmado |
| **Worker** | os **12** shims em `public/` bloqueiam acesso via navegador (`PHP_SAPI !== 'cli'` → 403); a lógica real vive em `workers/` | `public/worker_*.php` | confirmado |
| **Cron** | mesma via CLI dos workers; sem endpoint HTTP | `workers/` | confirmado |
| **Instalação** | `storage/install.lock` + código de autorização **uso único** (`state='issued'`) com TTL, `hash_equals` no `token_hash`, binding de navegador (sessão + User-Agent); consolidado é template com marcadores | `public/install.php:102-115` | confirmado |
| **Upload (backup)** | allowlist de extensão (`sql`/`zip`), `is_uploaded_file`, `finfo` MIME conferido contra a extensão, `move_uploaded_file` para diretório fixo, `basename` no download/delete (sem path traversal), guarda de zip-bomb por ratio; **restaurar importado exige digitar `RESTAURAR IMPORTADO`**, com a proveniência lida da **assinatura**, não da coluna (3.5) | `BackupService::importarUpload`, `BackupController` | confirmado |
| **Telemetria PWA** | só POST, exige `Sec-Fetch-Site` same-origin/same-site (A-07), teto de tamanho; sem cookies/tokens | `public/pwa_telemetry.php` | confirmado |
| **SSRF (saída)** | HTTPS-only, allowlist host+porta, bloqueio de IP privado/reservado, pin via `CURLOPT_RESOLVE`, redirects desativados | `TinyEndpointSecurityService`, `VsmEndpointSecurityService` | confirmado |
| **Segredos em repouso** | AES-256-GCM; `encrypt` idempotente; `decrypt` tolerante a vazio/texto-puro; simetria leitura×escrita para todos os segredos reais | `CryptoService`, `IntegrationConfig`, `ConfiguracaoController` | confirmado |
| **CSRF** | token `bin2hex(random_bytes(32))` por sessão, estável; `validate()` com `hash_equals` | `app/Core/Csrf.php` | confirmado |

---

## 3. Achados

**Nenhum defeito confirmado ou provável identificado** nas superfícies auditadas. Não há correção a
aplicar nesta fase.

Não se declara o sistema "100% seguro" (proibição explícita do CLAUDE.md). Declara-se que, **com as
evidências disponíveis**, a varredura por superfície não encontrou brecha, e que a postura é coerente
com o histórico de correções já aplicadas nesta linha (A-01..A-13, B-01..B-06, C-01..C-08, F-01/02,
G-01..G-06, H-01, P0-*, T-*).

---

## 4. Decisões deliberadas confirmadas intactas (não "corrigir" sem teste)

Conforme `SECURITY.md` §3. Foram verificadas e **mantidas**:

1. **Degradação de rate limit por superfície** — painel abre (com registro), webhook/telemetria
   fecham, login escala para contador de sessão. `RateLimitService::POLICIES` recusa superfície sem
   política declarada.
2. **Callback OAuth fora do gate de sessão** — necessário (cookie `SameSite=Strict` não vem no
   retorno cross-site); não é anônimo (state assinado + authz pelo banco).
3. **Assinatura v1 de webhook ainda aceita** — dívida com prazo, até a VSM migrar; cada v1 aceito
   vira evento de segurança.
4. **Rota classificada por igualdade** via `RouteCatalogService`, nunca por substring (B-06).
5. **Backup importado ≠ gerado localmente** — proveniência dentro do payload assinado; restauração
   lê a assinatura, não a coluna.
6. **Redirect HTTPS fora do `.htaccess`** — feito por `App::enforceProductionSafety` com proxy
   confiável e host canônico.
7. **Rota inexistente devolve 404 real** — evento de auditoria só para sessão autenticada.

---

## 5. Limites (sem validação nesta passagem)

- **Runtime**: não se reexecutou a matriz completa contra banco real nesta fase; a auditoria é
  estática e apoia-se na validação de runtime já registrada no `CLAUDE.md`.
- **Multiempresa (2+ empresas)**: fora do escopo desta fase; permanece com a dívida documentada
  (H-01) e bloqueada em pré-requisitos externos.
- **Chamadas reais a Tiny/VSM e ciclo OAuth completo**: dependem de credenciais reais.

"A CI está verde" **não** equivale a "o Hub está inviolável": o verde cobre os portões, e esta fase
cobre as superfícies pela leitura — nenhuma das duas cobre um pentest dinâmico contra a instalação
de produção real.
