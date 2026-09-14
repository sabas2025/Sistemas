# RELATÓRIO V104.14 — Teste de Segurança Assistido

## Objetivo

Aplicar uma tela interna para auditoria segura e não invasiva do HUB publicado, permitindo verificar segurança/cybersegurança sem executar ataque, força bruta, fuzzing, exploração, DoS ou scanner agressivo.

## Nova tela criada

Menu:

```text
Segurança > Teste Segurança Assistido
```

Rota principal:

```text
index.php?page=security-assisted-test
```

Rotas adicionais:

```text
index.php?page=security-assisted-test-run
index.php?page=security-assisted-test-download&file=...
```

## Arquivos criados

```text
app/Services/SecurityAssistedTestService.php
app/Controllers/SecurityAssistedTestController.php
views/security_assisted_test.php
storage/security-reports/.htaccess
```

## Arquivos alterados

```text
app/Services/FastRouteDispatcherService.php
app/Services/RouteModuleRegistry.php
views/layout_top.php
config/config.php
public/install.php
```

## O que o teste verifica

### Ambiente e HTTPS

- app_env em produção.
- HTTPS detectado.
- display_errors desativado em host público.
- usuário MySQL não-root em produção.
- timezone PHP.
- chave de criptografia forte.

### Superfície pública

- robots.txt bloqueando indexação.
- .htaccess em public.
- .htaccess na raiz.
- config/.htaccess.
- storage/.htaccess.
- install.lock presente.
- ausência de arquivos sensíveis dentro de public.

### Sessão e autenticação

- 2FA obrigatório para administradores.
- timeout de sessão.
- expiração absoluta.
- SameSite Strict.
- HttpOnly.
- session strict mode.
- rate limit de login.

### Headers, CSP e WAF

- CSP com nonce.
- CSP sem unsafe-inline.
- WAF limitado ao painel.
- exceções permanentes para Tiny/VSM.
- trusted proxy em host público.

### Banco e SchemaGuard

- modo banco único em hospedagem compartilhada.
- conexão principal MySQL.
- Mapa do Banco.
- tabelas oficiais presentes.
- colunas ausentes.

### Backup e restore

- chave HMAC de backup.
- backups fora da pasta public.
- serviço de assinatura disponível.
- Backup Trust Score disponível.

### Tiny/VSM e webhooks

- perfil VSM padrão pedidos-integradora.
- Base URL VSM configurada.
- anti-replay ativo.
- secret de webhook Tiny obrigatório/recomendado.
- Tiny V3 OAuth sem expor tokens.
- SSL verify esperado nas chamadas.

### Workers, filas e legado

- workers públicos bloqueiam navegador.
- /workers fora de public.
- LegacyRouteGuardService ativo.
- flag para bloquear legado disponível.

### Integridade e auditoria

- manifesto FIM presente.
- assinatura diária de auditoria habilitada.
- SecurityEventService disponível.
- Trace ID ativo.
- funções shell desativadas/mediadas.

## Segurança do relatório

O relatório mascara ou omite automaticamente:

```text
password
senha
token
access_token
refresh_token
secret
client_secret
authorization
api_key
HMAC
```

## Saídas geradas

Quando clicar em “Gerar relatório seguro”, o sistema grava em:

```text
storage/security-reports/security-assisted-YYYYMMDD-HHMMSS-XXXXXXXX.json
storage/security-reports/security-assisted-YYYYMMDD-HHMMSS-XXXXXXXX.md
```

A pasta possui `.htaccess` com bloqueio de acesso direto.

## Validação feita

- Lint PHP em app/public/views/workers.
- Nenhum erro de sintaxe encontrado.
- Execução CLI do `SecurityAssistedTestService::run(false)` validada.
- ZIP testado com `unzip -t`.

## Observação operacional

Este recurso não substitui pentest formal. Ele serve para auditoria interna segura e para gerar evidência sem expor segredos. Para pentest real, usar ambiente de homologação, escopo formal e autorização documentada.
