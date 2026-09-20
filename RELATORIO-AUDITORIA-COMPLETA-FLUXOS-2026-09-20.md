# RELATÓRIO — Auditoria completa dos fluxos do Hub

**Data:** 2026-09-20 · **Release base:** V104.49.3-R7 · **Commit base:** `1663a88`
**Escopo:** validação ponta a ponta — login/sessão, rotas, validação de pedido, validação de NF-e,
estoque, baixa, gravação de API (VSM). Auditoria de runtime em MariaDB real; documento informativo.

## Método
Ambiente provisionado (`provision-e2e-environment.php`) em MariaDB real; navegação por HTTP como
admin; harness CLI para exercitar a lógica dos serviços contra o banco. Sem alterar código.

## Fase 1 — Login / Sessão — OK

| Cenário | Resultado |
|---|---|
| Dashboard deslogado | 302 (protegido) |
| Login sem CSRF | 403 (CSRF exigido) |
| Senha errada | rejeitada ("inválido") |
| Login correto → dashboard | 200 |
| Rota inexistente | 404 (dispatcher chega ao `naoEncontrado`) |
| Logout → dashboard | 302 (sessão destruída) |

## Fase 2 — Rotas — OK, nenhuma quebrada

Varredura de **232 rotas GET** autenticado: **zero 5xx, zero texto de erro**
(`Fatal error`/`Uncaught`/`PDOException`/`SQLSTATE[`). Distribuição: 130×200, 25×302, 64×403,
6×404 (rotas de detalhe sem parâmetro), 7×405 (POST-only). As 10 telas dos fluxos (pedidos,
produtos, estoque-dashboard, fiscal, fiscal-dashboard, fiscal-xml, fiscal-timeline, fila,
monitor-divergencias, reconciliacao) renderizam **200**.

## Fase 3 — Validação de PEDIDO (Tiny → VSM) — OK

`PedidoTinyVsmValidationService::validar()` executa e **bloqueia** pedido inválido com motivos
precisos (medido): *"Endereço de entrega sem bairro"*, *"Item #1 SKU sem mapeamento Hub"*. A lógica
de validação (SKU mapeado, endereço, cliente, status) está ativa e correta.

## Fase 4 — Validação de NF-e — OK

`XmlNfeHomologationService::validarChaveNfe()` executa e **rejeita** chaves inválidas — inclusive
uma chave de 44 dígitos com **dígito verificador incorreto** (valida o **módulo-11**, não só o
tamanho). Validação estrita e correta.

## Fase 5 — Estoque / Baixa / Gravação de API — OK

Roteamento das duas APIs VSM provado por reflexão (sem chamada externa), com bases distintas
configuradas:

| Operação | API | URL montada |
|---|---|---|
| `enviarPedido` (gravação) | gravação | `{vsm_url}/api/pedidos` |
| `enviarBaixaEstoque` (gravação) | gravação | `{vsm_url}/api/estoque/baixa` |
| `consultarEstoque` (consulta) | consulta | `{vsm_url_consulta}/api/estoque/consulta` |

Gravação (pedido + baixa) e consulta roteiam para **APIs diferentes**. A URL de consulta passa pela
mesma proteção anti-SSRF da de gravação (bloqueia local/privado + resolve DNS).

## Fase 6 — Código inativo / erro — OK, nada

- Lint limpo; **51 testes enterprise + 9 portões estáticos** verdes.
- Nenhuma referência à base VSM antiga sobrou; `baseConsulta`/`urlConsulta`/`vsm_url_consulta`
  consumidos.

## Veredito geral

O Hub está **íntegro de ponta a ponta**: login/sessão seguros, zero rota quebrada, validações de
pedido e NF-e funcionando, gravação/consulta VSM roteando para as APIs corretas.

## Ressalvas honestas (fato vs. hipótese)

- **Não houve chamada real à Tiny nem à VSM.** O ambiente não tem conectividade com os provedores e
  o anti-SSRF barra hosts fictícios (correto). A prova ponta-a-ponta com os provedores reais depende
  de credenciais/URLs reais configuradas em Configurações.
- A auditoria exercitou a **lógica** (validadores, roteamento, telas, rotas). O envio efetivo ao
  provedor é o único elo que só se fecha com o ambiente real conectado.
- O que continua sem validação contra provedor real: ciclo OAuth Tiny V3 completo e qualquer chamada
  de verdade ao Tiny/VSM — como já registrado no CLAUDE.md.
