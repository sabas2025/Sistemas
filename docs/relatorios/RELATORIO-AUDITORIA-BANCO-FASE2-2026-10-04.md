# Relatório — Auditoria de Banco (Fase 2) — 2026-10-04

> Rodada de auditoria da **Fase 2 (Banco)** das 12 fases do `CLAUDE.md`.
> **Natureza:** varredura read-only estática. Nenhuma alteração de schema aplicada.
> **Base:** `main` em `59698a6`.
> **O que mudou neste relatório:** só a nota "zero FKs" do `CLAUDE.md` foi corrigida (ver B2-N1) —
> nenhuma mudança de schema.

## Escopo

Tabelas · colunas · tipos · defaults · índices · PK · FK · constraints · ENUMs · duplicidades ·
integridade referencial · órfãos · migrations · `schema_migrations` · paridade instalação nova ×
atualização. Migrations devem ser idempotentes, seguras, reversíveis, compatíveis, protegidas
contra reexecução.

## Método (evidências)

- Portões estáticos de schema executados localmente (sem banco): `sql-inventory-check.php`,
  `mysql-schema-static-check.php`, `mysql-module-parity-check.php`, `schema-runtime-ddl-check.php`,
  `build-consolidated-schema.mjs --check`.
- Inventário de `database/migrations/` (18 arquivos) e leitura das duas mais novas (018, 019).
- `grep` de FK/`REFERENCES` no consolidado; conferência de ordem de criação e engine; conferência
  de escritor/leitor da tabela nova.

## Portões estáticos de schema — todos verdes

| Portão | Resultado |
|---|---|
| `sql-inventory-check` | OK — **136 tabelas** |
| `mysql-schema-static-check` | OK — migration idempotente, colunas/índices críticos presentes |
| `mysql-module-parity-check` | OK — módulos ≡ consolidado (colunas, tipos, defaults, índices, constraints, engine, collation) |
| `schema-runtime-ddl-check` | OK — DDL restrito aos 12 arquivos autorizados |
| `build-consolidated-schema --check` | OK — `install.sql`/`install_final_current.sql` = 8 módulos |

## Migrations novas desde a última rodada — ambas exemplares

- **`20260926_018_vsm_credentials.sql`** (+5 colunas em `configuracoes_integracao`): idempotente
  (guarda `col=0`), portável (`information_schema`, não `SHOW`), reversível (documentada no
  cabeçalho), espelha `database/modules/core.sql`, e **preserva** `vsm_token` legado.
- **`20260927_019_drop_tiny_v3_manual_token_columns.sql`** (DROP de 2 colunas): **plano seguro
  explícito** — as colunas eram comprovadamente mortas (nunca escritas; lidas só como `!empty()` em
  OR com o token vivo → sempre false), as leituras foram removidas no mesmo commit, é decisão de
  produto registrada, idempotente (guarda `col=1`, sem `DROP COLUMN IF EXISTS` que o MySQL 8
  recusa), portável e reversível (documentada). Satisfaz a proibição de `DROP COLUMN sem plano
  seguro`.
- Modelo de execução **manual** das migrations intacto (6 de 18 na lista de `SchemaMigrationService::checksum()`,
  por desenho — não há executor automático, como o `CLAUDE.md` já documenta).

## Achado — B2-N1 (Informativa / drift de documentação)

### O schema deixou de ter "zero FKs": passou a ter EXATAMENTE UMA
- **Evidência:** `database/install_final_current.sql:74` e `database/migrations/20260917_017_myouro_consulta.sql:16` —
  `CONSTRAINT fk_myouro_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id)` na tabela
  `myouro_conexoes` (136ª tabela, feature MyOuro). É a **única FK** do schema (a 2ª ocorrência de
  `REFERENCES` no arquivo é falso positivo em outra linha).
- **Arquivo/tabela:** `myouro_conexoes` (módulo `core.sql` + migration 017).
- **Gravidade:** Informativa (drift de documentação, não defeito de schema).
- **Por que NÃO é defeito:** ordem de criação correta (`empresas` na linha 52, `myouro_conexoes` na
  61), `ENGINE=InnoDB`, `UNIQUE uk_myouro_empresa (empresa_id)` já serve de índice da FK (sem
  duplicado), paridade nova×atualização confere (mesma constraint nos dois caminhos), e a matriz de
  runtime da CI (MySQL 8 + MariaDB 11.4) instala o schema **verde**.
- **Impacto:** o `CLAUDE.md` afirmava *"zero FKs nas 135 tabelas"* e *"mudá-lo seria alteração
  estrutural sem autorização"* — agora são **136 tabelas e 1 FK**. Indicador desatualizado na
  memória do projeto.
- **Correção aplicada nesta rodada:** nota do `CLAUDE.md` atualizada — 135→136 tabelas, a FK do
  MyOuro registrada como **exceção documentada** à postura FK-free (com o aviso de não copiar o
  padrão para outras tabelas sem autorização). Nenhuma mudança de schema.
- **Status:** `corrigido e validado` (a documentação; o schema permanece como está, por decisão).

## Não medido nesta rodada (precisa de banco vivo)

Paridade **runtime** nova×atualização por diff de `information_schema`, linhas órfãs e duplicatas de
índice em runtime. Mitigações em vigor: a matriz de CI instala consolidado e modular nos dois
runtimes (verde), as migrations novas espelham `core.sql` por construção + o portão estático de
paridade, e as duplicatas de índice conhecidas (I-18 corrigida; 2 frias registradas) seguem
inalteradas. A medição profunda contra MariaDB real pode ser feita sob demanda (receita no
`CLAUDE.md`).

## Veredito

Schema sólido e sob controle dos portões. Sem defeito de banco Crítico/Alto/Médio. Único item
(B2-N1) é drift de documentação — corrigido. As duas migrations desde a última rodada seguem o
contrato (idempotente, segura, reversível, paritária), inclusive o DROP de colunas com plano seguro.
