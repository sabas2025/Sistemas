# Auditoria do Hub conforme `PROMPT_-_HUB.md`

Data: 2026-09-14 · Base auditada: **V104.49.3-R7** · Metodologia: as 12 fases do documento

> Regra que governa o documento: *"Comece sempre pela auditoria. Não aplique alterações antes de
> apresentar evidências e impacto."* Foi o que se fez: varredura primeiro, correção depois, e
> **nada** foi alterado sem evidência reproduzível.

## Resultado

**2 achados confirmados e corrigidos. 1 hipótese descartada por evidência. 1 observação informativa.**

Os dois achados têm a mesma natureza e é ela que os torna relevantes: **indicadores que mentiam**.
Nenhum quebrava fluxo de negócio. O risco está em outro lugar — um painel de prontidão que já
está vermelho por defeito próprio deixa de servir para detectar o problema real quando ele chegar.

---

## F-01 — Checagem de proteção dos workers procurava um método que nunca existiu

| Campo | Conteúdo |
|---|---|
| **Identificação** | `CommercialHardeningService::workersGuarded()` verifica guard CLI pelo nome errado |
| **Evidência** | `grep -rn "function requireCli" app/ workers/ public/` → **nenhuma ocorrência**. `grep -l 'WorkerCliGuardService::enforce' workers/*.php` → **12 de 12**. Execução do check original: `status=erro, faltando=12 de 12`. |
| **Arquivo** | `app/Services/CommercialHardeningService.php:68` (versão anterior) |
| **Gravidade** | **Alta** |
| **Causa raiz** | O nome do método estava repetido numa **string literal** (`'WorkerCliGuardService::requireCli'`) em vez de derivado da classe. O guard real sempre se chamou `enforce()`; a string nunca acompanhou. |
| **Impacto** | O item "Operação / Workers CLI" devolvia **ERRO permanente** listando os 12 workers como desprotegidos. Esse status alimenta `ProductionCommercialReadinessService`, então a prontidão de produção ficava degradada de forma crônica e falsa. |
| **Cenário de falha** | Um worker perde de fato a chamada ao guard e passa a ser executável pelo navegador. Ninguém percebe: o indicador **já estava vermelho** para todos os 12. O alarme falso permanente anula o alarme verdadeiro. |
| **Correção aplicada** | O nome do método passa a ser resolvido por `method_exists()` sobre a própria `WorkerCliGuardService` (`guardMethodName()`), aceitando `enforce` e caindo para ele por padrão. A recomendação exibida no painel — que instruía a chamar um método inexistente — passa a citar o método real. |
| **Risco da correção** | Baixo. Nenhuma assinatura pública alterada; o método é `private static`. Se a classe for renomeada no futuro, a reflexão acompanha em vez de divergir. |
| **Compatibilidade** | Total. Não toca banco, rota, API, Tiny nem VSM. |
| **Como testar** | `php tests/enterprise/v104_49_3_r7_auditoria_fases_test.php` — asserções 1 a 4. Ou abrir o painel de Endurecimento Comercial: o item passa de erro para `12 workers protegidos`. |
| **Como reverter** | Restaurar o bloco `workersGuarded()` anterior. Alteração contida em um arquivo, sem migration. |
| **Status** | **corrigido e validado** |

## F-02 — Auditoria multiempresa com catálogo duplicado e exigência insatisfazível

| Campo | Conteúdo |
|---|---|
| **Identificação** | `TenantScopeAuditService` mantinha catálogo próprio e exigia `filial_id` |
| **Evidência** | Catálogo local: **15 tabelas**, contra as **31** de `TenantScopeService::scopedTables()` — fonte única estabelecida na R7. `grep -c filial_id database/install_final_current.sql` → **1** (em `pedidos_integracao`). Nenhuma das tabelas do catálogo local tem `filial_id`, logo `$missingFilial > 0` sempre e `status` nunca era `ok`. |
| **Arquivo** | `app/Services/TenantScopeAuditService.php:4-23` · `views/commercial_hardening_final.php:28-29` |
| **Gravidade** | **Média** |
| **Causa raiz** | Dois catálogos para o mesmo conceito. O serviço é de **V104.18**, anterior tanto à consolidação de `filiais` em `empresas` (R6, seção 2.4) quanto ao `TenantScopeService` (R7), e nunca foi reconciliado com nenhum dos dois. |
| **Impacto** | Duplo. (a) Alerta permanente por uma coluna que deixou de existir por decisão de projeto. (b) O catálogo local exigia `empresa_id` em `logs_integracao`, `auditoria_eventos` e `backups_banco` — tabelas **deliberadamente globais** à instalação —, contradizendo o desenho do isolamento; e media 15 tabelas enquanto o isolamento real cobre 31, deixando 16 fora da auditoria. |
| **Cenário de falha** | Uma tabela nova entra em `TenantScopeService` sem `empresa_id`. A auditoria de prontidão não a vê (não está no catálogo local) e continua exibindo o mesmo alerta genérico de sempre. O operador não tem como distinguir o alerta crônico do alerta novo. |
| **Correção aplicada** | O catálogo local foi **removido**; a auditoria passa a iterar `TenantScopeService::scopedTables()`. A verificação de `filial_id` foi retirada do serviço e a coluna correspondente saiu da tela. O resumo passa a declarar explicitamente quando nenhuma tabela é encontrada no banco. |
| **Risco da correção** | Baixo–médio. A auditoria passa a medir 31 tabelas em vez de 15, então **pode surgir alerta legítimo** em instalação que ainda não aplicou a migration `20260914_010_tenant_isolation.sql` — isso é o comportamento correto, não regressão. A chave `filial_id` sai do array de resultado; a view foi ajustada no mesmo commit. |
| **Compatibilidade** | Preserva a assinatura pública `run(bool $persist=true): array` e a persistência em `tenant_scope_audit_snapshots`. Nenhuma migration. `pedidos_integracao.filial_id` **não foi removida** — as regras proíbem `DROP COLUMN` sem plano seguro. |
| **Como testar** | `php tests/enterprise/v104_49_3_r7_auditoria_fases_test.php` — asserções 5 a 10. Em banco com a migration aplicada, o painel deve mostrar `31 tabelas avaliadas; 0 sem empresa_id`. |
| **Como reverter** | Restaurar o `$operationalTables` e o bloco `$missingFilial`, mais a coluna na view. Um arquivo de serviço e uma view, sem migration. |
| **Status** | **corrigido e validado** |

