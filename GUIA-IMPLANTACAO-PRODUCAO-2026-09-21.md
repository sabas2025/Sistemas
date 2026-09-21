# GUIA DE IMPLANTAÇÃO EM PRODUÇÃO — Hub V104.49.3-R7

**Base:** `main` @ `d98f7ba` · **Data:** 2026-09-21 · **Público:** responsável pela operação do Hub.

Guia operacional único, do começo ao fim, para levar as pendências desta linha à produção. Cada
passo diz **o que rodar**, **como conferir** e **como reverter**. O SQL detalhado das migrations
está em `RELATORIO-RUNBOOK-MIGRATIONS-CAPACIDADE-2026-09-21.md` (runbook #23); aqui é a visão
consolidada com os demais passos (segredos, branch protection, toggles).

> **Nada aqui foi rodado na SUA produção.** As migrations foram ensaiadas contra MariaDB 10.11 local
> (runbook #23). A execução em produção é sua. Rode numa janela de baixa carga e com backup à mão.

---

## Ordem geral (visão de topo)

```
0. Pré-voo (backup + medir volume + confirmar CI verde na main)
1. Migrations SEM janela ......... 011 → 013 → 015 → 016
2. Cron da retenção operacional ... worker_retencao.php a cada 15 min
3. Migration COM janela .......... 012 (PK BIGINT) — workers parados, webhooks drenados
4. Auditar/rotacionar segredos .... rotate-secrets.php --audit
5. Branch protection .............. marcar "Hub CI / gate" como required
6. Toggles de produção (quando aplicável) .. webhook_signature_require_v2 / session_driver
7. Verificação pós-implantação
```

Todas as migrations são **idempotentes e condicionais**: reexecutar não causa efeito nem erro. Uma
instalação recente já nasce com quase tudo — nesse caso a migration apenas se registra e não altera
nada. Rodá-las é sempre seguro.

---

## Passo 0 — Pré-voo (OBRIGATÓRIO)

1. **CI verde na `main`.** Confirme que o último push na `main` está com **Hub CI** e **PWA Quality**
   verdes antes de tirar schema dali.
2. **Backup verificado.** Painel → **Backups → Gerar** e **confira o SHA-256**. É o rollback da `012`
   (Passo 3) e a rede de segurança de tudo.
3. **Meça o volume das tabelas quentes** — dimensiona a janela da `012`:
   ```bash
   DB=<seu_banco>
   mysql -u <user> -p "$DB" -e \
    "SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES
     WHERE TABLE_SCHEMA='$DB'
       AND TABLE_NAME IN ('fila_integracao','pedidos_integracao','pedidos_hub','logs_integracao')
     ORDER BY TABLE_ROWS DESC;"
   ```
   Quanto maiores essas tabelas, mais longa a janela da `012`. **Quanto antes rodar, mais barato.**

---

## Passo 1 — Migrations SEM janela (011, 013, 015, 016)

Criam/removem índices e uma coluna/tabela. Podem rodar com o Hub no ar; prefira baixa carga.

```bash
DB=<seu_banco>
for m in 20260914_011_indices_capacidade \
         20260914_013_capacidade_logs_sessoes \
         20260915_015_indice_fila_duplicado \
         20260916_016_indice_integration_events_fila; do
  echo ">>> $m"
  mysql -u <user> -p "$DB" < database/migrations/$m.sql || { echo "FALHOU em $m"; break; }
done
# conferência
mysql -u <user> -p "$DB" -e \
 "SELECT migration,status FROM schema_migrations
  WHERE migration REGEXP 'indices_capacidade|logs_sessoes|indice_fila|integration_events';"
```

| Migration | Efeito | Custo (ensaio) |
|---|---|---|
| **011** | 3 índices de empresa em `pedidos_integracao` | 34 ms |
| **013** | `logs_integracao.empresa_id` + 2 índices; tabela `sessoes`; **backfill** de empresa única | 36 ms |
| **015** | remove `idx_fila_ready` (duplicata exata de `idx_fila_status_proxima_prioridade`) | 20 ms |
| **016** | índice `idx_ie_fila(fila_id,id)` em `integration_events` | ~0,13 s / 150 mil linhas |

> **013 — backfill:** só preenche `empresa_id` histórico quando há **exatamente uma** empresa
> cadastrada (`COUNT(*)=1 → MIN(id)`). Com 2+ empresas, deixa NULL de propósito.

**Rollback (Passo 1):**
- 011 → `DROP INDEX idx_pedidos_integracao_empresa / idx_pedidos_empresa_status / idx_pedidos_empresa_data ON pedidos_integracao;`
- 013 → `DROP INDEX idx_logs_integracao_empresa / idx_logs_empresa_data ON logs_integracao; ALTER TABLE logs_integracao DROP COLUMN empresa_id; DROP TABLE sessoes;` (a `sessoes` só se `session_driver`≠`database`)
- 015 → `CREATE INDEX idx_fila_ready ON fila_integracao(status,proxima_tentativa,prioridade,id);`
- 016 → `DROP INDEX idx_ie_fila ON integration_events;`

---

## Passo 2 — Agendar a retenção operacional no cron

O expurgo operacional precisa rodar **fora do caminho de requisição** (achados C-04/I-15). Sem este
cron, seis tabelas (`logs_integracao`, `auditoria_eventos`, `tiny_webhooks`, `metricas_api`,
`diagnostico_api`, `selftest_relatorios`) nunca são expurgadas.

```cron
*/15 * * * * php /caminho/do/hub/workers/worker_retencao.php >> /var/log/hub-retencao.log 2>&1
```

**Conferência:** após o primeiro disparo, `tail /var/log/hub-retencao.log` deve mostrar execução
limpa (status de auditoria segue o que aconteceu — sem erro silencioso).

**Rollback:** remover a linha do cron. Nenhum dado é perdido além do expurgo já feito (que é o
comportamento desejado).

---

## Passo 3 — Migration 012 (PK BIGINT) — **EM JANELA DE MANUTENÇÃO**

`ALTER ... MODIFY` de chave primária **reconstrói tabela e índices** e **bloqueia** as tabelas.
Custo proporcional ao volume (medido no Passo 0). **Quanto antes, mais barato.**

1. **Pare os workers:** `worker_fila`, `worker_estoque`, `worker_fiscal`, `worker_reconciliacao`.
2. **Suspenda/drene** a entrada de webhooks (um INSERT concorrente fica bloqueado até o fim do ALTER).
3. **Confirme o backup do Passo 0.**
4. Rode e confira:
   ```bash
   mysql -u <user> -p "$DB" < database/migrations/20260914_012_pk_bigint_capacidade.sql
   mysql -u <user> -p "$DB" -e \
     "SELECT TABLE_NAME,COLUMN_NAME,DATA_TYPE FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA='$DB' AND DATA_TYPE='int'
        AND ((COLUMN_NAME='id' AND TABLE_NAME IN('fila_integracao','pedidos_integracao','pedidos_hub','logs_integracao'))
             OR COLUMN_NAME IN('fila_id','pedido_hub_id'));"
   # esperado: CONJUNTO VAZIO (nada mais INT nessas colunas)
   ```
5. **Religue** workers e a entrada de webhooks.

A ordem interna da `012` é segura: converte primeiro as colunas que **referenciam** as chaves e só
então as PKs — nunca há referência estreita apontando para chave larga.

**Rollback (012):** **restaurar o backup do Passo 0.** Não reverter por `ALTER` BIGINT→INT: só é
seguro se nenhum `id` já tiver passado de 2.147.483.647, o que não se garante depois de voltar a operar.

---

## Passo 4 — Auditar (e, se preciso, rotacionar) segredos

Rode **primeiro em modo auditoria** (não escreve nada):

```bash
php scripts/rotate-secrets.php --audit
```

Reporta força, reuso e valores conhecidos das chaves geridas (`encryption_key`,
`token_vault_hmac_key`, `audit_daily_signature_key`, `fim_manifest_hmac_key`,
`integration_replay_hmac_key`, `webhook_secret`, `vsm_hmac_secret`, `backup_signature_key`). Exit 0
= tudo forte, distinto e desconhecido.

**Se — e só se — a auditoria apontar problema**, rotacione as chaves indicadas:

```bash
php scripts/rotate-secrets.php --rotate=<chave1,chave2> --confirm   # sem --confirm é simulação
```

> **Cuidado com dois efeitos colaterais** (o próprio script avisa):
> - `webhook_secret` / `vsm_hmac_secret` → **informe o novo valor ao outro lado (Tiny/VSM) ANTES**
>   do próximo webhook, senão as chamadas legítimas passam a ser recusadas.
> - `fim_manifest_hmac_key` → **regenere o manifesto de integridade** (painel → Segurança → FIM).

**Rollback:** a rotação sem `--confirm` é simulação (não há o que reverter). Com `--confirm`, o
rollback é restaurar o backup do Passo 0 e reconectar o outro lado ao segredo anterior — por isso
rotacione um de cada vez e só o que a auditoria pedir.

---

## Passo 5 — Marcar `Hub CI / gate` como *required* (ação de admin do repositório)

O job `gate` do `hub-ci.yml` **só fica verde se TODOS os anteriores** (static-enterprise,
mysql-runtime, e2e-authenticated) ficarem. Ele já existe e fica verde; falta só torná-lo obrigatório
para bloquear merge de PR vermelha.

**GitHub → Settings → Branches → Branch protection rules → `main` →** *Require status checks to pass
before merging* → adicionar **`gate`** (aparece como `Hub CI / gate`). Salvar.

**Rollback:** remover o check da regra de proteção — reversível a qualquer momento na mesma tela.

---

## Passo 6 — Toggles de produção (só quando aplicável)

Editáveis em `config/config.php`, seção `security` (o instalador cria a partir de
`config/config.example.php`):

| Toggle | Padrão | Quando ligar |
|---|---|---|
| `webhook_signature_require_v2` | `false` | **Quando a VSM migrar** para HMAC v2. Ligar antes disso recusaria os webhooks atuais da VSM (que ainda usam o esquema aceito). |
| `session_driver` | `'file'` | Só ao passar de **um servidor** para vários (sessão compartilhada em banco). Em nó único, `file` é o correto — não mexa. |

> Estes NÃO são passos a executar agora; são gatilhos condicionais. Ligá-los sem o pré-requisito
> (VSM migrada / segundo servidor) **quebra** fluxo legítimo. **Rollback:** voltar o valor anterior
> e recarregar — nenhum dado é afetado.

---

## Passo 7 — Verificação pós-implantação

1. **Login e dashboard** respondem 200 (fluxo de troca de senha obrigatória na primeira vez, se aplicável).
2. **Painel → Filas:** itens sendo reservados/processados normalmente (o índice canônico da fila
   segue fazendo o trabalho).
3. **Painel → api/status:** controles de segurança sem degradação inesperada.
4. **Um webhook de teste** de cada lado (Tiny e VSM) é aceito e auditado — confirma que nenhum
   segredo/toggle quebrou a entrada legítima.
5. **`schema_migrations`** lista todas as migrations aplicadas com status de sucesso.

Se qualquer passo falhar, o rollback de cada seção acima é local; a rede de segurança final é a
restauração do backup do Passo 0.

---

## Resumo em uma tela

| # | Passo | Janela? | Rollback |
|---|---|---|---|
| 0 | Backup + medir volume + CI verde | não | — |
| 1 | Migrations 011/013/015/016 | não | DROP INDEX / DROP COLUMN por migration |
| 2 | Cron `worker_retencao.php` | não | remover linha do cron |
| 3 | Migration 012 (PK BIGINT) | **SIM** | restaurar backup do Passo 0 |
| 4 | `rotate-secrets.php --audit` (+rotação se preciso) | não | restaurar backup + reconectar segredo |
| 5 | `Hub CI / gate` required | não | remover check da regra |
| 6 | Toggles (VSM/multi-servidor) | não | voltar valor anterior |
| 7 | Verificação pós-implantação | — | — |

**Não confunda "a CI está verde" com "o Hub está validado em produção":** o verde cobre schema, E2E
e portões; a chamada real a Tiny/VSM e o ciclo OAuth completo dependem de credenciais/rede reais e
seguem fora do alcance de auditoria.
