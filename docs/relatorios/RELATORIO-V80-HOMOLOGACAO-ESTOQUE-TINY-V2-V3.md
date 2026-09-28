# RELATÓRIO V80 — Homologação de Estoque Tiny V2/V3

## Objetivo
Ajustar as telas **Tiny V2 - Homologação** e **Tiny V3 - Homologação** para refletirem o fluxo correto do HUB:

- Tiny V2/V3: autenticação, produto, estoque, pedido e retorno da API Tiny.
- VSM: fonte oficial do estoque.
- XML/NF-e: permanece no módulo próprio **XML / NF-e**, não dentro da homologação Tiny.

## Alterações aplicadas

### 1. Removido campo "Chave NF-e / XML" das telas Tiny V2 e Tiny V3
Motivo: na homologação Tiny, esse campo não é necessário. XML/NF-e pertence ao módulo de XML/NF-e e ao fluxo VSM, não à tela principal de homologação Tiny.

### 2. Homologação de estoque reforçada
As telas agora destacam a etapa:

- SKU de homologação;
- saldo Tiny;
- saldo VSM;
- diferença;
- status de conferência/divergência.

### 3. Status Geral ajustado
O Status Geral passa a considerar somente:

- Token/API Key ou OAuth;
- Produto;
- Estoque;
- Pedido;
- Retorno Tiny.

XML/NF-e foi removido do cálculo da homologação Tiny.

### 4. Retorno Tiny explicado na própria tela
Foi incluído aviso explicativo:

> Retorno Tiny é a confirmação técnica de que a API do Tiny respondeu corretamente às consultas usadas na homologação: produto, estoque e pedido. Não é XML/NF-e.

### 5. Serviços ajustados
Arquivos alterados:

- `app/Services/TinyV2HomologationService.php`
- `app/Services/TinyV3HomologationService.php`
- `views/tiny_v2_homologacao.php`
- `views/tiny_v3_homologacao.php`

## Recomendação futura
Criar uma tela independente chamada **Homologação XML/NF-e VSM → HUB**, separada das telas Tiny V2/V3, para validar:

- XML recebido;
- chave NF-e;
- vínculo com pedido;
- envio/retorno ao Tiny quando aplicável;
- auditoria fiscal/XML.

