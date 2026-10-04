# Runbook — Janela de manutenção da migration `012` (PK INT → BIGINT)

**Data do documento:** 2026-09-28
**Migration alvo:** `database/migrations/20260914_012_pk_bigint_capacidade.sql` (achado C-02)
**Release:** V104.49.3-R7 · MySQL 8 / MariaDB 10.5+
**Natureza:** procedimento operacional. **Não altera código.** Executado por quem tem acesso ao
servidor de produção e ao banco.

> Este runbook consolida o que a própria migration e o `CLAUDE.md` já dizem, num passo-a-passo
> acionável. Leia o cabeçalho da migration antes de rodar — ele é a fonte.

---

## 1. Por que, e por que agora

Quatro tabelas de alto volume têm chave primária **INT** (teto 2.147.483.647). Na meta de 500
pedidos/min, `logs_integracao` estoura em ~2 anos e `fila_integracao` em ~2,7 anos. `AUTO_INCREMENT`
não reaproveita id apagado, então **retenção não adia o estouro**. No estouro, o `INSERT` falha com
`Duplicate entry '2147483647' for key 'PRIMARY'` e a entrada de trabalho **para por inteiro**.

**O custo da migration é proporcional ao volume** (reconstrói tabela + índices):

| Volume da tabela | Tempo esperado |
|---|---|
| < 1M linhas | segundos |
| ~10M linhas | minutos |
| ~100M linhas | horas |

**Conclusão: quanto antes rodar, mais barata.** Rodar com as tabelas ainda pequenas é o objetivo.

---

## 2. O que a migration faz (fiel ao arquivo)

**Idempotente** — cada `ALTER` só roda se o tipo atual ainda for `int` (checagem via
`information_schema.COLUMNS`). Reexecução é segura.

**Ordem interna** (colunas que **referenciam** as chaves vêm **antes** das próprias chaves, para não
existir referência estreita apontando para chave larga em nenhum instante):

1. Colunas referenciadoras → BIGINT:
   - `pedidos_validacao.fila_id`, `pedidos_validacao.pedido_hub_id`
   - `pedidos_payloads.pedido_hub_id`
   - `pedidos_status_historico.pedido_hub_id`
   - `pedidos_nfe_xml.pedido_hub_id`
2. Chaves primárias → `BIGINT NOT NULL AUTO_INCREMENT`:
   - `fila_integracao.id`, `pedidos_integracao.id`, `pedidos_hub.id`, `logs_integracao.id`
3. Registra em `schema_migrations` (`status='aplicada'`).

**Bloqueio:** `ALTER TABLE ... MODIFY` de PK geralmente exige `ALGORITHM=COPY`. **Assuma bloqueio** —
INSERT concorrente fica preso até o fim. Por isso, janela.

---

## 3. Pré-requisitos (ordem de implantação das migrations de capacidade)

Do `CLAUDE.md`. Se ainda não aplicadas, siga a ordem — a `012` é a **última**, e as anteriores não
precisam de janela:

```
backup verificado
  → 011  (índices de capacidade)          — sem janela
  → 013  (logs + sessões)                 — sem janela
  → 015  (remove índice duplicado da fila) — barata, sem janela
  → agendar workers/worker_retencao.php no cron
  → 016  (índice de integration_events)   — ~0,13 s, sem janela
  → 012  (PK BIGINT)                       — ESTA, em janela
```

Confirme o que já foi aplicado:
```sql
SELECT migration, status, criado_em FROM schema_migrations ORDER BY migration;
```

---

## 4. Antes da janela (checklist)

- [ ] **Backup completo e verificado.** Gere pelo painel (**Backups → Gerar**) e **confirme o
      SHA-256**. O rollback desta migration É a restauração deste backup (§7).
- [ ] Anote o tamanho das 4 tabelas para estimar o tempo:
      ```sql
      SELECT table_name, table_rows,
             ROUND((data_length+index_length)/1024/1024) AS mb
      FROM information_schema.TABLES
      WHERE table_schema = DATABASE()
        AND table_name IN ('fila_integracao','pedidos_integracao','pedidos_hub','logs_integracao');
      ```
