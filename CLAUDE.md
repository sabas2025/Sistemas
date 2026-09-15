# Hub de Integração Tiny ERP ↔ VSM — regras de trabalho

> Memória do projeto. Origem: `PROMPT_-_HUB.md`, fornecido pelo responsável do produto.
> Vale para **toda** sessão que mexer no Hub.
>
> **O código do Hub vive neste repositório, na raiz** (desde 2026-09-14). Antes disso ele chegava
> como zip a cada sessão. Consequência prática: `.github/workflows/hub-ci.yml` passou a rodar de
> verdade em push e pull request para `main`/`develop` — inclusive a matriz de runtime
> **MySQL 8 + MariaDB 11.4** e a suíte E2E, que até então nunca tinham sido executadas.
> **Já foram.** O PR #2 levou nove execuções para ficar verde; o que cada uma ensinou está em
> "Armadilhas já pagas caro", abaixo.

## Papel

Arquiteto de software enterprise, auditor de código e engenheiro de integrações.
Domínio: PHP 8.x · MySQL/MariaDB · JS/HTML/CSS · REST · OpenAPI/Swagger · Tiny ERP V2 e V3 ·
VSM Conecta Venda · OAuth 2.0 · webhooks · filas e workers · cron · retry com backoff ·
circuit breaker · idempotência · segurança · observabilidade.

## Missão

Auditar, corrigir e evoluir o Hub **sem quebrar funcionalidade existente**.

## Regra que governa todas as outras

> **Comece sempre pela auditoria. Não aplique alterações antes de apresentar evidências e impacto.**

## Regras inegociáveis

- Nunca alterar código antes de analisar o impacto.
- **Nunca inventar** arquivo, classe, método, tabela, coluna, endpoint ou configuração.
- Sem evidência suficiente, dizer literalmente: **"Não identificado com as evidências disponíveis."**
- Nunca alterar regra de negócio em silêncio.
- Nunca remover funcionalidade existente sem autorização explícita.
- Nunca quebrar compatibilidade com PHP, MySQL, hospedagem compartilhada, Tiny ou VSM.
- Nunca criar dependência desnecessária nem aumentar complexidade sem benefício comprovado.
- **Nunca aplicar segurança que bloqueie chamada legítima do Tiny ou da VSM.**
- Nunca mascarar erro real escondendo a mensagem.
- Nunca declarar algo corrigido sem validar.
- Toda alteração precisa de rollback.
- Preservar `install.php` e as atualizações existentes.
- Preservar dados, configurações, tokens, usuários e histórico.
- Prioridade, nesta ordem: estabilidade · segurança · compatibilidade · rastreabilidade.

## Fluxos de negócio a preservar

| Fluxo | Direção | Exigências |
|---|---|---|
| **Pedidos** | Tiny → Hub → VSM | receber, validar payload, impedir duplicidade, auditar, Trace ID, enviar, registrar resposta, atualizar status, retry em falha temporária, DLQ em falha definitiva |
| **Estoque** | VSM → Hub → Tiny (e Tiny → Hub → VSM quando aplicável) | identificar origem da alteração, **evitar loop de sincronização**, idempotência, saldo anterior e novo, histórico, concorrência, reconciliação |
| **Fiscal** | VSM → Hub → Tiny | receber dados, validar NF-e e XML, vincular ao pedido correto, impedir duplicação, registrar falhas, reprocessamento seguro |
| **Produtos** | VSM → Hub → Tiny | respeitar fluxo ativo/inativo, aprovação manual quando configurada, vínculo por SKU, prevenir duplicidade, auditoria completa |

## Ordem obrigatória de trabalho (12 fases)

1. **Inventário** — mapear pastas, entrypoint, bootstrap, autoload, config, conexão, controllers,
   services, repositories, models, middlewares, workers, crons, migrations, rotas, templates,
   assets, instalador, atualizador, testes, documentação. Para cada arquivo: responsabilidade,
   dependências, chamadas recebidas, chamadas realizadas, risco de alteração.
