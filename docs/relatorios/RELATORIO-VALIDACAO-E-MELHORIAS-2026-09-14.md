# Validação independente da R7 e sugestões de melhoria

Data: 2026-09-14 · Base: **V104.49.3-R7**

> Esta rodada não repetiu os testes que eu mesmo escrevi — eles têm os mesmos pontos cegos de quem
> os escreveu. A validação confere o **fato no código**, arquivo por arquivo, e depois procura o
> que nenhuma rodada anterior encontrou.

## 1. Resultado da validação

**45 checagens de evidência direta: 45 OK, 0 falhas.** Todos os achados das quatro rodadas estão
efetivamente aplicados no pacote.

| Rodada | Achados | Verificados |
|---|---|---|
| A — reauditoria externa | A-01 … A-13 | 12/12 |
| B — auto-auditoria | B-01, B-03, B-05, B-06 | 6/6 |
| F — auditoria `PROMPT_-_HUB.md` | F-01, F-02 | 5/5 |
| C — capacidade (100 clientes / 500 ped/min) | C-01 … C-08 | 19/19 |
| Invariantes gerais | catálogo, índices, globais | 3/3 |

**12 portões de CI verdes** · 35 testes · 135 tabelas em paridade · 137 SQL reescritos com
placeholders, parênteses e colunas/valores conferidos um a um.

## 2. Dois defeitos NOVOS, na própria correção C-07

A validação encontrou dois problemas que as rodadas anteriores não viram — ambos no código mais
recente, que é o menos revisado. **Ambos corrigidos.**

### D-01 — A tabela de sessão nunca seria limpa *(Alta)*

| Campo | Conteúdo |
|---|---|
| **Evidência** | `php -r 'echo ini_get("session.gc_probability");'` → **`0`** neste próprio ambiente. Com `gc_probability=0`, o PHP **nunca** chama `SessionHandlerInterface::gc()`. Busca por `sessoes` em `worker_retencao.php` e `DataRetentionService.php`: **nenhuma ocorrência**. |
| **Arquivo** | `app/Services/DatabaseSessionHandler.php::gc()` · `app/Services/DataRetentionService.php` |
| **Gravidade** | **Alta** |
| **Causa raiz** | Confiei no coletor do PHP. Mas `gc_probability=0` é padrão de fábrica em Debian e Ubuntu, que desligam o coletor de propósito e limpam sessões de **arquivo** por cron (`/usr/lib/php/sessionclean`) — cron que não sabe nada de tabela. Escrevi um handler cuja limpeza depende de um mecanismo que, nas distribuições mais comuns, está desligado. |
| **Impacto** | Ao ligar `security.session_driver='database'`, a tabela `sessoes` cresceria **para sempre**. Com 100 clientes, isso é crescimento contínuo de linhas e de índice. A correção C-07 teria trocado o problema de escala por um vazamento de armazenamento. |
| **Cenário de falha** | Operador liga o driver de banco ao adicionar o segundo servidor. Meses depois, `sessoes` tem dezenas de milhões de linhas, o `INSERT ... ON DUPLICATE KEY` do `write()` fica lento e **toda requisição autenticada** paga o custo. Nada no painel aponta para a causa. |
| **Correção aplicada** | `DataRetentionService::cleanupSessoes()`, chamado por `cleanup()` — ou seja, executado pelo `worker_retencao.php` no cron, independente do coletor do PHP. Usa `security.session_absolute_timeout_seconds` como corte. `ultimo_acesso` é `INT` (epoch), não `DATETIME`, então não cabia na regra genérica de idade das outras tabelas. |
| **Risco da correção** | Baixo. Só apaga sessão já expirada pelo timeout absoluto — sessão viva não é tocada. `LIMIT 5000` por execução evita lote longo. |
| **Compatibilidade** | Nenhuma mudança de schema, rota ou API. Com `session_driver='file'` (padrão) o método sai cedo, porque a tabela não é usada. |
| **Como testar** | `php tests/enterprise/v104_49_3_r7_capacidade_test.php` (asserções D-01), ou rodar `php workers/worker_retencao.php` e conferir a linha `sessoes` na saída. |
| **Como reverter** | Remover `cleanupSessoes()` e sua chamada. Um arquivo, sem migration. |
| **Status** | **corrigido e validado** |

