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
| `EmpresaCatalogService` | Catálogo de `empresas`: listar, validar id de formulário e **`empresaUnicaId()`** — a empresa única da instalação, que é de onde a gravação sem sessão tira o `empresa_id` |
| `RetryPolicyService` | Toda a matemática de backoff: `attempts()`, `baseDelayMs()`, `sleep()` (retry na requisição) e **`proximaTentativaEm()` / `jitterSegundos()`** (reagendamento de fila, G-03). Classe folha — as três filas dependem dela |

**Portões de CI (13) — todos precisam ficar verdes**
`php-lint.sh` · `enterprise-tests.sh` (**42 testes**) · `schema-runtime-ddl-check.php` ·
`controller-route-check.php` · `vsm-openapi-check.php` · `build-classmap.php --check` ·
`tenant-scope-check.php` · `secret-hygiene-check.php` · `build-consolidated-schema.mjs --check` ·
`sql-inventory-check.php` · `mysql-schema-static-check.php` · `mysql-module-parity-check.php` ·
**`build-assets.mjs --check`** (`npm run check:pwa`, desde 2026-09-15: os `.min` servidos têm de
bater com a fonte — por isso o `static-enterprise` agora instala Node e dependências)
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

- **Folha de estilo que a tela carrega mas o build NÃO regenera.** `views/layout_top.php:50` carrega
  `integration-center.min.css`, e `scripts/pwa/build-assets.mjs` não listava
  `integration-center.css` em `cssFiles`. O minificado servido era de julho e podia divergir da
  fonte sem nada acusar: **editar o `.css` não chegava na tela**. Descoberto ao corrigir a
  divergência PWA↔web — a edição simplesmente não teve efeito. Conferido antes de incluir a folha
  no build que minificar a fonte reproduz o `.min.css` commitado (só normalizações do clean-css:
  `0%`→`0`, aspas em `[data-hub-theme=dark]`, ordem de seletores), então a inclusão não muda o CSS
  servido. **Ao mexer em qualquer `.css`/`.js` de `public/assets/`, confira antes se ele está na
  lista do build** — a varredura que encontra órfãs compara os `*.min.*` referenciados nas views
  com `cssFiles`/`jsFiles`. Hoje não há nenhuma órfã — e desde 2026-09-15 o portão
  `npm run check:pwa` trava a CI quando um `.min` sai de sincronia com a fonte. **A própria CI
  mascarava o problema antes disso:** rodava `npm run build:pwa`, que reescrevia os `.min` no
  runner, então os testes rodavam contra assets recém-construídos enquanto os commitados podiam
  estar velhos. O passo agora confere em vez de consertar, nos dois workflows. Conferido que o
  portão pega os dois casos: fonte editada sem rebuild, e `.min` ausente.

- **`grep` não encontra a regra que decide, quando ela vive num `padding` abreviado.** Ao alinhar a
  `.topbar` do PWA com a web eu troquei a base de 8px para 12px com base no que o grep achou — e
  **inverti a divergência no mobile** (web 8px, PWA 12px), porque existe
  `.topbar{padding:calc(8px + var(--hub-safe-top)) … !important}` num bloco `max-width:991.98px`,
  que o grep por `padding-top` nunca casaria e que **já soma o recorte**. A regra standalone era
  redundante no mobile. Quem resolveu foi perguntar ao navegador, não ao grep:
  `CSS.getMatchedStylesForNode` via CDP lista todas as regras que casam, na ordem de precedência,
  com `!important` e media query. **Antes de afirmar qual valor a web usa, meça o computado e peça
  a lista de regras casadas** — em folha com cascata de 5 arquivos e `!important` espalhado, ler
  CSS de cabeça erra.

- **PWA e web são idênticos por medição, não por promessa.** Em 2026-09-15 as cinco divergências
  reais foram eliminadas e o resultado é **zero diferença de CSS em 20 combinações tela/rota**
  (10 rotas × 2 tamanhos). O que sobra no diff e **não** é divergência: as alturas
  (`scrollHeight`/`clientHeight`/`height`), porque a janela do app não tem barra de endereço
  (761→848 no desktop, 705→792 no celular), e `.app-shell` ganhar `#f8fafc` no standalone, que é
  inócuo — `html`, `body`, `.main` e `.content` já são essa cor nos dois modos. Para repetir a
  medição: Chromium com `launchPersistentContext` e `--app=<url>` dá `display-mode: standalone`
  de verdade (`chromium.launch({args:['--app=…']})` **não** dá, e
  `Emulation.setEmulatedMedia` com a feature `display-mode` é ignorado), e compara-se o estilo
  computado contra uma aba comum.

