# RELATÓRIO V100 — Segurança do Painel + Integrações Tiny/VSM em modo seguro

## Análise profunda aplicada

Foram revisados os pontos críticos indicados:

- SQL textual interno e uso de SQL em pontos sensíveis.
- Autenticação, fingerprint de sessão e origem de IP.
- WAF e compatibilidade com payloads Tiny/VSM.
- Rate limit no login por IP e por usuário/e-mail.
- 2FA obrigatório para administradores.
- CSP com nonce.
- Session Version para logout global.
- FIM — integridade de arquivos.
- Backup assinado com HMAC e restore apenas validado.
- Circuit Breaker inteligente por integração.
- Retry com backoff exponencial.
- Trace ID em chamadas Tiny/VSM.
- HMAC opcional HUB ↔ VSM.
- Central Técnica > Segurança com novos painéis.

## Melhorias aplicadas

### 1. Autenticação e sessão

- Removido uso direto de `REMOTE_ADDR` em `Auth`.
- Login agora usa `RequestContext::ip()` com `TrustedProxyService`.
- Fingerprint de sessão usa prefixo seguro de IP (`/24` IPv4 ou `/64` IPv6), evitando travamento por pequenas mudanças de IP e evitando depender do IP bruto.
- Rate limit por IP e por e-mail/usuário:
  - `login_ip_rate_limit_per_hour`
  - `login_ip_rate_limit_per_minute`
  - `login_user_rate_limit_per_hour`
  - `login_user_rate_limit_per_minute`
- Criado `AuthRepository` para centralizar operações SQL sensíveis de autenticação.
- Login 2FA não carrega mais senha em campo hidden após a primeira etapa.
- Admin agora tem 2FA obrigatório por padrão (`require_admin_2fa=true`).
- Quando admin ainda não tem TOTP, o sistema gera chave e força cadastro antes do login.

### 2. WAF seguro e compatível

- WAF agora roda somente em páginas administrativas/painel.
- Rotas `api/`, Tiny e VSM não recebem bloqueio por palavras-chave.
- Mantida proteção por:
  - Secret/HMAC.
  - Allowlist opcional de IP.
  - Rate limit.
  - Auditoria.
  - Circuit breaker.

Rotas Tiny/VSM continuam livres para receber payloads legítimos com termos que poderiam parecer SQL/XSS, sem bloquear integração real.

### 3. Tiny

- Tiny V3 mantém OAuth obrigatório.
- Tiny V2 continua compatível com token legado para não quebrar produção quando V2 estiver ativo.
- Todas as chamadas Tiny recebem Trace ID.
- Adicionado retry com backoff exponencial.
- Circuit breaker passa a diferenciar falhas transitórias de erros de negócio.
- Auditoria e logs técnicos preservam dados sensíveis mascarados.

### 4. VSM

- Adicionado suporte a HMAC opcional HUB ↔ VSM:
  - `vsm_hmac_enabled`
  - `vsm_hmac_secret`
- Todas as chamadas VSM recebem Trace ID.
- Retry com backoff exponencial.
- Circuit breaker independente e mais inteligente.
- Payload VSM não recebe filtro genérico de SQL/XSS.
- Allowlist opcional de IP mantida.

### 5. Circuit Breaker inteligente

- Criada política por sistema: Tiny, Tiny V3 e VSM.
- Falhas transitórias abrem o circuito:
  - timeout
  - cURL
  - HTTP 429
  - HTTP 5xx
  - JSON inválido
- Erros permanentes/de negócio não abrem circuito indevidamente:
  - validação
  - HTTP 400/401/403
  - token ausente
  - SKU/produto não encontrado
- Estado meio-aberto registrado em auditoria.
- Abertura do circuito gera evento de segurança e notificação.

### 6. Backups e restore

- Backup assinado com HMAC.
- Em produção, chave HMAC fraca/ausente bloqueia assinatura.
- Importação de backup agora valida o SQL antes de assinar.
- Restore exige:
  - confirmação manual `RESTAURAR`;
  - assinatura HMAC válida;
  - SHA-256 compatível;
  - comandos SQL permitidos;
  - bloqueio de comandos perigosos.

### 7. Central Técnica > Segurança

Novo painel centralizado com:

- Visão geral.
- Eventos.
- IPs bloqueados.
- Circuit Breakers.
- Integridade de Arquivos.
- Score de Segurança.
- Hardening.
- Certificados SSL.
- Auditoria de Usuários.
- Pentest Checklist.

Indicadores adicionados:

- Integridade OK.
- Tiny monitorado.
- VSM monitorado.
- SSL/HTTPS ativo.
- Backups HMAC.
- Tentativas de login bloqueadas.
- IPs bloqueados.
- Eventos críticos.
- Circuit breakers abertos.

### 8. Hardening de produção

Checks adicionados/fortalecidos:

- `install.php` removido ou bloqueado por lock.
- `.env/config` protegido.
- `storage` protegido.
- backups protegidos.
- debug desativado.
- HTTPS obrigatório.
- HSTS ativo.
- CSP com nonce.
- Trusted Proxy configurado.
- chave AES/HMAC válida.
- TOTP obrigatório para admin.

## Validação executada

- Lint PHP completo: 266 arquivos PHP sem erro de sintaxe.
- Teste de WAF:
  - `api/webhook/vsm/pedido`: não inspeciona.
  - `api/tiny/webhook/pedido`: não inspeciona.
  - `dashboard`: inspeciona.
  - `configuracoes`: inspeciona.
  - `tiny-v3-homologacao`: não inspeciona por WAF agressivo.

## Observação operacional

Na produção, após instalar ou atualizar:

1. Configurar `trusted_proxies` caso use Cloudflare/proxy/CDN.
2. Remover ou bloquear `public/install.php` após instalação.
3. Manter `storage/install.lock`.
4. Configurar `backup_signature_key` forte se não veio do install.php.
5. Habilitar `vsm_hmac_enabled=true` somente se a VSM suportar validar os headers HMAC.
6. Manter WAF apenas no painel para não bloquear Tiny/VSM.
