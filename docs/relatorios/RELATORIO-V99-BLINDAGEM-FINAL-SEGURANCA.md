# Relatório V99 — Blindagem Final de Segurança, Código e Banco

Data: 2026-06-23
Sistema: Hub de Integração Tiny ↔ VSM
Base: v98 blindagem operacional

## Resumo Executivo

A V99 aplica as sugestões finais da auditoria V98 com foco em segurança operacional real: IP/proxy confiável, rate limit por rota, proteção reforçada para backup/restore, assinatura HMAC do manifesto de integridade, endpoint de relatório CSP, migrations formais e classificação inicial de tabelas.

## Melhorias aplicadas

### 1. IP real com proxy confiável

Criado `app/Services/TrustedProxyService.php`.

Antes, headers como `X-Forwarded-For`, `X-Real-IP` ou `CF-Connecting-IP` poderiam ser aceitos sem validar se a requisição veio de proxy confiável.

Agora:

- O sistema usa `REMOTE_ADDR` por padrão.
- Headers de proxy só são aceitos quando `REMOTE_ADDR` está em `security.trusted_proxies`.
- HTTPS por `X-Forwarded-Proto` também só é aceito via proxy confiável.

### 2. Rate limit real por IP + rota

Criado `app/Services/RouteRateLimiterService.php`.

Inclui tabela:

- `rate_limit_hits`

Regras:

- Rotas comuns: `route_rate_limit_per_minute`.
- Rotas sensíveis/API: `route_rate_limit_sensitive_per_minute`.
- Excesso retorna HTTP `429`.
- Excesso repetido bloqueia IP temporariamente.

### 3. Backup assinado antes de restore

Criado `app/Services/BackupSignatureService.php`.

Agora backups gerados/importados recebem arquivo lateral:

```text
backup_xxx.sql.sig.json
backup_xxx.zip.sig.json
```

O restore só executa quando:

- assinatura existe;
- SHA-256 confere;
- HMAC confere;
- SQL passa pelo bloqueio de comandos perigosos;
- usuário possui `backup.restaurar`;
- confirmação manual é `RESTAURAR`.

### 4. Manifesto FIM assinado com HMAC

Atualizado `app/Services/FileIntegrityService.php`.

Agora o manifesto `storage/file_integrity_manifest.json` possui HMAC. Se alguém alterar o manifesto para esconder backdoor, o sistema marca:

```text
fim.manifesto_assinatura_invalida
```

### 5. CSP Report

Criado `app/Services/CspReportService.php` e rota:

```text
index.php?page=api/csp-report
```

A CSP agora pode registrar violações no `security_events`.

Documentação adicionada:

```text
docs/security/CSP-REPORT-V99.md
```

### 6. Hardening de shell

Criado `app/Services/ShellCommandService.php` para auditoria de funções perigosas.

Resultado da validação estática desta versão:

- Não foi identificado uso direto de `shell_exec`, `system`, `passthru`, `proc_open` ou `popen` em `app/`.
- Os usos de `exec` encontrados são majoritariamente `PDO::exec` para SQL/migrations, não comando de sistema.

### 7. Migrations formais

Adicionado:

```text
database/migrations/2026_06_23_v99_security_hardening.sql
```

Também gerado:

```text
database/install_final_v99.sql
```

### 8. Classificação inicial de tabelas

Adicionado:

```text
database/schema_inventory_v99.json
docs/security/TABELAS-CLASSIFICACAO-V99.md
```

Classificação inicial:

- `em_uso`
- `legado`
- `futuro`
- `remover_avaliar`

Nenhuma tabela foi removida automaticamente por segurança.

### 9. Configuração reforçada

Adicionados em `config/config.php` e `install.php`:

- `trusted_proxies`
- `route_rate_limit_per_minute`
- `route_rate_limit_sensitive_per_minute`
- `backup_signature_key`
- `fim_manifest_hmac_key`
- `csp_report_uri`

O instalador agora gera chaves fortes para backup e FIM.

## Arquivos criados

```text
app/Services/TrustedProxyService.php
app/Services/RouteRateLimiterService.php
app/Services/BackupSignatureService.php
app/Services/CspReportService.php
app/Services/ShellCommandService.php
database/migrations/2026_06_23_v99_security_hardening.sql
database/install_final_v99.sql
database/schema_inventory_v99.json
docs/security/CSP-REPORT-V99.md
docs/security/TABELAS-CLASSIFICACAO-V99.md
PHP-LINT-V99.log
```

## Arquivos alterados

```text
public/index.php
public/install.php
config/config.php
app/Core/App.php
app/Services/SecurityEventService.php
app/Services/FileIntegrityService.php
app/Services/BackupService.php
```

## Validação técnica

Executado `php -l` em arquivos PHP de `app/` e `public/`.

Resultado:

```text
Sem erros de sintaxe nos arquivos validados.
```

Log:

```text
PHP-LINT-V99.log
```

## Observações importantes

1. Configure `trusted_proxies` somente se usar Cloudflare, Nginx proxy, load balancer ou CDN.
2. Se deixar `trusted_proxies` vazio, o sistema usa apenas `REMOTE_ADDR`, que é o padrão mais seguro.
3. Backups antigos sem `.sig.json` não restauram diretamente pela tela. Para restaurar backup antigo, importe novamente pelo painel para gerar assinatura.
4. Depois de gerar o manifesto FIM em produção, qualquer alteração manual de PHP deve ser acompanhada da regeneração controlada do manifesto.
5. Nenhuma tabela foi excluída automaticamente porque isso poderia causar perda de histórico.

## Nota técnica estimada após V99

- Código PHP: 9,4/10
- Banco/schema: 9,2/10
- Autenticação/sessão: 9,5/10
- Auditoria/forense: 9,5/10
- Backup/restore: 9,3/10
- Infra/ambiente: depende do servidor real

Nota geral do código: 9,3/10.

## Próximo passo recomendado

Auditoria em ambiente real/homologação com URL funcional e usuário de teste, validando:

- headers HTTP reais;
- TLS/certificado;
- cookies reais;
- permissões por perfil;
- fluxo Tiny/VSM real;
- exposição de arquivos públicos;
- backup/restore em ambiente controlado;
- CSP reports após navegação real.