- **Portão estático verde não prova isolamento em runtime.** `tenant-scope-check.php` verifica que
  toda consulta a tabela com escopo passa pelo `TenantScopeService` — e passa: são 162 pontos, e o
  portão fica **verde**. Ele não verifica que existe empresa ativa. Como nada chama
  `TenantContextService::set()`, `currentEmpresaId()` devolve `null`, `where()` devolve predicado
  vazio, e os 162 pontos filtram nada (achado H-01). **Ao construir um portão, pergunte o que ele
  NÃO prova** — e escreva isso ao lado dele, porque quem vê verde assume o resto. Para medir
  isolamento de verdade: povoe duas empresas, entre por HTTP e olhe a tela; e confirme o mecanismo
  injetando a empresa na sessão, para separar "filtro quebrado" de "filtro nunca ligado".

- **O `DashboardController` está a poucas dezenas de bytes do teto que a CI impõe.**
  `tests/enterprise/v104_48_1_architecture_test.php` exige que ele fique **abaixo de 160 KB**, para
  impedir que o controller-deus volte a crescer. Ao acrescentar o campo *Empresa* na tela de
  usuários (2026-09-15) o arquivo passou o teto por **1.089 bytes**, e o teste pegou. A resposta
  certa não é levantar o limite — é o que a guarda pede: **lógica nova de domínio entra por
  serviço**. Extraído para `EmpresaCatalogService` (listar, e ler+validar o `empresa_id` do
  formulário numa chamada só), o controller ficou em 163.788 bytes, folga de 52. Em 2026-09-15 a
  correção do I-12 devolveu fôlego ao mover `filaMortaReprocessar()` para o `FilaController`, que o
  `RouteModuleRegistry` já declarava como dono: **163.547 bytes, folga de 293**. O caminho para
  ganhar espaço é esse — devolver handler ao controller que o registry aponta —, não levantar o
  limite. O arquivo continua precisando ser decomposto. Ao mexer nele, meça antes:
  `wc -c app/Controllers/DashboardController.php` contra 163840.

- **Jitter de fila é ADITIVO, nunca simétrico.** O achado G-03: `random(0, atraso)` (*full jitter*)
  **reduziria** o atraso, e isso é pior que não ter jitter — a política de `rate_limit` recua
  15/30/45 min justamente para parar de bater no provedor que já nos limitou. A fórmula é
  `atraso da política + random(0, teto)`, com teto de 10% do atraso, piso 30 s e limite 300 s, em
  `RetryPolicyService::jitterSegundos()`. Medido: 500 itens que falham no mesmo segundo passam de
  **1** para **31 segundos distintos** (atraso de 5 min) e **91** (15 min).

- **Janela de contexto em portão estático cobre por acidente.** O achado I-02: o
  `tenant-scope-check.php` aceitava a evidência de escopo em qualquer lugar de ±6 linhas. Em
  arquivo denso — `FilaController` tem métodos inteiros numa linha só — a janela alcança OUTRO
  método. Medido: `FilaController.php:7` gravava em `fila_integracao` com `$pdo->prepare()` cru e
  passava, porque a linha 5 (outro método, outra consulta) cita `TenantScopeService`; e
  `EstoqueVsmSchedulerService.php:210` passava pela linha 216, que trata de outra tabela. Duas
  linhas nasciam sem empresa com o portão **verde**. A gravação agora tem régua própria: evidência
  na própria sentença ou nas 4 linhas **seguintes**, citando a **mesma tabela** — para trás não
  vale, que é de onde vinha o falso negativo. Conferido lado a lado: o portão antigo diz `[OK]`
  sobre a árvore com os dois defeitos, o novo pega os dois.

