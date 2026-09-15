# Relatório V104.49.3-R7 — as 12 melhorias da seção 8 aplicadas

Data: 2026-09-14
Base: V104.49.3-R6 (promovida a R7: o conteúdo mudou e o artefato precisa de identidade própria)
Escopo: os 12 itens da seção 8 do relatório R6, **incluindo o isolamento multiempresa**

---

## Resumo

| # | Melhoria | Situação |
|---|---|---|
| 1 | Isolamento multiempresa | **Aplicada** — 31 tabelas, 168 consultas, verificação estática na CI |
| 2 | Exigir assinatura v2 de webhook | **Aplicada** — padrão em instalação nova; v1 vira aviso visível |
| 3 | Matriz MySQL/MariaDB bloqueando merge | **Aplicada** — job `gate` agregador |
| 4 | Rotação de segredos | **Aplicada** — ferramenta + auditoria + portão de CI |
| 5 | Gerar `classmap.php` | **Aplicada** — gerador, instalador e portão de CI |
| 6 | Estado de saúde no lugar de `$GLOBALS` | **Aplicada** — `SecurityHealthService`, exposto em `api/status` |
| 7 | Unificar limitadores de taxa | **Aplicada** — `RateLimitService`; corrigiu um defeito de atomicidade |
| 8 | E2E do ciclo OAuth | **Parcial, deliberadamente** — ver seção 8 |
| 9 | Proveniência em `backups_banco` | **Aplicada** — coluna + migration + UI |
| 10 | Aposentar `LegacyDatabaseUpgradeController` | **Aplicada** — 19 rotas mortas removidas; controller desligado |
| 11 | Listas explícitas no lugar de substring | **Aplicada** — `RouteCatalogService`; achou uma 2ª ocorrência do B-06 |
| 12 | `SECURITY.md` com modelo de ameaças | **Aplicada** |

Três defeitos reais foram encontrados **durante** a aplicação, nenhum deles previsto na seção 8.
Estão nas seções 7, 11 e 1.

---

## 1. Isolamento multiempresa

Era a maior lacuna estrutural: `empresas`/`filiais` existiam, `TenantContextService` guardava a
empresa ativa e `tenant_scope_required` bloqueava rotas — mas **nenhuma consulta filtrava por
empresa**. A tela sugeria um isolamento que o banco não tinha.

**Schema.** `empresa_id INT NULL` + índice em **31 tabelas** operacionais (pedidos, produtos,
estoque, fiscal, fila). Migration `20260914_010_tenant_isolation.sql`, idempotente. O backfill só
roda quando existe **exatamente uma** empresa cadastrada; com duas ou mais ele é pulado de
propósito — não há como adivinhar a quem o histórico pertence, e chutar seria pior.

**Camada.** `TenantScopeService` com `where()`, `whereStrict()`, `stamp()`, `assertRow()` e os
reescritores `applyToSelect()`/`applyToInsert()`/`run()`.

**Aplicação.** **168 consultas** em 25 arquivos passaram a aplicar o escopo. A transformação foi
feita através do reescritor testado, não editando SQL à mão em cada ponto.

**Propriedade de segurança que torna isto seguro de instalar:** sem empresa no contexto — o caso
de toda instalação atual — o reescritor é **passagem direta**: SQL idêntico, parâmetros idênticos.
O comportamento de hoje não muda. O isolamento passa a valer quando há empresa selecionada.

**Verificação.** `scripts/ci/tenant-scope-check.php` varre `app/` e `workers/` e reprova qualquer
consulta a tabela do catálogo que não aplique o escopo nem esteja numa exceção **com justificativa
escrita** (workers, diagnóstico e métrica agregada da instalação — 67 consultas). Sem esse portão,
a próxima consulta escrita à mão voltaria a vazar em silêncio, que foi exatamente como a lacuna
original sobreviveu.

### Defeito encontrado durante a aplicação

A primeira versão do verificador procurava `FROM <tabela>` literal — e por isso **não via** SQL com
o nome da tabela interpolado. `DashboardController::count()/tableRows()`,
`DashboardMetricsService` e `OperationCenterService` montam consultas assim e servem **nove
tabelas com escopo** através de `SAFE_TABLES`. Eles teriam continuado contando linhas de todas as
empresas nos cartões do dashboard, com o portão verde. O verificador foi ensinado a cobrar SQL
interpolado, e os cinco pontos foram corrigidos.

### Risco desta entrega, declarado

A reescrita altera o SQL de 168 pontos e **não foi executada contra um banco** — não há MySQL nem
Docker no ambiente desta sessão. O que foi feito no lugar:

- 25 testes exercitam o reescritor nos casos que quebram implementação ingênua: `LIMIT ?`
  (placeholder **depois** do ponto de inserção), `ON DUPLICATE KEY UPDATE`, palavra reservada
  dentro de string literal, consulta sem `WHERE`, `INSERT ... SELECT`;
