# Auditoria final e validação — V104.49.3-R7

Data: 2026-09-14 · Encerramento das seis rodadas de auditoria desta linha de trabalho

---

## 1. Veredito

**Todos os 34 achados das seis rodadas estão aplicados e verificados por evidência direta.**
**Nenhum defeito novo foi encontrado nesta auditoria final.**

| Rodada | Origem | Achados | Verificados |
|---|---|---|---|
| **A** | Reauditoria externa | A-01 … A-13 | 12/12 |
| **B** | Auto-auditoria da R6 | B-01, B-03, B-05, B-06 | 6/6 |
| **F** | Auditoria pelo `PROMPT_-_HUB.md` | F-01, F-02 | 5/5 |
| **C** | Capacidade (100 clientes / 500 ped/min) | C-01 … C-08 | 19/19 |
| **D** | Validação independente | D-01, D-02 | 4/4 |
| **E** | Vazão da fila | E-01 … E-05 | 15/15 |
| — | Invariantes gerais | catálogo, índices, globais | 3/3 |

**61 checagens de evidência direta: 61 OK, 0 falhas.** Cada uma confere o fato no arquivo, não a
asserção de um teste escrito por quem fez a correção.

**12 portões de CI: 0 falhas** (com código de saída real conferido) · **36 testes** ·
135 tabelas em paridade modular · 137 SQL reescritos íntegros · 13 workers, nenhum sem guard.

---

## 2. Fases que esta rodada final cobriu

### Fase 5 — Tiny V2 × V3: **correto, sem achado**

A regra do projeto diz *"nunca assumir que V2 e V3 possuem o mesmo comportamento"*. A arquitetura
cumpre: serviços distintos (`TinyV2Service`, `TinyV3Service`), catálogos de erro separados
(`TinyV2ErrorCatalogService`), homologação separada, e `TinyFactory` escolhe por versão com
**fallback seguro auditado** — se V3 estiver selecionado mas não marcado como operacional, cai para
V2 e registra `TINY_V3_NOT_OPERATIONAL`. É mais do que a regra exige.

### Fase 9 — ações destrutivas: **correto, sem achado**

`backup-excluir`, `backup-restaurar`, `backup-importar` e `migracao-aplicar` exigem CSRF **e** frase
de confirmação digitada. Rodadas anteriores já haviam confirmado 0 formulários POST sem CSRF.

### Fase 1 — código órfão: **5 classes, removidas**

Cinco classes não são referenciadas em lugar nenhum de `app/`, `public/`, `workers/`, `views/`,
`tests/` ou `scripts/` — aparecem apenas no classmap, que indexa todo arquivo de classe:

`ConnectorOperationalCheckService` · `DemoResetSchedulerService` · `GenericPlannedConnector` ·
`ModularDatabaseAuditService` · `ReleaseQualityGateService`

Verifiquei também se instanciação dinâmica as alcançaria: os dois pontos de `new $controller()` no
dispatcher usam catálogos fechados de controller, não estas classes.

**Removidas em 2026-09-14, com autorização explícita do responsável do produto.** As três tabelas
que duas delas escreviam (`connector_operational_checks`, `comercial_demo_reset_logs`,
`system_release_checks`) **permanecem no schema** — só as classes saíram. Classmap: 244 → 239
classes; 12 portões verdes; 0 órfãs restantes. Detalhes, evidência e rollback em
`RELATORIO-REMOCAO-CLASSES-ORFAS-2026-09-14.md`. **Status: corrigido e validado.**

### Fase 10 — N+1: **não identificado com as evidências disponíveis**

Uma varredura por consulta dentro de laço devolveu 66 candidatos. **Amostrei antes de reportar**, e
a maioria é falso positivo da própria heurística: laços que montam parâmetros ou criptografam
campos e depois executam **uma** consulta (`DashboardController:989` e `:766`, por exemplo).

Encontrei **um** laço de escrita real — `ApiController:709`, um `INSERT` por item de estoque dentro
do processamento da baixa. É limitado pela quantidade de itens do pedido e não há evidência de que
seja gargalo.

A regra do projeto proíbe otimizar sem evidência de gargalo, e **não tenho profiling com volume**.
Reportar "66 problemas de N+1" seria inventar. Fica como ponto a medir, não como achado.

---

## 3. Padrão que atravessa as seis rodadas

Vale registrar, porque é o aprendizado mais útil deste trabalho: **quase todo achado grave foi um
controle que existia e não funcionava**, não um controle ausente.