- **`TenantScopeService::run()` com JOIN e SEM alias quebra a tela.** O achado I-08: `where()` monta
  o predicado com o prefixo que recebe em `$alias`; sem alias injeta `empresa_id = ?` puro, e se o
  JOIN traz outra tabela que também tem a coluna o banco recusa a consulta inteira —
  `Column 'empresa_id' in WHERE is ambiguous`. **Só aparece para quem tem empresa atribuída**, porque
  sem empresa na sessão o predicado nem entra: por isso sobreviveu à validação do H-01, feita com
  usuário sem empresa. Medido por HTTP: a tela **Fiscal** quebrava. E o achado apontava um ponto —
  **havia quatro** (`FiscalController`, `DashboardController`, e duas em `FiscalEnterpriseService`).
  Terceira vez que isso acontece nesta linha; ao corrigir escopo, varra a classe inteira.

- **Tela pedindo coluna que não existe derruba a rota, e o acerto de segurança esconde.**
  O achado I-06: `FilaController::index()` fazia `SELECT ... atualizado_em FROM fila_integracao`, e
  essa coluna **não existe** nessa tabela (só em `fila_estoque`, `fila_fiscal` e `fila_morta`). A
  tela Fila devolvia **500** para todo mundo — e a view nunca usou o campo. O que o mascarava é o
  achado I-07: a suíte E2E afirmava `body` sem `/Fatal error|Uncaught|PDOException|SQLSTATE\[/`, e o
  Hub esconde a exceção atrás da tela de Recuperação, **que é o comportamento correto** (nunca
  devolver stack trace ao usuário). O acerto de segurança cegava o teste: 23 de 23 verdes com uma
  das 10 rotas listadas completamente quebrada. A suíte agora confere também o **status HTTP**.
  Conferido nos dois sentidos: com o defeito reposto, a asserção de status falha nos 5 viewports.
  **Ao afirmar que uma tela "não tem erro", olhe o código HTTP — o texto da tela foi projetado para
  não contar.**

- **Auditoria mostrando `SQLSTATE[` na tela não é tela quebrada.** Perseguindo o I-06 eu marquei a
  rota `auditoria` como defeituosa numa varredura por texto. Ela estava **certa**: exibia os
  eventos `sistema.erro_fatal` gravados quando a Fila caiu. A trilha fez o trabalho dela. Varredura
  por texto de erro tem falso positivo justamente na tela que existe para mostrar erro.

- **`SHOW ... LIKE ?` é INVÁLIDO no Hub, e o erro se disfarça de "tabela ausente".** O achado I-10.
  Toda conexão abre com `PDO::ATTR_EMULATE_PREPARES => false` (`app/Core/Database.php`), e com
  prepares **nativos** o servidor recusa placeholder em `SHOW`: `SHOW TABLES LIKE ?` e
  `SHOW COLUMNS FROM t LIKE ?` morrem com `near '?'`. Medido nas duas configurações contra
  MariaDB 10.11: falha com nativos, passa com emulados — por isso o padrão parece correto para quem
  o testa fora do Hub. Eram **13 ocorrências**, quase todas dentro de `catch(Throwable)` devolvendo
  `false`, ou seja, **"a tabela não existe"**. Medido em runtime: `QueueV24AnalyticsService`,
  `TinyV2ObservabilityService`, `AuditIntegrityService` e `VsmFichaTecnicaService` declaravam
  **AUSENTE** tabela que existia, e o `PostInstallTestService` reprovava as **cinco tabelas
  centrais de qualquer instalação**. Em `SafeSqlUpgradeService::addColumnIfMissing()` não havia nem
  `catch`: a atualização assistida de schema morria antes do `ALTER`. Use sempre
  `Database::tableExists()` / `columnExists()` (ou as variantes `...On($pdo, …)`), que existem para
  isso e não usam placeholder. **`LIMIT ?` foi medido e está correto** — não confunda as duas
  coisas. Travado por `tests/enterprise/v104_49_3_sql_portability_test.php`.

- **O escopo não pode grudar `WHERE` em sentença que não é consulta de dados.** O achado I-09:
  `applyToSelect()` acrescentava o predicado a qualquer coisa que não fosse `INSERT`, então
  `SHOW COLUMNS FROM fila_integracao LIKE 'status'` virava
  `SHOW COLUMNS ... LIKE 'status' WHERE (empresa_id = ? OR empresa_id IS NULL)` — SQL inválido. As
  telas *Testes de Regressão Enterprise* e *Production Ready V25* exibiam o erro de sintaxe, e **só
  para quem tem empresa atribuída**, porque sem empresa o predicado nem entra. Agora há lista de
  **permissão** (`SELECT`/`UPDATE`/`DELETE`/`WITH`, tolerando espaços, parênteses e comentários à
  frente): sentença desconhecida sai intacta. Conferido que a checagem voltou a **funcionar**, não
  apenas a calar — ela lê o ENUM e responde OK.

