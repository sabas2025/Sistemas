# V104.41 — PWA minimalista, legível e responsivo

## Escopo aplicado
- Design tokens únicos para cor, tipografia, espaçamento, raio e largura.
- Tipografia nativa, sem dependência externa.
- Cabeçalho simplificado; ações PWA e logout permanecem no menu de ações.
- Descrição contextual por página.
- Menu lateral em grupos recolhíveis, mantendo todas as rotas.
- Cards, painéis, KPIs, formulários, alertas e modais padronizados.
- Tabelas simples transformadas em cartões no mobile; tabelas complexas preservam rolagem.
- Foco visível, labels automáticos em botões de ícone, redução de movimento e alto contraste.
- Botão Voltar ao topo em páginas longas.
- Screenshots reais no manifesto e versão do Service Worker atualizada.

## Compatibilidade
Nenhuma rota, campo, controller, service, worker, fila, regra Tiny ou regra VSM foi removida ou alterada. As mudanças são de apresentação e usabilidade.

## Risco e rollback
Risco principal: página legada com tabela curta que prefira rolagem horizontal. Adicione a classe `keep-scroll` nessa tabela. Rollback: remover as referências a `minimalist-enterprise.css` e `minimalist-ui.js`.

## Próximas melhorias recomendadas
1. Homologação visual das 128 views em dispositivos reais.
2. Definir `pageDescription` individual nas páginas técnicas mais importantes.
3. Marcar explicitamente tabelas complexas com `keep-scroll`.
4. Reduzir conteúdo do dashboard por prioridade operacional.
5. Criar testes Playwright com screenshots e comparação visual.