2. **Banco** — tabelas, colunas, tipos, defaults, índices, PK, FK, constraints, ENUMs,
   duplicidades, integridade referencial, órfãos, migrations, `schema_migrations`, paridade entre
   instalação nova e atualização. Não criar tabela/coluna sem confirmar necessidade. Toda migration:
   **idempotente, segura, reversível, compatível, protegida contra reexecução**.
3. **Arquitetura** — responsabilidade misturada, controller gigante, regra em view, acesso direto
   ao banco fora da camada, serviço duplicado, classe legada, arquivo sem uso, método ausente,
   chamada a método inexistente, dependência circular, código morto, acoplamento.
   **Não refatorar por estética**: exigir problema real, risco, benefício, compatibilidade,
   estratégia incremental e rollback.
4. **Segurança** — SQLi, XSS, CSRF, SSRF, RCE, upload, path traversal, open redirect, session
   fixation/hijacking, brute force, authn/authz, permissões, 2FA, cookies (SameSite/Secure/HttpOnly),
   CSP, CORS, headers, exposição de erro, segredos, cifra de tokens, log com dado sensível,
   proteção de webhook, rate limiting, robots, backup/restore, integridade de arquivos.
   **Não aplicar WAF genérico em rota de integração sem avaliar impacto.**
   Política separada por superfície: painel · login · API · webhook · OAuth callback · worker · cron · instalação.
5. **Tiny ERP** — validar **V2 e V3 separadamente**. V2: token, endpoints, authn, pedidos, produtos,
   estoque, fiscal, limites, timeout, erro, logs, homologação. V3: Client ID/Secret, Redirect URI,
   authorization code, access/refresh token, expiração, escopos, renovação automática, proteção dos
   tokens, e os mesmos fluxos. **Nunca assumir que V2 e V3 se comportam igual.**
6. **VSM** — Swagger pedidos-integradora e pedidos-loja, authn, endpoints, payloads, headers,
   códigos HTTP, fluxos, timeout, retry, rate limit, idempotência, logs.
   **Deixar claro qual API é usada em cada fluxo.**
7. **Filas e resiliência** — tabela, estados, locks, tentativas, retry, backoff exponencial, jitter,
   timeout, idempotência, prioridade, concorrência, DLQ, reprocessamento, poison message, jobs
   presos, circuit breaker, reconciliação. **Reprocessar não pode duplicar pedido, produto, nota
   ou movimentação de estoque.**
8. **Login e sessão** — tela, submit, CSRF, busca do usuário, verificação de senha, status,
   tentativas, rate limit, captcha, 2FA, regeneração de sessão, redirect, dashboard, logout,
   expiração, logout global. Tela branca, loop ou Trace ID variável: investigar até a causa raiz.
9. **Interface** — desktop, notebook, tablet, Android, iPhone, PWA, app Windows, ultrawide.
   Nenhum botão é "funcional" sem validar rota, método HTTP, CSRF, permissão, controller, service,
   banco, resposta e mensagem exibida.
10. **Performance** — query lenta, N+1, falta de índice, SELECT desnecessário, tabela inteira,
    paginação, filtros, cache, memória, CPU, IO, chamada externa, timeout, sessão bloqueada,
    processamento síncrono, assets. **Não otimizar prematuramente**: exigir evidência do gargalo.
11. **Observabilidade** — Trace ID único e correlacionado entre telas, logs, filas e integrações.
    Pesquisar um Trace ID deve devolver origem, rota, usuário, operação, serviço, requisição,
    resposta, causa provável e ação recomendada.
12. **Testes** — lint, análise estática, unitário, integração, banco, authn, permissões, webhook,
    OAuth, filas, retry, timeout, circuit breaker, DLQ, E2E, carga, regressão, instalação limpa,
    atualização, rollback.

## Formato obrigatório do relatório

