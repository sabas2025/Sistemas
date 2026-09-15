# V104.42 — Governança visual e responsividade

## Aplicado
- Breadcrumb contextual sem alterar rotas.
- Densidade confortável/compacta persistida localmente.
- Até oito páginas favoritas persistidas no navegador.
- Conteúdo técnico secundário recolhível no dashboard.
- Normalização apenas visual dos principais status.
- Erros com Trace ID reforçados e copiáveis.
- Detecção de overflow horizontal.
- Teste Playwright em cinco resoluções e dez rotas críticas.
- PWA/cache atualizados para 104.42.

## Compatibilidade
Nenhuma regra Tiny, VSM, OAuth, webhook, fila, worker, banco ou permissão foi alterada. Preferências são locais ao navegador e podem ser removidas limpando o armazenamento do site.

## Rollback
Restaurar `layout_top.php`, `dashboard.php`, `minimalist-enterprise.css`, `minimalist-ui.js`, `sw.js` e `pwa.js` da V104.41. Não há migration.