- **Varrer rota por STATUS não basta; varrer por TEXTO tem falso positivo.** Ao estender a auditoria
  às 178 rotas alcançáveis por GET, nenhuma devolvia 5xx — e mesmo assim duas exibiam erro de banco
  no corpo, com HTTP 200, porque o `catch` da tela mostra a mensagem em vez de derrubar a resposta
  (I-09). As duas medições se complementam: **status** pega tela derrubada, **texto** pega erro
  capturado e exibido. E o texto acusa de graça a tela de Auditoria, que existe justamente para
  mostrar erro — confira o caso antes de chamá-lo de defeito.

- **Chamada a método de serviço que não existe passa por todos os portões.** O achado I-11:
  `(new UniversalUpgradeService($this->pdo))->executarTodos()` tinha DOIS erros na mesma linha — o
  construtor declara `string $root` e recebia um `PDO`, e `executarTodos()` **nunca existiu** (a
  classe só tem `run()`). O botão *Executar todos os updates com segurança* devolvia **500 desde
  sempre**. O `controller-route-check.php` confere método ausente apenas para as rotas de
  `FastRouteDispatcherService::$directActions`; chamada a serviço escapa dele. A tela era a terceira
  camada desalinhada: lia `$r['ok']` e `$r['ignorados']`, chaves que o serviço nunca devolveu, e
  passava `$r['erros']` (array) para `e()`. **Ao mexer numa tela antiga, confira as três camadas** —
  controller, serviço e view derivam em silêncio. Travado por
  `tests/enterprise/v104_49_3_service_call_test.php`, que **não prova tipo de argumento**: isso só
  aparece exercitando a rota.

- **Exceção de regra de negócio virando 500 suja o canal de alarme.** O achado I-12:
  `fila-morta-reprocessar` passava o id direto ao serviço, que **lança** quando o registro não
  existe. Aba antiga, duplo clique ou item já expurgado davam 500, tela de Recuperação e um
  `sistema.erro_fatal` na trilha — que é justamente o canal usado para detectar defeito real. O
  irmão `fila-reprocessar` já guardava o id (`if($id>0)`); só este não. Mora agora no
  `FilaController`, que o `RouteModuleRegistry` **sempre declarou** como dono da rota — o que de
  quebra tirou o handler do `DashboardController` e devolveu folga ao teto (43 → **293 bytes**).
  A tela da Fila Morta também não lia o `?reprocessado=1` para o qual era redirecionada: o operador
  clicava e nada mudava. As três respostas agora aparecem.

- **A empresa se perdia na ida para a fila morta e na volta.** O achado I-13, descoberto ao medir o
  efeito da correção do I-12: o reprocessamento criava a linha nova com `empresa_id` NULL, desfazendo
  o isolamento de entrada recém-aplicado. Eram dois saltos — `enviar()` não copiava a empresa do
  item que falhou, e `reprocessar()` não a devolvia. O dono nunca precisou ser adivinhado: a própria
  linha sabe de quem é. Medido na cadeia inteira contra MariaDB: `fila_integracao` 1 → `fila_morta`
  1 → `fila_integracao` 1. **Ao consertar um fluxo, meça o dado que ele grava, não só o código HTTP.**

- **Rota de mutação não se audita por GET.** As 78 rotas que recebem POST das views precisam de
  CSRF — que no Hub é **por sessão e estável** (`Csrf::token()`), então um token serve para a
  sessão inteira. O sinal confiável não é o texto da tela (que esconde a exceção de propósito) nem
  só o status: é contar os eventos `sistema.erro_fatal` gravados na trilha entre o antes e o depois
  de cada POST. Foi assim que I-11 e I-12 apareceram. Rodar isso exige banco descartável: seis das
  rotas são destrutivas (`backup-excluir`, `backup-restaurar`, `backup-importar`,
  `ambiente-demo-reset`, `usuario-excluir`, `trocar-senha`) — todas se comportaram bem, mas
  `trocar-senha` deve ficar por último, porque invalida o login das seguintes.

