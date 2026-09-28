# Hotfix V104.48.1-R2 — VSM, Segurança e Self-Test

## Incidentes corrigidos

1. `public/index.php?page=vsm-testes` informava ausência de `vsm_endpoints`, `vsm_campos_mapeamento` e `vsm_endpoint_logs`.
2. O Self-Test gerava `SQLSTATE[42S22] Unknown column 'score'` em `DashboardController.php`.
3. `IpBlockService` e `WafService` registravam repetidamente ausência de `security_events` e `ips_bloqueados`.
4. O redactor confundia IDs de migration como `20260712_001` com telefone/CEP.
5. A mesma falha best-effort podia ser gravada centenas de vezes.

## Causa raiz

- O schema oficial possuía as tabelas, mas as migrations incrementais não garantiam VSM/SOC/Self-Test em bases antigas ou parcialmente atualizadas.
- `DashboardController::selftest()` consultava a coluna inexistente `score` e não carregava `detalhes`/`trace_id`, usados pela view.
- O Enterprise Core anterior verificava as tabelas VSM, porém não incluía integralmente as tabelas SOC e a compatibilidade de `selftest_relatorios`.

## Correções

- Nova migration idempotente `20260712_004_vsm_security_selftest_recovery.sql`.
- Criação/recuperação de:
  - `security_events`
  - `ips_bloqueados`
  - `rate_limit_hits`
  - `vsm_endpoints`
  - `vsm_campos_mapeamento`
  - `vsm_endpoint_logs`
  - `selftest_relatorios`
- ALTER condicional das colunas de contrato VSM e Self-Test.
- Enterprise Core agora cria, completa e verifica essas tabelas.
- Self-Test consulta `id, status, resumo, detalhes, trace_id, criado_em`.
- Validar Banco passou a conferir colunas VSM, SOC e Self-Test.
- Mensagens operacionais orientam migrations 001, 002, 003 e 004.
- Redaction não mascara mais identificadores de migration como telefone/CEP.
- Logs best-effort idênticos são limitados por operação/erro em janela de 60 segundos.
- A tela VSM Testes oferece acesso direto ao reparo do banco.

## Segurança e compatibilidade

- Nenhum `DROP`, `TRUNCATE` ou `DELETE FROM` foi incluído.
- Nenhuma configuração, token ou credencial é incluída no hotfix.
- Tiny e VSM não tiveram regras de negócio ou contratos alterados.
- Os templates de endpoint VSM continuam desativados e não verificados.
- A migration pode ser executada mais de uma vez.

## Validação local

- 420 arquivos PHP aprovados no lint.
- 22 testes enterprise aprovados, 184 verificações OK e 0 falhas.
- Schema consolidado: 134 tabelas sem duplicidade.
- DDL operacional não autorizado: 0.
- 96 rotas validadas.
- As 7 definições da migration 004 correspondem exatamente às colunas do schema oficial.
- CI estático aprovado.

## Gate externo

A execução real da migration em MySQL não foi realizada neste ambiente porque `pdo_mysql` não está disponível e não existe acesso ao banco `ctbatop1_loja02`. O pacote inclui regressão MySQL destrutiva isolada para homologação.

## Aplicação no servidor

1. Criar backup dos arquivos e banco.
2. Aplicar o ZIP hotfix na raiz do HUB, preservando `config/config.php`.
3. No painel, abrir `Central Técnica > Banco > Mapa do Banco` e clicar em **Aplicar tabelas e migrations pendentes**.
4. Se o painel não conseguir executar DDL, aplicar `database/migrations/20260712_004_vsm_security_selftest_recovery.sql` pelo phpMyAdmin no banco `ctbatop1_loja02`.
5. Reabrir `public/index.php?page=vsm-testes`.
6. Abrir `public/index.php?page=selftest` e executar o Self-Test.
7. Confirmar que `security_events` e `ips_bloqueados` aparecem como OK no Mapa do Banco.
8. Regenerar a baseline FIM após confirmar os arquivos.
