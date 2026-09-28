# Auditoria de capacidade — 100 clientes, 500 pedidos por minuto

Data: 2026-09-14 · Base: **V104.49.3-R7** · Escopo: da tela de login ao código e às tabelas

> A meta declarada muda o eixo da auditoria. Vários pontos deste sistema não estão "errados"
> isoladamente — eles deixam de servir **nesta escala**. É o caso do achado C-01, que sozinho
> impediria o Hub de aceitar a integração que ele existe para servir.

## Veredito

**O Hub, como estava, NÃO suportaria 500 pedidos por minuto.** Seria recusado no primeiro minuto e
a integração ficaria indisponível por uma hora. **Dois achados foram corrigidos**; **seis
recomendações** dependem de decisão sua e estão dimensionadas abaixo.

| # | Achado | Gravidade | Situação |
|---|---|---|---|
| C-01 | Webhook de entrada tratado como rota de painel: 20 req/min e bloqueio do IP do Tiny | **Crítica** | **Corrigido** |
| C-03 | `pedidos_integracao` sem índice em `empresa_id` — a maior tabela com escopo | **Alta** | **Corrigido** |
| C-02 | Chave primária `INT` estoura em 2 a 2,7 anos no volume alvo | **Crítica** | Recomendação |
| C-04 | Expurgo de retenção roda dentro da requisição do usuário | **Alta** | Recomendação |
| C-05 | `logs_integracao` sem escopo de empresa | **Alta** | Recomendação |
| C-06 | `fila_integracao` sem regra de retenção | **Média** | Recomendação |
| C-07 | Sessão em arquivo impede escalar horizontalmente | **Média** | Recomendação |
| C-08 | 3 a 4 consultas por requisição antes de qualquer lógica | **Média** | Recomendação |

---

## C-01 — CRÍTICA — O Hub recusaria os próprios pedidos do Tiny *(corrigido)*

| Campo | Conteúdo |
|---|---|
| **Evidência** | `RouteRateLimiterService::isSensitive()` → `str_starts_with($route,'api/')` é **true** para `api/webhook/vsm/pedido` e as outras nove rotas de entrada. Limite aplicado: `route_rate_limit_sensitive_per_minute`, **default 20** (`config.example.php:21`, `install.php:846`), com teto `max(5, min(600, $limit))`. E `if ($hits > ($limit * 3)) IpBlockService::block($ip, ..., 60, 'alto')`. |
| **Arquivo** | `app/Services/RouteRateLimiterService.php:19-20, 33-34, 55` |
| **Causa raiz** | Classificação de rota por prefixo. Todo `api/*` foi tratado como ação sensível de painel — e um webhook máquina-a-máquina não é isso. É a mesma família do achado B-06 (rota classificada por semelhança de nome), agora com impacto de disponibilidade em vez de segurança. |
| **Impacto, com a conta** | A 500 pedidos/min: a requisição **21** do minuto recebe HTTP 429; a requisição **61** dispara `IpBlockService::block()` e o IP do Tiny fica bloqueado **60 minutos**. Resultado: ~96% dos pedidos recusados e a integração blackholeada por uma hora. Mesmo configurando o máximo permitido (600), a folga sobre 500 seria de 20%, insuficiente para pico. |
| **Segundo efeito, de vazão** | A chave do balde é `(ip, usuario_id, rota, metodo, janela_inicio)`. Os webhooks do Tiny chegam do **mesmo IP**, anônimos (`usuario_id=0`), na **mesma rota**, no **mesmo minuto** — os 500 pedidos colapsam numa **única linha**, com `INSERT ... ON DUPLICATE KEY UPDATE` mais `SELECT`: **duas escritas por requisição disputando uma só linha**, serializando a entrada inteira. |
| **Cenário de falha** | Pico de vendas. O Tiny dispara 500 webhooks/min. O Hub recusa a partir do 21º e bloqueia o IP no 61º. O Tiny reenfileira, o bloqueio persiste 60 min, os pedidos envelhecem. Do lado do Hub, os painéis ficam verdes: nenhum pedido *falhou* — eles nunca entraram. |
| **Correção aplicada** | Os dez webhooks de entrada passam a ter **superfície própria** (`webhook_inbound`) na fachada de rate limit, com orçamento configurável (`webhook_route_rate_limit_per_minute`, default **3000/min**) e contagem no **contador atômico em disco**, não na tabela disputada. O desvio acontece **antes** de `ensureSchema()`, então o caminho quente também deixa de pagar as duas escritas. A classificação usa `RouteCatalogService`, por igualdade exata. |
| **O canal não ficou sem limite** | Continuam valendo: allowlist de IP dedicada (`tiny_allowed_ips`/`vsm_allowed_ips`), assinatura HMAC, contador de tentativas **pré-autenticação** (`TinyWebhookSecurityService`, política FECHADA) e guarda de replay por nonce. |
| **Risco da correção** | Baixo. Rotas de painel mantêm 20/min. Em degradação do contador a política é ABERTA — perder pedido por diretório de cache inacessível seria trocar problema de infraestrutura por perda de receita, com as outras quatro camadas ainda de pé. |
| **Como testar** | `php tests/enterprise/v104_49_3_r7_capacidade_test.php` — inclui simulação real de **500 chamadas no mesmo minuto** sem bloqueio. |
| **Como reverter** | Remover o desvio em `enforce()` e a superfície `webhook_inbound`. Sem migration. |
| **Status** | **corrigido e validado** |