- **A autenticação dos webhooks do Tiny NÃO é a da VSM.** A VSM assina HMAC
  (`v2:MÉTODO:rota:timestamp:nonce:hash`, `WebhookSecurityService`); o Tiny manda um **segredo
  compartilhado no header** `X-TINY-HUB-SECRET` (`TinyWebhookSecurityService`), comparado com
  `hash_equals`. Em cima disso vêm lista de CNPJ autorizado, lista de IP, teto de payload e **dois**
  limitadores — o de tentativas roda ANTES da autenticação (achado A-09), e o de gravações depois.
  Medido nas seis rotas contra MariaDB: sem segredo → 401, segredo errado → 401, correto → aceito,
  **zero erro fatal**. Com a lista de CNPJ ligada: autorizado → 200, outro → 401, vazio → 401, e o
  bloqueio auditado como `TINY_WEBHOOK_CNPJ_NOT_ALLOWED`. Ao mexer num dos lados, **não copie o
  desenho do outro**.

- **O webhook de PEDIDO do Tiny carrega CNPJ; o da VSM não carrega empresa nenhuma.** Isso decide a
  metade aberta do H-01: se um dia a instalação passar a ter duas ou mais empresas, o lado Tiny tem
  sinal para resolver a empresa (o CNPJ já é validado contra `tiny_webhook_cnpj_autorizados`), e o
  lado VSM não tem. Não são simétricos, e a decisão de produto precisa tratá-los separado.

- **`success` no corpo contradizendo o status HTTP.** O achado I-14:
  `responderTiny(true, …, $validacao['ok']?202:422)` tinha o primeiro argumento **fixo em `true`**
  enquanto o código variava, então um pedido bloqueado respondia `HTTP 422` com `"success": true`.
  Quem integra lê um dos dois e erra. Que era inconsistência, e não contrato, ficou provado pelo
  próprio arquivo: a resposta de estoque sem itens já fazia `responderTiny(false, …, 422)` — aquela
  linha era a única a divergir entre 14. **O código HTTP não foi tocado**: mudá-lo alteraria quando
  o Tiny reenvia, que é decisão de produto. Medido antes de classificar a gravidade: três reenvios
  do mesmo pedido bloqueado produzem **uma linha só** em `pedidos_validacao` (idempotência por
  `pedido_origem_id`), então o contrato mentia mas não duplicava. Travado por
  `tests/enterprise/v104_49_3_webhook_contract_test.php`.

- **Varredura que casa "dentro de uma linha" não vê a chamada que motivou o teste.** Ao escrever a
  checagem do I-14 eu casei `responderTiny(` e o código HTTP na mesma linha — e ela ficou verde
  sobre a árvore com o defeito reposto, porque justamente aquela chamada é **multilinha** e o código
  vem de um ternário. Generalizada para balancear parênteses e ler a chamada inteira, passou de 11
  para 14 respostas conferidas e pegou o caso. **Ao escrever verificação estática, teste-a contra o
  defeito original antes de confiar nela** — não contra um parecido.

- **Em shell, função sem `local` sobrescreve a variável do laço que a chama.** A primeira bateria
  dos webhooks Tiny fazia `for R in <rotas>` e, dentro da função, `R=$(curl …)`. Da segunda sonda em
  diante a rota virava `401` e o POST ia para `index.php?page=401`, que responde 302 — e o placar
  parecia dizer que o segredo não estava sendo exigido. Não era o Hub: era o harness. **Declare
  `local` em toda variável de função de harness**, e desconfie de resultado onde a coluna de
  identificação mudou de valor sozinha.