### D-02 — O indicador de saúde apontava para o lugar errado *(Média)*

| Campo | Conteúdo |
|---|---|
| **Evidência** | `DatabaseSessionHandler::read()` chamava `SecurityHealthService::degrade('event_log', ...)`. O controle `event_log` é descrito como *"Registro de eventos de segurança (tabela security_events indisponível)"*. |
| **Gravidade** | **Média** |
| **Causa raiz** | Reaproveitei um nome de controle existente em vez de declarar o próprio. |
| **Impacto** | Falha no armazenamento de **sessão** apareceria no painel e em `api/status` como *"registro de eventos de segurança indisponível"*. O operador investigaria a tabela errada. É **exatamente a família F-01/F-02** — indicador que mente — que eu mesmo registrei como armadilha no `CLAUDE.md` e repeti na correção seguinte. |
| **Correção aplicada** | Controle próprio `session_store`, com descrição própria: *"Armazenamento de sessão em banco indisponível (usuários podem ser deslogados)"*. |
| **Risco / Compatibilidade** | Nenhum. Só muda o rótulo exibido. |
| **Como reverter** | Voltar a chamada para `'event_log'` e remover a entrada do catálogo. |
| **Status** | **corrigido e validado** |

## 3. Hipóteses levantadas e DESCARTADAS por evidência

O documento exige não inventar achado. Estas foram investigadas e **não** são problemas:

| Hipótese | Por que não procede |
|---|---|
| Cache do C-08 usaria um IP diferente do usado por `block()`, e o bloqueio não invalidaria | Falso. `SecurityEventService::ip()` e `RequestContext::ip()` delegam ambos a `TrustedProxyService::clientIp()`. Todos os chamadores de `block()` (`RouteRateLimiterService`, `WafService`) usam `SecurityEventService::ip()`. As chaves coincidem. |
| `$page` chegaria ao `enforce()` sem normalização, quebrando a igualdade exata do `webhook_inbound` | Falso. `public/index.php:132` define `$page` uma vez e passa o **mesmo valor** ao limitador e ao dispatcher, que também casa por igualdade. |
| `DataRetentionService::cleanup()` ainda seria alcançável pela web | Falso. Única chamada: `workers/worker_retencao.php:38`. |
| `applyToSelect()` colocaria o predicado no lugar errado em `UNION` ou subconsulta | Nenhum dos 137 SQL reescritos usa `UNION` ou subconsulta no nível externo. |
| `run()` receberia tabela diferente da que o SQL usa | Os 137 literais foram conferidos: **0 divergências**. |
| Rotas `api/*` de painel ainda sofreriam contenção de linha única | Falso. `usuario_id` faz parte de `uk_rate_bucket`, então clientes distintos ocupam linhas distintas. |

---

# 4. Sugestões de melhoria

Ordenadas por relação custo/benefício. **Nenhuma foi aplicada** — todas mudam comportamento ou
exigem decisão de produto.

## Alta prioridade

### M-01 — Resolver o `OR empresa_id IS NULL` do isolamento

`TenantScopeService::where()` gera `AND (empresa_id = ? OR empresa_id IS NULL)`. O `OR ... IS NULL`
é deliberado — sem ele, ligar o isolamento faria todo o histórico anterior à migration desaparecer
das telas sem aviso. Mas ele tem dois custos reais em escala:

1. **Índice.** O otimizador não faz varredura de faixa limpa sobre `(empresa_id, status)` com um
   `OR IS NULL` no meio; costuma cair em *index merge* ou varredura maior.
2. **Isolamento.** Enquanto houver linha com `empresa_id NULL`, **todos os clientes a enxergam**.

**Caminho sugerido:** depois de aplicar `20260914_010` e confirmar que o backfill classificou tudo
(`SELECT COUNT(*) FROM <tabela> WHERE empresa_id IS NULL` = 0 em cada uma das 32), trocar o padrão
de `where()` para `whereStrict()` — que já existe e já é usado na exportação de logs. Sugiro uma
chave `security.tenant_scope_strict` para a virada ser reversível sem editar código.

### M-02 — Validar o isolamento contra banco real com duas empresas