## C-03 — ALTA — A maior tabela com escopo não tinha índice de empresa *(corrigido)*

**Evidência:** varredura das 31 tabelas com escopo — `pedidos_integracao` era a **única** com
`empresa_id` e **sem** índice nele. **Causa raiz:** a migration `20260914_010` pulava tabelas que
já tinham a coluna, e o índice foi junto no pulo — `pedidos_integracao` tinha `empresa_id` desde
antes da R7. **Impacto:** com 100 clientes, toda listagem de pedidos varria as linhas de **todos**
antes de filtrar.

**Correção:** `idx_pedidos_integracao_empresa(empresa_id)` mais os compostos
`idx_pedidos_empresa_status(empresa_id,status)` e `idx_pedidos_empresa_data(empresa_id,criado_em)`
— porque o filtro real das telas nunca é só a empresa. Migration `20260914_011`, idempotente.
Uma guarda no teste passa a reprovar **qualquer** tabela com escopo sem índice em `empresa_id`.
**Status: corrigido e validado.**

---

# Recomendações — dependem da sua decisão

## C-02 — CRÍTICA — A chave primária `INT` é uma bomba-relógio

**Evidência e conta**, a 500 pedidos/min (≈3 itens de fila e ≈4 linhas de log por pedido):

| Tabela | PK | Linhas/min | Estoura em |
|---|---|---|---|
| `logs_integracao` | `INT` | ~2.000 | **2,0 anos** |
| `fila_integracao` | `INT` | ~1.500 | **2,7 anos** |
| `pedidos_integracao` | `INT` | 500 | 8,2 anos |
| `pedidos_hub` | `INT` | 500 | 8,2 anos |

`AUTO_INCREMENT` **não reaproveita** id de linha apagada — expurgo de retenção não adia nada, o
contador só sobe. No estouro: `Duplicate entry '2147483647' for key 'PRIMARY'` e **a fila para de
aceitar trabalho**. A entrada de pedidos morre por inteiro.

**Por que agir agora:** `ALTER TABLE ... MODIFY id BIGINT` numa tabela com centenas de milhões de
linhas é operação de horas com bloqueio. O custo cresce todo dia. Hoje, com as tabelas pequenas,
é minutos.

**Recomendação:** migrar para `BIGINT UNSIGNED` as quatro tabelas acima **antes de entrar em
produção com esse volume**. `estoque_movimentos`, `auditoria_eventos` e `notas_fiscais` já são
`BIGINT` — o padrão existe, só não foi aplicado nessas quatro. Não apliquei porque `ALTER` de tipo
de PK exige janela de manutenção e plano de rollback definidos por você.

## C-04 — ALTA — O expurgo roda dentro da requisição do usuário

**Evidência:** `RouteRateLimiterService.php:61` chama `DataRetentionService::maybeRun()`, que em
`random_int(1,100) === 1` executa até seis `DELETE ... LIMIT 5000`.

