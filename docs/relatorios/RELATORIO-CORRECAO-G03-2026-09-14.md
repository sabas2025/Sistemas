# Relatório — Correção G-03 aplicada (V104.49.3-R7)

**Data:** 2026-09-14 · **Release:** V104.49.3-R7 (correção de resiliência; sem mudança de contrato)
**Origem:** `RELATORIO-AUDITORIA-FINAL-COMPLETA-2026-09-14.md`, achado G-03 · **Gravidade:** Média
**Autorização:** explícita do responsável do produto.

---

## 1. Antes de alterar

**Situação atual.** O reagendamento de item com falha (`proxima_tentativa`) era **determinístico**.

**O achado original apontava um ponto. Ao implementar, encontrei quatro.** Corrigir só o primeiro
teria deixado três filas com o mesmo defeito e um relatório falso.

| # | Onde | Fila | Atraso |
|---|---|---|---|
| 1 | `QueueService::marcarResultado()` | `fila_integracao` | `delay_minutes` da política enterprise |
| 2 | `EnterpriseIdempotencyGuardService::markGuardFailureQueueItem()` | `fila_integracao` | **300s fixos** |
| 3 | `EstoqueEnterpriseService::proximaTentativa()` | `fila_estoque` | escada `retry_minutos` (5,15,30,60) |
| 4 | `FiscalEnterpriseService::proximaTentativa()` | `fila_fiscal` | escada `retry_minutos` (5,15,30,60) |

Os quatro faziam `date('Y-m-d H:i:s', time() + N*60)`.

**O ponto 2 era o pior caso.** Falha do guard de idempotência costuma ser sistêmica (armazenamento
indisponível): **todos** os itens em voo recebiam exatamente 300 s e voltavam juntos, no mesmo
segundo, cinco minutos depois.

**Problema.** Quando a VSM ou o Tiny falha rápido (503 imediato), muitos itens terminam no mesmo
segundo, recebem o mesmo atraso e voltam a ficar elegíveis **no mesmo segundo**. O provedor recebe
uma rajada sincronizada em vez de uma rampa.

**Causa raiz.** O jitter existia só no retry **dentro da requisição**
(`RetryPolicyService::sleep()`), não no reagendamento da fila.

**Atenuante, declarado sem maquiagem.** Se as chamadas demoram (timeout de 30 s), os términos já se
espalham naturalmente e o efeito some. O problema aparece na falha rápida em lote. E a base nunca
foi o gargalo: a reivindicação é serial (`FOR UPDATE SKIP LOCKED ... LIMIT 1`). O que se corrige
aqui é a pressão sobre o **provedor**, não sobre o MySQL.

**Arquivos afetados.** `RetryPolicyService` (helper novo) e os quatro pontos acima.

**Banco afetado.** Nenhum. Nenhum DDL, nenhuma migration, nenhuma coluna nova. Só muda o **valor**
gravado em `proxima_tentativa`, no mesmo formato `Y-m-d H:i:s`.

**APIs afetadas.** Nenhuma.

**Risco.** Baixo. O item volta alguns segundos mais tarde do que voltaria.

**Rollback.** Restaurar os cinco arquivos do pacote anterior
(SHA-256 `a2997a5b2c5baba26b410584f9f97453e1d15283a4fe6b51ef8b1ff48946a393`) e remover
`tests/enterprise/v104_49_3_r7_backoff_jitter_test.php`. Sem migration, sem dado.

---

## 2. Decisão de desenho: por que **aditivo**, e por que em `RetryPolicyService`

**O jitter é aditivo, nunca simétrico.** A fórmula clássica de *full jitter*
(`random(0, atraso)`) **reduziria** o atraso — e isso seria pior que não ter jitter nenhum: a
política de `rate_limit` recua 15/30/45 min justamente para **parar de bater** no provedor que já
nos limitou. Antecipar desfaria a proteção. Por isso: `atraso da política + random(0, teto)`.
A escada configurada em `retry_minutos` continua valendo **integralmente**.

**Teto = 10% do atraso, com piso de 30 s e limite de 300 s.**

| Atraso da política | Faixa de jitter | Efeito em 500 itens que falham juntos |
|---|---|---|
| 2 min | 0–30 s | espalha em 30 s |
| 5 min | 0–30 s | **31 segundos distintos**, pico de 26 itens/s |
| 15 min | 0–90 s | **91 segundos distintos**, pico de 12 itens/s |
| 60 min | 0–300 s | espalha em 5 min |
| 180 min | 0–300 s | espalha em 5 min (não estoura o teto da política) |

**Fica em `RetryPolicyService`, não em `QueueService`.** É onde já mora a matemática de backoff
(`attempts()`, `baseDelayMs()`, `sleep()`) e é uma classe folha — `EstoqueEnterpriseService` e
`FiscalEnterpriseService` passam a depender dela sem acoplamento a `QueueService`. Uma
implementação, um lugar para ajustar, um lugar para testar. **Não foi criado serviço paralelo.**

