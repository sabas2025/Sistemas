# RELATÓRIO V104.33 — GOVERNANÇA LLM SEGURA APLICADA

## Objetivo
Aplicar as melhorias sugeridas para uso futuro de IA/LLM no Hub sem ativar chamadas externas por padrão e sem alterar Tiny, VSM, pedidos, estoque, fiscal, XML, filas ou regras de negócio.

## Melhorias aplicadas

| Área | Aplicado |
|---|---|
| Segurança | LLM continua desativado por padrão e em homologação por padrão. |
| API Key | Chave LLM salva criptografada no Token Vault, nunca exibida após salvar. |
| Permissão | Acesso restrito a admin/supervisor ou permissão explícita `llm.usar` / `llm.administrar`. |
| Auditoria | Validações, bloqueios, aprovações e armazenamento de chave geram eventos auditáveis. |
| Mascaramento | Contexto e logs usam `SensitiveDataService` para mascarar tokens, segredos, CPF/CNPJ e e-mails. |
| Prompt Injection | Heurística ampliada para detectar jailbreak, vazamento de prompt, segredos, SQL destrutivo e tentativa de burlar aprovação. |
| Custos | Limite diário, mensal e por requisição com estimativa de tokens/custo. |
| Contexto | Contexto mínimo, truncamento por política e prévia redigida. |
| Aprovação humana | Criada fila `llm_approval_queue` para aprovar/rejeitar uso real. |
| Ações automáticas | Permanece bloqueado: IA não altera banco, Tiny, VSM, produção, estoque, fiscal, XML ou logs. |
| Banco | Novas tabelas `llm_policy_settings`, `llm_approval_queue`, `llm_usage_daily` e colunas adicionais em `llm_audit_logs`. |
| UX | Tela Governança LLM com política, cofre de chave, teste de prompt, custo e aprovações. |

## Arquivos principais alterados

- `app/Services/LlmGatewayService.php`
- `app/Services/LlmPolicyService.php`
- `app/Services/LlmCostGuardService.php`
- `app/Services/LlmApprovalService.php`
- `app/Controllers/EnterpriseCoreController.php`
- `views/llm_governance.php`
- `app/Services/SchemaMigrationService.php`
- `app/Services/SystemVersionService.php`
- `app/Core/Database.php`
- `config/config.php`
- `database/update_v104_33_llm_governance_secure.sql`
- `database/install_final_current.sql`
- `database/repair_current.sql`
- `public/sw.js`

## Como aplicar no servidor

1. Subir o ZIP da V104.33.
2. Fazer backup do banco.
3. Entrar no painel.
4. Abrir `Central Técnica > Enterprise Core`.
5. Aplicar Enterprise Core para criar/atualizar as tabelas LLM.
6. Abrir `Central Técnica > Governança LLM`.
7. Manter ambiente em Homologação.
8. Cadastrar provider/modelo somente se necessário.
9. Salvar API key no Token Vault.
10. Testar prompts antes de qualquer uso real.

## Impacto

- Tiny: não afetado.
- VSM: não afetado.
- Banco: adiciona tabelas/colunas opcionais e idempotentes.
- APIs: não quebra rotas existentes.
- Produção: mais segura, pois IA segue bloqueada para ações automáticas.
- Rollback: voltar para o ZIP V104.32; tabelas novas podem permanecer sem afetar o sistema.

## Opinião técnica
A V104.33 deixa a base LLM adequada para ambiente empresarial: primeiro governa, audita e controla custo; depois permite pensar em integrações reais. Não recomendo ativar chamadas externas em produção antes de validar homologação, Token Vault, auditoria e fluxo de aprovação humana.
