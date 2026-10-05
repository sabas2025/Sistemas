# Correção D-02 — `worker_estoque.php` gravava em coluna inexistente no fluxo VSM → Tiny

**Data:** 2026-10-05 · **Release:** V104.49.3-R7 · **Origem:** auditoria ao encher o cartão
"Estoque sincronizado" da demo (tela Dashboard / Estoque).

## Identificação
O ramo principal do worker de estoque (VSM → Tiny, "VSM é o estoque real") atualizava
`estoque_movimentos` gravando a coluna `retorno_tiny`, que **não existia** na tabela.

## Evidência
- **Escritor:** `workers/worker_estoque.php:43` (ramo `destino === 'tiny'`):
  `UPDATE estoque_movimentos SET status=?, retorno_tiny=? WHERE trace_id=? AND sku=? ORDER BY id DESC LIMIT 1`.
- **Schema (antes):** `estoque_movimentos` tinha `retorno_vsm`, **não** `retorno_tiny`. Confirmado em
  3 fontes: `SHOW COLUMNS` no banco real, `database/modules/estoque.sql` (só `retorno_vsm`) e grep
  em `database/` — nenhuma migration adicionava a coluna (ela existe só em `pedidos_integracao`,
  `produtos_mapeamento`, `produto_pendencias`).
- **Runtime (MariaDB):** o UPDATE do ramo destino=tiny falhava com
  `SQLSTATE[42S22] Unknown column 'retorno_tiny'`.

## Arquivo/classe/método/tabela
`workers/worker_estoque.php::` (loop, ramo destino=tiny) · tabela `estoque_movimentos`.

## Gravidade
**Alta** — quebra de fluxo principal (sincronização de estoque VSM → Tiny) e risco de reescrita
repetida de saldo no Tiny.

## Causa raiz
Coluna ausente no schema. O ramo irmão (destino=vsm, linha 55) grava `retorno_vsm`, que existe; o
ramo destino=tiny foi escrito para gravar `retorno_tiny` (a resposta do Tiny), mas a coluna nunca
foi criada no `estoque_movimentos`.

## Impacto
O `try/catch` do worker (linha 63) capturava o `Unknown column` e rebaixava a "falha do item":
1. o movimento **nunca** virava `status='sucesso'` no fluxo VSM → Tiny (cartão "Estoque
   sincronizado" ficava 0 em produção para esse fluxo);
2. o item de `fila_estoque` era marcado `erro` e **re-tentado** até `falha_definitiva`;
3. **risco de duplicação:** a linha 37 (`$tiny->atualizarEstoque()`) pode já ter atualizado o Tiny
   com sucesso antes do UPDATE estourar — e o retry reenvia a atualização ao Tiny. Isso roça a
   regra "reprocessar não pode duplicar movimentação de estoque".

## Cenário de falha
VSM envia saldo autoritativo → Hub enfileira → worker chama `atualizarEstoque` no Tiny (sucesso) →
UPDATE em `estoque_movimentos.retorno_tiny` lança `Unknown column` → item marcado erro → retry
reenvia o saldo ao Tiny.

## Por que passou despercebido
Nenhum portão exercita o worker com banco real no ramo destino=tiny; a CI aplica schema e roda E2E
de painel, não o worker de estoque end-to-end. O `catch(Throwable)` transforma o erro fatal num
"erro de item", que parece falha transitória de integração.

## Correção aplicada
Adicionada a coluna `retorno_tiny LONGTEXT NULL` a `estoque_movimentos`, espelhando `retorno_vsm`,
nos **dois caminhos** (lição I-18, mesmo commit):
- `database/modules/estoque.sql` — coluna após `retorno_vsm`;
- `database/migrations/20261005_020_estoque_movimentos_retorno_tiny.sql` — idempotente
  (`information_schema` + `ALTER ... ADD COLUMN ... AFTER retorno_vsm`), reexecutável;
- `database/install_final_current.sql` / `install.sql` — regenerados por
  `node scripts/ci/build-consolidated-schema.mjs`.

**Nenhuma lógica tocada** — só o schema ganhou a coluna que o worker já gravava.

## Risco da correção
Baixo. Coluna nova, anulável, só escrita (nada a lê para decidir fluxo). Sem efeito sobre dados
existentes.

## Compatibilidade
MySQL 8 e MariaDB 11.4 (migration via `information_schema`, sem `IF NOT EXISTS` de coluna). Sem
janela de manutenção (`ADD COLUMN` anulável é online nas duas engines para esta tabela).

## Como testar
- **Estático:** `tests/enterprise/v104_49_3_estoque_retorno_tiny_column_test.php` — ancora o schema
  na verdade do escritor (o worker grava `retorno_tiny` ⇒ a coluna existe no módulo, no consolidado
  e numa migration). Conferido contra o defeito reposto (coluna removida do módulo): **reprova**.
  Contagem de colunas do contrato do instalador atualizada 1566 → **1567** em
  `v104_49_3_install_sql_contract_test.php`.
- **Runtime (MariaDB):** re-provisionado pelo consolidado → `SHOW COLUMNS` tem `retorno_tiny`; o
  UPDATE exato do worker (ramo destino=tiny) grava `status='sucesso'` sem erro. Caminho de
  atualização: migration aplicada sobre tabela sem a coluna a adiciona e é idempotente (2× sem
  erro), com `retorno_tiny` após `retorno_vsm` (paridade).

## Como reverter
`ALTER TABLE estoque_movimentos DROP COLUMN retorno_tiny;` — nada lê a coluna para decidir fluxo.

## Status
**corrigido e validado** (estático + runtime, instalação nova e atualização).

## Resíduo registrado (não aplicado)
O worker segue com `catch(Throwable)` que rebaixa qualquer erro de DDL/DML a "falha de item". Com a
coluna criada o sintoma some, mas a classe de defeito "erro de schema disfarçado de falha de
integração" permanece latente. Endurecer o worker para distinguir erro de schema de erro de
integração é melhoria de observabilidade — registrada aqui, fora do escopo desta correção.
