# Classificação inicial de tabelas — V99

Classificação sugerida para governança:

## EM USO
- usuarios
- permissoes_perfil
- configuracoes_integracao
- login_tentativas
- security_events
- ips_bloqueados
- rate_limit_hits
- auditoria_eventos
- auditoria_hash_chain
- backups_banco
- fila_integracao
- fila_morta
- circuit_breakers
- pedidos_integracao
- produtos_mapeamento
- estoque_movimentos
- estoque_divergencias
- nfe_integracao

## LEGADO / MANTER ATÉ VALIDAR HISTÓRICO
- audit_exports
- auditoria_detalhes
- dashboard_testes_execucoes
- mapper_versions
- tiny_v2_retry_policies

## FUTURO / MÓDULO DE APOIO
- module_health_snapshots
- fiscal_reconciliacao_snapshots
- estoque_reconciliacao_agendada
- instalacao_prechecks

## REMOVER AVALIAR
Nenhuma tabela deve ser removida automaticamente sem backup e validação de uso real em produção.
