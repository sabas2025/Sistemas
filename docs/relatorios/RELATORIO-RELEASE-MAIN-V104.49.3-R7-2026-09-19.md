# Relatório de release — `main` · HUB Tiny/VSM `V104.49.3-R7+20260917.1`

Data: 19/09/2026
Branch: `main`
Merge commit de topo: `a9e36ee` (Merge PR #7)
Repositório: `sabas2025/Sistemas`

## 1. Identificação da release

| Item | Valor |
|---|---|
| Versão | `V104.49.3-R7+20260917.1` (`SystemVersionService::BUILD = '20260917.1'`) |
| HEAD da `main` | `a9e36ee` |
| Linha de trabalho | R7 (sobre R5 base, R6 reauditoria) |
| Runtime alvo | PHP 8.x · MySQL 8 / MariaDB 11.4 (matriz de CI) e MariaDB 10.11 (validação local) |
| Arquitetura | MVC vanilla, sem Composer, autoloader por classmap |
| Estrutura | 136 tabelas · 1.562 colunas · 560 índices |

## 2. Composição desta release

A `main` chegou a este estado por dois merges:

- **PR #6 — `1306305`** — Auditoria de segurança R7 (achados I-01…I-27) + build `20260917.1`: importação do MyOuro GraphQL (somente leitura, token AES-256-GCM com AAD por empresa), executor de upgrade R7 (`scripts/upgrade-r7.php`, parser que recusa `DELIMITER`/`/*!`, migrations 008–017), isolamento multiempresa fechado (leitura nega sem contexto, escrita bloqueia sem contexto), e correção do manifesto de integridade (831 entradas, 0 FAILED).
- **PR #7 — `04c1331`** — **correção do instalador desta release** (detalhada abaixo).

## 3. O que muda em relação ao PR #6 (delta do PR #7)

### Problema corrigido — instalação limpa caía em HTTP 409 no dashboard

Numa instalação limpa via `public/install.php`, o administrador nascia com
`usuarios.empresa_id` **NULL** e `configuracoes_integracao.integracao_empresa_id`
**NULL**. Na primeira sessão autenticada isso batia no gate de isolamento
(`IntegrationTenantService::enforceRequest` → **HTTP 409**), e o dashboard e as
rotas operacionais não carregavam sem um passo manual no banco.

O gate está **correto** — o defeito era o seed do instalador não vincular a
empresa única que os próprios módulos já semeiam (`empresas` id=1).

### Correção (`public/install.php`, `install_admin_user`)

- Resolve a empresa única com a **mesma regra** das migrations 010/014 e de
  `EmpresaCatalogService::empresaUnicaId()`: `COUNT(*)=1` então `MIN(id)`.
- Insere o admin já com `empresa_id` quando a coluna existe.
- Vincula `configuracoes_integracao.integracao_empresa_id` à mesma empresa,
  **sem sobrescrever** um vínculo diferente (só grava se NULL ou já igual).
- `install.php` é autônomo (não carrega o autoloader do Hub — lição I-21): a
  existência das colunas é conferida por `information_schema` cru, que aceita
  placeholder (lição I-10).
- Tudo dentro da transação já existente; rollback preservado.
- `deve_trocar_senha` permanece 1 (troca obrigatória no primeiro login) — regra
  de negócio intacta.

Arquivos alterados pelo PR #7: `public/install.php` (+38/−2) e
`CHECKSUMS-SHA256.txt` (+1/−1). Nenhuma alteração de schema, de regra de negócio
de runtime ou de assinatura pública.

## 4. Validações executadas

### 4.1 CI (GitHub Actions) no head exato `04c1331`

- **Hub CI run #23** → `completed / success`, incluindo a matriz de runtime
  **MySQL 8.0 + MariaDB 11.4**, a suíte **E2E** (Playwright) e os **14 portões**.
- PR #7 mergeada com `mergeable_state: clean`, sem conflito e sem review
  bloqueando.

### 4.2 Local — instalação a partir do **ZIP final da `main`** (MariaDB 10.11 real)

Banco descartável, usuário MySQL exclusivo, `public/install.php` executado
ponta a ponta por HTTP (autorização CLI → authorize → install):

| Verificação | Resultado |
|---|---|
| Integridade do pacote extraído (FIM) | **831/831 OK** |
| Pacote limpo (sem `config.php` / `install.lock`) | ✅ |
| Instalação concluída | 136 tabelas, 1.562 colunas |
| `usuarios.empresa_id` | **1** (antes da correção: NULL) |
| `configuracoes_integracao.integracao_empresa_id` | **1** (antes: NULL) |
| login → troca de senha obrigatória | 302 para `trocar-senha` (correto) |
| dashboard após a troca | **200** (antes: 409) |
| rotas operacionais (fila, fila-morta, divergencia-estoque, baixas-estoque, fiscal, produtos, usuarios, auditoria, configuracoes) | **todas 200** |
| rota desconhecida | **404** (dispatcher sadio) |

### 4.3 Local — suíte e portões (checkout limpo da `main`)

- Suíte enterprise **47/47** (inclui o contrato do instalador, que roda as
  funções reais de `public/install.php`).
- 14 portões estáticos verdes; manifesto FIM **831/831 OK**.

## 5. Ordem de implantação (migrations de capacidade)

As migrations são de **execução manual** (não há executor automático de
diretório). Ordem recomendada, após backup verificado:

1. `011` (índices)
2. `013` (logs + sessões)
3. `015` (remove o índice duplicado da fila; barata, sem janela)
4. agendar `worker_retencao.php` no cron
5. `016` (índice de `integration_events`; ~0,13 s, sem janela)
6. `012` (**PK BIGINT — exige janela de manutenção**: workers parados, webhooks
   drenados, backup verificado)

## 6. Pendências abertas (não são defeitos — decisões/ações de operação)

- **H-01 para 2+ empresas** — resolvido para empresa única; com duas ou mais, a
  escrita sem sessão (webhook/fila/worker/cron) não tem como decidir a empresa,
  e o payload da VSM não a carrega. Exige decisão de produto.
- **Migration `012` (PK BIGINT)** — pendente, exige janela de manutenção.
- **Marcar `Hub CI / gate` como *required*** na proteção de branch — ação de
  quem administra o repositório (Settings → Branches).
- **Rotação de segredos** (`php scripts/rotate-secrets.php --audit`).
- **Ligar `security.webhook_signature_require_v2`** quando a VSM migrar.
- **Ciclo OAuth Tiny V3 completo** e qualquer chamada real a Tiny/VSM/MyOuro —
  dependem de credenciais reais; ainda sem validação de runtime.

## 7. Rollback

- **Do PR #7 (a correção do instalador):** `git revert 04c1331` e regenerar a
  linha do `public/install.php` no `CHECKSUMS-SHA256.txt`.
- A correção não altera schema; instalações já feitas continuam válidas.

## 8. Escopo do que foi provado

Este relatório cobre: integridade do pacote, instalação limpa, autenticação,
sessão, troca de senha obrigatória e as rotas do painel por HTTP contra banco
real, além da CI (matriz MySQL/MariaDB + E2E). **Não** cobre chamadas reais ao
Tiny/VSM/MyOuro (dependem de credenciais) nem o cenário de 2+ empresas
(bloqueado por design — H-01). Conforme a regra do projeto, a release **não** é
declarada "100% funcional"; o verde cobre a tabela acima, não o resto.