Continua sendo a pendência mais importante, e nenhuma quantidade de teste estático a substitui.
O procedimento está na seção 5 do `SECURITY.md`. **Sem isso, o isolamento é uma promessa
verificada apenas por leitura de código.**

### M-03 — Medir antes de confiar nos índices novos

`EXPLAIN` das consultas das telas de pedidos, fila e logs, com volume representativo, confirmando
que `idx_pedidos_empresa_status` e `idx_logs_empresa_data` são realmente escolhidos. Índice criado
não é índice usado — e com o `OR IS NULL` do M-01 há motivo concreto para desconfiar.

## Média prioridade

### M-04 — Teto de `hits` no balde de rate limit

`rate_limit_hits.hits` é `INT` e só cresce dentro da janela do minuto. Não é risco prático hoje,
mas um laço de cliente mal configurado inflaria a linha sem teto. Um `LEAST(hits+1, 100000)` no
`ON DUPLICATE KEY UPDATE` fecha isso a custo zero.

### M-05 — Painel para o `SecurityHealthService`

O estado de degradação já é exposto em `api/status`, mas não há **tela**. Um cartão no dashboard
com os controles degradados evitaria depender de alguém consultar a API. Os controles já têm
descrição em português no catálogo — falta só renderizar.

### M-06 — Alerta quando a chave primária se aproximar do teto

Depois da migration `012` o risco fica remoto, mas o sistema não tem como avisar. Uma checagem no
`worker_selftest.php` comparando `AUTO_INCREMENT` (via `information_schema.TABLES`) com o teto do
tipo, alertando acima de 70%, transforma um incidente silencioso em aviso antecipado.

### M-07 — `logs_integracao` e `auditoria_eventos` deveriam ser particionadas por data

São as duas maiores tabelas no volume alvo. `PARTITION BY RANGE` em `criado_em` torna o expurgo de
retenção um `DROP PARTITION` instantâneo, em vez de `DELETE ... LIMIT 5000` repetido. Exige MySQL
com partições habilitadas e muda o plano de backup — por isso é decisão de infraestrutura, não
mudança de código.

## Baixa prioridade / dívida

### M-08 — `pedidos_integracao.filial_id` continua vestigial

Nenhum código lê ou grava. Não removi porque as regras proíbem `DROP COLUMN` sem plano seguro.
Quando houver janela (a mesma da `012`), vale remover junto.

### M-09 — Três serviços de retenção coexistem

`DataRetentionService`, `RetentionService` e `DataRetentionService::cleanupFilaConcluida` fazem
trabalho semelhante com regras e prazos diferentes. É a mesma duplicação que causou o F-02.
Consolidar em um só, com catálogo único de regras, evita que os prazos divirjam.

### M-10 — `Logger::hasTraceColumn()` usa `static $has` por processo

Em worker de longa duração, uma mudança de schema em runtime não seria notada até reiniciar.
Inofensivo hoje; vale um comentário explicando a escolha, ou TTL curto.

### M-11 — Cobertura E2E ainda não exercita o ciclo OAuth completo

Segue dependendo de credenciais Tiny reais, pelo motivo já documentado (o guard de SSRF recusa
provedor falso local, e desligá-lo no CI validaria uma configuração que não pode existir em
produção). Quando houver credenciais de homologação, é o maior buraco de cobertura restante.

---

## 5. O que continua sem validação

**"Não identificado com as evidências disponíveis"** para tudo que exige banco ou carga:
comportamento sob 500 pedidos/min reais, plano de execução dos índices, tempo do `ALTER` da
migration `012`, latência do `DatabaseSessionHandler` sob concorrência, e o comportamento real das
integrações Tiny V2/V3 e VSM.

Não há MySQL, Docker nem ambiente de carga nesta sessão. **Toda afirmação deste relatório é
estática**, derivada de leitura de código e schema, e cada uma cita arquivo e linha.

Os portões de runtime MySQL 8 / MariaDB 11.4 existem no workflow e são obrigatórios pelo job
`gate` — **execute-os antes de produção.** Doze portões verdes aqui não significam "testado em
produção".

---

# ADENDO 2 — vazão da fila: cinco correções aplicadas (E-01 a E-05)