- os **136 SQL literais** reescritos foram verificados um a um: nº de `?` igual ao nº de
  parâmetros, parênteses balanceados, colunas do `INSERT` iguais aos valores;
- auditoria de equivalência de conexão: nenhum ponto trocou de banco no modo modular;
- auditoria de tipo de retorno: nenhum ponto usava o retorno de `execute()` como booleano.

**Antes de usar com mais de um cliente na mesma instalação, valide contra o seu banco com dados de
duas empresas.** O procedimento está na seção 5 do `SECURITY.md`.

## 2. Assinatura v2 de webhook

Instalação **nova** nasce com `webhook_signature_require_v2 = true` — numa base nova não existe
integração legada e manter a v1 aceita seria dívida no dia zero. Instalação existente mantém a v1
até a VSM migrar, mas cada webhook v1 aceito passa a gerar evento de segurança e a aparecer como
controle degradado no painel e em `api/status`. Deixou de ser um `false` silencioso.

## 3. Matriz de banco bloqueando merge

Proteção de branch não lida bem com job de matriz (o nome do check muda por entrada). Foi criado o
job **`gate`**, que depende de `static-enterprise`, `mysql-runtime` (MySQL 8 + MariaDB 11.4) e
`e2e-authenticated` e só fica verde se todos passarem. Marque `Hub CI / gate` como *required* em
Settings → Branches e a matriz inteira passa a bloquear o merge.

## 4. Rotação de segredos

- `SecretStrengthService`: detecta segredo vazio, curto, de baixa entropia, **repetido entre
  chaves** e igual a valor de exemplo/CI publicado. Nunca devolve o valor, só o veredito.
- `scripts/rotate-secrets.php`: audita, simula e rotaciona. Sempre grava backup do `config.php`
  antes, e o cabeçalho documenta **o que cada rotação invalida** (assinaturas de backup existentes,
  dados cifrados, cadeia de auditoria, o outro lado da integração).
- `scripts/ci/secret-hygiene-check.php`: reprova a CI se o `config.example.php` trouxer segredo
  preenchido ou se algum arquivo do pacote carregar segredo literal.

## 5. Geração do `classmap.php`

O autoloader lia `storage/cache/classmap.php`, mas **nada no pacote o gerava** — estava com 209 de
236 classes. `ClassmapBuilderService` monta o mapa a partir da árvore (por tokens, não regex), o
instalador o gera **antes** do manifesto de integridade, e `php scripts/build-classmap.php --check`
reprova a CI se ele ficar defasado. Hoje: 242 classes.

## 6. Estado de saúde dos controles

`$GLOBALS['HUB_SECURITY_DEGRADED']` morria no fim da requisição e só deixava rastro no log do
servidor — o operador descobria pelo incidente. `SecurityHealthService` persiste com TTL, expõe em
`api/status` e nomeia cada controle em português. Nenhum ponto do código usa mais a variável global.

## 7. Fachada única de rate limit

Três implementações com três políticas de falha e nenhuma declarada. `RateLimitService` tem **uma**
tabela de políticas; superfície sem `on_storage_failure` é **recusada em tempo de chamada**, para
que o próximo limitador não nasça fail-open como o A-06.

### Defeito encontrado durante a aplicação

`LoginRateLimitFallbackService` lia com `LOCK_SH` e escrevia com `LOCK_EX` em chamadas
**separadas** — o mesmo defeito de atomicidade do achado A-07, que na ocasião foi corrigido apenas
no contador do PWA. Duas tentativas simultâneas liam o mesmo valor e o limite era ultrapassado por
corrida, justamente no caminho que existe para conter força bruta de credenciais. Agora usa o
contador atômico.

Também foi resolvida a composição do login: se banco **e** disco falharem, o sistema não libera
força bruta nem tranca todos para fora — escala para o contador de sessão, que é fraco mas é um
limite real, e a degradação fica visível.

## 8. E2E do ciclo OAuth — parcial, e por quê

A intenção era subir um provedor OAuth falso no CI e percorrer o ida-e-volta completo. **Não foi
feito, deliberadamente.** `TinyEndpointSecurityService::validateUrl()` exige HTTPS, recusa host
local e resolve o host para confirmar IP público (proteção contra SSRF). Um provedor falso em
`http://127.0.0.1` só passaria se essas três defesas fossem desligadas no CI — e o teste estaria
validando uma configuração que **não pode existir em produção**, que é o pior tipo de teste verde.

Foi entregue o que só o navegador prova: `tests/e2e/specs/oauth-callback-security.spec.js` cobre o
callback alcançável sem sessão (a correção do A-02), a recusa de state forjado e de state vazio
sem vazar stack trace, e o 404 real em rota desconhecida. **A troca do code por token continua
exigindo credenciais Tiny reais.**

