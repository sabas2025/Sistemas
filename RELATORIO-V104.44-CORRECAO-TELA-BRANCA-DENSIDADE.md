# V104.44 — Correção de tela branca por densidade

## Causa
O atributo `data-hub-density` era usado simultaneamente no `<body>` (estado) e no botão (ação). O JavaScript atualizava `innerHTML` de todos os elementos com esse atributo e, quando a preferência compacta estava salva, substituía todo o conteúdo do `<body>`.

## Correção
- O `<body>` continua usando `data-hub-density` somente como estado.
- O botão passou a usar `data-hub-density-toggle`.
- Os seletores de leitura e clique no JavaScript foram limitados ao botão.
- Preferências antigas em `localStorage` continuam compatíveis.
- Nenhuma regra de negócio, rota, banco, Tiny ou VSM foi alterada.

## Rollback
Restaurar `views/layout_top.php` e `public/assets/minimalist-ui.js` da V104.43.
