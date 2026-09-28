# Relatório — Correções G-01 e G-02 aplicadas (V104.49.3-R7)

**Data:** 2026-09-14 · **Release:** V104.49.3-R7 (sem mudança de release: correção de defeito, sem
mudança de contrato) · **Origem:** `RELATORIO-AUDITORIA-FINAL-COMPLETA-2026-09-14.md`
**Autorização:** explícita do responsável do produto.

---

## 1. Antes de alterar

**Situação atual.** A auditoria completa final confirmou dois defeitos em `TinyV2Service`, ambos
ausentes nos dois clientes irmãos (`TinyV3Service` e `VsmService`).

**Problema e causa.**

- **G-01 (Alta).** `logEndpoint()` declara `array $request` e passava esse array para
  `SensitiveDataService::maskJson()`, que declara `string $body`. Array não é coercível para string
  em PHP 8 — nem em modo não-estrito. O `TypeError` era garantido nas três chamadas (linhas 45, 52 e
  59) e o `catch(Throwable)` do próprio método o convertia em um aviso best-effort. Resultado:
  `tiny_v2_endpoint_logs` **nunca recebeu um registro sequer**.
- **G-02 (Alta).** `Audit::event()` não mascara nada — a máscara é responsabilidade de quem chama.
  O V2 era o único cliente que não aplicava: a linha da requisição mascarava só a chave `token`, e a
  linha da resposta de **sucesso** gravava `'json'=>$json` cru. Os dois caminhos de **erro** do
  próprio arquivo já mascaravam.

**Arquivos afetados.** `app/Services/TinyV2Service.php` (único arquivo de produção alterado).

**Banco afetado.** Nenhum DDL. `tiny_v2_endpoint_logs` já existe (`database/modules/observabilidade.sql:201`,
colunas `request_body`/`response_body` em `LONGTEXT NULL`). A correção apenas passa a gravar nela.

**APIs afetadas.** Nenhuma. Tiny V2, V3 e VSM continuam recebendo exatamente a mesma requisição —
a alteração é só no que se **registra**.

**Risco.** Baixo. G-01 faz uma tabela hoje vazia começar a receber linhas (`RetentionCleanupService`
já faz o expurgo dela por `criado_em`). G-02 reduz o detalhe da auditoria de suporte — é o custo
aceito, por decisão do responsável.

**Plano de teste.** Prova ponta a ponta do `INSERT` com PDO real, teste de regressão no portão de CI,
os 12 portões, os verificadores independentes e a varredura de chamadas estáticas.

**Rollback.** Restaurar `app/Services/TinyV2Service.php` do pacote anterior
(SHA-256 `eb4fc9180b2943523ae779745f4ba7e959c40747f29a7b9f194b45f037986d66`) e remover
`tests/enterprise/v104_49_3_r7_tiny_v2_log_mascara_test.php`. Sem migration, sem dado a restaurar.

---

## 2. Depois de alterar

### Arquivos modificados

| Arquivo | Alteração |
|---|---|
| `app/Services/TinyV2Service.php` | G-01: `logEndpoint()` passa a usar `SensitiveDataService::sanitizeForStorage()` (aceita `mixed`, mascara, serializa e trunca com hash) em vez de `maskJson()`. G-02: as duas chamadas `Audit::event` de requisição e de resposta de sucesso passam por `SensitiveDataService::mask()` |
| `tests/enterprise/v104_49_3_r7_tiny_v2_log_mascara_test.php` | **novo** — 18 checagens que falham se qualquer um dos dois defeitos voltar |
| `CHECKSUMS-SHA256.txt` | regenerado por último, sobre a árvore congelada |
| `RELATORIO-CORRECOES-G01-G02-2026-09-14.md` | este documento |

`sanitizeForStorage()` não foi criado para isto: **já existia** no `SensitiveDataService` e é o
método próprio para sanitizar payload destinado a armazenamento. O desenho aplicado espelha o
`TinyV3Service::logEndpoint()`, que nunca teve o defeito.

### Migrations
Nenhuma.

### Compatibilidade
PHP 8.x · MySQL 8 · MariaDB 11.4 · hospedagem compartilhada · Tiny V2/V3 · VSM — **inalteradas.**
Instalação nova e atualização: inalteradas (nenhum DDL mudou). Nenhuma assinatura pública alterada
(`logEndpoint` é `private`).

### Testes realizados e resultados

| Verificação | Resultado |
|---|---|
| **Prova E2E do `INSERT`** (PDO SQLite real, três chamadas com os tipos exatos dos call sites) | **3 linhas gravadas** — antes da correção eram **0** |
| Dado sensível nas linhas gravadas | CPF `12*******01`, e-mail `jo************om`, telefone e endereço: **nenhum em claro** |
| CPF dentro do JSON embutido na chave `pedido` | também mascarado |
| **Teste de regressão reverte-e-quebra** | com o defeito reintroduzido de propósito, o teste falha em **5** asserções e sai com código 1; restaurado, sai 0. Arquivo conferido byte a byte após restaurar |
| Novo teste de regressão | **18 checagens OK** |
| `php-lint.sh` | OK |
| `enterprise-tests.sh` | OK — **37 testes** (era 36) |
| `schema-runtime-ddl-check.php` | OK |
| `controller-route-check.php` | OK |
| `vsm-openapi-check.php` | OK |
| `build-classmap.php --check` | OK |
| `tenant-scope-check.php` | OK |
| `secret-hygiene-check.php` | OK |
| `build-consolidated-schema.mjs --check` | OK |
| `sql-inventory-check.php` | OK |
| `mysql-schema-static-check.php` | OK |
| `mysql-module-parity-check.php` | OK |
| **Portões com falha** | **0 de 12** |
| Varredura independente de evidências | 61 OK, 0 falha |
| Reescrita SQL multiempresa | 137 literais OK |
| Chamadas `Classe::metodo()` | **3353 resolvidas, 0 inexistente** |

Exit codes lidos direto do comando, sem `pipe` intermediário.

### Riscos residuais — declarados, não minimizados

1. **A prova do `INSERT` usou SQLite, não MySQL.** O harness substitui `Database::forTable()` por um
   PDO SQLite real e desvia apenas o guard `SHOW TABLES LIKE`, que o SQLite não entende. O `INSERT`
   com nove parâmetros é executado de verdade, mas **não contra MySQL/MariaDB**. Não há banco neste
   ambiente.
2. **A máscara não cobre uma chave `nome` solta.** `SensitiveDataService::KEYS` inclui
   `nome_cliente` e `cliente_nome`, **não** `nome`. Um payload Tiny com `cliente.nome` continua
   gravando o nome em claro. Isso **não foi introduzido** por esta correção — é o comportamento
   existente do catálogo de chaves. Ampliar o catálogo é decisão sua: `nome` é uma chave genérica e
   mascará-la afeta todos os logs do sistema, não só o Tiny V2.
3. **Não foi medido o volume** que `tiny_v2_endpoint_logs` passará a receber sob 500 pedidos/minuto.
   `RetentionCleanupService` já cobre a tabela por `criado_em` e o expurgo roda em
   `workers/worker_retencao.php`, fora do caminho da requisição — mas o dimensionamento real só se
   verifica em produção.
4. **A correção não foi exercitada contra o Tiny V2 real.** Depende de token válido.

### Implantação
Substituir `app/Services/TinyV2Service.php` e adicionar o novo teste. Não parar worker, não parar
webhook, não rodar migration. Nada no caminho de requisição muda.

### Rollback
Seção 1.

**Status: corrigido e validado** (validação estática mais prova de execução com PDO real, nos
limites declarados acima).
