# V104.43 — UX, acessibilidade, tema e governança visual

## Aplicado
- Busca global de rotas com Ctrl/Cmd+K.
- Atalhos: `/` foca busca do menu; Alt+H abre Dashboard.
- Tema escuro opcional, persistido localmente.
- Skip link e foco no conteúdo principal.
- Estado online/offline acessível.
- Cabeçalho fixo em tabelas longas e contagem de registros.
- Testes adicionais para zoom, paisagem e overflow.
- Cache PWA V104.43.

## Impacto
Somente apresentação. Nenhuma regra Tiny, VSM, banco, fila, worker, webhook ou permissão foi alterada.

## Rollback
Restaurar layout_top.php, layout_bottom.php, minimalist-ui.js, minimalist-enterprise.css, pwa.js e sw.js da V104.42.
