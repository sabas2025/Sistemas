# Relatório — Remoção das 5 classes órfãs (V104.49.3-R7)

**Data:** 2026-09-14 · **Release:** V104.49.3-R7 (sem mudança de release: é remoção de código morto,
não há mudança de comportamento) · **Autorização:** explícita do responsável do produto.

---

## 1. Antes de alterar

**Situação atual.** A auditoria final da linha R7 apontou 5 classes presentes na árvore e no classmap
que **nenhum arquivo do sistema referencia**. Vieram das linhas V104.16/V104.19, quando os painéis
que as consumiam foram removidos; as classes ficaram.

**Problema.** Código morto no classmap. Não é vulnerabilidade: nenhuma delas é alcançável por rota,
permissão, worker, cron ou instanciação dinâmica. O custo é de manutenção e de ruído em auditoria —
uma classe que grava em tabela mas nunca é chamada faz parecer que a tabela está viva.

**Causa.** Remoção de funcionalidade sem remoção do serviço que a atendia.

**Arquivos afetados (removidos):**

| Arquivo | Origem | O que fazia | Tabela que escrevia |
|---|---|---|---|
| `app/Services/ConnectorOperationalCheckService.php` | V104.19 | snapshot operacional de conectores | `connector_operational_checks` |
| `app/Services/DemoResetSchedulerService.php` | V104.19 | log de auditoria de reset de demo | `comercial_demo_reset_logs` |
| `app/Connectors/GenericPlannedConnector.php` | V104.16 | conector "planejado" genérico | — |
| `app/Services/ModularDatabaseAuditService.php` | V104.16 | contagem de `Database::getConnection(` em `app/` | — |
| `app/Services/ReleaseQualityGateService.php` | V104.19 | registro de checagem de release | `system_release_checks` |

**Banco afetado: nenhum.** As três tabelas **permanecem** no schema. Não foram removidas e não devem
ser: `DROP TABLE` sem plano seguro é proibido, elas estão declaradas em `database/modules/core.sql`,
nos `install_final_*`, no `schema_inventory_current.json` e são verificadas por
`mysql-schema-static-check.php`, `sql-inventory-check.php` e
`tests/enterprise/v104_48_1_schema_vsm_contract_test.php`. Removê-las quebraria três portões de CI.
Ficam como tabelas vazias e inertes.

**APIs afetadas: nenhuma.** Tiny V2, Tiny V3 e VSM não tocam nessas classes.

**Risco.** Baixo, com uma ressalva honesta: o risco real de apagar classe é o carregamento dinâmico
por string. Foi o que se verificou primeiro (seção 2).

**Plano de implementação.** Provar que são órfãs → salvar cópia para rollback → remover →
regenerar classmap → rodar os 12 portões → reconferir → regenerar `CHECKSUMS-SHA256.txt` por último.

**Plano de teste.** Os 12 portões de CI, os 36 testes enterprise, a varredura independente de
referências e a varredura de órfãs sobre o classmap inteiro.

**Rollback.** Restaurar os 5 arquivos do pacote anterior
(SHA-256 `f7edff9bd378e8871018df4b41cd62a24c2e409bd97f48a07be90918d777b9b2`) e rodar
`php scripts/build-classmap.php`. Sem migration para reverter, sem dado para restaurar.

---

## 2. Evidência de que eram órfãs

Antes da remoção, para cada uma das 5 classes:

| Checagem | Resultado |
|---|---|
| Referência em `.php`, `.sql`, `.js`, `.json`, `.html`, `.sh`, `.mjs` fora do próprio arquivo | **0** |
| Referência como string (`'NomeDaClasse'`) | **0** |
| Declaração de rota ou permissão | **0** |
| Instanciação dinâmica alcançável | **0** — os únicos `new $var` do sistema estão em `FastRouteDispatcherService.php:85` e `:93`, e o `$controller` vem de catálogo fechado de controllers, não destas classes |
| `GenericPlannedConnector` no registro de conectores | **não** — `ConnectorRegistryService::all()` instancia uma lista literal fechada (Tiny, VSM, Bling, Omie, Mercado Livre, Shopee, Amazon, TikTok Shop) |

Únicas menções restantes: relatórios históricos em `.md` (`RELATORIO-V104.19-…`,
`RELATORIO-V104.16-…`, `RELATORIO-V104.3-…`) e o `CHECKSUMS-SHA256.txt` — este último é regenerado
ao final e passa a não listá-las.

**Status: confirmado.**

---

## 3. Depois de alterar

**Arquivos modificados**

| Arquivo | Alteração |
|---|---|
| 5 arquivos acima | removidos |
| `storage/cache/classmap.php` | regenerado — **244 → 239 classes** |
| `CHECKSUMS-SHA256.txt` | regenerado por último, sobre a árvore congelada |
| `RELATORIO-REMOCAO-CLASSES-ORFAS-2026-09-14.md` | este documento |

**Migrations:** nenhuma.

**Compatibilidade:** PHP 8.x, MySQL 8, MariaDB 11.4, hospedagem compartilhada, Tiny V2/V3 e VSM —
inalteradas. Instalação nova e atualização: inalteradas (nenhum DDL mudou).

**Testes realizados e resultados**

| Verificação | Resultado |
|---|---|
| `php-lint.sh` | OK |
| `enterprise-tests.sh` (36 testes) | OK |
| `schema-runtime-ddl-check.php` | OK |
| `controller-route-check.php` | OK |
| `vsm-openapi-check.php` | OK |
| `build-classmap.php --check` | OK |
| `tenant-scope-check.php` | OK |
| `secret-hygiene-check.php` | OK |
| `build-consolidated-schema.mjs --check` | OK |
| `sql-inventory-check.php` | OK |
| `mysql-schema-static-check.php` | OK |
| `mysql-module-parity-check.php` | OK |
| **Portões com falha** | **0 de 12** |
| Varredura independente de evidências (61 checagens) | 61 OK, 0 falha |
| Reescrita de SQL multiempresa (137 literais) | OK |
| Varredura de órfãs sobre as 239 classes do classmap | **0 órfãs restantes** |

Os exit codes foram capturados diretamente do comando, sem `pipe` intermediário — a armadilha que
já mascarou falha de portão nesta linha de trabalho.

**Riscos residuais.** Um só, e é honesto declará-lo: toda a verificação é **estática**. Não houve
MySQL nem execução real. Se existir referência a essas classes construída em runtime por
concatenação de string que a varredura textual não alcance, ela não foi vista. Nenhuma das 5 tinha
padrão compatível com isso, e nenhuma aparecia em tabela de rotas ou permissões.

**Implantação.** Substituir a árvore de código. Não parar worker, não parar webhook, não rodar
migration. Nada no caminho de requisição muda.

**Rollback.** Seção 1.

**Status: corrigido e validado** (validação estática, nos limites acima).
