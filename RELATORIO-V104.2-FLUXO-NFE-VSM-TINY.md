# RELATÓRIO V104.2 - Fluxo NF-e VSM → Tiny

## Pedido aplicado

Na tela:

`Integrações > Escolher fluxos ativos > Notas fiscais`

foi adicionado o novo fluxo:

**Enviar NF-e autorizada do VSM para a Tiny**

## Arquivos alterados

- `app/Services/IntegrationOrchestratorService.php`
- `app/Services/PedidoCicloVidaService.php`
- `views/orquestracao_integracoes.php`
- `app/Controllers/DashboardController.php`
- `database/modules/core.sql`
- `database/install.sql`
- `database/install_final_v103.sql`
- `database/update_v104_2_fluxo_nfe_vsm_tiny.sql`

## O que foi criado

### Nova macro operacional

`sync_vsm_enviar_nota_tiny`

Controla se o sistema pode enviar NF-e/XML autorizado vindo da VSM para a Tiny.

### Novo checkbox granular

`fluxo_nfe_vsm_enviar_tiny`

Aparece na seção **Notas fiscais** da tela de fluxos ativos.

### Novo item no catálogo de fluxos

ID do fluxo:

`nfe_vsm_enviar_tiny`

Título exibido:

`Enviar NF-e autorizada do VSM para a Tiny`

Direção:

`VSM → Hub → Tiny`

Tipo:

`nfe`

## Segurança aplicada

O fluxo respeita o travamento já existente:

`sync_exigir_nfe_autorizada`

Ou seja: quando esse travamento estiver ativo, o sistema bloqueia envio de NF-e não validada/autorizada.

## Ajuste operacional

O método:

`PedidoCicloVidaService::enviarXmlParaTiny()`

agora consulta a orquestração antes de enviar XML/NF-e para a Tiny.

Se o fluxo estiver desativado, o envio é bloqueado com mensagem clara.

## Instalação e banco

Para instalações novas, o schema foi atualizado nos SQLs principais.

Para bases existentes, foi criado:

`database/update_v104_2_fluxo_nfe_vsm_tiny.sql`

Além disso, a própria tela de orquestração cria as colunas automaticamente ao salvar, por causa do mecanismo `safeAddColumn()`.

## Validação

- Validação de sintaxe PHP com `php -l`.
- Validação do catálogo de fluxos.
- Conferência do grupo **Notas fiscais** com dois fluxos:
  - VSM → Hub → Tiny
  - Tiny → Hub → VSM