---

## 3. Depois de alterar

### Arquivos modificados

| Arquivo | Alteração |
|---|---|
| `app/Services/RetryPolicyService.php` | **novos** `jitterSegundos(int): int` e `proximaTentativaEm(int $minutos): string` |
| `app/Services/QueueService.php` | `fila_integracao` passa a agendar por `proximaTentativaEm()` |
| `app/Services/EnterpriseIdempotencyGuardService.php` | os 300 s fixos passam a ter jitter (mesmo atraso base) |
| `app/Services/EstoqueEnterpriseService.php` | `proximaTentativa()` — **assinatura preservada** |
| `app/Services/FiscalEnterpriseService.php` | `proximaTentativa()` — **assinatura preservada** |
| `tests/enterprise/v104_49_3_r7_backoff_jitter_test.php` | **novo** — 21 checagens |
| `CHECKSUMS-SHA256.txt` | regenerado por último |
| `RELATORIO-CORRECAO-G03-2026-09-14.md` | este documento |

Nenhuma assinatura pública foi alterada: as duas `proximaTentativa(int $tentativa): ?string`
continuam idênticas, e `proximaTentativaEm(): string` satisfaz `?string`.

### Migrations
Nenhuma.

### Compatibilidade
PHP 8.x · MySQL 8 · MariaDB 11.4 · hospedagem compartilhada · Tiny V2/V3 · VSM — **inalteradas.**
Instalação nova e atualização: inalteradas. O valor gravado continua `Y-m-d H:i:s`, e o índice
`idx_fila_status_proxima_prioridade(status, proxima_tentativa, prioridade, id)` segue servindo a
consulta quente.

### Testes realizados e resultados

| Verificação | Resultado |
|---|---|
| Limites do jitter (6 atrasos × 3.000 sorteios) | dentro de `[0..teto]` em todos; faixa usada por inteiro |
| `base <= 0` | devolve 0, nunca negativo |
| **9.000 agendamentos: algum antes do que a política manda?** | **0 violações** — o jitter é comprovadamente aditivo |
| Acréscimo máximo observado | 300 s (o teto) |
| **Dispersão de 500 itens falhando no mesmo segundo** | 5 min: **31 segundos distintos**, pico 26 itens/s · 15 min: **91 distintos**, pico 12 itens/s — antes: **500 no mesmo segundo** |
| Formato `Y-m-d H:i:s` | preservado |
| Assinaturas de `proximaTentativa` | preservadas nas duas classes |
| Nenhum ponto determinístico restante | 4 de 4 convertidos, 0 `date('Y-m-d H:i:s', time()+…)` solto |
| **Teste reverte-e-quebra** | com os 4 pontos revertidos, o teste falha em **8** asserções e sai 1; restaurado, sai 0; arquivos conferidos byte a byte |
| Novo teste de regressão | **21 checagens OK** |
| **12 portões de CI** | **0 falha** |
| `enterprise-tests.sh` | **38 testes** (era 37) |
| Varredura independente | 61 OK, 0 falha |
| Reescrita SQL multiempresa | 137 literais OK |
| Chamadas `Classe::metodo()` | **3361 resolvidas, 0 inexistente** |
| G-01 e G-02 seguem aplicados | validação dirigida OK · prova E2E do `INSERT` OK |

### Riscos residuais — declarados

1. **O ganho não foi medido em produção.** Não há ambiente de carga nem provedor real. O que está
   provado é a **dispersão do agendamento** (31 e 91 segundos distintos, medidos), não a redução de
   erro na VSM ou no Tiny. A afirmação honesta é: os itens deixaram de voltar todos no mesmo
   segundo. Se isso melhora a taxa de sucesso, só a produção dirá.
2. **`random_int()` lança se não houver fonte de entropia.** É o mesmo uso já existente em
   `RetryPolicyService::sleep()` e em `RequestContext::id()` — este último roda no início de toda
   requisição, então um sistema nessa condição já teria falhado antes. Não foi adicionado guard
   para não divergir do padrão do projeto.
3. **O teto de 300 s é um julgamento, não uma medição.** Escolhido para espalhar sem distorcer a
   política. Se a produção mostrar que 500 itens em 30 s ainda é rajada demais para a VSM, o número
   está em um lugar só (`RetryPolicyService::jitterSegundos`).
4. **`fila_estoque` e `fila_fiscal` não foram exercitadas com worker real** — a validação foi do
   valor agendado, não do ciclo completo do worker.

### Implantação
Substituir os cinco arquivos e adicionar o teste. Não parar worker, não parar webhook, não rodar
migration. Itens já agendados com `proxima_tentativa` antiga continuam válidos — nada a migrar.

### Rollback
Seção 1.

**Status: corrigido e validado** (validação estática mais medição de distribuição em PHP CLI, nos
limites declarados acima).
