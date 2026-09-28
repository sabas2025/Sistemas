# V95 - Segurança aplicada sobre v94

Data: 2026-06-23

## Correções críticas aplicadas
- `RequestContext` não aceita mais `HTTP_X_TRACE_ID` como trace principal. O trace é sempre gerado internamente.
- `install.php` não exibe mais erros na tela; registra detalhes em `storage/logs/install_errors.log` com Trace ID.
- `DashboardController::count()` e `tableRows()` agora usam whitelist de tabelas, validação de filtros e validação de `ORDER BY`.
- Login executa `password_verify()` contra hash dummy quando o usuário não existe, reduzindo enumeração por timing.
- Fingerprint de sessão passou a usar IP completo + User-Agent.
- Cookie de sessão alterado para `SameSite=Strict` e `session.gc_maxlifetime=28800`.

## Correções altas/médias aplicadas
- Rate limit por IP no login: 20/hora e 5/minuto configuráveis em `config/config.php`.
- Senha temporária de novo usuário agora é aleatória com `bin2hex(random_bytes(8))`.
- CSP preparada para nonce por request (`App::cspNonce()`), removendo `unsafe-inline` de `script-src` no padrão.
- `CryptoService` aceita AAD contextual opcional e mantém fallback para registros antigos.
- Limpeza real de retenção exige permissão `logs.purgar`.
- Criado `storage/.htaccess`, `storage/backups/.htaccess` e `index.html` para reduzir risco de exposição.
- Adicionada configuração `tiny_allowed_ips` para futura/atual validação de webhooks Tiny.

## Observação
A V95 mantém compatibilidade com dados cifrados antigos. Para aproveitar AAD contextual em todos os campos, as chamadas futuras podem usar `CryptoService::encrypt($valor, 'field:...')` e `decrypt($valor, 'field:...')`.