Data: 2026-09-14 · Origem: auditoria da pergunta *"a fila evita travar em alta escala?"*

A fila protege a **ingestão** corretamente — o webhook enfileira e responde, sem chamada externa no
caminho da requisição. O risco estava no consumidor. Três correções haviam sido propostas; ao
aplicá-las, dois defeitos adicionais apareceram no mesmo arquivo.

| # | Achado | Gravidade | Situação |
|---|---|---|---|
| E-01 | Chave duplicada no array do heartbeat | Baixa | **Corrigido** |
| E-02 | `$processed` contava a iteração vazia | Média | **Corrigido** |
| E-03 | Lote padrão de 50 — 10× abaixo da meta | Alta | **Corrigido** |
| E-04 | `worker_fila.php` é armadilha de nome (1 item/execução) | Alta | **Corrigido** |
| E-05 | Sem alarme de contrapressão | Alta | **Corrigido** |

## E-01 — Chave duplicada no heartbeat *(Baixa)*

**Evidência:** `workers/worker_enterprise.php:36` — `['processed'=>…,'empty'=>…,'max_seconds'=>$maxSeconds,'max_seconds'=>$maxSeconds,…]`.
A segunda ocorrência sobrescreve a primeira. **Impacto:** nenhum em runtime (mesmo valor), mas é
sinal de edição descuidada num arquivo crítico. **Correção:** removida a duplicata.

## E-02 — `$processed` contava um item que não existiu *(Média)*

**Evidência:** `$processed++` vinha **antes** do teste `str_contains($out,'fila_vazia')`. Quando a
fila esvaziava, a última volta do laço não processou nada mas entrou na conta.

**Impacto:** o número reportado no heartbeat e no JSON ficava sempre 1 a mais ao esvaziar. Isso
importa mais do que parece: **é esse número que serve para calcular a taxa de drenagem** ao
dimensionar os processos paralelos. Um contador otimista levaria a subdimensionar.

**Correção:** o `break` da fila vazia passou a vir antes do incremento.
**Como testar:** `php tests/enterprise/v104_49_3_r7_vazao_fila_test.php` (asserção E-02, confere a
ordem das instruções no código executável).

## E-03 — Lote padrão 10× abaixo da meta *(Alta)*

**Evidência:** `$limit = max(1, min(500, (int)($argv[1] ?? 50)))`. O README documentava
`worker_enterprise.php 50`. Quem rodasse sem argumento ficava em 50 itens por execução — contra
500 pedidos/min entrando.

**Por que é seguro elevar:** verifiquei antes de mexer que o laço já para em **duas** condições
independentes do limite — fila vazia (`$empty = true; break;`) e `$maxSeconds` (padrão 300s). O
limite de 50 era o fator que interrompia a drenagem **antes da hora**; ele não protegia contra
nada. Com padrão 500, a execução passa a ser limitada por tempo ou por fila vazia, que é o
comportamento correto de um laço de drenagem.

**Correção:** padrão de 50 para 500. Teto (500) e piso (1) preservados; argumento explícito
continua mandando.
**Verificado executando:** sem argumento → 500; com `30` → 30; com `9999` → 500.
**Como reverter:** trocar `?? 500` por `?? 50`.

## E-04 — `worker_fila.php` é uma armadilha de nome *(Alta)*

**Evidência:** `ApiController::processarFila()` chama `QueueService::pegarProximo()` — singular, sem
laço. `worker_fila.php` o invoca uma vez e sai. Confirmado que **não existe laço** em nenhum lugar
de `app/` ou `workers/` em volta desse método.

**Impacto:** é o nome óbvio e o **primeiro exemplo do README**. Quem agendar por intuição drena
**1 item por minuto**. A 500 pedidos/min o acúmulo é de ~499 itens/min — em uma hora, ~30 mil
itens; a fila nunca drena. Nada no sistema avisava (até o E-05).

**Correção:** cabeçalho no arquivo declarando em maiúsculas que processa **um** item por execução,
para que serve, o que acontece em carga, e apontando para `worker_enterprise.php 500`. O worker
**não foi removido nem alterado em comportamento** — serve para diagnóstico, e removê-lo seria
tirar funcionalidade sem autorização.

