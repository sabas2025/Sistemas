# Correção — Integridade do Dashboard: 7 "erros" eram indicador que mente

**Data:** 2026-10-05 · **Release:** V104.49.3-R7 · **Origem:** auditoria da tela *Integridade do
Dashboard* (`page=dashboard-integridade`) durante a gravação do vídeo-demo. A tela exibia
**70 verificações · 63 OK · 7 erros** — e os 7 eram falso-alarme.

## Identificação
A tela de saúde da camada de aplicação marcava **7 itens como erro**: 6 itens de menu com
"rota ausente" (Produtos, Estoque, XML/NF-e, Reconciliação, Configurações, Central Técnica) e 1
botão com "ação ausente; CSRF não detectado" (Aplicar estrutura fiscal). Nenhum é defeito de
produto — todas as 6 rotas funcionam e o botão foi removido de propósito.

## Evidência
- **Rotas marcadas "ausentes" existem no dispatcher.** Medido: `case 'page'` no
  `DashboardController` = 0 para as seis; `'page'` no `FastRouteDispatcherService::$dispatchGroups`
  = 1 para `produtos`, `baixas-estoque`, `fiscal`, `reconciliacao`, `configuracoes`,
  `central-tecnica`. A própria **Central Técnica** — tela de onde o card é aberto — aparecia como
  "rota ausente".
- **Botão fiscal é órfão.** `grep "atualizar-v43-fiscal-dashboard-install" views/` = 0 ocorrências
  (o botão saiu de `views/fiscal.php` com o Fiscal Modelo A, 2026-10-04). O handler sobrevive só
  como rota de upgrade legado em `LegacyDatabaseUpgradeController.php:69`, sem nenhuma UI que o
  dispare.

## Arquivo/classe/método
`app/Services/MenuActionTestService.php` → `MenuActionTestService::run()`. A lista de itens
"Menu:"/"Botao:" da tela *Integridade do Dashboard* vem daqui (via
`DashboardIntegrityService::checks()`, linha 42).

## Gravidade
**Média (observabilidade).** Classe F-01/F-02 do `CLAUDE.md`: *"indicador que mente é pior que
indicador ausente"*. Sem perda de dado, sem mudança de fluxo. Mas uma tela de saúde que acusa
7 erros falsos corrói a confiança do operador e pode afogar um erro verdadeiro no ruído.

## Causa raiz
`run()` resolvia a rota do menu olhando **só** o `DashboardController` (`case 'page'`). A campanha
**Fase 3** (PRs #53–#58) moveu domínios inteiros para controllers dedicados, despachados pelo
`FastRouteDispatcherService::$dispatchGroups` — eles não são mais `case` no Dashboard. O serviço
**irmão** `DashboardIntegrityService` **já havia aprendido isso** (comentário na linha 34:
*"Sem a segunda fonte, toda rota já extraída viraria falso 'não localizada' — indicador que
mente."*) e consulta as duas fontes; o `MenuActionTestService` ficou para trás. O botão fiscal é
uma checagem **órfã** — testa um elemento de UI deliberadamente removido.

## Impacto
Operador na tela *Integridade do Dashboard* via "7 erros" em 70 pontos, todos falsos. Nenhum
fluxo de negócio afetado — apenas o indicador.

## Correção aplicada
`MenuActionTestService::run()`:
1. **Menu:** adicionada a 2ª fonte na resolução da rota — `str_contains($dispatcher, "'$page'")`
   —, espelhando o padrão já provado do `DashboardIntegrityService`. O `$dispatcher` é o conteúdo
   de `FastRouteDispatcherService.php`.
2. **Botão fiscal:** removida a entrada órfã `atualizar-v43-fiscal-dashboard-install` do array
   `$forms` (feature removida em 2026-10-04). Os 3 botões vivos (Verificar dashboard, Backup ZIP,
   Processar fila) permanecem.

Nenhuma rota, view, CSRF ou regra de negócio foi tocada — só o checador de saúde.

## Risco da correção
Baixo. Muda apenas a lógica de verificação de um painel de diagnóstico. Reversível por
`git revert`.

## Compatibilidade
PHP 8.x, MySQL 8 e MariaDB 11.4. Sem schema, migration ou janela.

## Como testar
- **Runtime (sem banco):** `tests/enterprise/v104_49_3_menu_action_route_sources_test.php`
  **exercita `MenuActionTestService::run()`** (o serviço só lê arquivos + `OperationalRouteService`)
  e exige **zero item com status erro**; confere que o botão órfão não está mais no conjunto; e
  documenta a regressão provando que a fonte única (DashboardController) deixaria 6 rotas de menu
  sem `case`. Conferido nos dois sentidos: verde com a correção; **repondo o defeito** (removendo a
  2ª fonte e devolvendo o botão órfão) o teste reproduz **exatamente os 7 erros do vídeo**
  (6 menu + 1 botão) e reprova (exit 1).
- **Runtime (HTTP, já observado):** a própria navegação mostra as 6 telas abrindo normalmente
  (Central Técnica inclusive), e o Health Check por Módulo confirma a camada de banco 9/9.

## Resultado esperado na tela
*Integridade do Dashboard*: **70 verificações → 70 OK, 0 erros**.

## Como reverter
`git revert` do commit. Sem passos de dados.

## Status
**corrigido e validado** (runtime do serviço + regressão nos dois sentidos reproduzindo os 7 erros;
suíte enterprise 83 → 84).

## Observação (não é bug): Health Check por Módulo
A tela irmã *Health Check por Módulo* (`page=health-modulos`) testa a camada de **banco** (conexão
+ tabela crítica por módulo) e estava **9/9, 0 erros** — honesta. As duas são complementares: banco
× aplicação. A ironia registrada é que o indicador de banco estava correto e o de aplicação mentia.
