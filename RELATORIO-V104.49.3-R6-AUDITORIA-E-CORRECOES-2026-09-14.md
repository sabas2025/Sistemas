# Relatório V104.49.3-R6 — Auditoria, correções de portabilidade MariaDB, reauditoria externa e auto-auditoria

Data: 2026-09-14
Base: V104.49.3-R5 (esta árvore é promovida a R6: o conteúdo mudou e o artefato precisa de identidade própria)
Ambiente de validação: Ubuntu + PHP 8.4 (estático) e VPS aaPanel com **MariaDB 10.11.16** (runtime real)

---

## 1. Auditoria de segurança — o que foi verificado e confirmado correto

A revisão cobriu manualmente as áreas da OWASP Top 10 aplicáveis a este código. Os controles
abaixo foram lidos no código real (não assumidos a partir dos relatórios anteriores) e estão
corretos:

| Área | Evidência |
|---|---|
| SQL Injection | Consultas dinâmicas usam whitelist de tabelas/colunas (`SAFE_TABLES`/`SAFE_COLUMNS`) e todo filtro de usuário passa por prepared statement |
| XSS | Helper `e()` = `htmlspecialchars(ENT_QUOTES)` aplicado de forma consistente na saída das views |
| Autenticação | bcrypt via `password_hash`/`password_verify`, hash dummy contra enumeração por timing, rate limit por IP e por usuário, bloqueio temporário |
| 2FA | TOTP com janela de tolerância, obrigatório para admin quando configurado, expiração do setup pendente |
| Sessão | Cookie `HttpOnly`/`SameSite=Strict`/`Secure`, `session_regenerate_id()` no login, fingerprint, timeout de inatividade e absoluto |
| CSRF | Token de 32 bytes aleatórios comparado com `hash_equals` |
| Criptografia | AES-256-GCM com IV aleatório e AAD; chaves-padrão inseguras são rejeitadas |
| Webhooks | HMAC-SHA256 com `hash_equals`, janela de timestamp e nonce anti-replay |
| Path traversal | Download de backup resolve o arquivo por ID no banco, com `basename()` como defesa adicional |
| Autorização | `PermissionService::can()` nega por padrão quando não há permissão explícita |
| Execução de SO | Nenhum `eval`, `exec`/`shell_exec`/`system`, `unserialize` ou `create_function` no código da aplicação |

Nenhuma vulnerabilidade crítica explorável remotamente foi encontrada nesta revisão.

---

## 2. Correções aplicadas

### 2.1 Segurança

**`security.admin_ip_allowlist` era decorativa** — a chave existia em `config.example.php` e no
`install.php`, mas nenhum ponto do código a lia; quem a configurasse acreditando restringir o
painel por IP não tinha proteção alguma.

Criado `app/Services/AdminIpAllowlistService.php`, com a decisão pura (`isAllowed()`) separada do
efeito colateral HTTP (`enforceForRequest()`). Suporta IP exato e faixas CIDR (v4 e v6). Aplicado
em `FastRouteDispatcherService::dispatch()` **antes** do bloco de login — caso contrário a
allowlist não protegeria a própria tela de login. Ficam de fora: site comercial público,
`api/csp-report`, `api/status` e webhooks Tiny/VSM (que têm validação HMAC própria). As rotas
`api/*` de uso interno do painel **continuam protegidas**, pois exigem sessão autenticada.
Lista vazia mantém o comportamento anterior (sem restrição).

**Dados de cliente real no pacote** — o nome de uma empresa cliente e sua cidade estavam
embutidos como seed em 36 arquivos SQL de instalação/migração, além de uma senha específica na
lista de senhas bloqueadas. Substituídos por valores genéricos (`Empresa Demonstração`).

**Hardening HTTP dependia exclusivamente de Apache** — headers de segurança, bloqueio de
extensões sensíveis e bloqueio de `worker_*.php` existiam apenas em `.htaccess`, inertes em
Nginx. Adicionado `nginx.conf.example` com o equivalente, recomendando apontar o document root
para `public/`.

### 2.2 Qualidade da suíte de testes

