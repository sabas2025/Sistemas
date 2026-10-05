# Correção D-03 — alerta de webhook Tiny repetido nunca chegava (tipo fora do ENUM)

**Data:** 2026-10-05 · **Release:** V104.49.3-R7 · **Origem:** auditoria da tela Logs/Relatórios
durante a gravação do vídeo-demo (3 linhas "Falha ao criar notificação").

## Identificação
A notificação "Webhook Tiny reenviado várias vezes" nunca era criada em produção (MySQL/MariaDB
strict): `TinyWebhookService` passava a `NotificationService::criar()` um `tipo` fora do ENUM
`notificacoes.tipo`.

## Evidência
- **Caller:** `app/Services/TinyWebhookService.php:115`
  `NotificationService::criar('webhook_repetido', 'Webhook Tiny reenviado várias vezes', …, 'alerta', …)`.
- **Schema:** `notificacoes.tipo ENUM('pedido_novo','pedido_integrado','erro_integracao','fila','estoque','baixa_estoque','produto_novo','integracao_sucesso','nota_fiscal','sistema')` — **`'webhook_repetido'` não está na lista** (confirmado no módulo `observabilidade.sql` e no consolidado).
- **Reprodução (MariaDB strict):** INSERT com `tipo` fora do ENUM é rejeitado; o `catch(Throwable)`
  de `criar()` registra "Falha ao criar notificação" e retorna 0. Em modo não-estrito o valor viraria
  string vazia — por isso só aparece em servidor strict (o hub01).

## Arquivo/classe/método/tabela
`TinyWebhookService` (ramo de webhook duplicado, `recebido_repetido >= 3`) → `NotificationService::criar()` → `notificacoes`.

## Gravidade
**Média** — observabilidade. Sem perda de dado; mas um alerta operacional útil (o Tiny reenviando o
mesmo webhook, sinal de que pode não ter recebido HTTP 200) **nunca disparava**.

## Causa raiz
Valor de `tipo` fora do ENUM. Mesmo padrão "catch que esconde" dos achados G-01/I-15/D-02: o erro de
schema é rebaixado a best-effort e some.

## Impacto
Quando o Tiny reenvia o mesmo webhook 3+ vezes, o Hub deveria alertar o operador. Em produção strict
o alerta era descartado silenciosamente — o operador não via o sinal de falha no endpoint.

## Correção aplicada (Opção B — menor risco de quebra)
`TinyWebhookService.php`: `tipo` `'webhook_repetido'` → **`'erro_integracao'`** (valor já existente no
ENUM). A categoria é só rótulo; a urgência segue em `severidade='alerta'`, o significado no
título/mensagem e o link `?page=tiny-webhooks&status=duplicado`. **Nenhum schema, migration ou ENUM
foi tocado.**

**Por que B e não "adicionar ao ENUM" (A):** conferido que **nenhum leitor** filtra
`tipo='webhook_repetido'` (só havia a escrita) e **nenhum teste** trava a lista do ENUM. B não mexe em
schema nem exige migration no cliente, elimina a superfície de paridade (módulo × migration ×
consolidado) e não pode gerar drift — portanto não quebra o Hub. A seria "mais correta no papel" mas
introduz schema+migration a aplicar e manter em três lugares, risco desnecessário já que ninguém
depende da categoria.

## Risco da correção
Mínimo. 1 linha em `app/`, sem schema. A notificação passa a ser criada; categoria muda de uma
inexistente para `erro_integracao`.

## Compatibilidade
PHP 8.x, MySQL 8 e MariaDB 11.4. Sem migration, sem janela.

## Como testar
- **Estático:** `tests/enterprise/v104_49_3_notificacao_tipo_enum_test.php` — lê o ENUM do consolidado
  e exige que **todo** 1º argumento literal de `NotificationService::criar()` em `app/` e `workers/`
  pertença a ele (33 callers varridos, todos válidos). Pega a classe inteira do defeito. Conferido
  contra o defeito reposto: reprova nomeando `TinyWebhookService.php: 'webhook_repetido'`.
- **Runtime (MariaDB strict):** `criar('erro_integracao', …)` insere sem erro (medido ao validar o
  seed em `STRICT_ALL_TABLES`: `notificacoes` gravada, 0 "Falha ao criar notificação").

## Como reverter
`git revert` da linha (volta a `'webhook_repetido'`). Sem passos de dados.

## Status
**corrigido e validado** (estático com regressão nos dois sentidos + insert validado em strict mode).

## Observação de origem (não é bug do produto)
As 3 linhas "Falha ao criar notificação" vistas na tela de Logs do hub01 eram do
**seed de demonstração** (`seed-demo-hub.php`, seção 10), que passava `pedido`/`divergencia`/`integracao`
(fora do ENUM). Corrigido no próprio script (fora do repositório/zip) para valores válidos; validado em
strict mode com 0 falhas. O seed revelou o defeito real do produto (D-03), mas não é parte dele.
