# RUNBOOK — Aplicação das migrations de capacidade (sequência)

**Base:** `main` @ `1fbde48` (V104.49.3-R7) · **Ensaio:** MariaDB 10.11, 2026-09-21
**Alvo:** aplicar, em produção, as migrations de capacidade na ordem correta, com segurança e rollback.

> **Ensaio feito nesta sessão contra MariaDB real** — não é produção. Reproduzi uma instalação
> **antiga** (PKs INT, índices ausentes, índice duplicado presente), semeei dados, e apliquei a
> sequência. Todas convergiram para o alvo, os dados sobreviveram e a reaplicação é no-op. Os
> comandos abaixo são os validados; a execução em produção é sua.

---

## Ordem de implantação (a que este runbook segue)

`backup verificado` → **011** (índices) → **013** (logs + sessões) → **015** (remove índice duplicado da fila)
→ agendar `worker_retencao.php` no cron → **016** (índice de `integration_events`) → **012** (PK BIGINT, EM JANELA)

Todas são **idempotentes e condicionais**: reexecutar não causa efeito nem erro. Uma instalação
recente já nasce com quase tudo (os módulos atuais já declaram BIGINT e os índices) — nesse caso as
migrations simplesmente registram-se e não alteram nada. Rodá-las é sempre seguro.

---

## Passo 0 — Backup verificado (OBRIGATÓRIO antes de 012)

Painel → **Backups → Gerar** e **confira o SHA-256**. O rollback da `012` é a restauração deste
backup (reverter BIGINT→INT só é seguro se nenhum `id` já tiver passado de 2.147.483.647, o que não
se garante depois de voltar a operar).

## Passo 1 — Migrations SEM janela (011, 013, 015, 016)

Não bloqueiam operação de forma relevante (criam/removem índices e uma coluna/tabela). Podem rodar
com o Hub no ar, mas prefira baixa carga.

```bash
DB=<seu_banco>
for m in 20260914_011_indices_capacidade \
         20260914_013_capacidade_logs_sessoes \
         20260915_015_indice_fila_duplicado \
         20260916_016_indice_integration_events_fila; do
  echo ">>> $m"
  mysql -u <user> -p "$DB" < database/migrations/$m.sql || { echo "FALHOU em $m"; break; }
done
mysql -u <user> -p "$DB" -e \
 "SELECT migration,status FROM schema_migrations WHERE migration REGEXP 'indices_capacidade|logs_sessoes|indice_fila|integration_events';"
```

**O que cada uma faz** (medido no ensaio):
| Migration | Efeito | Custo |
|---|---|---|
| 011 | 3 índices de empresa em `pedidos_integracao` | segundos |
| 013 | `logs_integracao.empresa_id` + 2 índices; tabela `sessoes`; **backfill** de empresa única | segundos |
| 015 | remove `idx_fila_ready` (duplicata exata de `idx_fila_status_proxima_prioridade`) | instantâneo |
| 016 | índice `idx_ie_fila(fila_id,id)` em `integration_events` | ~0,13 s / 150 mil linhas |

> **013 — backfill:** só preenche `empresa_id` histórico quando há **exatamente uma** empresa
> cadastrada (regra `COUNT(*)=1 → MIN(id)`). Com 2+ empresas, deixa NULL de propósito (não há como
> saber a quem o log antigo pertence). No ensaio, com 1 empresa, os 3 logs antigos receberam `empresa_id=1`.

## Passo 2 — Agendar a retenção operacional no cron

```cron
# expurgo operacional fora do caminho de requisição (achado C-04/I-15)
*/15 * * * * php /caminho/do/hub/workers/worker_retencao.php >> /var/log/hub-retencao.log 2>&1
```

## Passo 3 — Migration 012 (PK BIGINT) — **EM JANELA DE MANUTENÇÃO**

`ALTER ... MODIFY` de chave primária **reconstrói a tabela e os índices** e **bloqueia** as tabelas.
Custo proporcional ao volume: pequeno hoje (segundos), horas se adiado. **Quanto antes, mais barato.**