**`scripts/ci/enterprise-tests.sh` mascarava falhas** — com `set -e`, a primeira falha abortava
o script e **todos os testes seguintes em ordem alfabética eram pulados em silêncio**. Corrigido
envolvendo a execução em `if` (comando dentro de condicional não aciona `errexit`); a suíte agora
roda até o fim e falha no final com a lista de quem falhou.

**`tests/enterprise/v104_48_1_schema_vsm_contract_test.php` era um falso negativo permanente** —
checava o seed do catálogo VSM em `app/Services/VsmEndpointService.php`, mas esse seed havia sido
movido para o schema SQL em refactor anterior. O teste falhava sempre e, por causa do `set -e`
acima, mascarava a suíte inteira. Corrigido para checar o seed onde ele de fato está.

### 2.3 Portabilidade MariaDB (todas descobertas rodando contra MariaDB 10.11 real)

A CI do projeto declara matriz MySQL 8 + MariaDB, mas os defeitos abaixo só se manifestam em
MariaDB e passaram despercebidos — em parte porque o `set -e` acima truncava a suíte.

**(a) `PDO::exec()` com result set pendente** — as migrations aplicam `ALTER TABLE` condicional
via `SET @hub_sql := IF(..., 'ALTER TABLE ...', 'SELECT 1'); PREPARE ...; EXECUTE ...;`. Quando a
coluna já existe, o ramo executado é `SELECT 1`. O MySQL trata `EXECUTE` de prepared statement
como chamada de procedure (result set + pacote de status final), e `PDO::exec()` não drena esse
extra em builds baseadas em libmysqlclient — o comando seguinte falhava com
`SQLSTATE[HY000] 2014 Cannot execute queries while other unbuffered queries are active`.
Corrigido com `execDrain()` (usa `query()` + `closeCursor()` explícito).

**(b) `COLUMN_DEFAULT` como expressão SQL** — o MariaDB devolve o valor padrão de
`information_schema.COLUMNS` como expressão (`NULL` em texto, strings entre aspas), enquanto o
MySQL 8 devolve o valor cru. Confirmado no servidor: `SELECT COLUMN_DEFAULT IS NULL` retornou
`0` para coluna sem default. Corrigido com `normalizeColumnDefault()`, que desembrulha aspas e
só interpreta o texto `NULL` como ausência de default quando o servidor é MariaDB — evitando
mascarar um `DEFAULT 'NULL'` legítimo no MySQL.

**(c) Gate pós-instalação reprovava toda coluna inteira `UNSIGNED`** *(bloqueava a instalação)* —
`normalized_schema_sql()` remove o espaço ao redor de parênteses, então o `bigint(20) unsigned`
do MariaDB virava `bigint(20)unsigned`; ao remover a largura de exibição sem repor o separador,
o resultado era `bigintunsigned`, que nunca batia com o esperado do DDL (`bigint unsigned`). O
MySQL 8.0.19+ não expõe largura de exibição, então o defeito **só existe em MariaDB**. Afetava
`security_events.id`, `ips_bloqueados.id`, `rate_limit_hits.id` e
`integration_replay_guard.time_bucket`, impedindo qualquer instalação nova.

Corrigido preservando o separador ao remover a largura. Observação: os outros três normalizadores
do projeto (`SchemaMigrationService`, os dois scripts de regressão) são simétricos — aplicam o
mesmo tratamento aos dois lados da comparação — e por isso não têm o defeito.

### 2.4 Consolidação do modelo de dados

`empresas` e `filiais` não possuíam tela, rota, nem qualquer `INSERT`/`UPDATE`/`DELETE` no
código: existiam apenas como contadores no dashboard e como insumo dos limites de licença. As
colunas `empresa_id`/`filial_id` nunca foram usadas como filtro em consulta alguma (limitação já
reconhecida no próprio `config.example.php`).

Consolidado em `empresas` (`filiais.empresa_id` é `NOT NULL`, então filiais dependia de
empresas — o inverso não é verdade; e `empresas.cnpj` cobre o cenário de CNPJ único). O seed de
`filiais` foi removido e a tabela saiu das telas e whitelists, **mas permanece no schema** para
não quebrar instalações existentes.

---

## 3. Testes adicionados

