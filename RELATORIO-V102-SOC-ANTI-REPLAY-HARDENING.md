# Relatório V102 — SOC, anti-replay, legado, banco e hardening

## Objetivo
Aplicar as melhorias obrigatórias da V102 no HUB Tiny/VSM mantendo a regra principal: proteção máxima no painel administrativo e proteção compatível nas integrações, sem WAF agressivo nem filtros por palavras-chave nos payloads Tiny/VSM.

## Correções aplicadas

### 1. Varredura profunda de `exec(`
Foi criado o serviço `CodeExecutionAuditService` para separar automaticamente:

- `PDO::execute()` / `$statement->execute()` — execução de prepared statement.
- `PDO::exec()` — SQL textual interno/DDL/migração.
- `curl_exec()` — chamada HTTP via cURL.
- `exec()`, `shell_exec()`, `system()`, `passthru()`, `proc_open()`, `popen()`, `pcntl_exec()` — execução real do sistema operacional.

Resultado no pacote analisado:

- `PDO::execute()`: 338 ocorrências.
- `PDO::exec()`: 79 ocorrências.
- `curl_exec()`: 7 ocorrências.
- Chamada real ao sistema operacional: 0 ocorrência.

Também foi mantida recomendação operacional: configurar `disable_functions=exec,shell_exec,system,passthru,proc_open,popen,pcntl_exec` no `php.ini` de produção.

### 2. Controllers legados
Os controllers históricos foram movidos para:

- `app/Legacy/Controllers/V50Controller.php`
- `app/Legacy/Controllers/V51Controller.php`

O autoload agora carrega também `app/Legacy/Controllers` e `app/Legacy/Services`, preservando compatibilidade sem deixar esses arquivos misturados aos controllers ativos.

### 3. Classificação ATIVO / LEGADO / FUTURO
Foi criado o serviço `LegacyInventoryService`, exibido na Central de Segurança, classificando controllers e services em:

- ATIVO
- LEGADO
- FUTURO

### 4. Classificação de tabelas do banco
Foi criado o serviço `DatabaseInventoryService`, classificando tabelas em:

- Operacionais
- Técnicas
- Homologação
- Futuras
- Legado

Também identifica o módulo do banco associado a cada tabela.

### 5. Circuit Breakers independentes
Tiny V2 deixou de usar o nome genérico `tiny` e passou a usar `tiny_v2`.

Circuit breakers esperados:

- `tiny_v2`
- `tiny_v3`
- `vsm`
- `fiscal`

### 6. Anti Replay para VSM
Foi criado `IntegrationReplayGuardService` com tabela:

- `integration_replay_guard`

Campos principais:

- `request_hash`
- `request_time`
- `origem`
- `rota`
- `trace_id`
- `ip`
- `status`

A validação VSM agora bloqueia reenvio/replay dentro da janela configurada em:

```php
security.integration_anti_replay_window_seconds = 600
```

### 7. WAF com exceções permanentes
O WAF mantém inspeção forte no painel, mas nunca aplica filtros agressivos em:

- `api/tiny/*`
- `api/vsm/*`
- `api/webhook/tiny/*`
- `api/webhook/vsm/*`
- `webhook/tiny/*`
- `webhook/vsm/*`

Configuração adicionada:

```php
security.waf_never_inspect_routes
```

### 8. Assinatura diária da auditoria
Foi criado `AuditDailySignatureService`, com geração de assinatura diária:

- SHA-256 dos eventos do dia.
- HMAC da assinatura.
- Arquivo em `storage/audit-signatures/audit-signature-YYYY-MM-DD.sig.json`.
- Registro em tabela `audit_daily_signatures`.

### 9. Backup Trust Score
Foi criado `BackupTrustService` para pontuar backups por:

- existência do arquivo;
- assinatura `.sig.json`;
- HMAC/SHA-256 válido;
- nome em padrão seguro;
- tamanho válido;
- backup recente.

### 10. Health Check em tempo real
Foi criado `RealtimeHealthService` com checks para:

- Banco;
- Fila;
- Tiny V2;
- Tiny V3;
- VSM;
- Fiscal.

### 11. Token Vault interno
Foi criado `TokenVaultService` e a gravação de token Tiny V3 agora tenta espelhar tokens no vault interno com:

- provider;
- ambiente;
- tipo de token;
- hash;
- ciphertext;
- versão;
- expiração;
- rotação.

### 12. Security Operations Center
A Central de Segurança foi ampliada com novos menus:

- SOC;
- Eventos;
- IPs bloqueados;
- Circuit Breakers;
- Execução de Código;
- Legado/Banco;
- Backup Trust;
- Health Real Time;
- Assinaturas Auditoria;
- Integridade de Arquivos;
- Score de Segurança;
- Hardening;
- Certificados SSL;
- Auditoria de Usuários;
- Pentest Checklist.

## Arquivos principais alterados

- `app/Core/Autoload.php`
- `app/Core/Database.php`
- `app/Controllers/DashboardController.php`
- `app/Services/WafService.php`
- `app/Services/WebhookSecurityService.php`
- `app/Services/TinyV2Service.php`
- `app/Services/TinyV3TokenService.php`
- `app/Services/SecurityHardeningService.php`
- `app/Services/ShellCommandService.php`
- `views/security_center.php`
- `views/layout_top.php`
- `config/config.php`
- `public/install.php`
- `database/modules/core.sql`
- `database/modules/fila.sql`
- `database/modules/observabilidade.sql`
- `database/update_v102_soc_anti_replay_auditoria.sql`

## Serviços novos

- `CodeExecutionAuditService.php`
- `LegacyInventoryService.php`
- `DatabaseInventoryService.php`
- `IntegrationReplayGuardService.php`
- `AuditDailySignatureService.php`
- `BackupTrustService.php`
- `RealtimeHealthService.php`
- `TokenVaultService.php`

## Validação

- Todos os arquivos PHP foram validados com `php -l`.
- Nenhum erro de sintaxe encontrado.
- `V50Controller` e `V51Controller` foram movidos para `app/Legacy/Controllers` e continuam carregáveis via autoload.
- Varredura automática confirmou 0 chamada real ao sistema operacional.

## Observação importante
Não foi feito teste real em MySQL/Tiny/VSM porque o ambiente de análise não possui suas credenciais nem o banco de produção conectado. A validação realizada foi estrutural, sintática e de consistência do pacote.
