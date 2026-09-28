# RELATÓRIO-RESUMO — Auditoria completa da sessão (2026-09-21)

**Release base:** V104.49.3-R7 · **Commit final:** `acd4183` (main) · **Ambiente de prova:** MariaDB 10.11 real.
Consolida a linha de auditoria runtime desta sessão: achados corrigidos, superfícies confirmadas sólidas,
e o que continua fora de alcance. Documento informativo.

## 1. Achados encontrados e CORRIGIDOS

Cada correção tem teste enterprise que **reprova sobre o código antigo** e foi medida em banco real.

| ID | Área | Defeito | Correção | PR |
|---|---|---|---|---|
| **J-01** | Resiliência | Banco fora fazia TODA rota (inclusive webhooks) responder **409 + SQLSTATE cru**; `PDOException` (subclasse de `RuntimeException`) caía no catch de tenant | Capturar `PDOException` e propagar → **503 + tela de Recuperação**; Tiny/VSM reenviam, sem vazar | #24 |
| **K-01** | Log / segurança | `SensitiveDataService::mask()` chamava `maskScalar()` com **array** → `Array to string conversion`, mascarava como "Array" | `maskSensitiveValue()` recursivo; estrutura preservada, sem warning | #25 |
| **L-01** | Reconciliação | `reconciliarSku()` **propagava exceção** com VSM fora → tela de Recuperação, sem registro | Degrada com elegância: audita, retorna `indisponivel`, aviso amigável | #27 |
| **M-01** | Fiscal | Chave NF-e (módulo-11) **não era validada** no intake real; chave com DV inválido virava `validado=1` | `receberRetornoVsm` valida a chave quando presente (usa `validarChaveNfe`) | #28 |

## 2. Superfícies auditadas e CONFIRMADAS SÓLIDAS (sem defeito)

| Superfície | Evidência-chave |
|---|---|
| **Pedido** Tiny→VSM→NF-e→status→retorno Tiny | ciclo `recebido_tiny → recebido_vsm → xml_validado → enviado_tiny → concluido`; contrato HTTP coerente |
| **Baixa de estoque** Tiny→VSM | enfileirado em `fila_estoque`, política `vsm_fonte_real` |
| **Produto novo** VSM→Hub→Tiny | recebe → aprovação manual (secure-by-default) → mapeia → envia |
| **Atualização de produto existente** | cadastro/status/estoque; preflight ao Tiny antes de alterar |
| **Fluxo reverso de estoque** VSM→Tiny | idempotência/anti-loop por hash, saldo auditado, alerta de negativo |
| **DLQ / resiliência de fila** | retry/backoff, DLQ carimbando empresa, reprocesso sem duplicar (I-13), item preso liberado |
| **Execução real do LLM (B2+B5)** | 7 portões secure-by-default; auditoria grava **hash** da saída, nunca o texto cru |
| **Backup / restore** | assinatura autoritativa, proveniência inforjável (A-11), restore blindado (verify+confirmação reforçada+rollback+lock) |
| **Login / sessão (fase 8)** | cookie HttpOnly+SameSite, regeneração de sessão, força bruta contida, `session_version`, `deve_trocar_senha` |
| **Autenticação de webhook** | Tiny (segredo+CNPJ) e VSM (HMAC v2 + anti-replay por nonce **e** por corpo); tudo auditado |

**Isolamento por empresa (H-01 empresa única):** em todos os fluxos medidos, 100% das linhas de negócio
nasceram com `empresa_id`, zero nulos.

## 3. Saúde da `main` (medida nesta sessão)
- **14/14 portões estáticos** verdes · **55 testes enterprise** verdes · **runtime consolidado + modular** verde.
- CI verde em cada PR desta linha (matriz MySQL 8.0 + MariaDB 11.4 + E2E autenticado).

## 4. Fora de alcance (por dependência de credenciais/rede) — não é defeito
- Chamada externa real a **Tiny / VSM / Anthropic** (as tentativas sem rede falham de forma honesta e auditada).
- Ciclo **OAuth Tiny V3** completo.

## 5. Pendências operacionais / de produção (do responsável)
- **Aplicar as migrations de capacidade** em produção (runbook mergeado; a `012` PK BIGINT encarece com o tempo).
- `rotate-secrets.php --audit` no servidor · marcar `Hub CI / gate` como *required* · ligar
  `webhook_signature_require_v2` quando a VSM migrar · H-01 para 2+ empresas (decisão de produto).

## Veredito
As quatro correções desta sessão fecham defeitos reais de fluxo principal/segurança, todos travados por
teste. As demais superfícies auditadas estão íntegras e casam com o design documentado. A `main` está
verde. **Não há pendência de auditoria em aberto** — o que resta é implantação/decisão de produto.