| Arquivo | Cobertura |
|---|---|
| `tests/enterprise/v104_49_3_admin_ip_allowlist_test.php` | 24 asserções: IP exato, CIDR /24, /32, /0, IPv6, entrada malformada, rotas isentas, rotas protegidas e a regressão do B-06 (página administrativa `tiny-webhooks` não é isenta). **Verificado que falha quando o defeito é reintroduzido** |
| `tests/enterprise/v104_49_3_mariadb_type_contract_test.php` | Contrato de tipo do instalador nos dois bancos, exercitando as funções reais extraídas do `install.php`. **Verificado que falha quando o defeito é reintroduzido** |
| `tests/enterprise/v104_49_3_backup_provenance_test.php` | 13 asserções sobre o A-11: proveniência dentro do payload assinado, **adulteração da proveniência invalida o HMAC**, compatibilidade com assinatura v1, e falha fechada (sem assinatura ⇒ tratado como externo) |
| `tests/enterprise/v104_49_3_r6_reauditoria_test.php` | 40 asserções cobrindo as correções que haviam ficado sem regressão: contador atômico (incremento, janela deslizante, `degraded` com armazenamento realmente inutilizável), política de cada chamador (webhook FECHADO, telemetria FECHADA, rota ABERTA-com-registro), ordem de tentativa × autenticação no webhook Tiny, assinatura v2, ordenação do dispatcher, revalidação do usuário no callback OAuth, remoção do `selectLight()`, 404 real sem reflexão da rota, `.htaccess` sem redirecionamento por cabeçalho e as quatro guardas do script destrutivo de CI |

---

## 4. Validação executada

**Estática (PHP 8.4):** `php-lint.sh` OK · `enterprise-tests.sh` **32 testes OK** ·
`schema-runtime-ddl-check.php` OK · `controller-route-check.php` 104 rotas OK ·
`vsm-openapi-check.php` OK · `sql-inventory-check.php` 134 tabelas OK ·
`mysql-schema-static-check.php` OK · `mysql-module-parity-check.php` OK ·
`build-consolidated-schema.mjs --check` OK

Os oito portões foram reexecutados do zero **depois** das correções da seção 7, não só antes.

**Runtime contra MariaDB 10.11.16 real:**

| Script | Resultado |
|---|---|
| `mysql-runtime-regression.php` — schema consolidado + migrations 001/002/003/004/007 aplicadas duas vezes, contratos de coluna e índice, posse de fila | `[OK]` exit 0 |
| `mysql-modular-runtime-regression.php` — 8 módulos instalados duas vezes em 8 bancos, inventário, 24 colunas-alvo, índices, sentinelas de preservação de dados | `[OK]` exit 0 |

---

## 5. Reauditoria externa de 2026-09-14 — 13 achados (A-01 a A-13)

Uma auditoria independente analisou o pacote e reprovou a promoção para produção. Os 13 achados
foram verificados individualmente contra o código: **todos procedentes, nenhum falso positivo**.
Cada um está corrigido nesta R6.

