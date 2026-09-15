# RELATÓRIO V104.4 — Melhorias VSM, Fluxos, CSP e Legado

## Objetivo

Aplicar as melhorias sugeridas na análise minuciosa da V104.3, com foco em reduzir erro operacional na configuração VSM, deixar a tela de fluxos como fonte oficial, corrigir botões incompatíveis com CSP forte, organizar legado e consolidar SQL.

## Melhorias aplicadas

### 1. Configuração VSM mais clara

A tela `Configurações de integração` agora possui bloco específico para VSM / Conecta Venda:

- `VSM URL Base`
- `VSM Token`
- `API principal VSM`
- `API loja opcional`
- `Swagger pedidos-integradora`
- `Swagger pedidos-loja`
- `WAF em APIs VSM` exibido como desativado para payloads Tiny/VSM
- `Observação operacional VSM`

Padrão recomendado aplicado:

```text
API principal: pedidos-integradora
API loja opcional: desativado
WAF agressivo em payload VSM: desativado
```

Arquivos alterados:

- `views/configuracoes.php`
- `app/Controllers/DashboardController.php`
- `app/Services/IntegrationConfig.php`
- `database/update_v104_4_vsm_fluxos_csp_legado.sql`
- `database/install_final_v104_4.sql`

### 2. Fluxos ativos agora têm fonte oficial única

A tela:

```text
Integrações > Escolher fluxos ativos
```

passou a ser a fonte oficial dos fluxos.

A tela antiga de `Configurações` não exibe mais checkboxes legados de fluxo operacional. Ela mostra aviso e link para a orquestração.

### 3. Modelo seguro recomendado ajustado para sua operação

Modelo aplicado:

```text
Pedido gerado no Tiny
↓
HUB valida cliente, documento, endereço, itens e SKU
↓
HUB envia para VSM usando pedidos-integradora
↓
VSM processa pedido
↓
VSM gera/retorna NF-e autorizada
↓
HUB valida XML/NF-e
↓
HUB envia NF-e autorizada para Tiny
↓
Estoque oficial VSM atualiza Tiny
```

Fluxos ativos no modelo seguro:

- `pedido_tiny_enviar_vsm`
- `nfe_vsm_enviar_tiny`
- `estoque_vsm_enviar_tiny`
- `produto_status_vsm_enviar_tiny`
- `produto_novo_vsm_bloquear`
- `produto_novo_tiny_bloquear`

Fluxos deixados desligados por padrão para evitar duplicidade/loop:

- `pedido_vsm_receber`
- `pedido_vsm_enviar_tiny`
- `nfe_tiny_enviar_vsm`
- `estoque_tiny_enviar_vsm`
- `produto_status_tiny_enviar_vsm`

### 4. Botões e formulários corrigidos para CSP forte

Removidos eventos inline em views:

- `onclick`
- `onsubmit`
- `onchange`
- `onkeyup`
- `oninput`
- `javascript:`

Antes, alguns botões podiam não funcionar quando a CSP com nonce bloqueava JavaScript inline.

Agora foi criado comportamento seguro centralizado em:

- `views/layout_bottom.php`

Com suporte a:

- `data-confirm`
- `data-copy-text`
- `data-copy-target`
- `.js-print`
- `.js-select-on-focus`

Telas corrigidas incluem:

- `usuarios.php`
- `backups.php`
- `tiny_v3_ficha.php`
- `teste_real_tiny.php`
- `produto_pendente_comparar.php`
- `pedido_validacao_detalhe.php`
- `pedido_ciclo_detalhe.php`
- `tiny_v2_homologacao.php`
- `tiny_v3_homologacao.php`
- `evidencias_homologacao.php`
- `entrada_producao.php`
- `security_fim.php`
- `login.php`
- `pedido_detalhe.php`

Resultado da varredura:

```text
Eventos inline restantes: 0
```

### 5. Orquestração separada em controller próprio

Criado:

- `app/Controllers/OrquestracaoController.php`

As rotas abaixo agora passam pelo controller dedicado antes do `DashboardController`:

- `orquestracao-integracoes`
- `salvar-orquestracao-integracoes`
- `testar-orquestracao-fluxo`

Isso reduz dependência direta do `DashboardController` e inicia a separação de responsabilidades.

### 6. Legado V50/V51 classificado e controlado

Criado:

- `app/Services/LegacyRegistryService.php`

Classificação:

- `V50Controller`: `LEGADO_CONTROLADO`
- `V51Controller`: `LEGADO_CONTROLADO_COM_FUNCOES_ATIVAS`

As rotas de migração legadas são bloqueadas fora do fluxo seguro e redirecionadas para `Migrações Seguras` quando necessário.

### 7. SQL oficial consolidado

Criado:

- `database/install_final_v104_4.sql`
- `database/update_v104_4_vsm_fluxos_csp_legado.sql`
- `database/README-SQL-V104.4.md`
- `database/legacy_updates_manifest_v104_4.json`

Recomendação:

- Instalação nova: usar `install_final_v104_4.sql`
- Base existente: usar `update_v104_4_vsm_fluxos_csp_legado.sql` ou aplicar pelo menu `Migrações Seguras`

## Validação realizada

```text
Arquivos PHP validados: 289
Linhas PHP: 18.865
Erros de sintaxe PHP: 0
Eventos inline JS: 0
ZIP testado: OK
```

## Observação

Não foi executado teste real em MySQL/Tiny/VSM porque este ambiente não possui suas credenciais nem conexão com seu banco de produção. A validação feita foi estrutural, sintática e de consistência do pacote.
