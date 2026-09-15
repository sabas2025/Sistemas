# Modelo de ameaças e decisões de segurança — Hub de Integração

Melhoria 12 da seção 8 do relatório V104.49.3-R6.

Várias decisões de segurança deste sistema são deliberadas e **contraintuitivas**. Até aqui elas
viviam apenas em comentários de código, o que tem um custo concreto: alguém lê o trecho, acha que
encontrou um defeito e "corrige" — desfazendo a escolha. Este documento existe para que a próxima
pessoa saiba o que é decisão e o que é dívida.

Se você vai alterar qualquer coisa listada em **Decisões deliberadas**, leia a justificativa antes.
Se discordar, mude com um teste que prove o novo comportamento, não em silêncio.

---

## 1. O que este sistema protege

O Hub fica entre o ERP **Tiny/Olist** e o sistema **VSM**, movendo pedidos, produtos, estoque e
documentos fiscais. Os ativos, em ordem de gravidade se comprometidos:

| Ativo | Por que importa |
|---|---|
| Tokens OAuth e segredos de integração | Dão acesso de escrita ao ERP do cliente |
| Segredos de `config.php` | Quebram assinatura de backup, cofre de tokens e cadeia de auditoria |
| Backups do banco | Cópia completa dos dados operacionais |
| Fila de integração | Injetar itens na fila move estoque e emite documentos |
| Trilha de auditoria | Se adulterável, um incidente se torna ininvestigável |
| Dados operacionais (pedidos, NF-e) | Dados de clientes finais |

## 2. Quem é o adversário considerado

1. **Anônimo na internet.** Alcança: login, site comercial, `api/csp-report`, `api/status`,
   telemetria do PWA e os dez webhooks de entrada. É o adversário que mais importa.
2. **Sistema externo comprometido** (Tiny ou VSM). Fala com os webhooks com credenciais válidas.
3. **Usuário autenticado de perfil baixo** (operador/gerente) tentando escalar privilégio.
4. **Administrador legítimo cometendo um erro operacional** — restaurar um backup de origem
   desconhecida, rodar um script destrutivo no servidor errado. Tratado como ameaça de verdade.
5. **Quem tem acesso de leitura ao pacote** (o zip de distribuição). Por isso nenhum segredo real,
   hostname de cliente ou dado de produção pode ir dentro dele.

**Fora do escopo:** invasor com root no servidor; invasor com acesso direto ao MySQL; ataque físico;
comprometimento da cadeia de suprimentos do PHP ou do sistema operacional.

## 3. Decisões deliberadas (não "corrija" sem ler)

### 3.1 O rate limit de rotas degrada ABERTO; o de webhook e telemetria degrada FECHADO
`RateLimitService::POLICIES` declara, por superfície, o que acontece quando o contador não
funciona. Não existe resposta correta única:

- **Webhook Tiny e telemetria PWA → FECHADO.** São canais públicos. Sem limite de tentativas, o
  webhook vira força bruta contra um segredo estático e a telemetria vira POST público ilimitado.
- **Rotas do painel → ABERTO, com registro.** Este caminho já é o último recurso: só roda quando o
  banco falhou. Bloquear todo o painel porque `storage/cache/security` também ficou inacessível
  transformaria dois problemas de infraestrutura em indisponibilidade total.
- **Login → nem um nem outro.** Se banco e disco falharem, escala para um contador de sessão
  (fraco, mas real) em vez de liberar força bruta ou trancar todos para fora.

Toda degradação aparece em `SecurityHealthService`, no painel e em `api/status`.
**Não acrescente um limitador sem declarar a política:** `RateLimitService` recusa superfície
desconhecida em tempo de chamada, de propósito.

### 3.2 O callback OAuth roda FORA do gate de sessão
`tiny-v3-callback` é despachado antes de `Auth::requireLogin()`. Isso é necessário: o retorno do
provedor é uma navegação cross-site e o navegador **não** envia o cookie `SameSite=Strict`. Com o
gate na frente, o fluxo OAuth nunca completava.

Ele **não** é anônimo. A identidade vem da transação OAuth assinada (uso único, com TTL) e a
**autorização vem do banco no momento do callback** — `AuthRepository::userById()` revalida a conta
(filtra `ativo=1`) e a permissão é avaliada contra o perfil atual, não contra o instantâneo gravado
no state (que pode ter até 10 minutos). Conta desativada nesse intervalo não passa.

A allowlist de IP administrativa roda **antes** do callback e continua valendo: o retorno vem do
navegador do próprio administrador, então o IP é o dele.

### 3.3 A assinatura v1 de webhook ainda é aceita
A v2 cobre versão, método HTTP, rota canônica, timestamp, nonce e hash do corpo. A v1 cobre apenas
timestamp, nonce e corpo — uma assinatura capturada para uma rota podia ser reapresentada em outra.

