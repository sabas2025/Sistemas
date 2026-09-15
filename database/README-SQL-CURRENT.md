# SQL oficial atual — V104.49.3-R5

Atualizado em **2026-08-22**. Os contratos de instalação, reparo e módulos
foram consolidados para impedir instalações concluídas com schema VSM incompleto.

## Arquivos ativos

- `modules/*.sql`: caminho usado pelo `public/install.php`; cada arquivo deve ser
  executado somente no banco do módulo correspondente.
- `install_final_current.sql`: schema canônico para instalação nova em banco único.
- `install.sql`: cópia byte a byte do schema canônico, mantida apenas como alias
  de compatibilidade.
- `repair_current.sql`: fallback de recuperação para banco único existente,
  sempre após backup. Além do DDL, contém seeds idempotentes de catálogo,
  permissões e histórico de migrations; não é um arquivo "somente estrutural".
  Ele não sobrescreve usuários, hashes de senha, tokens, secrets, endpoints ou
  configurações operacionais; não use esse arquivo como instalador inicial. Em
  ambiente modular, prefira **Central Técnica > Enterprise Core**, que resolve a
  conexão correta de cada tabela.
- `migrations/20260712_001` a `20260712_004` e `20260713_007`: histórico incremental
  preservado para atualizações controladas.
- `baseline/schema-v104.36.sql`: baseline histórico mantido por compatibilidade,
  com o contrato crítico de colunas compatibilizado com a V104.49.3-R5.

## Contrato crítico consolidado

A V104.49.3-R5 garante as 24 colunas que estavam divergentes em cinco tabelas:

- `configuracoes_integracao`: 7 colunas de ambiente/liberação VSM;
- `schema_migrations`: `version` e `description`;
- `vsm_endpoints`: 6 colunas de teste seguro, origem e contrato;
- `vsm_campos_mapeamento`: 8 colunas de contrato e metadados;
- `vsm_endpoint_logs`: `modo_teste_seguro`.

Os índices adicionados fora de `CREATE TABLE` usam consulta a
`information_schema.statistics` e SQL preparado. Não use
`CREATE INDEX IF NOT EXISTS`: essa forma não é portátil para MySQL 8.

## Procedimento seguro

1. Gere e valide um backup antes de qualquer reparo.
2. Confirme se a implantação usa banco único ou módulos separados.
3. Em instalação nova, use o instalador web, que aplica `modules/*.sql`.
   O preflight recusa qualquer banco que já contenha tabelas do Hub.
4. Em base existente, execute primeiro a simulação do Enterprise Core.
5. Aplique as correções e valide as colunas pelo Mapa do Banco.
6. Só habilite endpoints VSM depois do teste seguro em homologação.

> **Não execute `install.sql` ou `install_final_current.sql` diretamente sem
> renderização.** Eles contêm placeholders deliberados (`{DB_NAME}`,
> `{ADMIN_HASH}`, `{TINY_*}`, `{VSM_*}` e `{WEBHOOK_SECRET}`). O caminho oficial é
> `public/install.php`, que valida e substitui esses valores. Para automação
> controlada, renderize todos os placeholders, use hash `password_hash` real para
> o administrador e recuse a execução se qualquer trecho `{...}` permanecer.

O AutoRepair do painel executa somente `CREATE TABLE IF NOT EXISTS` e alterações
estruturais permitidas. Em modo modular, cada `modules/*.sql` é enviado à conexão
do próprio módulo; seeds e demais comandos DML são ignorados. Módulos ausentes na
configuração não são redirecionados silenciosamente para o banco core.

`install.sql` e `install_final_current.sql` são gerados de forma determinística
pelos oito módulos com `node scripts/ci/build-consolidated-schema.mjs`. A CI usa
`--check` para bloquear divergência. Seus seeds usam inserção idempotente e não
atualizam configurações operacionais em conflito.

Arquivos versionados antigos em `database/legacy/` permanecem apenas para auditoria
e não devem ser aplicados em uma instalação atual.
