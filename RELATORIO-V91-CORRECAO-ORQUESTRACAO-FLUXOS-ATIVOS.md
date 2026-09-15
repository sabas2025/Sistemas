# V91 - Correção Orquestração Tiny ⇄ VSM

## Problema corrigido
Na tela `index.php?page=orquestracao-integracoes`, ao marcar o fluxo **Enviar pedidos originados no Tiny para a VSM** e clicar em **Salvar fluxos ativos**, o estado não permanecia ativo após recarregar.

## Causa provável
A tela carregava regras com `SyncRulesService::all()`, que não contempla todos os campos específicos `fluxo_*` da orquestração granular. Com isso, o salvamento podia ocorrer, mas a leitura visual não refletia o estado persistido.

## Correções aplicadas
- A tela agora lê a configuração por `IntegrationOrchestratorService::all()`.
- O salvamento garante a existência de todas as colunas `fluxo_*` e macros de orquestração.
- O salvamento grava e relê a configuração persistida para confirmar ativos/total.
- Criada tabela `orquestracao_fluxos_historico`.
- Adicionado histórico de alterações com Trace ID.
- Adicionado teste individual por fluxo.
- Adicionado status visual do fluxo `pedido_tiny_enviar_vsm`.
- Adicionado alerta quando o checkbox específico está ativo, mas a macro principal está desligada.
- Removida exposição da atualização técnica antiga V39 da tela principal.

## Melhoria funcional
Agora o operador consegue ver:
- quantos fluxos estão ativos;
- se `Tiny → VSM pedido` está ativo;
- qual macro controla cada fluxo;
- últimas alterações;
- Trace ID do salvamento;
- teste de autorização do fluxo.

## Arquivos alterados
- `app/Controllers/DashboardController.php`
- `views/orquestracao_integracoes.php`
- `database/install_final_v91.sql`

## Validação
- PHP sem erro de sintaxe.
- ZIP testado.