---

## Hipótese levantada e DESCARTADA por evidência

**Fase 7 — ausência de backoff exponencial e jitter.** A primeira varredura buscou os termos
apenas em `QueueService.php` e `DeadLetterQueueService.php` e não os encontrou. **Conclusão
errada, por varredura estreita.** A evidência real:

- `app/Services/RetryPolicyService.php:19-20` — `$delay = self::baseDelayMs() * (2 ** ($attempt-2));`
  seguido de `$jitter = random_int(0, min(250, $delay));`
- `app/Services/QueueRetryPolicyEnterpriseService.php:25` — backoff por categoria de erro
- `app/Services/CircuitBreakerService.php` — circuit breaker presente

**Status: não é achado.** Registrado no teste de regressão para não ser "descoberto" de novo.

## Observação informativa

**`pedidos_integracao.filial_id` é vestigial.** Sobrevive à consolidação da R6, nenhum código a lê
ou grava (a única menção é na whitelist `SAFE_COLUMNS` do `DashboardController`, que apenas a
autoriza). **Não foi removida**: as regras do projeto proíbem `DROP COLUMN` sem plano seguro e
remoção de estrutura sem autorização explícita. Fica documentada para que uma auditoria futura não
volte a exigir `filial_id` por vê-la no schema. **Status: informativa, não corrigida por decisão.**

---

## Fases sem achado

| Fase | O que foi verificado | Resultado |
|---|---|---|
| 3 — Arquitetura | 270 arquivos, 241 classes; toda chamada `Classe::metodo()` e `$this->metodo()` conferida contra os métodos declarados, com herança | Nenhuma chamada a método inexistente |
| 7 — Filas | backoff, jitter, circuit breaker, lease, DLQ, `proxima_tentativa` | Presentes — ver hipótese descartada |
| 9 — Interface | Todo `<form method="post">` das views | **0** sem token CSRF |
| 11 — Observabilidade | `RequestContext::id()` em 75 arquivos; 152 colunas `trace_id` no schema | Correlação presente |

**Fases não cobertas nesta rodada** — e o documento exige dizê-lo em vez de presumir:
**5 (Tiny V2 × V3)** e **6 (VSM)** dependem de credenciais reais e de tráfego contra as APIs;
**10 (performance)** exige evidência de gargalo em banco com volume, e o documento proíbe otimizar
sem ela. Para as três: **"Não identificado com as evidências disponíveis."**

---

## Depois de alterar — resumo exigido pelo documento

**Arquivos modificados (4)**
- `app/Services/CommercialHardeningService.php` — `guardMethodName()` por reflexão; `workersGuarded()` usa o método real; duas recomendações do painel corrigidas
- `app/Services/TenantScopeAuditService.php` — catálogo único, sem `filial_id`, resumo explícito
- `views/commercial_hardening_final.php` — coluna `filial_id` removida da tabela
- `tests/enterprise/v104_49_3_r7_auditoria_fases_test.php` — **novo**, 13 asserções

**Migrations:** nenhuma. **Banco:** não alterado. **APIs afetadas:** nenhuma.

**Testes realizados:** 12 portões de CI, todos verdes — `enterprise-tests.sh` passou de 33 para
**34 testes**; lint, DDL, rotas (104), OpenAPI, classmap (242 classes), escopo multiempresa,
higiene de segredos, schema consolidado, inventário (134 tabelas), contrato estático e paridade
modular.

**Riscos residuais:** F-02 pode expor alerta legítimo em instalação sem a migration de isolamento
aplicada — comportamento correto, mas o operador precisa saber para não confundir com regressão.

**Implantação:** substituir os arquivos; nenhum passo de banco. **Rollback:** restaurar os três
arquivos de produção a partir do pacote R7 anterior; sem efeito colateral em dados.
