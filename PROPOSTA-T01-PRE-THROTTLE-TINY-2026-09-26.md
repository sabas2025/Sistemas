# PROPOSTA DE IMPLEMENTAÇÃO — Pré-throttle de saída para a API Tiny (achado T-01)

**Data:** 2026-09-26 · **Natureza:** proposta de desenho (formato "Antes de alterar código"). **NADA
APLICADO.** Fica pronta para aprovar; só entra em execução **com a evidência de gargalo medida** pelo
`analise-limite-tiny.sql` num pico real (fase 10 do CLAUDE.md: não otimizar sem gargalo comprovado).

---

## Situação atual
- O Hub **reage** ao limite do Tiny: quando estoura, recebe erro e reagenda o item da fila com recuo
  de 5/15/30 min (`RetryPolicyService`, política `rate_limit`). Não há **pré-limite**.
- Desde a PR #37, o Hub **mede** o consumo (headers `x-limit-api` / `X-RateLimit-*` gravados na trilha).
- Tetos conhecidos (referência §B.2/§B.4): V2 por plano (30/60/120) e **lote de 5/min** para
  `produto.incluir/alterar`; V3 por plano e leitura/escrita (30/30 · 60/60 · 120/100 · 140/100).

## Problema
Na sincronização de **produto** (VSM→Tiny), `produto.incluir/alterar` conta como **lote (5/min)**. Se o
worker de produto processar > 5/min, o Tiny recusa, o Hub recua 15 min e a fila **represa**. Hoje o Hub
só descobre isso **estourando** — não se autorregula antes.

## Causa raiz
O controle de limite é **reativo** e mora só no caminho de **entrada** (`RateLimitService` é fachada de
rate limit de **requisições recebidas**). Não há um freio no caminho de **saída** (chamadas do Hub AO
Tiny), e o de saída tem regra diferente: **nunca rejeitar** uma chamada legítima — só **pausar/adiar**.

## Arquivos afetados (previstos)
- `app/Services/RateLimitService.php` — acrescentar superfícies de **saída** ao catálogo `POLICIES`
  (não altera as de entrada existentes).
- `app/Services/TinyV2Service.php` / `TinyV3Service.php` — consultar o freio **antes** de chamar; e
  alimentar o teto real lido do header (via `limitOverride`).
- **Worker de produto** (o consumidor de `produto_vsm_*` em `ApiController`/fila) — ao ver o balde
  cheio, **adiar** o item para o próximo ciclo em vez de chamar.
- `config/config.example.php` + coluna em `configuracoes_integracao` — o **plano/teto** como fallback.

## Banco afetado
- Uma coluna nova opcional em `configuracoes_integracao` (ex.: `tiny_plano` ou tetos explícitos),
  idempotente e reversível — **só** se decidirmos guardar o plano no painel (a sua ideia). O freio
  funciona sem ela, usando o header como fonte primária.
- Nenhuma migração destrutiva. `AtomicRateCounterService` já tem sua tabela/arquivo de contadores.

## APIs afetadas
- Tiny V2 e V3 (saída). VSM **não** — este freio é só para o Tiny.

## Risco
- **Médio-baixo**, com uma regra inegociável: o freio de saída **PAUSA/ADIA**, nunca **bloqueia** uma
  chamada legítima (usa `peek()` para ler o balde sem rejeitar). Um freio mal calibrado só **atrasa**
  sincronização — nunca perde pedido/produto (a fila reprocessa). Calibração errada para menos =
  throughput menor que o possível; para mais = volta a estourar (e o recuo reativo continua de rede).

## Plano de implementação (incremental, cada passo reversível)
1. **Catálogo de superfícies de SAÍDA** em `RateLimitService::POLICIES` (só acréscimo):
   - `tiny_v2_out` — janela 60, default = teto do plano V2, `on_storage_failure=open` (nunca travar
     saída por falha de contador).
   - `tiny_v2_batch` — janela 60, **default 5** (`produto.incluir/alterar`).
   - `tiny_v3_read` / `tiny_v3_write` — janela 60, defaults por plano (30/60/120/140 e 30/60/100/100).
   O teto real vem do **header** medido (`limitOverride`), com o plano como fallback.
2. **Consulta antes da chamada** nos clientes: `peek()` da superfície certa; se dentro do orçamento,
   segue e `hit()` marca o uso; se **estourado**, o cliente devolve um sinal "adiar" (não erro).
3. **Worker de produto adia** ao receber "adiar": reagenda o item para o próximo ciclo (segundos),
   sem contar como falha nem consumir tentativa — diferente do recuo de 15 min, que continua só para
   o estouro real.
4. **(Opcional, a sua ideia) plano no painel:** campo em Configurações para o operador informar o
   plano; vira o **fallback** do teto quando o header não veio. O header continua **autoritativo**.
5. **Observabilidade:** expor no `api/status`/painel o "orçamento restante" que já é medido, para o
   operador ver o freio atuando.

## Plano de teste (contra MariaDB real, como nas outras fases)
- Unit: as novas políticas existem e recusam superfície sem `on_storage_failure` (invariante do B-01).
- Runtime: simular 10 chamadas de lote no mesmo minuto → confirmar que da 6ª em diante o worker
  **adia** (não chama o Tiny, não gera erro, reprocessa no ciclo seguinte) e que, sob o teto, tudo
  passa sem atraso. Medir que **nenhuma** chamada legítima é bloqueada.
- Portões: php-lint, classmap, enterprise (com teste novo que reprova sobre o código antigo).

## Rollback
- Remover as superfícies de saída do `POLICIES` e a consulta nos clientes → volta ao comportamento
  reativo atual (que permanece intacto como rede de segurança). A coluna opcional de plano é
  reversível por migração idempotente.

## Gatilho de execução
Esta proposta **só é executada** quando o `analise-limite-tiny.sql` mostrar, num pico real:
- consulta (2) com algum minuto > **5** chamadas de lote, **ou**
- consulta (1) encostando no teto do plano (consulta 3/4).
Se a medição mostrar folga, registra-se "medido, sem gargalo" e **nada é aplicado** — a fase 10 manda.

## Resumo em uma linha
Freio de **saída** que **pausa o worker** (nunca bloqueia chamada), usando a fachada única já
existente e o teto **medido** pelo próprio Hub; o "plano no painel" entra como fallback — e tudo só
liga com a evidência do pico.
