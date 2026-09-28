# RELATÓRIO V92 — Dashboard Executivo Real + Correção de Fluxos Ativos

## Objetivo
Corrigir dois pontos solicitados:

1. Fazer análise minuciosa do **Dashboard Executivo** e identificar se os valores são reais.
2. Corrigir a página **Central Técnica → Central de Integrações → Escolher fluxos ativos**, voltando a apresentação para um modelo mais limpo e corrigindo o problema de salvar checkboxes.

---

## 1. Dashboard Executivo

### Problema encontrado
O Dashboard Executivo mostrava percentuais como `Tiny V2 55%`, `Tiny V3 70%` e `VSM 30%` usando fallback fixo quando faltava configuração.

Isso podia passar a impressão de que os valores eram 100% reais, quando parte deles vinha de regra interna/fallback.

### Correção aplicada
Agora o Dashboard Executivo informa a fonte de cada card:

- **Real**: vem de tabela/homologação registrada no banco.
- **Config.**: vem apenas de configuração local, sem teste real salvo.

### Fontes auditadas
A tela agora mostra a auditoria das fontes:

- `pedidos_hub`
- `pedidos_nfe_xml`
- `estoque_movimentos`
- `estoque_divergencias`
- `fila_integracao`
- `fila_estoque`
- `tiny_v2_homologacao_testes`
- `tiny_v3_homologacao_testes`

### Benefício
O operador agora consegue saber se o número exibido é dado operacional real ou apenas indicação baseada em configuração.

---

## 2. Orquestração / Fluxos Ativos

### Problema encontrado
A página tinha dois problemas principais:

1. Existiam formulários internos dentro do formulário principal, o que pode quebrar o POST dos checkboxes no HTML.
2. Ao salvar, toda chave que não vinha no POST era gravada como `0`. Isso desligava macros internas como `sync_tiny_enviar_pedido_vsm`, mesmo quando o checkbox `fluxo_pedido_tiny_enviar_vsm` estava marcado.

Resultado prático:

- O usuário marcava o fluxo.
- Clicava em salvar.
- A tela recarregava como se não estivesse ativo.

### Correção aplicada
- A tela voltou para uma apresentação mais limpa, próxima da versão anterior.
- Removido formulário aninhado.
- Botão “Testar regra deste fluxo” agora usa formulário separado oculto.
- O salvamento agora preserva configurações não editadas.
- Quando um fluxo específico é marcado, a macro correspondente é ativada automaticamente.
- O status efetivo agora considera checkbox + macro corretamente.

### Exemplo corrigido
Fluxo:

`Enviar pedidos originados no Tiny para a VSM`

Agora, ao marcar e salvar:

- `fluxo_pedido_tiny_enviar_vsm = 1`
- `sync_tiny_enviar_pedido_vsm = 1`

Assim o fluxo permanece ativo após recarregar.

---

## 3. Arquivos alterados

- `app/Services/OperationCenterService.php`
- `app/Controllers/OperationCenterController.php`
- `app/Controllers/DashboardController.php`
- `views/dashboard_executivo.php`
- `views/orquestracao_integracoes.php`
- `database/install_final_v92.sql`

---

## 4. Validação

- Sintaxe PHP validada com `php -l`.
- ZIP testado com `unzip -t`.
- Dashboard agora distingue dado real de dado estimado/configuração.
- Fluxos ativos não dependem mais de formulários aninhados.
- Fluxo Tiny → VSM pedido permanece ativo após salvar.

---

## 5. Sugestões futuras

1. Criar tela “Fontes de Dados do Dashboard” com botão de teste por tabela.
2. Criar log visual de antes/depois para cada alteração de fluxo.
3. Criar botão “Restaurar modelo seguro recomendado”.
4. Criar validação de produção que impede ativar fluxos conflitantes Tiny ↔ VSM sem confirmação.
5. Criar teste real de ponta a ponta por fluxo: pedido, estoque, produto e XML/NF-e.
