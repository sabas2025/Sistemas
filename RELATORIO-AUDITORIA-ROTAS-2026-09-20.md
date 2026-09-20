# Relatório de auditoria de rotas em runtime — `main` `V104.49.3-R7+20260917.1`

Data: 20/09/2026
Branch de origem: `main` @ `cb3a414`
Método: auditoria dinâmica (fase 9 e 11 do `CLAUDE.md`), contra banco real.

## Objetivo

Verificar, a partir do login e do teste de banco, se há **código quebrado ou
inativo** em qualquer rota do painel — e provar cada veredito com medição, não
com leitura de código.

## Ambiente

| Item | Valor |
|---|---|
| Pacote | ZIP final da `main` (`cb3a414`), instalação limpa |
| Banco | MariaDB 10.11 real, banco descartável, usuário MySQL exclusivo |
| Sessão | autenticada como admin (perfil `admin`) |
| Integridade do pacote (FIM) | 832/832 OK |
| Instalação | 136 tabelas |

## Universo de rotas

229 rotas distintas, reunidas de quatro fontes:
`FastRouteDispatcherService::$dispatchGroups` (104) + `$directActions` (4) + os
197 `page=` referenciados nas views + as rotas especiais
(`login`, `logout`, `dashboard`, `trocar-senha`, `manutencao`, `api/csp-report`,
`tiny-v3-callback`, `produto-institucional`, `demo-online`, `migracoes-seguras`,
`migracao-aplicar`).

## Método — três sinais por rota

Porque o Hub **esconde a exceção** atrás da tela de Recuperação (retorna 200
com mensagem em vez de stack trace), status HTTP sozinho mente. Cada rota foi
medida por três sinais simultâneos:

1. **Status HTTP** — pega tela derrubada (5xx).
2. **Texto de erro no corpo** — pega exceção capturada e exibida
   (`Fatal error`, `Uncaught`, `PDOException`, `SQLSTATE[`, `Class … not found`,
   `Call to a member function`, `Too few arguments`, …).
3. **Delta de `sistema.erro_fatal` na `auditoria_eventos`** — pega a exceção que
   a tela de Recuperação transforma em 200 silencioso.

## Resultado

### Login e banco (ponto de partida)

| Verificação | Resultado |
|---|---|
| login → dashboard | **200** |
| Sessão sobreviveu à varredura das 227 rotas | **sim** (dashboard 200 no fim) |

### Varredura das 227 rotas GET (`logout` e `trocar-senha` fora, por destruírem/alterarem a sessão)

| Status | Qtd | Significado |
|---|---|---|
| **200** | 123 | páginas renderizam |
| 204 | 1 | `api/csp-report` (sem corpo, correto) |
| 302 | 14 | redirects legítimos |
| 401 | 6 | webhooks VSM exigindo autenticação |
| 403 | 64 | rotas de mutação/admin exigindo POST + CSRF ou permissão |
| 404 | 6 | rotas que exigem parâmetro (`?id=`/`?file=`) + o prefixo `api` |
| 405 | 13 | rotas POST-only rejeitando GET |
| **5xx / texto de erro / `erro_fatal`** | **0** | **nenhuma rota quebrada** |

### Conclusão: nenhum código quebrado ou inativo

- **Zero rotas** com 5xx, erro no corpo ou `sistema.erro_fatal`.
- **Zero links de menu mortos** — os 84 links de navegação (`href … page=`)
  resolvem todos para rotas conhecidas; 75 abrem em 200 e 9 são rotas de
  detalhe/download que exigem parâmetro.
- Todos os não-200 são **comportamento correto**: guardas de método/CSRF/
  permissão (403/405/401) ou rotas que precisam de parâmetro (404/302 quando
  chamadas sem ele).

### Prova positiva das rotas de detalhe

`auditoria-detalhe` foi provada viva: **404 sem parâmetro**, **200 com `?id=`
real E com `?trace_id=` real** (a correção I-17 está ativa), corpo sem erro. As
demais rotas de detalhe/download (`pedido-detalhe`, `backup-download`,
`documento-comercial`, `security-assisted-test-download`, `pedido-ciclo-detalhe`,
`pedido-validacao-detalhe`, `produto-pendente-integracao-comparar`) respondem
404/302 sem parâmetro porque a entidade não existe numa instalação nova — é o
comportamento correto de "não encontrado", não rota morta (retornam 404/302
tratado, nunca 5xx).

### Categorias não-200 detalhadas

- **302 (14):** as 8 rotas de migração manual `atualizar-v*` (redirecionam por
  design, fora do menu), ações POST em GET (`salvar-configuracoes`,
  `teste-real-tiny-executar`), rotas de detalhe sem `?id=`, e `tiny-v3-callback`
  sem `state` OAuth.
- **401 (6):** os webhooks `api/webhook/vsm/*` e afins exigindo autenticação.
- **403 (64):** rotas de mutação/administração (`backup-excluir`,
  `ambiente-demo-reset`, `atualizador-seguro-executar`,
  `auditoria-assinar-trace`, …) que na ausência de POST+CSRF respondem 403.
- **404 (6):** rotas de detalhe/download que exigem parâmetro + o prefixo `api`
  (não é página).
- **405 (13):** rotas POST-only (`myouro-salvar`, `myouro-testar`,
  `vsm-*-salvar`, `enterprise-core-aplicar`, webhooks) rejeitando GET.

## Nota de método (honestidade de medição)

A primeira passada teve viés de medição: a lista alfabética incluía `logout`, e
ao alcançá-la a sessão foi destruída — 119 rotas alfabeticamente posteriores a
"l" caíram em **falso 302→login**. Corrigido excluindo as rotas que destroem/
alteram a sessão e **confirmando que a sessão sobreviveu** até o fim (dashboard
200). É a mesma armadilha de "indicador que mente" registrada no `CLAUDE.md`.

## Escopo do que foi provado

Cobre instalação, autenticação, sessão e as 227 rotas GET do painel contra banco
real. **Não** cobre chamadas reais a Tiny/VSM/MyOuro (dependem de credenciais)
nem o cenário de 2+ empresas (bloqueado por design — H-01). Conforme a regra do
projeto, a auditoria **não** declara o sistema "100% funcional".

## Ação

Auditoria **read-only** — nenhuma alteração de código, schema ou comportamento.
Este documento apenas registra o resultado.
