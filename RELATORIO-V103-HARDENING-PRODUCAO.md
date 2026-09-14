# RELATÓRIO V103 — Hardening de Produção, Workers, Banco e Integrações

Data da geração: 2026-06-25 02:30:29

## 1. Objetivo da V103

Aplicar todas as melhorias indicadas na análise da V102, com foco em produção segura:

- bloquear execução de workers por navegador;
- mover lógica real dos workers para fora de `public`;
- corrigir anti-replay VSM para não consumir hash antes de autenticar;
- impedir bloqueio eterno de payload por índice único antigo;
- corrigir tabelas ausentes de consulta Tiny;
- limpar duplicidade de tabelas em SQL modular;
- centralizar rotas antigas `atualizar-vXX` em bloqueio seguro;
- reforçar Token Vault;
- reforçar SSL verify em cURL;
- melhorar validação de banco modular;
- manter WAF fora dos payloads Tiny/VSM.

## 2. Validação estrutural

- Arquivos PHP no pacote: **287**
- Linhas PHP aproximadas: **18285**
- Arquivos SQL: **25**
- Tabelas únicas encontradas em todos os SQLs: **109**
- Tabelas no schema modular atual: **101**
- Duplicidade de tabela entre módulos: **0**
- Arquivos validados com `php -l`: **286**
- Erro de sintaxe PHP: **não encontrado**

## 3. Correção crítica: workers fora de `public`

Antes, vários workers ainda estavam dentro de `public`, o que aumentava risco de disparo via navegador.

Aplicado na V103:

- criada pasta `/workers`;
- copiada a lógica real dos workers para `/workers`;
- adicionada proteção `/workers/.htaccess`;
- arquivos `public/worker*.php` viraram apenas *shim* CLI;
- acesso HTTP aos `public/worker*.php` retorna **403**;
- `public/.htaccess` também bloqueia `worker*.php`;
- compatibilidade CLI mantida: quem já chamava `php public/worker_fila.php` ainda funciona, mas navegador não executa.

Workers tratados:

- Workers públicos encontrados: **11**
- Workers públicos com bloqueio CLI/403: **11**
- Workers reais movidos para `/workers`: **11**

Comando recomendado agora:

```bash
php workers/worker_fila.php
php workers/worker_estoque.php 20
php workers/worker_fiscal.php 20
php workers/worker_consulta_estoque_vsm.php --force 100
```

## 4. Anti-replay VSM corrigido

Problema encontrado na V102:

- o anti-replay registrava hash antes da autenticação HMAC/secret;
- uma requisição inválida poderia consumir o hash;
- o índice único `(origem, request_hash)` podia bloquear o mesmo payload para sempre.

Correção aplicada:

- `WebhookSecurityService` agora valida HMAC/secret/IP/tamanho/nonce primeiro;
- `IntegrationReplayGuardService::guard()` roda somente após autenticação aceita;
- novo `payload_hash`;
- novo `time_bucket`;
- novo `hmac_validated_at`;
- novo índice `uk_replay_origem_hash_bucket`;
- tentativa de remoção segura do índice antigo `uk_replay_origem_hash`;
- limpeza automática de registros antigos;
- replay dentro da janela continua bloqueado;
- reenvio legítimo fora da janela deixa de ficar bloqueado eternamente.

Também foram protegidos os webhooks VSM que ainda não chamavam validação central:

- `webhookVsmPedido`;
- `webhookVsmProduto`;
- `webhookVsmRetornoPedido`;
- `webhookVsmEstoque`.

## 5. Banco de dados e tabelas

Correções aplicadas:

- adicionada migração `database/migrations/2026_06_25_v103_production_hardening.sql`;
- criado `database/install_final_v103.sql`;
- atualizado `database/schema_inventory_v103.json`;
- adicionadas tabelas ausentes:
  - `estoque_consulta_tiny_execucoes`;
  - `estoque_consulta_tiny_resultados`;
- removida duplicidade modular de:
  - `fila_fiscal` em módulo incorreto;
  - `security_events` duplicada fora do core;
  - `ips_bloqueados` duplicada fora do core;
  - `rate_limit_hits` duplicada fora do core;
- removidos inserts de `permissoes_perfil` dentro de SQLs não-core, evitando erro em instalação modular.

Resultado do schema modular:

```text
Duplicidade de CREATE TABLE entre módulos: nenhuma
```

## 6. Rotas antigas de atualização

Problema:

