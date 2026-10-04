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
| SQLi interno | 2 ocorrências, ambas seguras: `IntegrationReplayGuardService` (`(int)$days`), `Database::indexExistsOn` (`$pdo->quote($index)`; `$table` só de catálogo interno) |
| XSS | toda saída de `$_GET` em view passa por `e()` ou é comparada a literal; nenhum echo cru |
| CSRF | rotas de mutação com `Csrf::validate()` (spot-check: `tinyV3TokenRenovar/Revogar`); forms com `Csrf::input()`; token por sessão estável |
| Open redirect | **0** `redirect()` com dado de request |
| Path traversal | **0** operação de arquivo com caminho de request |
| Upload (backup) | robusto — permissão, 0–100 MB, allowlist `.sql/.zip`, `is_uploaded_file`, MIME `finfo`, destino gerado pelo servidor (sem filename do usuário), chmod 0600, validação estrutural + assinatura `PROV_IMPORTED`, restore com confirmação reforçada |
| Segredo em log | `tinyV3TokenRenovar` loga `$ret` que **não** carrega token (só `sucesso/http_code/mensagem/ambiente`); `raw` de erro mascarado; V2 loga `data` mascarada |
| CORS | **0** `Access-Control-Allow-Origin: *` |
| Headers/CSP/cookies | `App.php`: X-Frame-Options SAMEORIGIN, X-Content-Type-Options nosniff, Referrer-Policy no-referrer, Permissions-Policy restritiva, CSP com nonce, HSTS condicional; cookies HttpOnly/SameSite=Strict/Secure-condicional (medido em rodadas anteriores) |

## Achado (Informativo / Baixa)

### S4-N1 — `Database::indexExistsOn()` interpola o identificador `$table` sem validá-lo contra catálogo
- **Evidência:** `app/Core/Database.php:462` — `'SHOW INDEX FROM `'.$table.'` WHERE Key_name = '.$quoted`. `$quoted` usa `$pdo->quote()` (ok); `$table` entra cru entre crases.
- **Arquivo/classe/método:** `app/Core/Database.php` → `indexExistsOn(PDO $pdo, string $table, string $index)`.
- **Gravidade:** Informativa (defesa-em-profundidade).
- **Causa raiz:** `SHOW INDEX` não aceita placeholder para o nome da tabela; o identificador é interpolado sem allowlist/regex.
- **Impacto real:** **nenhum hoje.** Os 4 chamadores — `DatabaseAutoRepairService`, `DatabaseValidationService` e 2 internos de `Database` — passam nomes de um catálogo fixo de schema, nunca entrada de request. Não é alcançável por usuário.
- **Cenário de falha:** só se um futuro chamador passasse `$table` vindo de request — aí seria injeção de identificador.
- **Correção recomendada:** validar o identificador (allowlist do inventário de schema, ou regex `^[A-Za-z0-9_]+$`) antes de interpolar, em `indexExistsOn` (e conferir irmãos `tableExistsOn`/`columnExistsOn`, que já usam `information_schema` com placeholder).
- **Risco da correção:** baixo. **Compatibilidade:** total. **Como testar:** portões + chamar com nome fora do padrão deve ser recusado. **Como reverter:** git revert.
- **Status:** `confirmado`, não aplicado — endurecimento sem risco medido; a fase 4/10 pedem evidência antes de agir.

## Decisões deliberadas re-confirmadas (não são defeito — `SECURITY.md`)

Degradação de rate limit por superfície (3.1) · callback OAuth fora do gate de sessão (3.2) ·
aceitação da assinatura v1 de webhook, dívida com prazo (3.3) · rota por igualdade, nunca substring
(3.4) · proveniência de backup lida da assinatura, não da coluna (3.5) · redirect HTTPS fora do
`.htaccess` (3.6) · 404 real para rota inexistente (3.7). Todas intactas.

## Veredito

Postura de segurança sólida. Sem achado novo Crítico/Alto/Médio nesta rodada. O único item (S4-N1)
é endurecimento opcional de defesa-em-profundidade, sem caminho de exploração atual.

## Pendências de segurança que NÃO são código (recap do `CLAUDE.md`/`SECURITY.md`)

- Ligar `security.webhook_signature_require_v2` quando a VSM migrar (fecha a dívida 3.3).
- Rotacionar segredos: `php scripts/rotate-secrets.php --audit`.
- Isolamento multiempresa de ENTRADA: aberto, bloqueado nas 3 perguntas à VSM/produto.