## 9. Proveniência em `backups_banco`

Coluna `proveniencia` + índice + migration `20260914_009` com backfill pelo prefixo `importado_`.
A listagem do painel passou a usar a coluna. **A decisão de restauração continua lendo a
assinatura** (protegida por HMAC), nunca a coluna — renomear o arquivo ou editar a linha não muda
nada de segurança.

## 10. Aposentadoria do `LegacyDatabaseUpgradeController`

A auditoria mostrou que as 19 rotas `atualizar-v*` do `DashboardController` já eram **código
morto**: `FastRouteDispatcherService` intercepta todo `atualizar-v*` antes e envia para
`MigrationController::legacyBlocked()`. Elas davam a impressão de existir uma superfície de DDL em
runtime que o roteamento já não alcançava — e bastava alguém reintroduzir um `case` para religar
tudo sem perceber. As 19 foram removidas e o controller passa a responder **410** salvo liberação
explícita por `commercial.allow_legacy_db_upgrade`, chave que não existe no exemplo nem é gravada
pelo instalador.

## 11. Listas explícitas no lugar de substring

`RouteCatalogService` é a fonte única de classificação de rota: listas exaustivas, casamento por
igualdade, e rota desconhecida cai no lado seguro (painel).

### Defeito encontrado durante a aplicação

O achado B-06 tinha uma **segunda ocorrência**, não relatada na R6: `WafService::shouldInspect()`
também desligava a inspeção com `str_contains($page, 'webhook')`. Ou seja, `tiny-webhooks` — a
página administrativa que edita segredo e CNPJs autorizados — ficava **fora do WAF** além de fora
da allowlist de IP. Ambas agora usam o catálogo, e o teste cobre as duas.

## 12. `SECURITY.md`

Modelo de ameaças, ativos, adversários considerados e — o principal — as **decisões deliberadas**
que parecem defeito e não devem ser "corrigidas" sem ler: por que a rota degrada aberta, por que o
callback OAuth roda fora do gate de sessão, por que a v1 ainda é aceita, por que o redirecionamento
HTTPS saiu do `.htaccess`.

---

## Validação executada

**12 portões, todos verdes** (reexecutados após a última alteração):

| Portão | Resultado |
|---|---|
| `php-lint.sh` | OK |
| `enterprise-tests.sh` | **33 testes** OK |
| `schema-runtime-ddl-check.php` | OK — DDL restrito a 11 arquivos |
| `controller-route-check.php` | OK — 104 rotas, 240 classes |
| `vsm-openapi-check.php` | OK |
| `build-classmap.php --check` | OK — 242 classes |
| `tenant-scope-check.php` | OK — 168 cobertas, 67 isentas justificadas |
| `secret-hygiene-check.php` | OK |
| `build-consolidated-schema.mjs --check` | OK |
| `sql-inventory-check.php` | OK — 134 tabelas |
| `mysql-schema-static-check.php` | OK |
| `mysql-module-parity-check.php` | OK — 134 tabelas em paridade |

Verificações específicas desta entrega: 136 SQL reescritos conferidos um a um (placeholders,
parênteses, colunas × valores); equivalência de conexão no modo modular; nenhum uso do retorno de
`execute()` como booleano.

**Não executado:** os portões de runtime MySQL 8 / MariaDB 11.4. Não há banco nem Docker neste
ambiente. Eles rodam no CI (`mysql-runtime`) e são obrigatórios pelo job `gate`.

## Testes acrescentados

| Arquivo | Cobertura |
|---|---|
| `v104_49_3_tenant_scope_test.php` | 25 asserções: catálogo, reescrita de SELECT/UPDATE/INSERT, posição do parâmetro com `LIMIT ?` e `ON DUPLICATE KEY`, palavra reservada em string, `assertRow`, schema e migration |
| `v104_49_3_admin_ip_allowlist_test.php` | +5: WAF não usa substring, catálogo classifica `tiny-webhooks` como painel |
| `v104_49_3_r6_reauditoria_test.php` | +5: degradação em serviço consultável, políticas declaradas, superfície desconhecida recusada |

## Pendências

- **Validar o isolamento contra banco real com duas empresas** antes de uso multi-cliente
  (procedimento na seção 5 do `SECURITY.md`).
- **Marcar `Hub CI / gate` como required** na proteção de branch — a melhoria 3 criou o job, mas
  marcar o check é uma configuração do repositório.
- **Ligar `webhook_signature_require_v2`** em instalações existentes assim que a VSM migrar.
- **Rotacionar os segredos** antes do primeiro uso real: `php scripts/rotate-secrets.php --audit`.
- **Ciclo OAuth completo** ainda depende de credenciais Tiny reais (seção 8).