**Impacto:** a 30 req/s, isso dispara a cada ~3 segundos, **dentro de uma requisição de usuário
sorteada**, que paga a latência de até 30.000 deleções. Um cliente vê a tela travar sem motivo
aparente, e o rastro não aponta para ele.

**Recomendação:** mover a retenção para um worker de cron dedicado (`workers/worker_retencao.php`)
e remover a chamada do caminho quente. O mesmo vale para o `DELETE` probabilístico de
`rate_limit_hits` em `RouteRateLimiterService.php:39`.

## C-05 — ALTA — `logs_integracao` não tem escopo de empresa

**Evidência:** `TenantScopeService::isScoped('logs_integracao')` → **false**.

**Impacto:** com 100 clientes, os logs de integração de todos ficam no mesmo balde, sem filtro. Um
operador que investiga o cliente A vê linhas do cliente B. É vazamento entre clientes na trilha
operacional — e é a tabela que mais cresce.

**Nota de projeto:** `auditoria_eventos` e `security_events` ficarem globais é **correto** (trilha
forense da instalação). `logs_integracao` é diferente: é operacional, por pedido, por cliente.

**Recomendação:** acrescentar `empresa_id` a `logs_integracao`, incluí-la no catálogo do
`TenantScopeService` e deixar o verificador estático cobrar os pontos de consulta.

## C-06 — MÉDIA — `fila_integracao` não tem regra de retenção

**Evidência:** `DataRetentionService` cobre `rate_limit_hits`, `integration_replay_guard`,
`security_events`, `logs_integracao`, `auditoria_eventos` e `module_health_snapshots`. **Zero**
ocorrências de `fila_integracao`.

**Impacto:** a tabela de maior escrita cresce sem teto. Itens concluídos há meses continuam sendo
lidos pelos índices em cada reserva de trabalho.

**Recomendação:** regra de retenção para itens em estado terminal (`concluido`/`processado`),
preservando `fila_morta` e o histórico de reprocessamento. Prazo é decisão de negócio.

## C-07 — MÉDIA — Sessão em arquivo impede escalar horizontalmente

**Evidência:** `public/index.php:93-109` chama `session_start()` sem `save_handler` configurado —
sessão em arquivo no disco local.

**Impacto:** a 500 pedidos/min mais 100 clientes no painel, um servidor só vira o limite. Ao
colocar um segundo servidor atrás de balanceador, a sessão não acompanha: o usuário cai no login a
cada troca de nó.

**Recomendação:** handler de sessão compartilhado (Redis, ou tabela no MySQL) antes de escalar
horizontalmente. Em nó único não há problema hoje.

## C-08 — MÉDIA — 3 a 4 consultas por requisição antes de qualquer lógica

**Evidência:** por requisição, antes do roteamento — `IpBlockService::enforce()` (1 `SELECT` +
verificação de schema), `WafService::inspect()`, `RouteRateLimiterService::enforce()`
(`INSERT ... ON DUPLICATE KEY` + `SELECT`, mais expurgos probabilísticos).

**Impacto:** a correção do C-01 já tirou os webhooks desse caminho. Resta o painel: 100 clientes
navegando pagam 3-4 consultas por clique antes de qualquer trabalho útil.

**Recomendação:** mover o bloqueio de IP para o contador atômico com cache curto, como já foi feito
com o rate limit. Medir antes de mexer — o documento do projeto proíbe otimizar sem evidência de
gargalo, e aqui a evidência é a contagem de consultas, não um profile de produção.

---

## O que NÃO foi possível validar

**"Não identificado com as evidências disponíveis"** para: comportamento sob carga real, plano de
execução dos índices novos (`EXPLAIN` exige banco com volume), latência real do `SKIP LOCKED` com
múltiplos workers concorrentes, e limites de taxa do lado do Tiny e da VSM. Não há banco nem
ambiente de carga nesta sessão. **Todas as afirmações acima são estáticas**, derivadas do código e
do schema, e cada uma cita o arquivo e a linha.

## Pontos que a auditoria confirmou SÓLIDOS

- **Reserva de fila:** `FOR UPDATE SKIP LOCKED` com fallback — correto para múltiplos workers.
- **Retry:** backoff exponencial com jitter (`RetryPolicyService`), por categoria de erro, mais
  circuit breaker.
