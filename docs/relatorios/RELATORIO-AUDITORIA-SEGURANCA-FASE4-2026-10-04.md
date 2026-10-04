# Relatório — Auditoria de Segurança (Fase 4) — 2026-10-04

> Rodada de auditoria da **Fase 4 (Segurança)** das 12 fases do `CLAUDE.md`.
> **Natureza:** varredura read-only. Nenhuma alteração de código foi aplicada nesta auditoria.
> **Base:** `main` em `2009554` (após #83–#87).
> **Pré-requisito cumprido:** `SECURITY.md` lido antes de classificar qualquer defeito — as decisões
> deliberadas lá descritas NÃO são tratadas como achados.

## Escopo (classes verificadas)

SQLi · XSS · CSRF · open redirect · path traversal · upload · segredo em log · cifra de token ·
proteção de webhook · headers/CSP/cookies · CORS · exposição de erro. Política por superfície
(painel · login · API · webhook · OAuth callback · worker · cron · instalação).

## Método (evidências medidas)

- **XSS:** `grep` de `<?=`/`echo` de `$_GET/$_POST/$_REQUEST/$_SERVER` em `views/`.
- **SQLi:** `grep` de `query/exec/prepare` com `$_GET/$_POST`, e heurística de concatenação de
  variável crua em `SELECT/INSERT/UPDATE/DELETE/FROM/WHERE`; inspeção manual de cada ocorrência.
- **Open redirect:** `grep` de `redirect(` com dado de request.
- **Path traversal / upload:** `grep` de `include/require/file_get_contents/fopen/readfile/unlink`
  com request; `$_FILES`; leitura de `BackupService::importarUpload`.
- **Segredo em log:** `grep` de `Audit::event/error_log/->log(` com `senha/password/secret/token`;
  inspeção do retorno de `TinyV3TokenService::refresh()`.
- **CORS / headers / cookies:** `grep` de `Access-Control-Allow-Origin` e dos headers em `app/Core/App.php`.

## Sinais verdes (medidos)

| Classe | Resultado |
|---|---|
| SQLi via request | **0** interpolação de `$_GET/$_POST` em query |
| SQLi interno | 2 ocorrências, ambas seguras: `IntegrationReplayGuardService` (`(int)$days`), `Database::indexExistsOn` (identificador validado por `^[a-zA-Z0-9_]+$` na linha 448 antes da interpolação + `$pdo->quote($index)`) |
| XSS | toda saída de `$_GET` em view passa por `e()` ou é comparada a literal; nenhum echo cru |
| CSRF | rotas de mutação com `Csrf::validate()` (spot-check: `tinyV3TokenRenovar/Revogar`); forms com `Csrf::input()`; token por sessão estável |
| Open redirect | **0** `redirect()` com dado de request |
| Path traversal | **0** operação de arquivo com caminho de request |
| Upload (backup) | robusto — permissão, 0–100 MB, allowlist `.sql/.zip`, `is_uploaded_file`, MIME `finfo`, destino gerado pelo servidor (sem filename do usuário), chmod 0600, validação estrutural + assinatura `PROV_IMPORTED`, restore com confirmação reforçada |
| Segredo em log | `tinyV3TokenRenovar` loga `$ret` que **não** carrega token (só `sucesso/http_code/mensagem/ambiente`); `raw` de erro mascarado; V2 loga `data` mascarada |
| CORS | **0** `Access-Control-Allow-Origin: *` |
| Headers/CSP/cookies | `App.php`: X-Frame-Options SAMEORIGIN, X-Content-Type-Options nosniff, Referrer-Policy no-referrer, Permissions-Policy restritiva, CSP com nonce, HSTS condicional; cookies HttpOnly/SameSite=Strict/Secure-condicional (medido em rodadas anteriores) |

## Achado

### S4-N1 — `Database::indexExistsOn()` interpola `$table` sem validar — **FALSO POSITIVO (retificado 2026-10-04)**
- **Veredito final:** `não identificado` (defeito inexistente). A proteção recomendada **já existe** no código.
- **Evidência da retificação:** `app/Core/Database.php:448`, primeira linha do método, **antes** de qualquer
  interpolação:
  `if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $index)) return false;`
  Qualquer `$table`/`$index` com caractere fora de `[A-Za-z0-9_]` retorna `false` antes de alcançar o
  `SHOW INDEX FROM `...`` da linha 462. `columnExistsOn` (linha 420) tem a mesma guarda. Não há como
  quebrar a crase nem injetar identificador.
- **Como o falso positivo surgiu (lição de método):** a auditoria leu o trecho com `sed -n '455,465p'`,
  começando **depois** da guarda da linha 448 — viu a interpolação (462) mas não a validação (448). É a
  armadilha de "janela de contexto" já documentada no `CLAUDE.md`. Correção de método: ao avaliar
  interpolação de identificador, **ler o método inteiro**, não um recorte.
- **Ação:** nenhuma. Aplicar uma guarda nova seria redundante e criaria falsa sensação de que havia buraco.
- **Status:** `não identificado` (retificado de `confirmado`).

## Decisões deliberadas re-confirmadas (não são defeito — `SECURITY.md`)

Degradação de rate limit por superfície (3.1) · callback OAuth fora do gate de sessão (3.2) ·
aceitação da assinatura v1 de webhook, dívida com prazo (3.3) · rota por igualdade, nunca substring
(3.4) · proveniência de backup lida da assinatura, não da coluna (3.5) · redirect HTTPS fora do
`.htaccess` (3.6) · 404 real para rota inexistente (3.7). Todas intactas.

## Veredito

Postura de segurança sólida. Sem achado novo Crítico/Alto/Médio nesta rodada. **Nenhum item acionável:**
o único candidato (S4-N1) foi **retificado como falso positivo** — a validação de identificador já existe
em `indexExistsOn`/`columnExistsOn` (regex `^[a-zA-Z0-9_]+$` antes da interpolação).

## Pendências de segurança que NÃO são código (recap do `CLAUDE.md`/`SECURITY.md`)

- Ligar `security.webhook_signature_require_v2` quando a VSM migrar (fecha a dívida 3.3).
- Rotacionar segredos: `php scripts/rotate-secrets.php --audit`.
- Isolamento multiempresa de ENTRADA: aberto, bloqueado nas 3 perguntas à VSM/produto.
