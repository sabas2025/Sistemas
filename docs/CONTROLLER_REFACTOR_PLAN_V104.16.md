# Plano de separação de controllers — V104.16

A V104.16 mantém compatibilidade com o `DashboardController`, mas passa a usar dispatcher rápido e controllers dedicados para rotas novas/comerciais.

## Já separado

- `CommercialController`
- `CommercialProductionController`
- `SecurityAssistedTestController`
- `DatabaseMaintenanceController`
- `OrquestracaoController`
- `ProductionSecurityController`
- `BackupController`
- `PedidoController`
- `FilaController`
- `VsmController`
- `TinyHomologacaoController`

## Próximos blocos para extração gradual

1. `DashboardHomeController` — dashboard inicial e KPIs.
2. `UserPermissionController` — usuários e permissões.
3. `AuditController` — auditoria, detalhes, exportação e hash chain.
4. `ConfigController` — configurações gerais e Tiny/VSM.
5. `LegacyUpdateController` — rotas históricas bloqueadas/controladas.
6. `OperationalReportsController` — logs, métricas e relatórios.

## Regra de migração

Não remover métodos antigos sem antes mover rota, view, permissão, CSRF e teste manual. O objetivo é reduzir risco operacional e preservar compatibilidade com instalações em produção.
