# Banco V104.8

Arquivo oficial para instalação nova: `database/install_final_v104_8.sql`.

Para base já instalada, use a tela **Central Técnica > Validar Banco**. Ela chama `DatabaseSchemaGuardService` e executa reparos idempotentes.

Os SQLs incrementais antigos que usavam `ADD COLUMN IF NOT EXISTS` ou índice anti-replay antigo foram movidos para `database/legacy/incrementais_v104_8/` para evitar execução acidental em MySQL/MariaDB incompatível.

Pontos corrigidos:
- `integration_replay_guard` usa `uk_replay_origem_hash_bucket` em vez de bloquear o mesmo payload para sempre.
- `schema_migrations` usa o padrão `migration/checksum/status/mensagem`.
- O schema oficial e o mapa de módulos são verificados pelo SchemaGuard.
