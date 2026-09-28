# Relatório V104.36

Foram aplicadas correções de idempotência atômica, política de falha segura, canonicalização de payload, lease/heartbeat da fila, migration incremental, baseline oficial, cache `no-store` para páginas administrativas e PWA multiplataforma.

## Compatibilidade
- Android: Chrome ou Edge, instalação como PWA.
- iPhone/iPad: Safari, Compartilhar, Adicionar à Tela de Início.
- Windows: Edge ou Chrome.
- macOS: Safari, Chrome ou Edge.
- Navegador: uso web responsivo.

## Compatibilidade preservada
As novas colunas possuem fallback enquanto a migration não foi executada. Os fluxos Tiny V2, Tiny V3, VSM, pedido, estoque, fiscal e XML/NF-e não tiveram regra de negócio alterada.

## Obrigatório antes de produção
Executar `database/migrations/20260710_001_idempotency_queue_lease.sql`, reiniciar workers e executar o teste enterprise V104.36.