Instalações **novas** nascem com `security.webhook_signature_require_v2 = true`. Instalações
existentes mantêm a v1 aceita **apenas** enquanto a VSM não migrar, e cada webhook v1 aceito vira
evento de segurança e controle degradado visível. Isto é dívida com prazo, não configuração
permanente.

### 3.4 Rota classificada por IGUALDADE, nunca por substring
O achado B-06 nasceu de `str_contains($page, 'webhook')`: isentava os webhooks reais da allowlist
de IP e, junto, `tiny-webhooks` — a página administrativa que edita segredo e CNPJs autorizados. O
mesmo padrão existia em `WafService::shouldInspect()`.

`RouteCatalogService` é a fonte única. Listas exaustivas, casamento por igualdade, e rota
desconhecida cai no lado seguro (painel: sujeita à allowlist e inspecionada). **Nunca** classifique
rota por semelhança de nome.

### 3.5 Backup importado não é equivalente a backup gerado localmente
Assinar um arquivo importado prova apenas que este Hub o ingeriu e validou — não que a origem seja
confiável. A proveniência (`local_generated` / `imported_untrusted`) fica **dentro do payload
assinado**, coberta pelo HMAC, e restaurar um importado exige digitar `RESTAURAR IMPORTADO`.

A coluna `backups_banco.proveniencia` existe para listagem e filtro. **A decisão de restauração lê
a assinatura, nunca a coluna** — renomear o arquivo ou editar a linha não muda nada.

### 3.6 O redirecionamento HTTPS não fica no `.htaccess`
A regra antiga decidia por `X-Forwarded-Proto` e montava o destino a partir de `Host` — ambos
enviados pelo cliente e avaliados pelo Apache **antes** de o PHP aplicar `TrustedProxyService`.

Foi removida em vez de endurecida: manter a condição do cabeçalho é a única forma de não criar loop
atrás de um proxy que termina TLS. O redirecionamento é feito por `App::enforceProductionSafety()`,
que só aceita `X-Forwarded-Proto` de proxies declarados em `security.trusted_proxies` e fixa o
domínio em `security.canonical_host`. Em produção, prefira redirecionar no virtual host
(veja `nginx.conf.example`), descartando os `X-Forwarded-*` vindos da internet.

### 3.7 Rota inexistente devolve 404 de verdade
Antes caíam no dashboard, o que mascarava links quebrados e fazia qualquer URL responder 200 — falso
positivo em smoke test. A página 404 não reflete rota, query nem URI da requisição. O evento de
auditoria só é gerado para sessão autenticada (`Auth::requireLogin()` roda antes do `switch`), então
um anônimo não consegue inundar o log.

---

## 4. Dívida conhecida

| Item | Situação |
|---|---|
| Isolamento multiempresa | Ver seção 5. Não use para mais de um cliente sem ler. |
| `LegacyDatabaseUpgradeController` | Depreciado; concentra DDL em runtime com `try/catch` que engolem erro. Desligado por padrão. |
| Assinatura v1 de webhook | Aceita até a VSM migrar (3.3). |

## 5. Isolamento multiempresa — leia antes de ativar

`commercial.tenant_scope_required` **bloqueia** acesso a rotas operacionais sem empresa selecionada.
Isso não é isolamento de dados por si só.

> A chave vive em `commercial`, não em `security` — é assim que `TenantContextService::strictEnabled()`
> a lê, e é onde `config.example.php` e o `install.php` a declaram. Até 2026-09-15 este documento
> dizia `security.tenant_scope_required`, que **não existe**: quem seguisse o passo 3 abaixo ligava
> uma chave inerte e podia concluir que o bloqueio estava ativo.

O isolamento de dados é aplicado por `TenantScopeService`, que filtra por `empresa_id` nas tabelas
registradas em seu catálogo, e é verificado estaticamente por `scripts/ci/tenant-scope-check.php` —
toda consulta a tabela com escopo precisa passar pelo serviço ou estar numa exceção justificada.