- [ ] Confirme espaço em disco livre ≥ ~2× o tamanho somado das 4 tabelas (o COPY duplica a tabela
      temporariamente).
- [ ] Comunique a janela aos operadores; a entrada de pedidos ficará suspensa.
- [ ] Tenha o comando de restauração testado e à mão.

---

## 5. Execução (na janela)

**5.1 Parar os workers** (cron ou supervisor) — pelo menos os que escrevem nas tabelas alvo:
`worker_fila`, `worker_estoque`, `worker_reconciliacao`. Confirme que nenhum
processo `php workers/worker_*` segue vivo:
```bash
ps aux | grep -E 'workers/worker_' | grep -v grep
```

**5.2 Suspender/drenar a entrada de webhooks.** Pare o tráfego de entrada (Tiny/VSM) no proxy/host,
ou coloque o Hub em manutenção, e aguarde a fila em `processando` zerar:
```sql
SELECT status, COUNT(*) FROM fila_integracao GROUP BY status;
```
Não inicie o ALTER enquanto houver item `processando`.

**5.3 Aplicar a migration.** As migrations são **SQL real** (não são template — só o consolidado
`install_final_current.sql` tem marcadores). Aplique com o cliente do banco, contra a base
configurada, capturando o tempo e o log:
```bash
time mysql -u <USUARIO> -p <NOME_DO_BANCO> \
  < database/migrations/20260914_012_pk_bigint_capacidade.sql \
  2>&1 | tee /tmp/mig012.log
# MariaDB: troque 'mysql' por 'mariadb' (10.11 renomeou os binários)
```
Não há substituição de marcador a fazer neste arquivo.

---

## 6. Verificação (pós, ainda na janela)

**6.1 As 4 PKs e as 5 colunas referenciadoras são BIGINT:**
```sql
SELECT table_name, column_name, data_type
FROM information_schema.COLUMNS
WHERE table_schema = DATABASE() AND column_name IN ('id','fila_id','pedido_hub_id')
  AND table_name IN ('fila_integracao','pedidos_integracao','pedidos_hub','logs_integracao',
                     'pedidos_validacao','pedidos_payloads','pedidos_status_historico','pedidos_nfe_xml')
ORDER BY table_name, column_name;
-- esperado: data_type = 'bigint' em todas
```

**6.2 Registro da migration:**
```sql
SELECT * FROM schema_migrations WHERE migration = '20260914_012_pk_bigint_capacidade';
-- esperado: status = 'aplicada'
```

**6.3 Idempotência (opcional):** reexecutar o arquivo deve ser no-op (todos os `IF DATA_TYPE='int'`
caem em `SELECT 1`), sem erro.

**6.4 Fumaça de escrita:** com um item de teste, confirme que um `INSERT`/reprocesso na fila funciona.

**6.5 Reativar:** religue a entrada de webhooks e os workers; confirme que a fila volta a drenar.

---

## 7. Rollback

O rollback **é a restauração do backup verificado** do passo §4 — **não** um `ALTER BIGINT → INT`.
Reverter o tipo só seria seguro se nenhum id já tivesse passado de 2.147.483.647, e **não há como
garantir isso** depois de voltar a operar. Portanto:

1. Pare workers e entrada (como em §5.1/§5.2).
2. Restaure o backup gerado antes da janela (painel **Backups → Restaurar**, ou o procedimento de
   restauração do servidor), conferindo o SHA-256.
3. Verifique o estado e só então reative.

Se o `ALTER` **falhar no meio**, o MySQL/MariaDB reverte a tabela em transação de DDL por tabela; mas
como são vários `ALTER` sequenciais, alguns podem ter completado. A migration é idempotente, então a
recuperação preferida é: diagnosticar o erro (espaço/lock), resolver, e **reexecutar o arquivo** (as
que já viraram BIGINT são puladas). Só recorra à restauração do backup se o banco ficar inconsistente.

---

## 8. Resumo de uma linha

Backup verificado → parar workers e drenar fila → `mysql < 20260914_012...sql` na janela → conferir
`bigint` nas 4 PKs + 5 FKs e `schema_migrations` → reativar. Rollback = restaurar o backup.