- **Variável nunca atribuída dentro de `catch(Throwable)` apaga uma rotina inteira e reporta
  sucesso.** O achado I-15: `RetentionService::limparOperacional()` usava `$pdo->prepare(...)` com
  um `$pdo` que **nunca era definido**. As seis deleções morriam com
  `Call to a member function prepare() on null`, o `catch` virava cada erro numa **string dentro do
  array de resultado**, e logo abaixo `Audit::event(…,'sucesso',…)` registrava o conjunto como
  sucesso — um evento verde carregando seis erros. Efeito: `logs_integracao`, `auditoria_eventos`,
  `tiny_webhooks`, `metricas_api`, `diagnostico_api` e `selftest_relatorios` **nunca foram
  expurgadas em instalação nenhuma**, e o `DataRetentionService` não as cobre (ele trata
  `fila_integracao` e `sessoes`, achados C-04 e D-01) — os dois não se sobrepõem, então não havia
  rede de segurança. Corrigido com o desenho do serviço que funciona: conexão **por tabela**
  (módulos diferentes), guarda de `tableExists`/`columnExists`, `LIMIT 5000` por execução e status
  de auditoria que segue o que aconteceu. Medido apagando linha antiga e mantendo a recente nos
  três alvos exercitáveis. **Ao ler um `catch(Throwable)` que escreve o erro no resultado, confira
  se o status reportado acima dele pode ser diferente de sucesso.**

- **Resiliência de fila: medida, e está sólida.** Cinco cenários contra MariaDB: falha temporária
  reagenda para o futuro e incrementa tentativas; o item **não** é reentregue antes da hora;
  esgotar o limite (`RetryPolicyService::attempts()`, 3) manda para `falha_definitiva` **e** cria a
  linha na fila morta já carimbada com a empresa; item preso por worker morto é solto por
  `liberarTravados()`; e dois reprocessamentos seguidos **não duplicam**. O jitter também:
  200 falhas no mesmo segundo caem em 30 instantes distintos (5 min) e 78 (15 min), e o menor
  atraso observado em 300 sorteios é exatamente a base da política — **aditivo, nunca reduz** (G-03).

- **Ao sondar um serviço, não invente o nome do método.** Escrevendo a sonda de fila eu chamei
  `QueueService::liberarPresos()`; o nome real é `liberarTravados()`. O fatal da sonda parecia
  defeito do Hub por um instante. Os nomes reais são `pegarProximo`, `marcarResultado`,
  `heartbeat`, `liberarTravados` e `reprocessar` — e `marcarResultado()` só finaliza item que está
  `processando` **e** com o mesmo `locked_by`, então uma sonda que insere direto com
  `status='pendente'` não marca nada e parece defeito. Reserve pelo caminho real.

- **OAuth Tiny V3: a parte que não depende de credencial foi medida, e passa.** Seis cenários de
  recusa em `OAuthStateService::consume()` — sem cookie, cookie adulterado em 1 byte, expirado,
  provedor diferente, state que não confere e state vazio —, aceite legítimo, e **replay do mesmo
  state bloqueado** pela proteção anti-replay. PKCE confere: challenge é S256 do verifier, e o
  verifier tem 86 caracteres (o mínimo da RFC 7636 é 43). O que continua sem prova é o ciclo
  completo com o servidor OAuth real.

- **`worker_consulta_estoque_vsm.php` sai com código 1 sem rede, e isso está CERTO.** A mensagem é
  `Host VSM não pôde ser resolvido por DNS`. Num ambiente sem acesso à VSM esse é o comportamento
  correto, não um defeito — não o trate como falha de auditoria.

**Pendências abertas**
- **`20260914_012_pk_bigint_capacidade.sql` exige JANELA DE MANUTENÇÃO** (workers parados, webhooks
  drenados, backup verificado). `ALTER` de chave primária reconstrói tabela e índices: segundos
  hoje, horas depois. **Quanto antes rodar, mais barata.**
