# Melhoria — ErrorCatalog cataloga PEDIDO_STATUS_ERRO

**Data:** 2026-10-05 · **Release:** V104.49.3-R7 · **Origem:** auditoria da tela de Auditoria
(Detalhe pelo Trace ID) durante a gravação do vídeo-demo. Um erro de ciclo de vida de pedido,
semeado para rastreio, exibia **"Erro não catalogado."** no lugar da causa/ação específicas.

## Identificação
A tela forense de Auditoria (busca por Trace ID) mostra **causa provável** e **ação recomendada**
a partir de `ErrorCatalog::explain($codigo)`. O código `PEDIDO_STATUS_ERRO` — emitido quando uma
etapa do ciclo de vida do pedido falha — **não estava no catálogo**, então caía no texto genérico
de fallback, justamente num dos erros mais úteis de se rastrear.

## Evidência
- **Emissor:** `app/Services/PedidoCicloVidaService.php:49`
  `Audit::event('pedido.status.'.$statusNovo, $erro?'erro':'sucesso', [… 'codigo_erro'=>$erro ? 'PEDIDO_STATUS_ERRO' : null …])`.
- **Catálogo (antes):** `app/Services/ErrorCatalog.php` tinha 8 códigos; `PEDIDO_STATUS_ERRO` não
  estava entre eles, então `explain('PEDIDO_STATUS_ERRO')` devolvia o fallback
  `['causa'=>'Erro não catalogado.', 'acao'=>'Use o Trace ID e payload técnico para investigar.', 'gravidade'=>'media']`.
- **Confirmado no vídeo-demo:** o erro de produto (DEMO) semeado para o fluxo de pedido exibia a
  causa genérica na tela de Detalhe da Auditoria.

## Arquivo/classe/método/tabela
`app/Services/ErrorCatalog.php` → `ErrorCatalog::explain()` (mapa estático de códigos). Nenhuma
tabela, migration ou schema envolvido — é mapa em código.

## Gravidade
**Baixa (observabilidade).** Sem perda de dado, sem mudança de fluxo. O erro já era gravado e
rastreável pelo Trace ID; faltava apenas a tradução específica de causa/ação na tela forense.

## Causa raiz
Catálogo incompleto: o código era emitido pelo serviço mas nunca recebeu entrada em `explain()`.

## Impacto
O operador que buscasse pelo Trace ID de um pedido que falhou via "Erro não catalogado." em vez de
ser direcionado ao Ciclo de Vida do Pedido e à etapa que falhou — orientação que o catálogo existe
para dar.

## Correção aplicada
Entrada nova em `ErrorCatalog::explain()`, logo após `QUEUE_PROCESS_ERROR`:

```php
'PEDIDO_STATUS_ERRO' => [
  'causa'=>'Uma etapa do ciclo de vida do pedido (validação de NF-e/XML ou envio ao Tiny) terminou em erro.',
  'acao'=>'Abra o Ciclo de Vida do Pedido pelo Trace ID, identifique a etapa que falhou e o detalhe da auditoria; corrija a causa (ex.: chave NF-e inválida) e reprocesse.',
  'gravidade'=>'alta'
],
```

Só adição ao mapa; nenhuma assinatura, chamada ou comportamento existente foi tocado. O fallback
genérico permanece intacto para os demais códigos.

## Risco da correção
Mínimo. Uma entrada num mapa estático de código, sem efeito fora da tela de Auditoria. Reversível
por `git revert`.

## Compatibilidade
PHP 8.x, MySQL 8 e MariaDB 11.4. Sem migration, sem schema, sem janela.

## Como testar
- **Estático + runtime (classe folha):**
  `tests/enterprise/v104_49_3_errorcatalog_pedido_status_test.php` carrega `ErrorCatalog`
  (sem dependências), mede o fallback genérico como régua e exige que
  `explain('PEDIDO_STATUS_ERRO')` devolva causa **e** ação **diferentes** do genérico, com código
  e gravidade válidos; e confere que `PedidoCicloVidaService` **ainda emite** o código (âncora no
  ternário do `Audit::event`, não em comentário). Conferido nos dois sentidos: verde com a entrada
  presente; com a entrada removida do catálogo, reprova nas duas asserções de "não é o fallback".
- **Runtime (já observado no vídeo-demo):** a tela de Detalhe da Auditoria pelo Trace ID passa a
  exibir a causa/ação específicas em vez de "Erro não catalogado.".

## Como reverter
`git revert` do commit (remove a entrada do mapa; o código volta ao fallback genérico). Sem passos
de dados.

## Status
**corrigido e validado** (runtime da classe folha + regressão nos dois sentidos; suíte enterprise
83/83).

---

## Oportunidade futura registrada — catálogo incompleto (NÃO aplicada aqui)

Medido nesta auditoria: o produto emite **58 códigos literais** de `codigo_erro` em `app/` +
`workers/` que **não** estão no catálogo (alguns são fragmentos de interpolação — p.ex.
`VSM_AUTH_HTTP_<code>`, `DB_UPDATE_V<n>`, `TINY_V<n>` —, então o número de famílias distintas é
menor). Hoje o catálogo cobre 9 códigos; os demais caem no fallback genérico na tela forense.

Exemplos de códigos emitidos e ainda sem tradução específica: `CSRF_INVALID`, `LOGIN_INVALID`,
`ACCESS_DENIED`/`PERMISSION_DENIED`, `ROUTE_NOT_FOUND`, `TENANT_SCOPE_VIOLATION`,
`NEGATIVE_STOCK_BLOCKED`, `PRODUCT_INACTIVE_WITH_STOCK*`, `DUPLICATE_EVENT_IGNORED`,
`QUEUE_BACKPRESSURE`, `VSM_CIRCUIT_OPEN`, `VSM_CREDENTIALS_MISSING`, `TINY_WEBHOOK_SECURITY_BLOCKED`,
`BACKUP_RESTORE_FAILED`, entre outros.

**Por que não catalogar todos agora:** ampliar o catálogo em bloco, sem revisar caso a caso a
causa/ação correta de cada código (e qual é alarme real × estado esperado — p.ex.
`DUPLICATE_EVENT_IGNORED` e `QUEUE_EMPTY` são comportamento normal, não defeito), seria enfileirar
texto sem evidência de que cada um ajuda o operador — contrário à regra de só mexer com impacto
avaliado. Fica **registrado, não aplicado**: catalogar os códigos de maior valor forense primeiro
(os de bloqueio de fluxo e de falha de integração), quando houver uma passada dedicada à
observabilidade. Para reproduzir a lista:

```
comm -23 \
  <(grep -rhoP "'codigo_erro'\s*=>\s*'\K[A-Z_]+" app/ workers/ | sort -u) \
  <(grep -oP "^\s*'\K[A-Z_]+(?=' =>)" app/Services/ErrorCatalog.php | sort -u)
```
