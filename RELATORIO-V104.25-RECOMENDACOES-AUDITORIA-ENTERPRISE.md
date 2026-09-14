# RELATÓRIO V104.25 — Recomendações da Auditoria Enterprise Aplicadas

## Escopo

Versão criada sobre a V104.24 para aplicar as recomendações críticas da auditoria enterprise sem alterar regras de negócio e preservando compatibilidade com Tiny, VSM, banco único/modular, backups, filas, auditoria e painel.

## Resumo executivo

A V104.25 corrige os pontos de maior risco identificados na auditoria:

1. Endpoint legado Tiny agora valida segurança antes de processar evento.
2. Webhook VSM exige HMAC em host público/produção, mesmo que a integração esteja marcada como homologação.
3. Anti-replay não usa mais chave fixa fallback em produção/host público.
4. VSM ganhou validação SSRF da URL base.
5. `repair_current.sql` ficou sem placeholders e sem `CREATE DATABASE`/`USE`.
6. Backups usam colunas explícitas nas consultas críticas.
7. Política de DDL runtime foi ajustada para reconhecer apenas serviços de schema/reparo permitidos.
8. Versão centralizada atualizada para V104.25.

## Alterações aplicadas

### 1. Webhook Tiny legado protegido

**Arquivo:** `app/Controllers/ApiController.php`  
**Classe:** `ApiController`  
**Método:** `webhookTinyEvento()`  
**Linha aproximada:** 200-225  
**Gravidade anterior:** Alta  

Antes, o endpoint legado processava o payload Tiny sem chamar a validação central `TinyWebhookSecurityService::validar()`.

Agora o fluxo faz:

```php
$this->validarSegurancaTiny($raw, is_array($evento) ? $evento : []);
```

Se falhar, retorna HTTP 401, grava auditoria e não enfileira baixa de estoque.

**Impacto positivo:** reduz risco de evento falso do Tiny/Olist.  
**Compatibilidade:** preservada; clientes válidos só precisam enviar secret/CNPJ conforme configuração.  
**Rollback:** remover o bloco `try/catch` de validação em `webhookTinyEvento()`, não recomendado em produção.

---

### 2. HMAC obrigatório em host público

**Arquivo:** `app/Services/WebhookSecurityService.php`  
**Classe:** `WebhookSecurityService`  
**Método:** `validar()`  
**Linha aproximada:** 60-100  
**Gravidade anterior:** Alta  

Antes, HMAC era obrigatório principalmente quando `ambiente = producao`. Em servidor público com integração ainda em `homologacao`, o secret simples poderia ser aceito.

Agora HMAC é obrigatório quando:

```php
ambiente === 'producao' || App::isPublicHost() || App::isProduction()
```

Secret simples só é aceito em homologação/local **não público**.

**Impacto positivo:** protege webhooks VSM em domínio público.  
**Compatibilidade:** homologação local continua funcionando.  
**Rollback:** voltar a aceitar secret simples em host público, não recomendado.

---

### 3. Anti-replay sem chave fixa em produção

**Arquivo:** `app/Services/IntegrationReplayGuardService.php`  
**Classe:** `IntegrationReplayGuardService`  
**Método:** `hashKey()`  
**Linha aproximada:** 80-95  
**Gravidade anterior:** Alta  

Antes, se não houvesse chave configurada, o sistema usava fallback fixo:

```php
hub-integration-replay-local-key
```

Agora, em produção/host público, se faltar chave real, o sistema falha fechado, grava evento crítico e bloqueia a integração.

**Configuração necessária:**

```php
'security' => [
  'integration_replay_hmac_key' => 'CHAVE_FORTE_UNICA_AQUI'
]
```

O `install.php` já gera essa chave automaticamente.

**Impacto positivo:** impede anti-replay fraco em produção.  
**Compatibilidade:** ambiente local ainda usa fallback controlado.  
**Rollback:** reativar fallback fixo, não recomendado.

---

### 4. Proteção SSRF na URL base VSM

**Arquivo:** `app/Services/VsmEndpointSecurityService.php`  
**Classe:** `VsmEndpointSecurityService`  
**Método:** `validateBaseUrl()`  
**Linha aproximada:** 15-70  
**Gravidade anterior:** Alta  

Foi adicionada validação para bloquear:

- `localhost`
- `127.0.0.1`
- `0.0.0.0`
- IP privado/reservado
- host `.local`
- host fora da allowlist quando configurada

**Arquivo impactado:** `app/Services/VsmService.php`  
**Método:** `__construct()`  

Agora a URL base da VSM é validada antes de qualquer chamada.

**Configuração recomendada:**

```php
'security' => [
  'vsm_allowed_hosts' => 'conectavenda.homolog.vsm.com.br,HOST_PRODUCAO_DA_VSM'
]
```

**Impacto positivo:** reduz risco de SSRF via configuração VSM.  
**Compatibilidade:** homologação VSM oficial já está liberada por padrão.  
**Rollback:** remover `validateBaseUrl()`, não recomendado.

---

### 5. SQL repair sem placeholders