| ID | Achado | Correção aplicada |
|---|---|---|
| A-01 | Manifesto com 77 divergências e arquivos fora do inventário | Árvore congelada, manifestos históricos enganosos removidos e `CHECKSUMS-SHA256.txt` regenerado por último. Verificação: 0 divergências, 774 entradas para 774 arquivos |
| A-02 | Callback OAuth Tiny V3 bloqueado pela sessão `SameSite=Strict` | Callback roteado antes do gate de sessão em `FastRouteDispatcherService`, via `DashboardController::dispatchOAuthCallback()`. A autorização deixa de depender da sessão: `OAuthStateService` passa a vincular usuário e perfil na transação assinada, e `PermissionService::canForProfile()` autoriza por esse perfil |
| A-03 | Gate E2E sem ambiente válido, com skips silenciosos | Novo job `e2e-authenticated` com MariaDB de serviço, `scripts/ci/provision-e2e-environment.php`, servidor local, `HUB_BASE_URL` obrigatória (sem fallback remoto), `npm ci` com lockfile versionado e `@playwright/test` fixado. Skip passou a reprovar o gate |
| A-04 | Redirecionamento HTTPS confiava em cabeçalhos do cliente | Regra removida do `.htaccess` (endurecê-la ali criaria loop atrás de proxy TLS); o redirecionamento fica com `App::enforceProductionSafety()`, que só honra `X-Forwarded-Proto` de proxies declarados. O instalador passa a persistir `canonical_host` |
| A-05 | Deriva de versão, classmap e documentação | Release promovida a R6 com data própria; classmap regenerado da árvore real (236 classes, incluindo as 4 que faltavam); declarações falsas de "V104.50" removidas |
| A-06 | Rate limiter de rotas falhava aberto | O `catch` passa a degradar para `AtomicRateCounterService`, contador local independente de banco, em vez de liberar a requisição |
| A-07 | Telemetria PWA com corrida e crescimento ilimitado | Leitura e escrita sob o mesmo lock exclusivo; `Sec-Fetch-Site` ausente deixa de ser tratado como mesma origem; cota de 8 MB e retenção de 3 meses no JSONL |
| A-08 | Assinatura VSM não cobria método nem rota | Assinatura v2 canoniza versão, método, rota, timestamp, nonce e hash do corpo; v1 segue aceita por compatibilidade até `webhook_signature_require_v2` ser ativado. Os quatro endpoints VSM passam a exigir POST; com v2 exigida, identificadores em query string são recusados |
| A-09 | Webhook Tiny contabilizava tentativas tarde demais | Contador de tentativas por IP avaliado **antes** da autenticação e sem depender do banco |
| A-10 | Catálogo RBAC de backup incompleto | `baixar`, `excluir`, `importar` e `restaurar` semeadas nos dois schemas e em migration idempotente. Importar/restaurar negadas fora do admin |
| A-11 | Backup importado virava "confiável" | Proveniência gravada dentro do payload assinado (coberta pelo HMAC). Restauração de arquivo externo exige a frase distinta `RESTAURAR IMPORTADO`, com auditoria própria |
| A-12 | API latente aceitava fragmentos SQL | `HeavyQueryOptimizerService::selectLight()` removido (não tinha chamadores) |
| A-13 | Rota desconhecida caía no dashboard | `404` real com página controlada e evento de auditoria |

### Divergência de avaliação registrada

Concordamos com o fato relatado em A-04, mas não com a severidade "Alta". `X-Forwarded-Proto` é
enviado pelo cliente e nenhum navegador o emite: quem o falsifica deixa de ser redirecionado
apenas na própria conexão, sem meio de rebaixar a conexão de terceiro. Somado ao HSTS já
enviado, o risco prático é dívida de hardening, não bypass explorável contra outro usuário.
A correção foi aplicada mesmo assim.

### Observação sobre a origem dos defeitos de integridade

Das 77 divergências de manifesto, 32 já existiam no pacote recebido, junto de dois arquivos de
segurança fora do inventário (`OAuthStateService.php` e `SensitiveHeaderRedactor.php`, ambos
declarando "V104.50"). Ou seja: parte do problema de integridade antecede as correções desta
rodada e tem origem em enxertar arquivos na árvore sem regenerar versão, classmap e manifesto —
exatamente o processo que o critério de liberação abaixo passa a impedir.

## 6. Pendências conhecidas (não corrigidas nesta rodada)

- **Isolamento multiempresa não implementado.** O filtro por empresa/filial não é aplicado em
  nenhuma consulta. Não ativar `commercial.tenant_scope_required` sem implementar a filtragem em
  `TenantContextService` — ligá-lo apenas bloqueia acesso, não isola dados.
- **Verificar a matriz MariaDB da CI.** Os defeitos 2.3(a), (b) e (c) deveriam ter sido
  capturados pelos jobs MariaDB do workflow. Vale confirmar se esses jobs estão realmente verdes
  ou se vinham falhando/truncando sem ninguém notar — o defeito 2.2 do `set -e` é candidato a
  explicar isso.
- **Auditoria estática.** Configuração real de servidor, versões de PHP/MySQL em produção e
  condições de corrida em runtime não foram cobertas por esta revisão.
- **Isolamento multiempresa segue não implementado** (ver item 1 acima): `empresas` e `filiais`
  foram consolidadas em `empresas`, mas nenhuma consulta filtra por tenant. Implementar em
  `TenantContextService` antes de qualquer uso multi-cliente.
- **Ciclo OAuth Tiny V3 precisa de validação em navegador real.** A correção do A-02 foi
  verificada estruturalmente (roteamento, vínculo de usuário, autorização por perfil), mas o
  ida-e-volta cross-site com o provedor só pode ser confirmado com credenciais Tiny reais.
