# V104.39 — Correção definitiva de rolagem responsiva

## Problema corrigido
As páginas podiam ficar estáticas, sem rolagem vertical, principalmente no PWA instalado, após abrir o menu móvel, retornar pelo histórico ou girar o aparelho. A causa era a combinação de `overflow:hidden`, alturas fixas em `100vh/100dvh` e persistência da classe `sidebar-open`.

## Alterações
- Nova camada final `public/assets/scroll-enterprise.css`.
- `html`, `body`, `.app-shell`, `.main` e `.content` voltaram a usar altura automática e rolagem vertical.
- O bloqueio do fundo ocorre somente enquanto o menu móvel está aberto.
- Recuperação automática da rolagem em `DOMContentLoaded`, `pageshow`, `pagehide`, resize e mudança de orientação.
- Compatibilidade com PWA standalone e áreas seguras de iPhone/iPad.
- Rolagem própria preservada para menu, modais, notificações, códigos e tabelas largas.
- Cache do Service Worker atualizado para V104.39.

## Validação
Execute: `php tests/enterprise/v104_39_scroll_test.php`.