- rotas `atualizar-vXX` acumuladas aumentavam risco operacional.

Aplicado:

- criado `MigrationController`;
- criada tela `Migrações Seguras`;
- `public/index.php` agora intercepta qualquer `atualizar-v*`;
- updates legados V50/V51/V52 não passam mais pelos controllers legados;
- tentativa de acessar rota antiga gera evento de segurança e auditoria;
- SQL arbitrário não é executado pelo navegador.

Nova rota:

```text
Central Técnica > Migrações Seguras
index.php?page=migracoes-seguras
```

## 7. Token Vault reforçado

Aplicado:

- `TokenVaultService` agora usa `hash_hmac` no `token_hash`;
- adicionada chave `token_vault_hmac_key`;
- adicionada leitura `getActive()`;
- Tiny V3 passa a consultar o Vault como fonte principal em runtime;
- `tiny_v3_tokens` fica como fallback e compatibilidade;
- refresh token Tiny V3 também tenta vir do Vault antes da tabela antiga.

## 8. Tiny/VSM com SSL verify explícito

Aplicado `CURLOPT_SSL_VERIFYPEER => true` e `CURLOPT_SSL_VERIFYHOST => 2` em chamadas principais:

- Tiny V2;
- Tiny V3;
- refresh OAuth Tiny V3;
- VSM;
- testes de endpoint VSM;
- health checks;
- callback OAuth Tiny V3.

## 9. WAF mantido compatível com Tiny/VSM

Mantido o princípio correto:

- WAF forte no painel administrativo;
- sem filtro agressivo de palavras em payload Tiny/VSM;
- rotas Tiny/VSM protegidas por secret, HMAC, allowlist opcional, auditoria, rate limit, anti-replay e circuit breaker.

Exceções preservadas:

```text
api/tiny/*
api/vsm/*
api/webhook/tiny/*
api/webhook/vsm/*
webhook/tiny/*
webhook/vsm/*
```

## 10. Varredura de execução de código

Classificação atual:

- `PDO::execute()`: **342** ocorrências;
- `PDO::exec()`: **84** ocorrências;
- `curl_exec()`: **8** ocorrências;
- chamadas reais de sistema operacional: **0**.

Resultado:

```text
Nenhuma chamada real a sistema operacional encontrada.
Não encontrei system(), shell_exec(), passthru(), proc_open(), popen(), pcntl_exec(), eval() ou unserialize() em uso real.
```

## 11. Validação de banco modular corrigida

`DatabaseValidationService` agora usa `Database::forTable()` para validar cada tabela no módulo correto.

Isso evita falso erro quando o HUB está em modo modular com bancos separados:

- core;
- pedidos;
- produtos;
- estoque;
- fiscal;
- fila;
- observabilidade;
- backups.

## 12. Health check em tempo real melhorado

`RealtimeHealthService` agora valida conexão em todos os módulos configurados, não apenas no banco core.

Health monitorado:

- Banco por módulo;
- Fila;
- Tiny V2;
- Tiny V3;
- VSM;
- Fiscal.

## 13. Arquivos novos/importantes

- `workers/`
- `workers/README-WORKERS.md`
- `app/Controllers/MigrationController.php`
- `views/migracoes_seguras.php`
- `database/migrations/2026_06_25_v103_production_hardening.sql`
- `database/install_final_v103.sql`
- `database/schema_inventory_v103.json`
- `storage/audit-signatures/.htaccess`

## 14. Observações importantes

Não executei teste real com MySQL/Tiny/VSM porque este ambiente não tem suas credenciais nem o banco de produção conectado. A validação feita foi estrutural, sintática e estática no pacote.

Antes de instalar em produção:

1. fazer backup assinado da versão atual;
2. testar em XAMPP/local;
3. rodar `public/install.php` em banco novo ou aplicar migração V103 no banco existente;
4. acessar `Central Técnica > Validar Banco`;
5. acessar `Central Técnica > Segurança > Health Real Time`;
6. configurar cron/agendador usando `/workers`, não `/public`;
7. validar OAuth Tiny V3, webhook secret Tiny e secret/HMAC VSM.

## 15. Conclusão

A V103 fecha os pontos mais críticos da V102: workers públicos, anti-replay antes da autenticação, bloqueio infinito de replay, duplicidade modular de tabelas, validação modular incorreta e rotas antigas de update.

O sistema fica mais seguro para produção mantendo compatibilidade com Tiny/VSM, sem WAF agressivo nos payloads das integrações.