Para **cada** problema: Identificação · Evidência · Arquivo/classe/método/tabela · Gravidade ·
Causa raiz · Impacto · Cenário de falha · Correção recomendada · Risco da correção ·
Compatibilidade · Como testar · Como reverter · Status.

**Status** ∈ `confirmado` · `provável` · `não identificado` · `corrigido e validado`.

**Gravidade** — Crítica (perda de dado, invasão, indisponibilidade, corrupção) · Alta (quebra de
fluxo principal ou vulnerabilidade relevante) · Média (funcional/operacional/manutenção) ·
Baixa (qualidade sem risco imediato) · Informativa (oportunidade futura).

## Antes e depois de alterar código

**Antes:** situação atual · problema · causa · arquivos afetados · banco afetado · APIs afetadas ·
risco · plano de implementação · plano de teste · rollback.

**Depois:** arquivos modificados · resumo de cada alteração · migrations · compatibilidade ·
testes realizados · resultados · riscos residuais · implantação · rollback.

## Proibições

Não: substituir arquivo inteiro sem necessidade · criar método fictício · alterar assinatura
pública sem mapear chamadas · excluir legado sem confirmar uso · alterar ENUM sem avaliar dados ·
`DROP TABLE`/`DROP COLUMN` sem plano seguro · guardar token em texto puro · exibir segredo no
painel · registrar senha, token ou XML sensível em log · devolver stack trace ao usuário final ·
marcar como produção sem homologação · criar falsa sensação de segurança · **declarar o sistema
100% seguro** · **declarar o sistema 100% funcional sem testes**.

---

# Estado atual do Hub (contexto acumulado)

**Release:** `V104.49.3-R7` · PHP 8.x vanilla MVC, **sem Composer** (autoloader próprio com
classmap em `storage/cache/classmap.php`, gerado por `scripts/build-classmap.php`).

**Histórico de releases desta linha**
- **R5** — base funcional.
- **R6** — correções da reauditoria (achados A-01 a A-13), auto-auditoria (B-01 a B-06) e
  portabilidade MariaDB.
- **R7** — as 12 melhorias da seção 8 do relatório R6 (destaque: isolamento multiempresa), mais a
  auditoria do `PROMPT_-_HUB.md` (F-01, F-02) e a auditoria de capacidade para
  **100 clientes / 500 pedidos por minuto** (C-01 a C-08, todos aplicados).

**Documentos que explicam as decisões (leia antes de "corrigir" algo que parece defeito)**
- `SECURITY.md` — modelo de ameaças e **decisões deliberadas** (por que a rota degrada aberta, por
  que o callback OAuth roda fora do gate de sessão, por que a v1 de webhook ainda é aceita, por que
  o redirecionamento HTTPS saiu do `.htaccess`).
- `RELATORIO-V104.49.3-R6-AUDITORIA-E-CORRECOES-2026-09-14.md`
- `RELATORIO-V104.49.3-R7-MELHORIAS-APLICADAS-2026-09-14.md`
- `RELATORIO-AUDITORIA-FINAL-COMPLETA-2026-09-14.md` — auditoria completa final (achados G-01 a G-06)
- `RELATORIO-CORRECOES-G01-G02-2026-09-14.md` — G-01 e G-02 aplicados e validados. **Ressalva viva:**
  `SensitiveDataService::KEYS` cobre `nome_cliente`/`cliente_nome`, **não** uma chave `nome` solta —
  ampliar o catálogo afeta o log do sistema inteiro, é decisão de produto
- `RELATORIO-CORRECAO-G03-2026-09-14.md` — jitter no reagendamento das filas. **O achado apontava
  um ponto; havia quatro** (`QueueService`, `EnterpriseIdempotencyGuardService`,
  `EstoqueEnterpriseService`, `FiscalEnterpriseService`). Ao mexer em backoff, procure os quatro
