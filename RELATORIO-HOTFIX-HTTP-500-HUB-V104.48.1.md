# Relatório do hotfix HTTP 500 — HUB V104.48.1

## Causa técnica confirmada no pacote

O bootstrap executava controles de segurança antes do bloco global `try/catch`. O `IpBlockService::enforce()` chamava a validação obrigatória das tabelas `security_events` e `ips_bloqueados` fora de proteção. Configuração sobrescrita, banco inacessível, driver `pdo_mysql` ausente ou migration pendente podiam produzir uma exceção não tratada e o navegador mostrava somente **HTTP ERROR 500**.

A configuração ativa também era distribuída dentro do ZIP completo, criando risco de sobrescrever o `config/config.php` de uma instalação existente.

## Correções

- bootstrap completo protegido por `try/catch`;
- capturador de falhas fatais com `register_shutdown_function`;
- página de recuperação com Trace ID;
- log `storage/logs/bootstrap-fallback.log`;
- status 503 para falha recuperável de configuração, banco ou schema;
- `IpBlockService` em modo degradado registrado, sem 500 genérico;
- `SecurityEventService::log()` em modo best-effort com fallback em arquivo;
- fallback de auditoria cria diretório e usa `LOCK_EX`;
- `.htaccess` compatível com Apache 2.2/2.4 e sem `Options -Indexes`;
- script CLI seguro `scripts/diagnose-http-500.php`;
- pacote hotfix não contém `config/config.php`.

## Validações

- 419 arquivos PHP passaram no lint;
- 22/22 testes enterprise passaram;
- configuração ausente retornou HTTP 503 com página e Trace ID;
- banco inacessível não derrubou a tela GET de login;
- manifesto FIM foi regenerado e validado;
- inventário SQL permaneceu com 134 tabelas sem duplicidade;
- regressão MySQL real permanece dependente de ambiente com `pdo_mysql`.
