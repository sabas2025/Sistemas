# RELATÓRIO V104.26 - CORREÇÕES APLICADAS COM COMPATIBILIDADE

Data: 2026-07-08
Pacote base: hub-tiny-v104-25-recomendacoes-auditoria-enterprise(1).zip
Novo pacote: hub-tiny-v104-26-enterprise-hardening-aplicado.zip

## Escopo
Correções aplicadas sem remover funcionalidades, sem alterar regra de negócio Tiny/VSM e sem alterar estrutura obrigatória do banco.

## Alterações aplicadas

1. api/status com exposição mínima para usuário anônimo
- Arquivo: app/Controllers/ApiController.php
- Antes: expunha fila, DLQ, status Tiny/VSM e circuit breakers sem autenticação.
- Agora: anônimo recebe apenas status ok/authentication_required, trace_id e timestamp. Detalhes exigem login e permissão dashboard.visualizar.
- Impacto Tiny/VSM: nenhum no fluxo de integração.
- Rollback: restaurar método statusJson anterior.

2. 2FA obrigatório deixou de bloquear primeiro acesso por padrão
- Arquivos: config/config.php, public/install.php
- Antes: require_admin_2fa=true por padrão.
- Agora: false por padrão, com opção no instalador para exigir 2FA já na instalação.
- Impacto: evita travamento do primeiro login. Segurança preservada porque o painel ainda permite ativar 2FA.
- Rollback: voltar require_admin_2fa para true.

3. Produção pública bloqueia segredos vazios/fracos
- Arquivo: app/Core/App.php
- Agora: em app_env=production + host público, o sistema bloqueia execução se chaves críticas estiverem ausentes/fracas.
- Chaves validadas: encryption_key, backup_signature_key, integration_replay_hmac_key, audit_daily_signature_key, token_vault_hmac_key, fim_manifest_hmac_key.
- Impacto: protege produção contra config padrão. Não afeta install.php nem ambiente local.
- Rollback: remover bloco V104.26 de enforceProductionSafety().

4. Fila com timeout configurável e heartbeat
- Arquivos: app/Services/QueueService.php, app/Controllers/ApiController.php, config/config.php, public/install.php
- Antes: item processando era liberado após 10 minutos fixos.
- Agora: timeout configurável por security.queue_processing_timeout_minutes, default 30 minutos, com heartbeat ao iniciar processamento.
- Impacto Tiny/VSM: reduz risco de duplicidade em chamadas demoradas.
- Rollback: voltar liberarTravados() anterior.

5. WAF sem bypass amplo por prefixos tiny-/vsm-
- Arquivo: app/Services/WafService.php
- Antes: qualquer rota começando com tiny- ou vsm- pulava WAF.
- Agora: apenas APIs/webhooks configurados em waf_never_inspect_routes ficam fora do WAF.
- Impacto Tiny/VSM: webhooks continuam preservados pela allowlist explícita.
- Rollback: restaurar bypass antigo.

6. Respostas raw Tiny/VSM mascaradas e truncadas
- Arquivos: app/Services/TinyV2Service.php, app/Services/VsmService.php, app/Services/SensitiveDataService.php
- Agora: resposta não JSON é mascarada e limitada a 2000 caracteres antes de retornar/registrar.
- Impacto: reduz vazamento de tokens/dados pessoais em erro bruto.
- Rollback: restaurar retorno raw integral.

7. Backup com limites adicionais
- Arquivos: app/Services/BackupService.php, app/Controllers/BackupController.php
- Agora: upload de backup vazio ou maior que 100MB é recusado; download usa content-type conforme .zip/.sql.
- Impacto banco: nenhum na estrutura. Restore continua validando assinatura, comandos perigosos e confirmação RESTAURAR.
- Rollback: remover validação de size e content-type dinâmico.

## Validação executada
- PHP lint em todos os arquivos .php.
- Relatório de alterações gerado.
- Nenhum arquivo original foi sobrescrito fora da pasta de trabalho.

## Plano de rollback geral
1. Manter o ZIP original intacto.
2. Substituir os arquivos alterados pelos equivalentes do pacote original.
3. Limpar cache/opcache do PHP, se houver.
4. Validar login, dashboard, backup, fila, Tiny e VSM.
5. Conferir Auditoria por Trace ID após primeiro acesso.
