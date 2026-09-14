# V104.45 — PWA claro e ícones unificados

## Problema
O PWA herdava automaticamente o tema escuro do sistema operacional, usava `theme_color` azul-marinho e ícones com fundo escuro, divergindo do visual web claro.

## Correção
- Tema claro passa a ser o padrão absoluto.
- Tema escuro somente quando escolhido explicitamente pelo usuário.
- `theme_color` alterado para azul institucional `#2563eb`.
- `background_color` mantido em `#f8fafc`.
- Barra de status do iOS configurada como `default`.
- Ícones PWA, maskable, favicon e Apple Touch recriados com fundo claro e a mesma identidade visual.
- Cache e versão atualizados para V104.45.

## Compatibilidade
Sem alterações em Tiny, VSM, banco, filas, workers, rotas, permissões ou regras de negócio.