- **H-01 — RESOLVIDO para instalação de empresa única; em aberto para 2+ empresas.**
  A leitura já isolava (`usuarios.empresa_id`, migration `20260915_014`, `Auth::finalizeLogin()`
  carimbando `$_SESSION['tenant_empresa_id']`). Faltava a escrita de entrada: webhook, fila, worker
  e cron rodam sem sessão, `applyToInsert()` devolvia o SQL intacto e a linha nascia com
  `empresa_id` NULL — visível a todas as empresas. **Decisão do produto (2026-09-15): empresa
  única.** `TenantScopeService::empresaParaGravar()` resolve a sessão primeiro e, fora dela, a
  ÚNICA empresa cadastrada (`EmpresaCatalogService::empresaUnicaId()`), com a **mesma regra** das
  migrations `010`/`014`: `COUNT(*) = 1` então `MIN(id)`. Todo instalador semeia exatamente uma
  (`INSERT IGNORE INTO empresas(id,nome,cnpj) VALUES(1,'Empresa Demonstração','')`, no consolidado
  e em `database/modules/core.sql`), então a resolução acontece.
  **A leitura NÃO recorre à empresa única, de propósito:** alargar `where()` poderia esconder linha
  já carimbada com outra empresa, e tela que perde dado sem aviso é pior que a lacuna. Só a
  gravação usa o recurso.
  Medido contra MariaDB 10.11, mesmo banco, antes e depois: webhook VSM real (HMAC v2, HTTP 200)
  gravava `fila_estoque` e `estoque_movimentos` com `empresa_id` **NULL** antes, **1** depois. Pela
  tela Fila, por HTTP: usuário sem empresa vê as duas linhas; usuário da empresa 1 vê as duas;
  usuário da empresa 2 **não vê** a linha da empresa 1 — antes veria, porque ela seria NULL.
  **Continua em aberto com DUAS OU MAIS empresas:** ali a entrada sem sessão não tem como decidir, o
  serviço devolve `null` e a linha nasce NULL, como antes. Resolver exige a decisão adiada (token de
  webhook por empresa? empresa por conexão Tiny/VSM?) — e o payload da VSM não carrega empresa.
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
| **Isolamento multiempresa, duas empresas** | sessão local, MariaDB 10.11 | leitura isola; escrita de entrada não — ver H-01 |
| **Escrita de ENTRADA isolada** (webhook VSM real, HMAC v2, HTTP 200) | sessão local, MariaDB 10.11 | verde — `empresa_id` NULL antes, 1 depois, mesmo banco |
| **Tela Fila / Fiscal com usuário COM empresa atribuída** | sessão local, MariaDB 10.11 | quebradas (I-06, I-08); corrigidas e remedidas verdes |
| **12 rotas do painel por STATUS HTTP**, usuário da empresa 1 | sessão local, MariaDB 10.11 | verde (antes: `fila` em 500) |
| **178 rotas alcançáveis por GET**, status + texto de erro, usuário da empresa 1 | sessão local, MariaDB 10.11 | verde (antes: 2 telas com erro de SQL em HTTP 200 — I-09) |
| **Helpers de existência de tabela** (4 serviços) contra a verdade do banco | sessão local, MariaDB 10.11 | verde (antes: os 4 diziam AUSENTE — I-10) |
| **78 rotas de MUTAÇÃO** (POST + CSRF), status + `sistema.erro_fatal` na trilha | sessão local, MariaDB 10.11 | verde (antes: 2 em 500 — I-11, I-12) |
| **Cadeia fila → fila morta → reprocessamento**, empresa em cada salto | sessão local, MariaDB 10.11 | verde (antes: a volta nascia NULL — I-13) |
| **6 webhooks de entrada do Tiny**: sem segredo / segredo errado / correto | sessão local, MariaDB 10.11 | verde — 401, 401, aceito; zero erro fatal |
| **Trava de CNPJ autorizado do Tiny** | sessão local, MariaDB 10.11 | verde — autorizado 200, outro 401, vazio 401 |
| **Pedido Tiny ponta a ponta**: bloqueado (422) e aprovado (202), com reenvio | sessão local, MariaDB 10.11 | verde — 3 reenvios, 1 linha; `empresa_id` carimbado |
| **13 workers executados** | sessão local, MariaDB 10.11 | verde — 12 limpos; o da VSM falha por DNS, que é o correto |
| **Retenção operacional** (6 tabelas) | sessão local, MariaDB 10.11 | quebrada (I-15); corrigida e medida apagando antiga e mantendo recente |
| **Resiliência de fila**: retry, backoff, DLQ, item preso, reprocessamento | sessão local, MariaDB 10.11 | verde nos 5 cenários |
| **OAuth V3 sem credencial**: 6 recusas, aceite, replay, PKCE S256 | sessão local, MariaDB 10.11 | verde |

**Continua sem validação contra banco real:** o ciclo OAuth Tiny V3 completo (depende de
credenciais reais) e qualquer chamada de verdade ao Tiny ou à VSM. Não confunda "a CI está verde" com "o Hub está
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