- **Assinatura VSM v2 depende do outro lado.** `webhook_signature_require_v2` permanece `false`
  até a VSM passar a assinar no formato v2; só então método, rota e identificadores ficam
  efetivamente cobertos.

---

## 7. Auto-auditoria da R6 — revisão das próprias correções (B-01 a B-06)

Corrigir 13 achados em uma rodada cria código novo que ninguém revisou. Esta seção é o resultado
de aplicar o mesmo roteiro de auditoria **ao código escrito nesta rodada**, deliberadamente
tratando as correções como suspeitas. Foram encontrados **quatro defeitos reais nas próprias
correções**, todos já corrigidos, e duas suspeitas foram descartadas por verificação.

### Achados confirmados e corrigidos

**B-01 — `AtomicRateCounterService` reproduzia o fail-open que foi criado para eliminar.**
*Severidade: alta.* O serviço criado para corrigir o A-06 (rate limit que sumia quando o banco
falhava) tinha três saídas de erro — diretório não criável, `fopen` falhando, `flock` falhando —
e todas devolviam `limited => false`. Se `storage/cache/security` ficasse inacessível (permissão,
disco cheio, montagem somente-leitura), o limite desaparecia em silêncio: exatamente o defeito
original, movido do banco para o disco. Pior, agora em três chamadores ao mesmo tempo.

*Correção:* `hit()` passa a devolver `degraded: bool`, e **cada chamador declara sua política**,
porque não existe resposta segura única:

| Chamador | Política em degradação | Porquê |
|---|---|---|
| `TinyWebhookSecurityService` | **FECHADO** — recusa o webhook | Canal público protegido por segredo estático; sem limite de tentativas é força bruta |
| `public/pwa_telemetry.php` | **FECHADO** — 503 + `Retry-After` | Telemetria é descartável; aceitar POST público ilimitado não é |
| `RouteRateLimiterService` | **ABERTO, registrado** | Já é o último recurso (o banco caiu); barrar todo o painel porque um diretório também falhou transformaria dois problemas de infraestrutura em indisponibilidade total. Marca `HUB_SECURITY_DEGRADED` e grava no log do servidor |

*Verificação:* criar um diretório exatamente onde o contador espera o arquivo faz `fopen('c+')`
falhar de verdade (`chmod` não serve — a suíte roda como root e root ignora a permissão).
Confirmado `degraded => true`, `limited => false`, `count => 0`. Agora coberto por regressão.

**B-03 — o callback OAuth autorizava por um instantâneo de perfil de até 10 minutos.**
*Severidade: média.* A correção do A-02 passou a gravar `user_id` e `perfil` dentro do state
assinado, e autorizava com esse `perfil`. Mas o state vive até 10 minutos: uma conta desativada ou
rebaixada nesse intervalo ainda completaria a conexão OAuth com a permissão antiga.

*Correção:* a transação assinada passa a estabelecer apenas **identidade**; a **autorização** vem
do estado atual do banco. `AuthRepository::userById()` (que já filtra `ativo=1`) revalida a conta,
e a permissão é avaliada contra o perfil corrente. Conta desativada no intervalo não passa mais.

**B-05 — script destrutivo de CI distribuído dentro do pacote de produção.**
*Severidade: alta.* `scripts/ci/provision-e2e-environment.php`, criado para corrigir o A-03, faz
`DROP DATABASE`, sobrescreve `config/config.php` e cria um administrador com senha conhecida.
A única proteção era uma expressão regular sobre o *nome do banco*. Um erro de variável de
ambiente em um servidor real destruiria configuração e dados.

*Correção:* quatro guardas cumulativas, todas verificadas na prática (cada caminho de recusa foi
executado e imprime a mensagem correta, exit 1):

1. Somente CLI (`PHP_SAPI !== 'cli'` recusa);
2. `HUB_CI_E2E_CONFIRM=1` obrigatório — sem ele o script não roda, mesmo com nome de banco válido;
3. Recusa se `storage/install.lock` existir (é uma instalação real, não um ambiente efêmero);
4. Recusa sobrescrever `config/config.php` que não tenha sido gerado pelo próprio script.

