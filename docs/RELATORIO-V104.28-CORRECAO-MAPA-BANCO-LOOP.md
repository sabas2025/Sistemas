# RELATÓRIO V104.28 — Correção Central Técnica > Mapa do Banco em loop

## Problema relatado
Ao clicar em **Central Técnica > Mapa do Banco**, a tela ficava carregando/rodando em loop.

## Causa provável
A tela `mapa-banco` chamava `DatabaseMapService::resumo()`, que executava automaticamente:

- `DatabaseAutoRepairService::repair(false)`
- `DatabaseSchemaGuardService::repair(false)`
- varredura de tabelas e colunas

Isso misturava uma tela de consulta com reparo/migração de schema. Em hospedagem compartilhada, banco grande, permissão parcial de `CREATE/ALTER` ou base antiga, a requisição podia demorar demais e parecer loop.

## Correção aplicada

### 1. Mapa do Banco virou leitura segura
Arquivo: `app/Services/DatabaseMapService.php`

Agora `resumo(false)` não executa AutoRepair/SchemaGuard automaticamente. A tela apenas lê o estado do banco.

### 2. Reparos continuam preservados
A funcionalidade de reparo não foi removida. Ela continua em:

- `Central Técnica > Validar/Reparar Banco`
- `DatabaseValidationService`
- `DatabaseSchemaGuardService`
- `DatabaseAutoRepairService`

### 3. Consulta de colunas otimizada
A leitura de colunas foi otimizada para consultar `INFORMATION_SCHEMA.COLUMNS` uma vez por conexão/banco, reduzindo várias chamadas `SHOW COLUMNS`.

### 4. Proteção contra travamento
A montagem do mapa agora tem limite seguro aproximado de 15 segundos e exibe aviso se precisar interromper.

### 5. Tratamento de erro no controller
Arquivo: `app/Controllers/DatabaseMaintenanceController.php`

A tela agora captura exceções e exibe mensagem amigável, sem cair em erro fatal ou ficar carregando indefinidamente.

### 6. View com avisos e ações claras
Arquivo: `views/mapa_banco.php`

A tela agora mostra modo de leitura, avisos, botão para recarregar o mapa e orientação para usar Validar/Reparar Banco quando houver divergência.

### 7. WAF atualizado
Arquivo: `config/config.php`

A rota `mapa-banco` foi adicionada explicitamente à lista de rotas técnicas do painel.

## Impacto

- Tiny: não afetado.
- VSM: não afetado.
- Pedidos: não afetado.
- Estoque: não afetado.
- Fiscal/XML: não afetado.
- Banco: sem alteração de schema obrigatória.
- APIs: não afetadas.
- Produção: reduz risco de travamento do painel.

## Rollback
Voltar os arquivos alterados para a versão V104.27:

- `app/Services/DatabaseMapService.php`
- `app/Controllers/DatabaseMaintenanceController.php`
- `views/mapa_banco.php`
- `config/config.php`
- `app/Services/SystemVersionService.php`

## Validação
Executar:

```bash
php -l app/Services/DatabaseMapService.php
php -l app/Controllers/DatabaseMaintenanceController.php
php -l views/mapa_banco.php
```

Depois testar no navegador:

1. Entrar no painel.
2. Abrir Central Técnica.
3. Clicar em Mapa do Banco.
4. Confirmar que a tela carrega sem ficar em loop.
5. Se aparecer divergência, clicar em Validar/Reparar Banco.