## E-05 — Nenhum alarme de contrapressão *(Alta)*

**Evidência:** busca por alarme de crescimento de fila em `app/` e `workers/`: nenhuma ocorrência.
O painel exibia o número de pendentes — número exibido só ajuda quem está olhando.

**Correção:** `QueueBackpressureService`, amostrado a cada execução do `worker_selftest.php`.

O critério é **crescimento sustentado** (3 ciclos seguidos), não limite absoluto — e a escolha é
deliberada: limite absoluto gera alarme falso no pico normal, que é exatamente o que a fila existe
para absorver, e não pega o vazamento lento, que é o perigoso. Também alarma se houver item
pendente parado há 30+ minutos, sintoma de worker fora do ar.

Dispara evento de auditoria `fila.contrapressao`, marca o controle `queue_backpressure` no
`SecurityHealthService` (visível no painel e em `api/status`) e volta a `healthy` quando drena.
Estado gravado sob `LOCK_EX`, para não corromper com workers paralelos. **Nunca lança** — alarme
que derruba worker é pior que ausência de alarme.

## Documentação — `workers/README-WORKERS.md` reescrito

Tabela comparando os dois workers de fila, conta de dimensionamento por latência
(150/300/500 ms → 2/3/5 processos), exemplo de cron com 4 processos paralelos, explicação de por
que a concorrência é segura (`SKIP LOCKED`, lease, heartbeat), cron obrigatório de retenção, e o
registro da decisão sobre envio em lote.

**Com um aviso explícito de que a tabela de latência não foi medida** — a latência real da VSM e do
Tiny não é conhecida, e o README instrui a calcular a taxa própria a partir do campo `processed`.

## Achado do próprio portão durante esta rodada

O `tenant-scope-check.php` **reprovou o `QueueBackpressureService`** que eu acabara de escrever: ele
consulta `fila_integracao`, tabela com escopo de empresa, sem isolamento nem justificativa. Está
correto que seja global — mede a profundidade da fila da **instalação** para alarmar o operador, e
recortar por empresa esconderia justamente o acúmulo agregado. Foi acrescentado às exceções **com a
justificativa escrita**, que é o que o portão exige.

Vale registrar: o portão criado no C-05 pegou um serviço novo escrito três rodadas depois. É o
comportamento pretendido.

## Correção do meu próprio harness de verificação

O laço que eu vinha usando para rodar os portões (`out=$(cmd | tail -1); echo $?`) capturava o
código de saída do `tail`, **não o do portão** — sempre 0. Ele teria mascarado qualquer falha. Foi
por isso que a reprovação do `tenant-scope-check` apareceu como `[0]` com texto de ajuda no lugar
do `[OK]`. O laço foi refeito capturando o `rc` do comando real, e a reexecução confirmou
**0 portões com falha**.

## Validação

**12 portões, 0 falhas** (com exit code correto) · `enterprise-tests.sh` **36 testes** ·
45 verificações de evidência das rodadas anteriores intactas · 137 SQL reescritos íntegros ·
13 workers, nenhum sem guard.

Novo: `tests/enterprise/v104_49_3_r7_vazao_fila_test.php`, 24 asserções cobrindo E-01 a E-05 e a
documentação — incluindo a premissa (`processarFila()` continua sendo de item único), para que a
auditoria não fique órfã se o método mudar.

## Arquivos

**Modificados:** `workers/worker_enterprise.php` · `workers/worker_fila.php` ·
`workers/worker_selftest.php` · `workers/README-WORKERS.md` ·
`app/Services/SecurityHealthService.php` · `scripts/ci/tenant-scope-check.php`
**Novos:** `app/Services/QueueBackpressureService.php` ·
`tests/enterprise/v104_49_3_r7_vazao_fila_test.php`

**Migrations:** nenhuma. **Banco:** não alterado. **APIs:** não alteradas.
**Rollback:** restaurar os seis arquivos modificados e remover os dois novos. Sem efeito em dados.

## O que continua sem validação

A latência real da VSM e do Tiny, e portanto o número exato de processos necessários.
**"Não identificado com as evidências disponíveis"** — depende de medição no ambiente de vocês.
O README explica como calcular a partir do `processed`.