**B-06 — a allowlist de IP isentava a página administrativa de webhooks.**
*Severidade: média.* A correção do A-05 isentava webhooks da restrição por IP com
`str_contains($page, 'webhook')` — casamento por **substring**. Isso acertava os dez webhooks de
entrada, mas também isentava `tiny-webhooks`, que **não é um webhook**: é a tela administrativa
que edita segredo de webhook, CNPJs autorizados e limites (`DashboardController::tinyWebhooks()`).
Com a allowlist ativa, o painel inteiro ficava restrito por IP e justamente essa tela continuava
alcançável de qualquer origem. Não é bypass de autenticação — login e RBAC seguiam valendo —, mas
anula a defesa em profundidade exatamente no ponto mais sensível.

*Correção:* lista exaustiva das dez rotas de webhook de entrada, casada por **igualdade**. Uma
rota de webhook futura que não seja adicionada à lista falha **fechada** (bloqueada pela
allowlist) em vez de abrir o painel por acidente de nomenclatura. O teste inclui uma guarda de
deriva que lê os `case` dos controllers de webhook e confirma que todos estão isentos, avisando
antes de a integração quebrar.

### Suspeitas verificadas e descartadas

- **B-02 — ordenação do callback OAuth × allowlist de IP.** Suspeita de que
  `tiny-v3-callback` ser despachado antes do gate de sessão o deixasse fora da allowlist. Falso: a
  allowlist roda **antes** do callback no `FastRouteDispatcherService`, e o callback é uma
  navegação do **navegador do próprio administrador** (o IP é o dele, não o do provedor OAuth) —
  portanto sujeito à allowlist e corretamente permitido quando o IP está na lista.
- **B-04 — a auditoria de rota inexistente (A-13) poderia ser um vetor de inundação de log.**
  Falso: `DashboardController::dispatch()` chama `Auth::requireLogin()` antes do `switch`, então
  só uma sessão autenticada alcança o `default`. Um anônimo não consegue gerar esses eventos.

### Falsos positivos da própria varredura, registrados para não se repetirem

- `selectLight` ainda aparece em `HeavyQueryOptimizerService.php` — **apenas no comentário** que
  documenta sua remoção. A função não existe mais; o teste de regressão agora remove comentários
  antes de verificar, justamente para não confundir documentação com código.
- `DashboardController.php:341` e `:412` interpolam identificadores em SQL — são os auxiliares
  pré-existentes `count()`/`tableRows()`, protegidos por `safeIdentifier`/`safeSqlFragment`/
  `safeOrderBy` contra listas brancas. Reverificado: não são regressão.
- `ProductionReadinessV24Service::hasColumn()` interpola `$table` em `SHOW COLUMNS` — os três
  chamadores passam literais fixos. Não é injetável.
- `public/.htaccess` nega `.json`, mas o manifesto do PWA é `.webmanifest`. Não quebra o PWA.

### Lacuna de processo corrigida junto

Sete correções da rodada A (A-02, A-06, A-07, A-08, A-11, A-12, A-13) haviam sido entregues **sem
regressão automatizada** — precisamente o erro que o achado A-01 denuncia em outra forma (um gate
que existe mas não prova nada). Foram adicionados `v104_49_3_backup_provenance_test.php` e
`v104_49_3_r6_reauditoria_test.php`, fechando a cobertura. A suíte passou de 30 para **32 testes**.

---

## 8. Melhorias sugeridas (não aplicadas — decisão do responsável pelo produto)

Ordenadas por relação custo/benefício. Nenhuma foi aplicada nesta rodada: todas mudam
comportamento ou arquitetura além do escopo de "corrigir os achados".

### Alta prioridade

1. **Implementar de fato o isolamento multiempresa.** Continua sendo a maior lacuna estrutural:
   `TenantContextService` bloqueia acesso mas nenhuma consulta filtra por empresa. Enquanto isso
   não existir, o sistema não é seguro para mais de um cliente na mesma instalação — e o risco é
   silencioso, porque a tela sugere isolamento que o banco não tem.