1. **Pare os workers:** `worker_fila`, `worker_estoque`, `worker_fiscal`, `worker_reconciliacao`.
2. **Suspenda/drene** a entrada de webhooks (um INSERT concorrente fica bloqueado até o fim do ALTER).
3. Confirme o backup do Passo 0.
4. Rode:
   ```bash
   mysql -u <user> -p "$DB" < database/migrations/20260914_012_pk_bigint_capacidade.sql
   mysql -u <user> -p "$DB" -e \
     "SELECT TABLE_NAME,COLUMN_NAME,DATA_TYPE FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA='$DB' AND DATA_TYPE='int'
        AND ((COLUMN_NAME='id' AND TABLE_NAME IN('fila_integracao','pedidos_integracao','pedidos_hub','logs_integracao'))
             OR COLUMN_NAME IN('fila_id','pedido_hub_id'));"
   # esperado: CONJUNTO VAZIO (nada mais INT nessas colunas)
   ```
5. Religue workers e a entrada de webhooks.

A ordem interna da 012 é segura: converte primeiro as colunas que **referenciam** as chaves
(`pedidos_validacao.fila_id/pedido_hub_id`, `pedidos_payloads`, `pedidos_status_historico`,
`pedidos_nfe_xml`) e só então as PKs — nunca há referência estreita apontando para chave larga.

---

## Rollback por migration

| Migration | Rollback |
|---|---|
| 011 | `DROP INDEX idx_pedidos_integracao_empresa / idx_pedidos_empresa_status / idx_pedidos_empresa_data ON pedidos_integracao;` |
| 013 | `DROP INDEX idx_logs_integracao_empresa / idx_logs_empresa_data ON logs_integracao; ALTER TABLE logs_integracao DROP COLUMN empresa_id; DROP TABLE sessoes;` (só se `session_driver`≠`database`) |
| 015 | `CREATE INDEX idx_fila_ready ON fila_integracao(status,proxima_tentativa,prioridade,id);` |
| 016 | `DROP INDEX idx_ie_fila ON integration_events;` |
| 012 | **Restaurar o backup do Passo 0** (não reverter por ALTER — ver Passo 0). |

---

## Resultados do ensaio (MariaDB 10.11, base "instalação antiga" + dados)

| Verificação | Antes | Depois |
|---|---|---|
| PKs `fila/pedidos_integracao/pedidos_hub/logs` | INT | **BIGINT** ✅ |
| Colunas referenciadoras (5) | INT | **BIGINT** ✅ |
| Índices de empresa em `pedidos_integracao` | 0 | **3** ✅ |
| `logs_integracao.empresa_id` + índices | ausente | **presente**; backfill → empresa 1 ✅ |
| Tabela `sessoes` | ausente | **presente** ✅ |
| `idx_fila_ready` (duplicata) / canônico | dup presente | **dup removida, canônico mantido** ✅ |
| `idx_ie_fila(fila_id,id)` | ausente | **presente** ✅ |
| Linhas de dados (5 tabelas) | seed | **preservadas** ✅ |
| AUTO_INCREMENT após BIGINT | max=3 | **próximo id=4, sem reuso** ✅ |
| Reaplicar as 5 na ordem | — | **rc=0, no-op, `schema_migrations` intacta, duplicata não voltou** ✅ |

**Tempos no ensaio (base pequena):** 011=34ms · 013=36ms · 015=20ms · 016=22ms · 012=100ms.
Em produção o único que cresce com o volume é a **012** — por isso a janela e a pressa.

## Ressalvas honestas
- Ensaio contra MariaDB **10.11** local, não contra a produção. O comportamento de bloqueio/tempo da
  `012` depende do volume real das suas tabelas — meça o tamanho antes de dimensionar a janela.
- Nenhuma chamada real a Tiny/VSM está envolvida; isto é exclusivamente schema.
