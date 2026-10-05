# Correção D-01 — Cartão "XML processados" do Dashboard contava token que o código nunca grava

**Data:** 2026-10-05 · **Release:** V104.49.3-R7 · **Origem:** auditoria durante a gravação do
vídeo-demo (tela Dashboard, hub01).

## Identificação
O cartão **XML processados** do Dashboard (grupo Fiscal e KPI do topo) exibia **0** mesmo com NF-e
validadas presentes no banco, enquanto a tela **XML / NF-e Enterprise** mostrava "Validados 1" para
o mesmo dado.

## Evidência
- **Leitor (defeito):** `app/Services/DashboardMetricsService.php`, `fiscalResumo['xml_processados']`
  contava `status_xml IN ('validado','enviado_tiny','concluido')`.
- **Escritor real:** `app/Services/PedidoCicloVidaService.php::receberRetornoVsm()` grava
  `status_xml = 'xml_validado'` no sucesso (o mesmo caminho que o `ApiController` usa ao receber o
  retorno da VSM); `enviarXmlParaTiny()` grava `'enviado_tiny'`. Os únicos valores possíveis de
  `status_xml` são: `xml_validado`, `erro_xml`, `enviado_tiny`, `erro_envio_tiny`.
- **Banco real (MariaDB, ambiente provisionado):** `SELECT DISTINCT status_xml → {erro_xml,
  xml_validado}`. Medição das duas consultas sobre o mesmo dado:
  - query antiga (`'validado'`): **0**
  - query nova (`'xml_validado'`): **1**

## Arquivo/classe/método
`DashboardMetricsService::collect()` → chave `fiscalResumo.xml_processados` (linha ~73).

## Gravidade
**Média** — observabilidade. Não há perda de dado nem risco de segurança; o número exibido mente
para menos, escondendo NF-e processadas.

## Causa raiz
Divergência de vocabulário entre quem **escreve** e quem **conta**: o cartão procurava o token
`'validado'` (sem prefixo), que o produto **nunca grava**. Dos três tokens da lista, só
`'enviado_tiny'` casava com a realidade; `'validado'` e `'concluido'` eram mortos. É o padrão
recorrente nesta linha — "indicador que mente é pior que indicador ausente" (achados F-01/F-02).

## Impacto
Toda NF-e **validada e ainda não enviada ao Tiny** (`status_xml='xml_validado'`) sumia do cartão —
**em produção, não só na demo**. Como o fluxo normal valida antes de enviar, o cartão subnotificava
o volume real de XML processado. A tela fiscal não era afetada porque seu `resumoFiscal` conta a
coluna inteira `validado=1`, não o texto.

## Cenário de falha
NF-e retorna da VSM, passa na validação de chave (módulo-11) e fica `xml_validado` aguardando envio
ao Tiny. O Dashboard mostra "XML processados: 0"; a tela XML/NF-e mostra "Validados: 1". Dois
indicadores do mesmo dado se contradizem.

## Correção aplicada
`DashboardMetricsService.php`: token `'validado'` → `'xml_validado'` na contagem de
`xml_processados`. `'enviado_tiny'` mantido; `'concluido'` mantido como defensivo (inócuo, também
não é escrito hoje). **Nenhuma escrita, schema ou regra de negócio foi tocada** — só a contagem de
um cartão de leitura.

```php
// antes
'xml_processados' => self::count('pedidos_nfe_xml', "status_xml IN ('validado','enviado_tiny','concluido')"),
// depois
'xml_processados' => self::count('pedidos_nfe_xml', "status_xml IN ('xml_validado','enviado_tiny','concluido')"),
```

## Risco da correção
Mínimo. Só muda o resultado de um cartão para mais (passa a incluir o que já deveria incluir). Sem
efeito em fila, webhook, integração ou instalação.

## Compatibilidade
PHP 8.x, MySQL 8 e MariaDB 11.4 (a consulta é um `IN` de literais, idêntico ao anterior). Sem
migration.

## Como testar
- **Estático:** `tests/enterprise/v104_49_3_dashboard_xml_metric_test.php` — ancora o cartão na
  verdade do escritor: exige que o `status_xml` de sucesso que `PedidoCicloVidaService` grava
  (`'xml_validado'`) esteja na lista do cartão, proíbe o token morto `'validado'` e confirma
  `'enviado_tiny'`. Conferido contra o defeito reposto: **reprova** (exit 1); com a correção:
  **passa** (exit 0).
- **Runtime (MariaDB):** com uma NF-e `xml_validado` no banco, a consulta antiga devolve 0 e a nova
  devolve 1.

## Como reverter
Reverter a string para `'validado'` (ou `git revert` do commit). Sem passos de dados.

## Status
**corrigido e validado** (estático + runtime contra MariaDB).

## O que NÃO foi alterado (e por quê)
- **Cartão "Estoque sincronizado" = 0:** investigado junto e **não é defeito**. Conta
  `estoque_movimentos` com status `sucesso/sincronizado/confirmado`; o fluxo de demonstração só
  enfileira em `fila_estoque` (status `pendente`) e não escreve `estoque_movimentos`, então 0 é o
  estado honesto. Alterar o cartão seria mascarar; encher a demo é tarefa do seed, não do produto.