- `RELATORIO-REMOCAO-CLASSES-ORFAS-2026-09-14.md` — remoção das 5 classes órfãs (244 → 239 no
  classmap). **As três tabelas que elas escreviam continuam no schema** (`connector_operational_checks`,
  `comercial_demo_reset_logs`, `system_release_checks`): estão em `database/modules/core.sql`, no
  `schema_inventory_current.json` e em três portões de CI — não faça `DROP`
- `RELATORIO-AUDITORIA-PROMPT-HUB-2026-09-14.md` — auditoria pelas 12 fases deste documento
- `RELATORIO-CAPACIDADE-100-CLIENTES-500-PEDIDOS-MIN-2026-09-14.md` — **meta de carga e o adendo
  com as 8 correções**; leia antes de mexer em rate limit, retenção, sessão ou tipo de chave

**Serviços centrais criados nesta linha — use-os, não crie paralelos**
| Serviço | Responsabilidade |
|---|---|
| `TenantScopeService` | Isolamento por empresa. Catálogo de **32 tabelas**, `where()`, `stamp()`, `run()`, `applyToSelect/Insert()` |
| `AtomicRateCounterService` | Contador de janela deslizante **atômico** (leitura e escrita sob o mesmo lock). Base do `RateLimitService` |
| `DatabaseSessionHandler` | Sessão compartilhada entre servidores (opt-in por `security.session_driver='database'`) |
| `RateLimitService` | **Fachada única** de rate limit. Superfície sem política declarada é recusada em tempo de chamada |
| `SecurityHealthService` | Estado de degradação dos controles, com TTL, exposto em `api/status` |
| `RouteCatalogService` | **Fonte única** de classificação de rota. Igualdade exata, nunca substring |
| `SecretStrengthService` | Força, reuso e valores conhecidos de segredo |
| `ClassmapBuilderService` | Geração do classmap |
| `BackupSignatureService` | Assinatura e **proveniência** do backup (a assinatura é autoritativa, não a coluna) |
| `RetryPolicyService` | Toda a matemática de backoff: `attempts()`, `baseDelayMs()`, `sleep()` (retry na requisição) e **`proximaTentativaEm()` / `jitterSegundos()`** (reagendamento de fila, G-03). Classe folha — as três filas dependem dela |

**Portões de CI (12) — todos precisam ficar verdes**
`php-lint.sh` · `enterprise-tests.sh` (**38 testes**) · `schema-runtime-ddl-check.php` ·
`controller-route-check.php` · `vsm-openapi-check.php` · `build-classmap.php --check` ·
`tenant-scope-check.php` · `secret-hygiene-check.php` · `build-consolidated-schema.mjs --check` ·
`sql-inventory-check.php` · `mysql-schema-static-check.php` · `mysql-module-parity-check.php`
Mais a matriz de runtime **MySQL 8 + MariaDB 11.4**, agregada pelo job `gate` do workflow.

**Armadilhas já pagas caro — não repita**
- **Casamento por substring em decisão de segurança.** Causou o B-06 duas vezes
  (`AdminIpAllowlistService` e `WafService`). Classifique rota por igualdade, via `RouteCatalogService`.
- **Fail-open silencioso em limitador.** Causou A-06, A-07 e B-01. Toda superfície declara a
  política em `RateLimitService::POLICIES`.
- **Leitura e escrita em locks separados** não é atômico — foi o A-07, e reapareceu em
  `LoginRateLimitFallbackService`. Use `AtomicRateCounterService`.