> ### Estado do isolamento em 2026-09-15 — leitura isolada, escrita de entrada ainda não
>
> A validação de runtime foi feita contra banco real com duas empresas. Na primeira passagem
> **reprovou**: nada selecionava a empresa ativa, `$_SESSION['tenant_empresa_id']` nunca existia e
> `TenantScopeService::where()` devolvia predicado vazio — a tela de pedidos mostrava as linhas
> das duas empresas.
>
> **Corrigido (achado H-01).** `usuarios` ganhou `empresa_id` (migration `20260915_014`) e
> `Auth::finalizeLogin()` passou a carimbar a sessão. Medido depois, lendo o arquivo de sessão de
> cada usuário:
>
> | usuário | sessão | a tela de pedidos mostra |
> |---|---|---|
> | `alfa@` (empresa 1) | `tenant_empresa_id\|i:1` | ALFA-001, ALFA-002, LEGADO-1 |
> | `beta@` (empresa 2) | `tenant_empresa_id\|i:2` | BETA-001, BETA-002, LEGADO-1 |
> | admin sem empresa | sem a chave | todas as linhas |
>
> Usuário sem empresa continua vendo tudo, de propósito: aplicar a migration não esvazia tela de
> ninguém, e o isolamento entra em vigor por usuário conforme as empresas são atribuídas.
>
> ### ⚠️ O que ainda NÃO está isolado: a escrita vinda de fora
>
> Webhook e processamento de fila rodam **sem sessão de usuário**. Sem empresa ativa,
> `TenantScopeService::applyToInsert()` devolve o SQL intacto — então a linha nasce com
> `empresa_id` NULL e, por `where()` incluir NULL, fica **visível a todas as empresas**. É o caso
> de `ApiController.php:739`, que grava `pedidos_integracao` no caminho de entrada.
>
> Ou seja: hoje a **leitura** está isolada para quem entra pelo painel com empresa atribuída; a
> **entrada de dados** ainda não tem de onde tirar a empresa. Resolver isso exige decidir a fonte
> (token do webhook por empresa? coluna na fila? empresa por conexão Tiny/VSM?) — decisão de
> produto, como foi a do vínculo usuário↔empresa.
>
> A atribuição é feita na tela **Usuários e Permissões** (campo *Empresa*, com a coluna na lista).
> Deixar em branco significa *sem empresa*, e o usuário vê os dados de todas — mantenha assim só
> para administração geral. Trocar a empresa de alguém incrementa `session_version`, ou seja,
> **revoga a sessão aberta dele**.

**Antes de usar com mais de um cliente na mesma instalação:**

1. Rode `php scripts/ci/tenant-scope-check.php` e confirme que passa sem exceções novas.
   Atenção: este portão fica **verde** mesmo com o isolamento inerte — ele verifica que a consulta
   passa pelo serviço, não que exista empresa ativa. Verde aqui não é prova de isolamento.
2. Aplique a migration `20260914_010_tenant_isolation.sql` e confira o backfill.
3. Ligue `commercial.tenant_scope_required`.
4. Atribua a empresa a cada usuário na tela **Usuários e Permissões** — sem isso ele vê tudo. E
   **resolva a empresa no caminho de entrada** (o aviso acima), ou os pedidos que chegarem por
   webhook ficarão visíveis a todas as empresas.
5. **Valide contra o seu banco**, com dados de duas empresas, que nenhuma tela mostra dados da
   outra. Dá para reproduzir sem Docker; a receita está no `CLAUDE.md`, na seção de validação
   contra banco real.

Lembre ainda que `where()` deixa passar linhas com `empresa_id IS NULL` — legado anterior à
migration, visível a todas as empresas por decisão deliberada. Onde herdar esse histórico for
errado, use `whereStrict()`.

## 6. Reportando uma vulnerabilidade

Relate ao responsável técnico da instalação. Inclua versão (`VERSAO.txt`), rota afetada e
`trace_id` quando houver — toda resposta de erro carrega um, e ele localiza o evento na auditoria.
Não abra issue pública com detalhe explorável.

## Isolamento multiempresa — estado medido em 2026-09-15

Decisão de produto desta data: **a instalação opera com empresa única**. O que isso muda, e o que
não muda:

- **Leitura** isola pela empresa da **sessão** (`TenantScopeService::where()`), e mantém visíveis as
  linhas legadas com `empresa_id` NULL. A leitura **não** recorre à empresa única: alargá-la poderia
  esconder linha já carimbada com outra empresa, e tela que perde dado sem aviso é pior do que a
  lacuna que se quer fechar.
- **Gravação** carimba a empresa da sessão ou, fora dela — webhook, fila, worker, cron —, a única
  empresa cadastrada (`TenantScopeService::empresaParaGravar()`). Regra idêntica à das migrations
  `20260914_010` e `20260915_014`: `COUNT(*) = 1` então `MIN(id)`.
- **Com duas ou mais empresas a entrada sem sessão continua sem resposta** e a linha nasce com
  `empresa_id` NULL, visível a todas. Isto é deliberado: atribuir a uma delas seria adivinhar. Antes
  de operar vários clientes reais é preciso decidir de onde a entrada tira a empresa — e note que o
  payload da VSM não carrega identificador de empresa.

Medido contra MariaDB 10.11, por HTTP, com webhook VSM assinado (HMAC v2): a linha de entrada
nascia com `empresa_id` NULL antes da correção e com `1` depois, no mesmo banco. Pela tela, um
usuário da empresa 2 deixou de enxergar a linha gravada para a empresa 1.

Não se conclui daqui que o Hub está pronto para multicliente: o que foi medido é a instalação de
empresa única.
