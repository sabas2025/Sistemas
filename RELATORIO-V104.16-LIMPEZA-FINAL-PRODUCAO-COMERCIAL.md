# RELATÓRIO V104.16 — Limpeza final, versão centralizada e preparação produção comercial

## Objetivo

Aplicar a preparação final do HUB para uso comercial, reduzindo confusão de versões, organizando SQL ativo, reforçando licenciamento por cliente, conectores plugáveis, multiempresa/multifilial e prontidão para venda/produção controlada.

## Resumo executivo

A V104.16 transforma a V104.15 em uma base mais profissional para comercialização. A camada comercial deixa de ser apenas demonstrativa e passa a ter serviços reais de controle de licença, modo de bloqueio opcional, registry de conectores plugáveis e checklist de produção comercial.

Status recomendado após esta versão:

- **Homologação comercial:** liberada.
- **Demonstração online sem dados reais:** liberada.
- **Pré-produção controlada:** liberada após Validar Banco e Teste Segurança Assistido.
- **Produção real com clientes:** liberar somente após homologação Tiny/VSM, backup/restore e licença ativa.

## Melhorias aplicadas

### 1. Versão centralizada

Criado:

- `app/Services/SystemVersionService.php`

Agora a versão oficial fica em um único serviço:

- `V104.16`
- `Limpeza final, versão centralizada e preparação produção comercial`
- `database/install_final_current.sql`
- `database/repair_current.sql`
- `v104_16_finalizacao_comercial`

Benefício: reduz mensagens antigas apontando para V104.12/V104.13/V104.15.

### 2. SQL ativo limpo

A raiz de `database/` agora mantém somente arquivos ativos:

- `install.sql`
- `install_final_current.sql`
- `install_final_v104_16.sql`
- `repair_current.sql`
- `update_v104_16_finalizacao_comercial.sql`
- `schema_inventory_current.json`
- `README-SQL-CURRENT.md`

SQLs antigos foram movidos para:

- `database/legacy/sql_ate_v104_15/`
- `database/legacy/docs_sql_antigos/`
- `database/legacy/schema_inventories_antigos/`

Benefício: reduz risco do operador executar SQL antigo por engano.

### 3. Licenciamento comercial real

Criado:

- `app/Services/LicenseEnforcementService.php`
- `views/license_blocked.php`

Configuração adicionada em `config/config.php` e no instalador:

```php
'commercial' => [
  'license_mode' => 'monitor', // off|monitor|enforce
  'allow_unlicensed_internal_use' => true,
  'tenant_scope_required' => false,
  'public_landing_noindex' => true,
]
```

Modos:

- `off`: não valida licença.
- `monitor`: registra alerta se licença estiver ausente/vencida, sem bloquear.
- `enforce`: bloqueia módulos quando a licença está inválida.

A tabela `comercial_clientes_licencas` recebeu reforços:

- `licenca_origem`
- `ultimo_check_em`
- `bloquear_ao_vencer`
- `assinatura_hmac`
- índice `idx_comercial_licenca_status_expira`

### 4. Multiempresa/multifilial preparado

Criado:

- `app/Services/TenantContextService.php`

Ele prepara:

- empresa atual;
- filial atual;
- escopo obrigatório opcional;
- filtro por `empresa_id` e `filial_id` quando a tabela possuir essas colunas.

Importante: por padrão fica em modo compatível. Para SaaS real, ativar:

```php
'commercial' => [
  'tenant_scope_required' => true,
]
```

### 5. Conectores plugáveis reais

Criada pasta:

- `app/Connectors/`

Arquivos adicionados:

- `ConnectorInterface.php`
- `AbstractConnector.php`
- `TinyConnector.php`
- `VsmConnector.php`
- `GenericPlannedConnector.php`

Criado:

- `app/Services/ConnectorRegistryService.php`

Conectores registrados:

- Tiny V2/V3 — ativo;
- VSM pedidos-integradora — ativo;
- Bling — planejado;
- Omie — planejado;
- Mercado Livre — planejado;
- Shopee — planejado;
- Amazon — planejado;
- TikTok Shop — planejado.

### 6. Tela de produção comercial

Criado:

- `app/Controllers/CommercialProductionController.php`
- `views/commercial_production_ready.php`
- `app/Services/ProductionCommercialReadinessService.php`

Nova rota:

```text
index.php?page=producao-comercial
```

A tela mostra:

- versão;
- status comercial;
- status de licença;
- checklist de produção comercial;
- conectores plugáveis;
- ações recomendadas.

### 7. Teste Segurança Assistido atualizado

Atualizado:

- `app/Services/SecurityAssistedTestService.php`

Agora inclui categoria:

- `Produção comercial`

Verifica:

- serviço de licença;
- modo de licença;
- registry de conectores;
- tenant scope;
- documentos comerciais.

### 8. Instalação atualizada

Atualizado:

- `public/install.php`

Agora o instalador grava a chave `commercial` no `config.php` gerado.