- A allowlist de IP existia no config e **nada a lia** (A-05).
- Os limitadores existiam e **falhavam abertos em silêncio** (A-06, A-07, B-01).
- A checagem de proteção dos workers procurava **um método que nunca existiu** — vermelha para
  sempre, anulando o alarme verdadeiro (F-01).
- A auditoria de tenant exigia **uma coluna removida por decisão de projeto** (F-02).
- As colunas `empresa_id` existiam e **nenhuma consulta filtrava por elas** (C-01/melhoria 1).
- O `worker_fila.php` tem o nome óbvio e drena **1 item por minuto** (E-04).

Por isso os portões de CI importam mais que as correções: **quatro deles pegaram defeitos em código
escrito depois que foram criados**, incluindo código meu. O `tenant-scope-check`, criado no C-05,
reprovou o `QueueBackpressureService` escrito três rodadas depois.

---

## 4. O que NÃO foi validado — leia antes de produção

**Todas as seis rodadas foram estáticas.** Não houve MySQL, Docker nem ambiente de carga em
nenhum momento. Isto é o limite honesto deste trabalho:

| Não validado | Por quê |
|---|---|
| Comportamento sob 500 pedidos/min reais | Sem ambiente de carga |
| Plano de execução (`EXPLAIN`) dos índices novos | Sem banco com volume |
| Tempo real do `ALTER` da migration `012` | Sem banco com volume |
| Latência da VSM e do Tiny — logo, quantos workers | Sem credenciais nem tráfego |
| Ciclo OAuth Tiny V3 completo | Depende de credenciais reais |
| Isolamento multiempresa com duas empresas | **O mais importante da lista** |
| Contratos VSM (pedidos-integradora / pedidos-loja) | Nenhum OpenAPI foi empacotado |

**12 portões verdes não significam "testado em produção".** Os portões de runtime
MySQL 8 / MariaDB 11.4 existem no workflow e são obrigatórios pelo job `gate` — **execute-os**.

---

## 5. Checklist de implantação

1. **Backup completo verificado** (painel > Backups > Gerar, conferir SHA-256).
2. Migration `20260914_011` — índices de capacidade. Rápida.
3. Migration `20260914_013` — `logs_integracao` com escopo + tabela `sessoes`. Rápida.
4. Agendar `worker_retencao.php`: `0,10,20,30,40,50 * * * *`. **Obrigatório** — sem ele as tabelas
   crescem sem teto.
5. Agendar `worker_selftest.php` a cada 5 min — é o que amostra a contrapressão da fila.
6. Agendar **N processos** de `worker_enterprise.php 500 55` em paralelo. Comece com 4 e ajuste
   pela taxa real (campo `processed` na saída). **`worker_fila.php` não serve para carga.**
7. Migration `20260914_012` — **PK `INT` → `BIGINT`, em janela de manutenção**, com workers parados
   e webhooks drenados. Quanto antes, mais barata: hoje são segundos, com volume são horas.
8. `php scripts/rotate-secrets.php --audit` e rotacionar o que ele apontar.
9. Definir `security.canonical_host`, `trusted_proxies` e o redirecionamento HTTPS no virtual host.
10. Configurar `security.admin_ip_allowlist` (agora efetiva) ou registrar a decisão de deixá-la vazia.
11. Marcar `Hub CI / gate` como *required* na proteção de branch.
12. **Validar o isolamento multiempresa contra banco real com duas empresas** antes de multi-cliente.
13. `session_driver='database'` **apenas** ao passar de um servidor.

---

## 6. Pendências que continuam abertas

- **Validação do isolamento com duas empresas** — seção 5 do `SECURITY.md`. Sem isso, o isolamento
  é uma promessa verificada só por leitura de código.
- **Ligar `webhook_signature_require_v2`** quando a VSM migrar. Enquanto a v1 for aceita, método e
  rota ficam fora da assinatura; cada aceite gera evento e controle degradado visível.
- **Contrato OpenAPI da VSM** — sem ele não há como avaliar envio em lote nem verificar o catálogo
  de endpoints. Importe quando disponível (`vsm-openapi-check.php` valida).
- ~~**5 classes órfãs** (seção 2)~~ — **removidas**, ver `RELATORIO-REMOCAO-CLASSES-ORFAS-2026-09-14.md`.
- **Medir N+1 com volume** (seção 2) — não otimizar sem evidência.

---

## 7. Integridade do artefato

Manifesto `CHECKSUMS-SHA256.txt` regenerado por último, sobre a árvore congelada, após remover os
artefatos de execução local (`storage/cache/security`, `storage/audit-*`). Verificação a partir do
pacote **extraído**, não da árvore de trabalho.