**Arquivo:** `database/repair_current.sql`  
**Gravidade anterior:** Média/Alta  

Removidos comandos:

```sql
CREATE DATABASE IF NOT EXISTS `{DB_NAME}`;
USE `{DB_NAME}`;
```

O repair agora deve ser executado no banco já selecionado no phpMyAdmin/cPanel.

**Impacto positivo:** evita placeholders vazando para SQL manual e evita erro por falta de permissão administrativa.  
**Compatibilidade:** `install.sql` continua com placeholders para uso do instalador.  
**Rollback:** usar `install.sql` via instalador, não o `repair_current.sql` antigo.

---

### 6. Consultas de backup mais leves e compatíveis

**Arquivos:**

- `app/Controllers/BackupController.php`
- `app/Services/BackupService.php`

As consultas críticas de backup agora usam colunas explícitas:

```sql
SELECT id, arquivo, nome_arquivo, caminho, tamanho_bytes, hash_sha256, hmac_sha256, trust_score, status, mensagem, trace_id, criado_em
FROM backups_banco
WHERE id=?
LIMIT 1
```

**Impacto positivo:** menos risco de incompatibilidade com schema antigo e menos payload.  
**Compatibilidade:** mantém `arquivo`, `nome_arquivo` e `caminho`.

---

### 7. Política de DDL runtime ajustada

**Arquivo:** `app/Services/SchemaRuntimePolicyService.php`  
**Classe:** `SchemaRuntimePolicyService`  

A lista de classes permitidas para DDL controlado agora inclui:

- `DatabaseSchemaGuardService`
- `DatabaseAutoRepairService`
- `BackupSchemaService`
- `IntegrationReplayGuardService`
- `SafeSqlUpgradeService`
- `MigrationController`

**Impacto positivo:** diferencia DDL legítimo de schema/reparo de DDL indevido em controller/service comum.

---

## Arquivos alterados

```text
app/Controllers/ApiController.php
app/Services/WebhookSecurityService.php
app/Services/IntegrationReplayGuardService.php
app/Services/VsmEndpointSecurityService.php
app/Services/VsmService.php
app/Services/SchemaRuntimePolicyService.php
app/Controllers/BackupController.php
app/Services/BackupService.php
app/Services/SystemVersionService.php
config/config.php
public/install.php
database/repair_current.sql
database/modules/core.sql
database/install.sql
database/install_final_current.sql
database/install_final_v104_25.sql
database/update_v104_25_enterprise_hardening_tiny_vsm.sql
```

## Validação executada

- PHP lint em `app`, `public`, `workers` e `config`.
- Nenhum erro de sintaxe PHP encontrado.
- Classmap regenerado com 209 classes/interfaces/traits.
- `database/install_final_current.sql` validado sem duplicidade de `CREATE TABLE`.
- `database/repair_current.sql` validado sem placeholders `{...}`.
- Versão centralizada validada como `V104.25`.

## Plano de rollback

1. Restaurar o ZIP da V104.24.
2. Restaurar `config/config.php` anterior se a allowlist VSM bloquear homologação.
3. Se o webhook VSM parar em homologação pública, configurar HMAC correto em vez de voltar secret simples.
4. Se o webhook Tiny parar, configurar `tiny_webhook_secret` e header `X-Hub-Secret`/`X-Tiny-Hub-Secret` conforme tela de configuração.
5. Se anti-replay bloquear, configurar `security.integration_replay_hmac_key` no `config.php`.

## Próximas recomendações

### Prioridade alta

1. Rodar teste real do webhook Tiny com secret correto.
2. Rodar teste real do webhook VSM com HMAC.
3. Configurar `vsm_allowed_hosts` com o host de produção assim que a VSM liberar.
4. Executar `Segurança > Teste Segurança Assistido` e corrigir qualquer crítico.
5. Rodar `Central Técnica > Validar Banco` e confirmar ausência de erro em massa.

### Prioridade média

1. Reduzir gradualmente o restante dos `SELECT *` nas listagens antigas do `DashboardController`.
2. Mover DDLs restantes para `DatabaseSchemaGuardService` quando possível.
3. Criar testes Playwright autenticados para backup, validar banco, Tiny e VSM.
4. Homologar conectores não Tiny/VSM antes de vender como prontos.

## Nota técnica estimada após V104.25

| Área | Nota |
|---|---:|
| Arquitetura | 8.4 |
| Segurança | 8.8 |
| Banco | 8.0 |
| Performance | 7.8 |
| Integração Tiny | 8.5 |
| Integração VSM | 8.4 |
| UX | 8.1 |
| Escalabilidade | 7.9 |
| Pronto para produção controlada | 8.3 |

## Conclusão

A V104.25 fecha os principais riscos técnicos da auditoria enterprise sem mudar regra de negócio. O sistema continua compatível com o fluxo principal:

```text
Tiny → HUB → VSM
VSM → HUB → Tiny
```

Ainda falta teste real em servidor com credenciais Tiny/VSM, workers via cron, backup/restore e relatório de segurança assistida sem críticos para liberar produção final.
