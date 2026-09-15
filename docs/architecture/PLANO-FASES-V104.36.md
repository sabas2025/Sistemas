# V104.36 — Consolidação das cinco fases

## Fase 1 — Segurança e idempotência
- Reserva idempotente atômica com `INSERT IGNORE`.
- Payload JSON canonicalizado e chave com contexto de tenant/empresa.
- Falha da proteção configurável por `idempotency_failure_policy`; produção usa `dlq`.
- Nenhuma chamada externa deve ocorrer quando a proteção falha.
- Correção da precedência na referência `fila_<id>`.

## Fase 2 — Banco e migrations
- Baseline oficial em `database/baseline/schema-v104.36.sql`.
- Migration incremental em `database/migrations/20260710_001_idempotency_queue_lease.sql`.
- `schema_auto_apply=false`: alterações estruturais devem ocorrer por migration controlada.
- Recomenda-se usuário `hub_runtime` sem CREATE, ALTER ou DROP após instalação.

## Fase 3 — Fila enterprise
- Lease de cinco minutos, heartbeat e liberação de item expirado.
- Compatibilidade com bancos ainda sem as novas colunas durante a atualização.
- Duplicados concluídos ou ativos são ignorados sem chamar Tiny/VSM.
- Falha do guard segue retry, bloqueio ou DLQ conforme política.

## Fase 4 — Organização e governança
- DDL novo centralizado em migrations.
- Baseline único definido como fonte para instalação nova.
- Novas regras devem permanecer em Services; Controllers apenas validam, autorizam e delegam.
- Refatoração gradual do DashboardController deve manter rotas legadas como fachadas até remoção segura.

## Fase 5 — Produção e PWA
- PWA compatível com Android, iPhone/iPad, Windows, macOS e navegador.
- Cache restrito a assets; respostas administrativas recebem `Cache-Control: no-store`.
- Aviso de atualização não bloqueante e dispensável.
- Instruções de instalação exibidas conforme a plataforma.
- Testes estáticos de idempotência, migration, cache e manifesto adicionados.

## Ordem de atualização
1. Backup completo e teste de restore.
2. Executar `database/migrations/20260710_001_idempotency_queue_lease.sql`.
3. Publicar código.
4. Reiniciar workers.
5. Executar `php tests/enterprise/v104_36_hardening_test.php`.
6. Validar uma integração de homologação antes de liberar produção.
