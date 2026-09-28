# Relatório de viabilidade — evolução LLM do Hub em VPS 8 GB / 4 cores

Data: 20/09/2026
Branch de origem: `main` @ `56ff788`
Natureza: **análise de viabilidade (read-only)** — nenhuma alteração de código, schema, rota ou comportamento.
Base: pergunta do responsável — é possível adicionar fallback, circuit breaker LLM,
RAG, embeddings, banco vetorial, agentes, MCP, function calling, structured output
e streaming **sem quebrar código nem rotas**, sabendo que o ambiente é uma VPS
própria de 8 GB de RAM e 4 núcleos.

## 1. Resposta curta

**Sim — tudo é adicionável sem quebrar o que funciona, por construção.** Cada
item vira serviço/tabela/rota **novos**: o `FastRouteDispatcherService` cai no
fallback para rotas desconhecidas (nunca quebra as existentes), as migrations do
Hub são idempotentes, e o subsistema LLM já é isolado e desligado por padrão. Os
fluxos Tiny/VSM não são tocados.

O risco real **não** é quebrar a integração — é **capacidade** e **encaixe** nas
restrições do projeto.

## 2. O que a VPS muda em relação à hospedagem compartilhada

O Hub foi desenhado para hospedagem compartilhada. Uma VPS própria derruba três
das quatro restrições que limitavam a evolução LLM:

| Restrição | Shared hosting | VPS 8 GB / 4 cores |
|---|---|---|
| Processo persistente | ❌ não | ✅ sim (workers, daemons, sidecars) |
| Rede externa | desligada por padrão | ✅ disponível (ainda gated por governança) |
| Sidecars (serviços auxiliares) | ❌ não | ✅ sim (embeddings, vetor, MCP em localhost) |
| Sem Composer no Hub PHP | continua | continua — **mas não atrapalha** |

O último ponto é o alicerce da estratégia: os componentes de IA rodam como
**serviços separados** (Python/Rust/Node), e o Hub PHP fala com eles pelo **mesmo
padrão de cliente HTTP endurecido** que já usa hoje — evidência: `MyOuroGraphqlService`,
`VsmService`, `TinyV3Service` (cURL com `CURLOPT_SSL_VERIFYPEER`, allowlist, sem
redirect). Nada de SDK, nada de Composer.

## 3. Alicerces que já existem no Hub (reuso, não reinvenção)

- `CircuitBreakerService` — circuit breaker pronto.
- `RetryPolicyService` / `QueueRetryPolicyEnterpriseService` — backoff/jitter.
- `HealthCheckService` / `RealtimeHealthService` / `SecurityHealthService` — estado de degradação.
- `LlmPolicyService` / `LlmGatewayService` / `LlmCostGuardService` / `LlmApprovalService` — governança (política, cofre de chave, custo, aprovação humana, redação).
- Clientes HTTP externos endurecidos (SSRF) — desenho a copiar para o cliente LLM.

Ou seja: "não existe cliente LLM real" é **escolha de projeto**, não incapacidade.

## 4. Viabilidade por recurso (VPS 8 GB / 4 cores, CPU-only)

| Recurso | Viável sem quebrar | Esforço | Observação de capacidade |
|---|---|---|---|
| Structured output (JSON Schema no servidor) | ✅ | Baixo | 100% local, trivial |
| Fallback + circuit breaker LLM | ✅ | Baixo | reusa `RetryPolicyService` + `CircuitBreakerService` |
| Function calling (ferramentas **só-leitura**) | ✅ | Médio | args validados no servidor; mutação não, por causa do guardrail |
| Streaming (SSE) | ✅ | Médio | viável com nginx + PHP-FPM e buffer desligado |
| Embeddings locais | ✅ | Médio | modelo pequeno (bge-small / multilingual-e5-small, ~120–470 MB) roda rápido em CPU |
| Banco vetorial | ✅ | Alto | Qdrant ou pgvector como sidecar (corpora pequeno/médio). **Sem tipo VECTOR nativo** no MariaDB 11.4/MySQL 8 — por isso sidecar, não coluna |
| RAG | ✅ | Alto | embeddings locais + vetor + Hub monta contexto |
| MCP (sidecar localhost) | ✅ | Alto | processo persistente agora existe |
| Agentes | ⚠️ contido | Alto | possível, mas manter **read-only + aprovação humana** (filosofia "IA não age sozinha") |
| Geração LLM **local** 7–8 B | ⚠️ cuidado | — | cabe quantizado (~5–6 GB) mas **CPU-only é lento** e brigaria por RAM com MySQL+Qdrant |

## 5. O ponto honesto sobre 8 GB / 4 cores sem GPU

Orçamento de RAM rodando tudo junto: MySQL (~1–2 GB) + PHP-FPM (~0,5 GB) +
Qdrant (~0,5–1 GB) + servidor de embeddings (~0,5 GB) deixa folga. **Mas** um LLM
local de 7–8 B (~6 GB) somado a isso **estoura os 8 GB**, e em CPU a geração
interativa fica lenta demais para o painel.

**Recomendação — modelo híbrido (o "sweet spot" da máquina):**
- **Local:** embeddings + banco vetorial + RAG (barato, rápido, privado, sem custo por chamada).
- **Geração:** API hospedada (um modelo pequeno e rápido para custo/latência; um maior para tarefas difíceis), com **modelo local pequeno (1–3 B) como fallback** quando a rede cair — exatamente o papel do `CircuitBreakerService` + fallback.

Assim não se gasta os 8 GB tentando inferir localmente, mantém-se qualidade e
velocidade de geração, e RAG/dados sensíveis ficam locais.

## 6. Ordem sugerida (aditiva, reversível, não quebra nada)

- **Fase A — risco zero, 100% local:** structured output + fallback/circuit-breaker LLM. Prepara o terreno reusando o que já existe. Nada externo.
- **Fase B — liga IA real, em homologação:** cliente LLM (desenho SSRF do MyOuro) + geração hospedada + ferramentas só-leitura. Streaming depois.
- **Fase C — RAG, só com caso de uso medido:** sidecar de embeddings + Qdrant + pipeline de ingestão. Começar com FULLTEXT do MySQL para validar utilidade antes do vetor.
- **Depois, se valer:** MCP como sidecar. Agentes só read-only + aprovação humana.

## 7. Regra que não muda

Cada fase entra **atrás da governança que o Hub já tem** (política, cofre de
chave, redação, custo, aprovação humana) e **em homologação primeiro**. Nada toca
os fluxos Tiny/VSM. Como o próprio prompt de auditoria LLM exige: benchmark e
métrica antes de trocar modelo, e rollback para cada alteração.

## 8. Fatos, e o que não foi medido

**Fatos (com evidência no repositório):** os alicerces da seção 3 existem; não há
cliente HTTP de LLM real; o banco-alvo (MariaDB 11.4 / MySQL 8) não tem tipo
vetor nativo. **Hipóteses (dependem de medição futura):** tamanhos de RAM por
sidecar e velocidade de inferência em CPU são estimativas de referência, não
medidas nesta VPS — devem ser confirmadas com carga real antes de decidir a
topologia. **Não identificado com as evidências disponíveis:** qualquer número de
throughput/latência específico desta máquina.

## 9. Ação

Análise **read-only** — nenhuma alteração de código, schema ou comportamento.
Este documento registra a viabilidade para orientar uma decisão futura; não
autoriza nem inicia implementação.
