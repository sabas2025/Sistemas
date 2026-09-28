# RELATÓRIO — Auditoria runtime do fluxo Fiscal / NF-e

**Data:** 2026-09-21 · **Release base:** V104.49.3-R7 · **Commit base:** `937b903` (main)
**Método:** ambiente provisionado em **MariaDB 10.11 real**; `PedidoCicloVidaService::receberRetornoVsm`
(intake real da NF-e) exercitado por harness CLI e leitura do banco. Documento informativo.

## O que passou

| Verificação | Resultado |
|---|---|
| **Impedir duplicidade** — mesmo XML reenviado | **não duplica** (`pedidos_nfe_xml.uq_xml_hash` + `ON DUPLICATE KEY UPDATE`) ✅ |
| **Vínculo ao pedido correto** | NF-e vinculada ao `pedido_hub_id` resolvido por `numero_pedido`/`pedido_tiny_id` ✅ |
| **Isolamento por empresa** | `pedidos_nfe_xml` todas com `empresa_id`, zero nulos ✅ |
| **XML mal-formado** | rejeitado (`parseXmlInfo` → erro, `validado=0`) ✅ |

## Achado M-01 (Média) — chave NF-e não era validada no intake real — **CORRIGIDO**

- **Evidência (antes):** uma NF-e com **chave de DV inválido** enviada pelo caminho real
  (`receberRetornoVsm`) era gravada como `status_xml=xml_validado`, **`validado=1`**, sem erro.
- **Causa raiz:** o validador `XmlNfeHomologationService::validarChaveNfe()` (44 dígitos + dígito
  verificador **módulo-11**) **existia mas não era chamado em nenhum ponto do `app/`/`workers/`** —
  só no self-test de homologação. O intake validava XML bem-formado e presença de chave, **não o DV**.
- **Impacto:** NF-e com chave estruturalmente inválida aceita como validada, podendo seguir adiante
  (ex.: `faturado`, envio ao Tiny). A fase Fiscal do CLAUDE.md exige "validar NF-e e XML".
- **Correção aplicada:** em `receberRetornoVsm`, quando **há** chave, chamar `validarChaveNfe($chave)`;
  se inválida, somar a `$erros` → `validado=0`, `status_xml=erro_xml`. **Só valida quando a chave está
  presente** — chave ausente segue a regra `exigir_chave_nfe`, para **não bloquear retorno legítimo**
  sem chave (regra do projeto: nunca bloquear chamada legítima da VSM).
- **Risco:** baixo. Não altera o caminho de sucesso de uma chave válida; apenas passa a **sinalizar**
  a chave inválida em vez de aceitá-la.
- **Validação (depois, MariaDB real):** chave **válida → `validado=1`** (não bloqueia legítima);
  chave **inválida (DV) → `validado=0` com erro**. Teste enterprise novo reprova sobre o código antigo.
- **Status:** **corrigido e validado.**

## Ponto informativo (registrado, não aplicado)
O dedup de NF-e é por **hash do XML**, não por **chave**. Dois XMLs diferentes com a **mesma chave**
geram duas linhas. Mudar para dedup por chave é alteração de **schema** (novo índice único) e de maior
risco — pode quebrar reenvios legítimos com XML re-assinado. Fica registrado, **não aplicado** sem
evidência de necessidade.

## Veredito
O fluxo fiscal impede duplicidade (por hash), vincula ao pedido correto, isola por empresa e rejeita
XML mal-formado. O único achado — **a chave NF-e não era validada no intake real (M-01)** — foi
**corrigido** usando o validador módulo-11 que já existia, sem bloquear o caminho legítimo.
Continua sem validação apenas a chamada real a Tiny/VSM (credenciais/rede).