- **Idempotência:** `idx_fila_idempotency_key`, `integration_replay_guard` com nonce, `uk_origem_pedido`.
- **Login:** CSRF validado, 2FA, regeneração de sessão, rate limit em quatro janelas com fallback
  atômico, cookie `SameSite=Strict`/`HttpOnly`/`Secure`, `use_strict_mode`.
- **Índices da fila:** `idx_fila_status_proxima_prioridade(status,proxima_tentativa,prioridade,id)`
  cobre exatamente a consulta de reserva.

## Validação desta entrega

12 portões verdes · `enterprise-tests.sh` **35 testes** (era 34) · paridade modular 134 tabelas ·
escopo multiempresa sem pendência · rotas 104 · classmap em dia.

O novo `v104_49_3_r7_capacidade_test.php` **simula 500 chamadas no mesmo minuto** contra a
superfície de webhook e confirma que nenhuma é bloqueada — a meta está fixada no código.

---

# ADENDO — as seis recomendações foram aplicadas

Data: 2026-09-14 · Todas as recomendações desta auditoria foram implementadas a pedido.

| # | Achado | Situação |
|---|---|---|
| C-02 | PK `INT` estoura em 2 a 2,7 anos | **Aplicado** — requer janela de manutenção |
| C-04 | Expurgo dentro da requisição | **Aplicado** |
| C-05 | `logs_integracao` sem escopo | **Aplicado** |
| C-06 | Fila sem retenção | **Aplicado** |
| C-07 | Sessão em arquivo | **Aplicado, opt-in** |
| C-08 | Consultas no caminho quente | **Aplicado** |

## C-02 — Chaves primárias `INT` → `BIGINT`

Migradas: `fila_integracao`, `pedidos_integracao`, `pedidos_hub`, `logs_integracao`.

A auditoria revelou algo que mudou o desenho: **10 das 12 colunas `fila_id` do schema já eram
`BIGINT`** — o schema já estava inconsistente, e as referências antecipavam a migração. Só
`pedidos_validacao.fila_id` era `INT`. Já `pedidos_hub.id` tinha as quatro colunas que a
referenciam ainda em `INT`.

Migradas junto, **antes** das chaves, para que em nenhum instante exista referência estreita
apontando para chave larga: `pedidos_validacao.fila_id`, `pedidos_validacao.pedido_hub_id`,
`pedidos_payloads.pedido_hub_id`, `pedidos_status_historico.pedido_hub_id`,
`pedidos_nfe_xml.pedido_hub_id`.

Tipo escolhido: **`BIGINT` com sinal**, seguindo a convenção dominante (97 das 100 PKs grandes do
schema) e casando com as colunas que já referenciavam. O schema não declara `FOREIGN KEY` alguma,
então nenhuma constraint quebra.

**`20260914_012_pk_bigint_capacidade.sql` EXIGE JANELA DE MANUTENÇÃO.** `ALTER TABLE ... MODIFY`
de chave primária reconstrói tabela e índices: segundos com tabela pequena, **horas** com centenas
de milhões de linhas. O cabeçalho da migration traz o procedimento: workers parados, entrada de
webhooks drenada, backup verificado antes. O rollback é a restauração do backup — reverter
`BIGINT → INT` só é seguro se nenhum id tiver passado de 2.147.483.647, e isso não há como
garantir depois de voltar a operar. **Execute com as tabelas ainda pequenas; o custo só cresce.**

## C-04 — Expurgo saiu do caminho da requisição

Removidos de `RouteRateLimiterService::enforce()`: o `DELETE` probabilístico em `rate_limit_hits`
e a chamada a `DataRetentionService::maybeRun()`. O caminho quente não faz mais manutenção de
tabela.

Criado **`workers/worker_retencao.php`** (CLI-only, com guard), que executa as regras de retenção,
limpa os baldes de rate limit vencidos e recolhe os contadores atômicos ociosos em disco. Agende:

```
0,10,20,30,40,50 * * * * php /caminho/workers/worker_retencao.php >> /var/log/hub-retencao.log 2>&1
```

## C-05 — `logs_integracao` com escopo de empresa

Coluna `empresa_id` + `idx_logs_integracao_empresa` + composto `idx_logs_empresa_data`. A tabela
entrou no catálogo do `TenantScopeService` (agora **32 tabelas**), e o verificador estático
imediatamente cobrou as **5 consultas** que a tocavam — todas corrigidas:

