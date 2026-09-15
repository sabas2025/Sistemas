# Workers do HUB

A lógica dos workers foi movida para este diretório, fora de `public/`. Todos são **CLI-only**:
execução via navegador devolve HTTP 403.

Os arquivos `public/worker*.php` são apenas *shim* de compatibilidade e também bloqueiam o navegador.

---

## Escolha do worker de fila — leia antes de agendar

Existem dois, e a diferença importa:

| Worker | Itens por execução | Para quê |
|---|---|---|
| `worker_fila.php` | **1** | Diagnóstico e volume baixo |
| `worker_enterprise.php [N] [seg]` | até N (padrão **500**) | **Produção com volume** |

`worker_fila.php` chama `ApiController::processarFila()` uma única vez, e esse método retira **um**
item da fila. Agendado de minuto em minuto, ele drena **1 item por minuto**. Isso é proposital —
serve para testar o fluxo —, mas **não serve para carga**.

`worker_enterprise.php` roda um laço que para em três condições: fila vazia, limite de itens, ou
`maxSeconds` (padrão 300s, configurável em `enterprise.queue_worker_graceful_stop_seconds`).

---

## Dimensionamento

A fila suporta concorrência com segurança: a reserva usa `FOR UPDATE SKIP LOCKED`, com lease e
heartbeat, e `locked_by` identifica o dono. **Rodar vários processos em paralelo é seguro** — dois
workers nunca pegam o mesmo item.

O custo por item é dominado pela chamada HTTP à VSM ou ao Tiny. Um processo é **serial**: a conta é
`60s ÷ latência por item`.

| Latência por item | Vazão de 1 processo | Processos para 500 itens/min |
|---|---|---|
| 150 ms | ~400/min | 2 |
| 300 ms | ~200/min | **3** |
| 500 ms | ~120/min | 5 |

> **Não meça pela tabela, meça no seu ambiente.** A latência real da VSM e do Tiny não é conhecida
> aqui. Use `processed` no JSON de saída do worker e o tempo de execução para calcular a sua taxa,
> e dimensione a partir dela.

E lembre que **500 pedidos/min gera mais de 500 itens**: os fluxos de estoque e fiscal enfileiram os
seus. Conte com folga.

### Exemplo — 4 processos paralelos, a cada minuto

```cron
* * * * * php /caminho/workers/worker_enterprise.php 500 55 >> /var/log/hub-fila-1.log 2>&1
* * * * * php /caminho/workers/worker_enterprise.php 500 55 >> /var/log/hub-fila-2.log 2>&1
* * * * * php /caminho/workers/worker_enterprise.php 500 55 >> /var/log/hub-fila-3.log 2>&1
* * * * * php /caminho/workers/worker_enterprise.php 500 55 >> /var/log/hub-fila-4.log 2>&1
```

O segundo argumento (`55`) faz cada processo encerrar antes do próximo disparo do cron, evitando
acúmulo de processos. Com a fila vazia, cada um sai na primeira iteração — não há custo em ocioso.

---

## Demais workers

```bash
php workers/worker_estoque.php 20
php workers/worker_fiscal.php 20
php workers/worker_consulta_estoque_vsm.php --force 100
php workers/worker_reconciliacao.php
php workers/worker_notificacoes.php
php workers/worker_xml_nfe.php
php workers/worker_backup.php
```

### Retenção — obrigatório em produção

```cron
0,10,20,30,40,50 * * * * php /caminho/workers/worker_retencao.php >> /var/log/hub-retencao.log 2>&1
```

Expurga `fila_integracao` em estado terminal, `rate_limit_hits`, `logs_integracao`,
`auditoria_eventos`, `sessoes` e os contadores em disco. **Sem ele as tabelas crescem sem teto.**

### Autoteste e alarme de contrapressão

```cron
*/5 * * * * php /caminho/workers/worker_selftest.php >> /var/log/hub-selftest.log 2>&1
```

Além do autoteste, cada execução é uma amostra do tamanho da fila. Se ela crescer em **3 ciclos
seguidos**, o alarme dispara: evento de auditoria `fila.contrapressao`, controle degradado
`queue_backpressure` visível no painel e em `api/status`. Também alarma se houver item pendente
parado há 30 minutos ou mais — sintoma de worker fora do ar.

O campo `fila_contrapressao` na saída JSON traz `pendentes`, `processando`, `mais_antigo_min` e
`ciclos_crescendo`.

---

## Sobre envio em lote para a VSM

Não implementado, e é uma decisão consciente. Enviar N pedidos numa chamada quebraria quatro
garantias que hoje funcionam: idempotência por item, retry com backoff por item, DLQ por item e
Trace ID por item. Numa falha parcial, reprocessar o lote duplicaria os itens que deram certo — e a
regra do projeto proíbe que reprocessar duplique pedido, produto, nota ou movimentação.

Além disso, **não há contrato OpenAPI da VSM no pacote**, então não há como afirmar que ela aceita
lote: os endpoints configurados são todos de recurso único.

Escalar em processos paralelos resolve a mesma vazão sem tocar em nada disso. Se a VSM publicar
contrato com endpoint de lote **que devolva status por item**, a decisão pode ser revista.