2. **Ligar `webhook_signature_require_v2` assim que a VSM assinar em v2.** Enquanto a v1 for
   aceita, método e rota continuam fora da assinatura — a correção do A-08 só fica efetiva quando
   o lado de lá migrar. Vale combinar uma data com a VSM e tratar isso como pendência com prazo,
   não como configuração opcional permanente.
3. **Adotar a matriz MySQL 8 + MariaDB como bloqueio de merge.** Os quatro defeitos de
   portabilidade desta rodada (um deles impedia a instalação) só apareceram em MariaDB real. Os
   jobs existem no workflow; falta torná-los obrigatórios na proteção de branch.
4. **Rotacionar toda chave declarada em `config.php` desta instalação.** Recomendação operacional
   independente do código: `encryption_key`, `backup_signature_key`, `webhook_secret`,
   `token_vault_hmac_key` e demais segredos devem ser rotacionados se houver qualquer dúvida sobre
   exposição, e nunca reaproveitados entre homologação e produção.

### Média prioridade

5. **Gerar o `classmap.php` na instalação/atualização.** O mapa é lido por `app/Core/Autoload.php`
   mas **nada no pacote o gera** — ele vinha com 209 das 236 classes, e as 27 restantes caíam no
   varredor de diretórios a cada requisição. Foi regenerado nesta R6, mas sem um gerador ele volta
   a ficar defasado no próximo arquivo novo. Um script chamado pelo instalador resolve.
6. **Substituir `$GLOBALS['HUB_SECURITY_DEGRADED']` por um serviço de estado de saúde.** O sinal de
   degradação do B-01 hoje só existe em variável global e no log do servidor. Exposto em
   `api/status` ou no painel, o operador vê que a proteção está degradada em vez de descobrir pelo
   incidente.
7. **Unificar os limitadores de taxa.** Coexistem hoje `RouteRateLimiterService` (banco),
   `AtomicRateCounterService` (disco) e o contador de login — três implementações com três
   políticas de falha. Uma fachada única com política declarada por rota reduz a chance de um
   quarto caminho nascer fail-open.
8. **Adicionar um teste E2E autenticado que exercite o ciclo OAuth com um provedor falso.** A
   correção do A-02/B-03 está verificada estruturalmente; o ida-e-volta cross-site só é provado
   com um provedor OAuth de mentira no próprio CI.
9. **Registrar a proveniência também na tabela `backups_banco`.** Hoje ela vive apenas na
   assinatura (`.sig.json`). Na listagem do painel, a distinção depende do prefixo `importado_` no
   nome do arquivo — frágil se alguém renomear.

### Baixa prioridade / dívida técnica

10. **Aposentar `LegacyDatabaseUpgradeController`.** Concentra a maior parte do DDL em runtime do
    sistema, em dezenas de blocos `try/catch` que engolem erro. Com o schema consolidado e as
    migrations versionadas, ele é sobretudo superfície de risco.
11. **Trocar `str_contains`/`str_starts_with` por listas explícitas nas demais decisões de rota.**
    O B-06 foi causado por casamento de substring em uma decisão de segurança; vale varrer os
    outros pontos onde rota é classificada por semelhança de nome.
12. **Publicar um `SECURITY.md` com o modelo de ameaças.** Várias decisões desta rodada (por que a
    rota degrada aberta, por que a v1 ainda é aceita, por que o callback roda fora do gate de
    sessão) estão hoje em comentários de código. Um documento curto evita que a próxima pessoa
    "corrija" uma escolha deliberada.

---

## 9. Critério de liberação

Este pacote está pronto para instalação em homologação. Antes de produção:

- [ ] Confirmar os jobs MariaDB **e** MySQL verdes no workflow (não apenas presentes).
- [ ] Validar o ciclo OAuth Tiny V3 em navegador real com credenciais Tiny.
- [ ] Definir `security.canonical_host`, `security.trusted_proxies` e o redirecionamento HTTPS no
      virtual host / proxy reverso, descartando os cabeçalhos `X-Forwarded-*` vindos da internet.
- [ ] Configurar `security.admin_ip_allowlist` (agora efetiva) ou registrar conscientemente a
      decisão de deixá-la vazia.
- [ ] Rotacionar todos os segredos de `config.php` antes do primeiro uso real.
- [ ] Não ativar `commercial.tenant_scope_required` enquanto o item 1 da seção 8 não estiver feito.