- `Logger::log()` — a linha nasce carimbada com a empresa ativa;
- tela de Logs e **exportação CSV** — esta com escopo **estrito**, porque incluir linhas legadas
  sem empresa num arquivo entregue a um cliente seria vazamento;
- `SHOW COLUMNS` — marcado como sonda de estrutura, não leitura de dado;
- `RetentionService` — exceção justificada: expurgo por idade da instalação inteira.

`auditoria_eventos` e `security_events` **continuam globais de propósito**: são a trilha forense
da instalação, não dados operacionais de um cliente.

## C-06 — Retenção da fila, apenas em estado terminal

`DataRetentionService::cleanupFilaConcluida()` expurga itens com status `concluido`, `processado`
ou `sucesso` mais velhos que `security.queue_done_retention_days` (padrão **30 dias**).

A regra é deliberadamente diferente das outras: **item pendente, em processamento, em retry ou em
falha definitiva nunca é apagado por idade** — seria perder trabalho. `fila_morta`,
`fila_reprocessamento_historico` e `payload_snapshots` ficam intactos: são a evidência de que algo
deu errado e existem justamente para sobreviver ao item.

## C-07 — Sessão compartilhada entre servidores *(opt-in)*

Tabela `sessoes` + `DatabaseSessionHandler` (implementa `SessionHandlerInterface` e
`SessionUpdateTimestampHandlerInterface`), ativado por `security.session_driver = 'database'`.
**O padrão continua `'file'`** — em nó único não há motivo para trocar.

Escolhido MySQL em vez de Redis deliberadamente: as regras do projeto proíbem criar dependência
desnecessária, o Hub não usa Composer e precisa rodar em hospedagem compartilhada.

O autoloader passou a subir **antes** de `session_start()` — único ponto em que o handler pode ser
registrado. Falha ao registrar **nunca derruba o bootstrap**: cai no handler de arquivo, que é o
comportamento anterior. Um Hub que não inicia porque a tabela de sessão não existe seria pior do
que um Hub que não escala horizontalmente.

## C-08 — Cache do resultado negativo no bloqueio de IP

`IpBlockService::enforce()` rodava uma consulta em **toda** requisição para descobrir que o IP não
está bloqueado — a resposta em ~100% dos casos. O resultado **negativo** passa a ser memorizado por
60 segundos no contador atômico em disco.

Só o negativo é memorizado, e a assimetria é o ponto: um IP bloqueado **jamais** é liberado por
cache, porque `block()` invalida a entrada na hora. O risco é limitado e conhecido — um bloqueio
criado por outro processo passa a valer em até 60 segundos. Falha de armazenamento não libera nem
bloqueia: cai na consulta ao banco, como antes.

## Validação deste adendo

12 portões verdes · `enterprise-tests.sh` **35 testes** · 135 tabelas em paridade modular ·
classmap 243 classes · escopo multiempresa sem pendência · 13 workers, nenhum sem guard ·
137 SQL reescritos com placeholders, parênteses e colunas/valores conferidos.

`v104_49_3_r7_capacidade_test.php` cobre os seis: tipo das PKs e das colunas que as referenciam,
ausência do expurgo no caminho quente, existência e guard do worker, retenção restrita a estado
terminal, handler de sessão opt-in com fallback, e o cache negativo com invalidação no bloqueio.

## O que continua fora de alcance desta sessão

**"Não identificado com as evidências disponíveis"** para: tempo real do `ALTER` do C-02 no banco
de vocês, comportamento sob carga, plano de execução dos índices novos e latência do
`DatabaseSessionHandler` sob concorrência. Não há banco nem ambiente de carga aqui. **Tudo acima é
estático.**

## Ordem sugerida de implantação

1. Backup completo verificado.
2. `20260914_011` (índices) — rápida, sem bloqueio relevante.
3. `20260914_013` (logs + sessões) — rápida.
4. Agendar `worker_retencao.php` no cron.
5. `20260914_012` (PK BIGINT) — **em janela, com workers parados**. Quanto antes, mais barato.
6. Só ao escalar para mais de um servidor: `session_driver = 'database'`.
