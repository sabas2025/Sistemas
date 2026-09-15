# RELATÓRIO V104.29 — Correção Central Técnica > Validar Banco em Loop

## Problema reportado
Ao clicar em **Central Técnica > Validar Banco**, a tela ficava carregando/rodando em loop.

## Causa técnica identificada
A rota `validar-banco` ainda executava automaticamente, no carregamento da tela:

- `DatabaseAutoRepairService::repair(false)`
- `DatabaseSchemaGuardService::repair(false)`

Isso fazia uma ação de consulta virar reparo completo de schema, com execução de SQL modular/current e validação de colunas/índices. Em hospedagem compartilhada, banco grande ou usuário MySQL com permissão limitada, o processo podia demorar demais e aparentar loop.

## Correção aplicada
A partir da V104.29:

1. **GET `index.php?page=validar-banco`** executa somente validação em leitura segura.
2. **AutoRepair/SchemaGuard não executam automaticamente ao abrir a tela.**
3. Reparo foi separado em botão explícito: **Reparar Schema Manualmente**.
4. O reparo usa **POST + CSRF + permissão**.
5. A validação tem limite de tempo e limite de itens exibidos para evitar travamento.
6. A auditoria grava resumo leve, não o payload completo de centenas de checks.

## Arquivos alterados

- `app/Services/DatabaseValidationService.php`
- `app/Controllers/DatabaseMaintenanceController.php`
- `app/Controllers/DashboardController.php` *(fallback legado)*
- `views/validar_banco.php`
- `config/config.php`
- `app/Services/SystemVersionService.php`

## Impacto Tiny/VSM
Nenhum fluxo Tiny/VSM foi alterado.

- Pedidos: preservado
- Estoque: preservado
- Fiscal/XML: preservado
- Webhooks: preservados
- Fila/DLQ: preservadas
- OAuth Tiny: preservado

## Impacto no banco
A abertura da tela não altera mais o banco. Alterações de schema só ocorrem se o administrador clicar no botão **Reparar Schema Manualmente**.

## Rollback
Para rollback, voltar os arquivos acima para a versão V104.28 ou restaurar o pacote anterior.

## Teste recomendado
1. Entrar no painel.
2. Abrir **Central Técnica > Validar Banco**.
3. Confirmar que a tela carrega sem ficar em loop.
4. Verificar status, Trace ID e duração.
5. Só clicar em **Reparar Schema Manualmente** após backup recente.
