# Relatório — Auditoria de Interface (Fase 9) — 2026-10-04

> Rodada de auditoria da **Fase 9 (Interface)** das 12 fases do `CLAUDE.md`.
> **Natureza:** varredura read-only estática. Nenhuma alteração de código.
> **Base:** `main` em `25afa56`.

## Escopo

Desktop, notebook, tablet, Android, iPhone, PWA, app Windows, ultrawide. **Nenhum botão é
"funcional" sem validar rota, método HTTP, CSRF, permissão, controller, service, banco, resposta e
mensagem exibida.**

## Método (evidências medidas)

- Inventário de `views/` (126 arquivos); varredura de `<form method=post>` e do token CSRF.
- Extração de todas as rotas referenciadas (`page=…`) em `views/` e cruzamento com o universo de
  rotas declaradas (`FastRouteDispatcherService::$directActions`/`$dispatchGroups`,
  `RouteModuleRegistry`, `RouteCatalogService`, `case '…'` dos controllers).
- Varredura de endpoints chamados por JS (`fetch`) nas views e em `public/assets/`.
- Varredura de rotas destrutivas/mutação atrás de `<a href>` (GET) — smell de CSRF-bypass.
- Cobertura de `PermissionService::require` nos controllers.

## Sinais verdes (medidos)

| Eixo | Evidência | Veredito |
|---|---|---|
| CSRF em mutação | **58** forms POST, todos com `Csrf::field()` | OK — 0 sem CSRF |
| Links → rota existe | **200** rotas referenciadas em `page=`, todas declaradas | OK — 0 link morto |
| Endpoint JS | `api/notificacoes/recentes` (fetch) declarado | OK |
| Mutação via GET | nenhuma rota destrutiva (`excluir/reset/restaurar/revogar/reprocessar/importar/…`) atrás de `<a href>` | OK |
| Permissão | `PermissionService::require` em **39** call-sites de controller | OK |
| Alvo de toque ≥44px | medido verde no I-26/I-27 (censo sem cota, 59 rotas a 390px) | OK |
| Paridade PWA × web | medido verde (59 rotas × 3 viewports × 2 modos; overflow em 354 combos) | OK |

A cadeia "botão → rota → método → CSRF → permissão → controller" está íntegra: o
`controller-route-check.php` (portão) garante que o handler de cada rota de ação direta existe; o
CSRF é por sessão e estável (`Csrf::token()`), presente em todos os forms; mutações são POST (nenhuma
atrás de GET).

## Resíduo (registrado, NÃO é achado novo)

- **Checkboxes sem rótulo clicável** (13 views): o alvo efetivo é o controle nativo (~13,3px). Já
  registrado no I-27 como **decisão de produto** — a correção é de markup/layout (associar
  `label[for]` ou tornar a célula clicável numa matriz de 137 caixas), não de token CSS. Não
  aplicado por decisão, não por omissão.

## Veredito

Interface sólida. A camada acionável está coerente (CSRF universal, zero link morto, zero mutação por
GET, permissão aplicada) e o responsivo já fora medido verde nas rodadas de PWA/alvo de toque.
**Nenhum achado Crítico/Alto/Médio.** Único item é o resíduo de checkbox, já registrado como decisão
de produto.

## Não medido nesta rodada (precisa de banco/navegador vivos)

A renderização real de cada tela por viewport/dispositivo foi medida em rodadas anteriores (tabela de
validação no `CLAUDE.md`); esta rodada é estática sobre a cadeia de ação. A remedição visual pode ser
refeita sob demanda (receita no `CLAUDE.md`, Chromium `--app` + aba).