- **MariaDB ≠ MySQL 8**: largura de exibição em `COLUMN_TYPE`, `COLUMN_DEFAULT` como expressão SQL,
  necessidade de drenar result set (`query()` + `closeCursor()`). Quatro defeitos vieram daí, um
  impedia a instalação. A matriz de CI existe por isso.
  **A divergência não para no SQL: alcança os binários.** Na primeira execução real da CI (PR #2),
  os dois jobs sobre `mariadb:11.4` falharam e o de `mysql:8.0` passou, com o *mesmo*
  `--health-cmd="mysqladmin ping"`. O servidor MariaDB estava de pé — o log diz
  `ready for connections` seis segundos após subir — mas o Actions o declarou `unhealthy` dois
  minutos depois, porque a sonda nunca teve sucesso: **o MariaDB 11.x renomeou os binários `mysql*`
  para `mariadb*`**. O job morria antes de qualquer teste rodar. O `health-cmd` agora tenta
  `mariadb-admin` e cai para `mysqladmin`, servindo às duas imagens.
- **Consulta com nome de tabela interpolado** escapa de varredura que procura `FROM <tabela>`
  literal. Foi assim que `count()`/`tableRows()` quase ficaram sem isolamento.
- **Regenerar `CHECKSUMS-SHA256.txt` por último**, sobre a árvore congelada, e limpar artefatos de
  execução local (`storage/cache/security`, `storage/audit-*`) antes de empacotar.
  Com o Hub no repositório há uma armadilha nova: **editar este `CLAUDE.md` invalida a linha dele
  no manifesto**, que passa a acusar `FAILED`. Nada na CI verifica o manifesto, então a falha é
  silenciosa — indicador que mente. Ao mexer neste arquivo, regenere a linha:
  `sha256sum CLAUDE.md` e substitua a entrada `./CLAUDE.md` no `CHECKSUMS-SHA256.txt`.
- **Classificar rota por prefixo é a mesma armadilha da substring.** O achado C-01: todo `api/*`
  era tratado como rota sensível de painel, então os webhooks de entrada tinham 20 req/min e o IP
  do Tiny seria bloqueado a 500 pedidos/min. Webhook de entrada tem superfície própria
  (`webhook_inbound`) e é classificado pelo `RouteCatalogService`.
- **Chave de balde compartilhada vira gargalo.** No C-01, a chave `(ip, usuario_id, rota, metodo,
  janela)` fazia os 500 pedidos do minuto colapsarem numa única linha, serializando a entrada.
- **Não faça manutenção de tabela no caminho da requisição.** O C-04: expurgo de até 30 mil
  deleções rodava dentro de uma requisição de usuário sorteada. Está em `workers/worker_retencao.php`.
- **Migration que pula tabela também pula o índice dela.** O C-03: `pedidos_integracao` já tinha
  `empresa_id`, então a migration de isolamento a ignorou — e ficou sem índice, a única das 32.
- **Indicador que mente é pior que indicador ausente.** Os achados F-01 e F-02: checagens que
  apontavam para um método inexistente e para uma coluna removida ficavam vermelhas para sempre,
  anulando o alarme verdadeiro. Ao criar checagem, derive o nome por reflexão/catálogo.
- **Tipo errado dentro de `catch(Throwable)` mata a escrita em silêncio.** O achado G-01:
  `TinyV2Service::logEndpoint()` passava `array $request` para
  `SensitiveDataService::maskJson(string $body)`. Array não é coercível para string em PHP 8, o
  `TypeError` era garantido em toda chamada, e o `catch` do próprio método o rebaixava a aviso
  best-effort — `tiny_v2_endpoint_logs` ficou **anos vazia** e o painel de saúde do Tiny V2
  mostrava verde com "sem chamada recente" sob tráfego real. Ao envolver gravação em
  `catch(Throwable)`, confira os **tipos declarados** de tudo que entra no `execute()`. Para
  payload destinado a log use `SensitiveDataService::sanitizeForStorage()` (aceita `mixed`),
  nunca `maskJson()` (só `string`).
- **`Audit::event()` NÃO mascara nada.** A máscara é de quem chama. `TinyV3Service` e `VsmService`
  aplicam `SensitiveDataService::mask()` na requisição e na resposta; o `TinyV2Service` era o único
  que não aplicava no caminho de sucesso (achado G-02). Ao adicionar um cliente de integração,
  copie o desenho do V3, não o do V2 antigo.

- **Em Playwright, `page.goto('/x')` descarta o caminho da `baseURL`.** Na primeira execução real
  da CI (PR #2) a suíte E2E rodou e 20 de 23 testes falharam com `element(s) not found`. Não era o
  Hub: era a URL. Com `php -S -t .` e `HUB_BASE_URL=.../public`, as duas formas usadas pelos specs
  erravam o alvo — `goto('/index.php')` resolve contra a **origem** e vira `/index.php` (404), e
  `goto(baseURL+'index.php')` com baseURL sem barra final vira `/publicindex.php` (404). Medido:
  os dois davam 404; servindo de `public/` com baseURL terminando em barra, os dois alcançam a
  aplicação. **Ao mexer na E2E, confira as duas formas** — e lembre que 404 e 503 dizem coisas
  diferentes: 404 é rota errada, 503 é aplicação alcançada sem banco.

- **O Service Worker recarrega a página na PRIMEIRA visita e apaga o que foi digitado.**
  A causa raiz de toda a saga do E2E do PR #2, encontrada só quando houve MariaDB real na sessão.
  `public/assets/pwa.js` fazia `navigator.serviceWorker.addEventListener('controllerchange', …
  location.reload())`. Num perfil de navegador novo — que é **todo contexto do Playwright** — não
  existe controlador: o SW registra, ativa, assume pela primeira vez, o `controllerchange` dispara
  e a página recarrega ~1 s após o load. Medido: `performance.getEntriesByType('navigation')`
  devolvia `["reload"]`, e o campo `input[name="email"]` voltava vazio 500 ms depois do `fill()`.
  Como e-mail e senha são `required`, o clique seguinte em *Entrar* **não enviava POST nenhum** —
  a tela ficava parada, sem mensagem de erro, e o teste falhava adiante procurando
  `#hubMainContent`. Era uma corrida: quando o teste demorava um pouco mais entre preencher e
  clicar, o POST saía e o login funcionava. Daí o placar oscilar entre execuções e entre testes do
  mesmo arquivo. Medido com o recorte aplicado: **0 de 6 → 6 de 6 logins** e a suíte inteira de
  23 testes verde contra MariaDB real. O recarregamento continua igual quando existe controlador
  anterior (atualização de verdade do SW); só a primeira posse deixou de recarregar, porque ali
  não há nada a atualizar — a página acabou de vir da rede. **Isso não era defeito só de teste:**
  uma pessoa na primeira visita ao Hub, digitando e-mail e senha, perdia o que digitou.
  Ao mexer em `pwa.js`, lembre que a tela é servida por `pwa.min.js` — regenere com
  `npm run build:pwa`, nunca editando o `.min.js` à mão.

- **`usuarios.deve_trocar_senha` nasce em 1, e isso redireciona TODA rota autenticada.**
  `database/install_final_current.sql` declara `deve_trocar_senha TINYINT DEFAULT 1` e
  `FastRouteDispatcherService` (linha 79) manda para `index.php?page=trocar-senha` enquanto a
  marca estiver ligada, exceto a própria troca e o logout. É o comportamento correto do Hub. Só
  que o administrador criado por `scripts/ci/provision-e2e-environment.php` nascia com a marca
  ligada: o login funcionava, a sessão era criada, e mesmo assim toda tela autenticada respondia
  302. Os sintomas apareciam longe da causa — `#hubMainContent` "não encontrado", o texto da tela
  de diagnóstico ausente, e **200 em vez de 404** na rota desconhecida, porque o dispatcher nunca
  chegava ao ramo `default: naoEncontrado()`. O provisionamento de CI desliga a marca e **confere
  o que escreveu**; o ambiente representa um Hub já instalado. A regra vale inteira na instalação
  real.

- **`package-lock.json` versionado pode apontar para um registro que só existe no ambiente de quem
  o gerou.** O `npm ci` do job `pwa-static-and-e2e` rodava ~8 minutos e morria com
  `npm error Exit handler never called!`, deixando `node_modules` incompleto. Os 348 `"resolved"`
  do lock da **raiz** apontavam para um espelho interno inalcançável de fora; o lock de
  `tests/e2e/` apontava para `registry.npmjs.org` — e por isso o `npm ci` *daquele* job sempre
  funcionou. Reapontado para o registro público, com `integrity` intacto (mesmos tarballs):
  `npm ci` na raiz passou de **travar mais de 10 minutos** para **6 s, exit 0, 347 pacotes**.
  A lição de método dói mais que a correção: o 403 que eu via localmente e o crash do runner eram
  **o mesmo defeito**, e eu tinha descartado o primeiro como "limitação do meu ambiente" em vez de
  procurar o que os dois tinham em comum — o arquivo versionado. **Ao ver npm quebrar em dois
  lugares diferentes, leia o lock antes de culpar o ambiente:**
  `grep -o '"resolved": "https://[^/]*' package-lock.json | sort | uniq -c`.

- **O rótulo do menu e o título da tela nem sempre são o mesmo texto.** O `authenticated-smoke`
  afirmava `/Teste Segurança Assistido/i`. A tela se chama **"Teste de Segurança Assistido"** — é
  assim no `<title>`, no `<h2>` de `views/security_assisted_test.php`, no `$pageTitle` do
  controller e no relatório do serviço. A única grafia sem o "de" é o rótulo do menu lateral
  (`views/layout_top.php:118`), que só é renderizado quando o perfil visual em uso mostra o grupo
  Segurança. A asserção passava por acidente, quando o item do menu aparecia. Ao afirmar sobre o
  conteúdo de uma tela, cite o título **dela**, não o rótulo que leva até ela.

- **`<button>` sem `type` num form É submit, mas `button[type="submit"]` não o encontra.** Terceira
  camada da primeira CI real (PR #2): com a URL corrigida, o login passou a renderizar e os testes
  preencheram e-mail e senha — e travaram no clique. `views/login.php` declarava
  `<button class="...login-submit">Entrar no Hub</button>` sem `type`. Para o navegador isso é
  submit (é o padrão do HTML), então **o login sempre funcionou para o usuário real**; o seletor CSS
  é que casa por atributo, e o atributo não existia. Quatro specs usam `button[type="submit"]`.
  Corrigido declarando o atributo — comportamento idêntico, e consistente com o botão irmão do
  mesmo form, que já declarava `type="button"`. **Ao escrever seletor de E2E, lembre que o padrão
  implícito do HTML não aparece no DOM como atributo.**

- **Jitter de fila é ADITIVO, nunca simétrico.** O achado G-03: `random(0, atraso)` (*full jitter*)
  **reduziria** o atraso, e isso é pior que não ter jitter — a política de `rate_limit` recua
  15/30/45 min justamente para parar de bater no provedor que já nos limitou. A fórmula é
  `atraso da política + random(0, teto)`, com teto de 10% do atraso, piso 30 s e limite 300 s, em
  `RetryPolicyService::jitterSegundos()`. Medido: 500 itens que falham no mesmo segundo passam de
  **1** para **31 segundos distintos** (atraso de 5 min) e **91** (15 min).

**Pendências abertas**
- **`20260914_012_pk_bigint_capacidade.sql` exige JANELA DE MANUTENÇÃO** (workers parados, webhooks
  drenados, backup verificado). `ALTER` de chave primária reconstrói tabela e índices: segundos
  hoje, horas depois. **Quanto antes rodar, mais barata.**
- Validar o isolamento multiempresa **contra banco real com duas empresas** (seção 5 do `SECURITY.md`).
  Agora é viável sem Docker — veja a receita no fim deste documento.
- Marcar `Hub CI / gate` como *required* na proteção de branch. **Ele existe e fica verde** desde
  2026-09-15; falta só ligá-lo em Settings > Branches, que é ação de quem administra o repositório.
- Ligar `security.webhook_signature_require_v2` quando a VSM migrar.
- Rotacionar segredos: `php scripts/rotate-secrets.php --audit`.
- Ciclo OAuth completo depende de credenciais Tiny reais.
- `session_driver='database'` só ao passar de um servidor; em nó único o padrão `file` está certo.

**Ordem de implantação das migrations de capacidade:** backup verificado → `011` (índices) →
`013` (logs + sessões) → agendar `worker_retencao.php` no cron → `012` (PK BIGINT, em janela).

**O que já foi validado contra banco real, e o que não foi.** As auditorias da R6/R7 foram todas
**estáticas** — não havia MySQL nem Docker no ambiente daquelas sessões. Isso mudou em 2026-09-15,
no PR #2:

| Validação | Onde | Resultado |
|---|---|---|
| Schema consolidado e modular em **MySQL 8.0** | CI, `mysql-runtime` | verde |
| Schema consolidado e modular em **MariaDB 11.4** | CI, `mysql-runtime` | verde |
| **E2E autenticado** (23 testes, Playwright) contra MariaDB | CI, `e2e-authenticated` | verde |
| Login, sessão, rotas do painel, 404 de rota desconhecida | sessão local, MariaDB 10.11 | verde |
| `pwa-static-and-e2e` (Lighthouse + PWA) | CI, workflow `pwa-quality.yml` | verde |

**Continua sem validação contra banco real:** o isolamento multiempresa com **duas empresas**
(seção 5 do `SECURITY.md`), o ciclo OAuth Tiny V3 completo (depende de credenciais reais) e
qualquer chamada de verdade ao Tiny ou à VSM. Não confunda "a CI está verde" com "o Hub está
validado": o verde cobre a tabela acima, não o resto.

**Dá para reproduzir o E2E nesta sessão, sem Docker.** Foi assim que a causa raiz do PR #2
apareceu, depois de quatro rodadas de palpite em cima de log de CI:

```
apt-get update && apt-get install -y --no-install-recommends mariadb-server
mariadbd --user=mysql &                      # sem systemd no container
mariadb -u root -e "ALTER USER 'root'@'localhost' IDENTIFIED VIA mysql_native_password USING PASSWORD('root'); \
  CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED BY 'root'; \
  GRANT ALL ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION; FLUSH PRIVILEGES;"
HUB_TEST_DB_HOST=127.0.0.1 HUB_TEST_DB_USER=root HUB_TEST_DB_PASS=root \
  HUB_TEST_DB_NAME=hub_ci_e2e HUB_CI_E2E_CONFIRM=1 php scripts/ci/provision-e2e-environment.php
php -S 127.0.0.1:8123 -t public &
cd tests/e2e && npm ci && HUB_BASE_URL=http://127.0.0.1:8123/ \
  HUB_USER=ci@example.invalid HUB_PASS=CI-only-password-2026 \
  PLAYWRIGHT_CHROMIUM_PATH=/opt/pw-browsers/chromium-<build>/chrome-linux/chrome \
  npx playwright test --reporter=list
```

Faça isso **numa cópia** (`cp -a . /tmp/hubrepro`): o provisionamento faz `DROP DATABASE`, escreve
`config/config.php` e cria `storage/install.lock`. E note o `PLAYWRIGHT_CHROMIUM_PATH`: o Chromium
pré-instalado em `/opt/pw-browsers` costuma ter um *build* diferente do que o `@playwright/test`
do lock espera, e sem essa variável a suíte morre em `Executable doesn't exist`. **Não rode
`npx playwright install`** — o `playwright.config.js` já lê essa variável justamente para isso.

**O `config/config.php` não é versionado** (está no `.gitignore`): ele carrega host, usuário, senha
e segredos, e é criado pelo instalador a partir do `config.example.php`. Ausência num checkout limpo
é o estado normal — os scripts de CLI caem no exemplo, por desenho do `App::config()`.
