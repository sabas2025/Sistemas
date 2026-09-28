# RELATÓRIO — Auditoria runtime de integridade e proveniência de backup/restore

**Data:** 2026-09-21 · **Release base:** V104.49.3-R7 · **Commit base:** `8ded2f5` (main)
**Método:** ambiente provisionado em **MariaDB 10.11 real**; `BackupSignatureService` exercitado por
harness CLI contra arquivos reais (assinatura, adulteração, forja) + leitura do wiring do restore.
Documento informativo. **Sem alteração de código — nenhum defeito encontrado.**

## Camada de assinatura (`BackupSignatureService`) — SÓLIDO (9/9)

| Cenário medido | Resultado |
|---|---|
| Backup local assinado | verifica OK, `provenance=local_generated` ✅ |
| **Conteúdo do backup adulterado** | restore **bloqueado** (SHA-256 não confere) ✅ |
| Arquivo adulterado → `provenance()` | `imported_untrusted` (fail restritivo) ✅ |
| **HMAC do `.sig.json` adulterado** | restore **bloqueado** ✅ |
| **Proveniência forjada** (imported→local) sem a chave | **bloqueado** — o HMAC cobre a proveniência (achado A-11) ✅ |
| Após forja → `provenance()` | `imported_untrusted` (não vira `local`) ✅ |
| **Sem `.sig.json`** | restore **bloqueado** (assinatura ausente) ✅ |
| Sem assinatura → `provenance()` | `imported_untrusted` ✅ |

A assinatura é de fato **autoritativa** (a coluna não decide), e a proveniência é **inforjável** sem a
chave HMAC (`security.backup_signature_key`, com exigência de 32+ caracteres em produção).

## Wiring do restore (`BackupService::restaurarPorId`) — defesa em profundidade ✅

Lido no código e confirmado:
1. `BackupSignatureService::verify($file)` roda **antes** de aplicar → aborta em backup adulterado/sem assinatura.
2. Backup de **origem externa** (`imported_untrusted`) exige a confirmação reforçada **`RESTAURAR IMPORTADO`**; caso contrário bloqueia e audita (`backup.restaurar.importado_bloqueado`).
3. Cross-check adicional do SHA-256 contra o registro de auditoria `backups_banco.hash_sha256`.
4. **Ponto de retorno automático** (rollback) gerado antes de restaurar.
5. **Lock de restore** impede restauração concorrente; erros auditados com mensagem mascarada.

## Veredito
Integridade e proveniência de backup/restore estão **sólidas e corretamente conectadas** ao caminho de
restauração. **Nada a corrigir.**

---

## Pendências para a PRÓXIMA sessão (anotadas, não aplicadas)

Superfícies ainda **não** reauditadas em runtime **nesta** sessão (foram validadas em sessões
anteriores; convém remedir e, se houver achado, corrigir):

1. **Login / sessão (Fase 8):** força bruta com os limites reais do código, fixação de sessão
   (regeneração de id no login), flags de cookie (HttpOnly, SameSite, Secure condicional),
   revogação por `session_version`, `deve_trocar_senha`, e a mensagem genérica de login.
2. **Fluxo reverso de estoque (VSM → Hub → Tiny aplicando saldo):** o worker de estoque aplicando a
   atualização no Tiny (preflight, idempotência, evitar loop de sincronização, saldo anterior/novo).
3. **Autenticação de webhook em runtime, ponta a ponta:** segredo compartilhado do Tiny
   (`X-TINY-HUB-SECRET`) e HMAC v2 da VSM — recusa por segredo/assinatura/CNPJ/IP, anti-replay.

Estas ficam registradas como próximo passo; nenhuma alteração de código foi feita nesta auditoria.