### 9. Menu atualizado

Atualizado:

- `views/layout_top.php`

Adicionado no grupo Comercial:

- `Produção Comercial`

Também adicionada versão no rodapé lateral do painel.

### 10. Página institucional preparada para SEO opcional

Atualizado:

- `app/Core/App.php`

Por padrão o HUB segue protegido com `noindex`. Mas a página institucional pode receber `index, follow` se você configurar:

```php
'commercial' => [
  'public_landing_noindex' => false,
]
```

Recomendação: publicar site institucional separado para SEO e manter painel com `noindex`.

### 11. Consultas pesadas parcialmente otimizadas

Foram otimizadas consultas de listagem em:

- `FilaController`
- `PedidoController`
- `CommercialProductService`

Observação técnica: ainda existem `SELECT *` intencionais em detalhes, backup/restore, auditoria completa e rotinas legadas. Remover todos exigiria validação tela por tela, pois algumas views esperam payload completo.

### 12. Documentos novos

Criados:

- `docs/comercial/16_PREPARACAO_PRODUCAO_COMERCIAL.md`
- `docs/ARQUITETURA-CONECTORES-V104.16.md`
- `docs/LICENCIAMENTO-COMERCIAL-V104.16.md`
- `docs/CONTROLLER_REFACTOR_PLAN_V104.16.md`
- `docs/ROUTE_MANIFEST_V104_16.json`

## Métricas da versão

- Arquivos totais: 509
- Arquivos PHP: 325
- Linhas PHP: 21.620
- Controllers: 34
- Services: 134
- Conectores: 5
- Views: 117
- Tabelas oficiais: 111
- Duplicidade de `CREATE TABLE`: 0
- JavaScript inline nas views: 0
- Erros de sintaxe PHP: 0

## Arquivos principais alterados/criados

### Criados

- `app/Services/SystemVersionService.php`
- `app/Services/LicenseEnforcementService.php`
- `app/Services/TenantContextService.php`
- `app/Services/ConnectorRegistryService.php`
- `app/Services/ProductionCommercialReadinessService.php`
- `app/Controllers/CommercialProductionController.php`
- `app/Connectors/ConnectorInterface.php`
- `app/Connectors/AbstractConnector.php`
- `app/Connectors/TinyConnector.php`
- `app/Connectors/VsmConnector.php`
- `app/Connectors/GenericPlannedConnector.php`
- `views/license_blocked.php`
- `views/commercial_production_ready.php`

### Alterados

- `app/Core/App.php`
- `app/Core/Autoload.php`
- `app/Core/Database.php`
- `app/Services/FastRouteDispatcherService.php`
- `app/Services/CommercialProductService.php`
- `app/Services/DatabaseSchemaGuardService.php`
- `app/Services/DatabaseValidationService.php`
- `app/Services/SecurityAssistedTestService.php`
- `public/install.php`
- `views/layout_top.php`
- `views/configuracoes.php`
- `database/install.sql`
- `database/modules/core.sql`

## Análise minuciosa após aplicação

### Pontos fortes

- Sistema agora tem versão única para mensagens e schema.
- Camada comercial ficou mais vendável e menos demonstrativa.
- Licença pode bloquear cliente vencido quando configurado.
- Conectores têm interface padrão.
- Banco oficial está mais limpo.
- SQL antigo ficou arquivado.
- Página pública pode ser configurada para SEO.
- Teste de segurança assistido agora avalia produção comercial.

### Pontos que ainda recomendo evoluir depois

1. Remover `SELECT *` remanescentes em detalhes e telas legadas com validação visual.
2. Extrair mais métodos do `DashboardController` em controllers dedicados.
3. Criar tela para selecionar empresa/filial atual no topo do painel.
4. Implementar cobrança real com gateway somente após contrato.
5. Implementar verificação online/offline de licença se vender SaaS multi-cliente.
6. Criar conectores reais para Bling/Omie/marketplaces somente com documentação e credenciais oficiais.
7. Rodar teste real Tiny/VSM/MySQL antes de chamar produção definitiva.

## Checklist depois de subir no servidor

Executar nesta ordem:

1. `Central Técnica > Validar Banco`
2. `Central Técnica > Mapa do Banco`
3. `Segurança > Teste Segurança Assistido`
4. `Comercial > Produção Comercial`
5. `Comercial > Licenças por Cliente`
6. `Comercial > Conectores Plugáveis`
7. `Backups > Gerar Backup`
8. `Backups > Restaurar em banco de teste`
9. Homologação Tiny V2/V3
10. Teste VSM `pedidos-integradora`

## Conclusão

A V104.16 deixa o HUB mais organizado para venda e implantação profissional. O sistema está adequado para demonstração, homologação e pré-produção comercial. Para produção real com clientes pagantes, a recomendação é habilitar `license_mode=enforce`, validar tenant por empresa/filial, rodar o Teste de Segurança Assistido e homologar Tiny/VSM com dados controlados.
