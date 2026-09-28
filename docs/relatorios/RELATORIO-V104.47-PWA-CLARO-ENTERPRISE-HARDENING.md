# RELATÓRIO V104.47 — PWA CLARO ENTERPRISE HARDENING

## Alterações aplicadas
- Versão unificada em 104.47.0 e cache hub-integracao-v104-47.
- Todos os assets próprios versionados no login, layout e Service Worker.
- Atualização do cache sem janela vazia: primeiro atualiza o cache vigente e depois remove versões antigas.
- Validação de Content-Type antes de gravar CSS, JS, imagens, HTML e manifesto.
- Respostas HTTP 500–504 em navegação passam para a tela offline clara.
- Proteção automática de formulários alterados por input/change e aviso ao sair.
- Adiamento de atualização vinculado à versão atual.
- Modal de instalação com foco, Escape, clique no fundo, focus trap e restauração de foco.
- Detecção de plataforma com userAgentData quando disponível.
- Diagnóstico avançado: HTTPS, controle da página, assets esperados/encontrados/ausentes e última checagem.
- Botões para verificar atualização, copiar diagnóstico e testar a tela offline.
- Tela offline verifica disponibilidade real do Hub, não apenas navigator.onLine.
- Botão de instalação do login ajustado para tema claro.
- Páginas e APIs continuam network-only/no-store; dados Tiny/VSM nunca são armazenados no cache.

## Recomendações futuras
1. Criar testes Playwright para instalação, atualização entre versões e formulários não salvos.
2. Executar Lighthouse CI em cada entrega, com metas mínimas de PWA, acessibilidade e performance.
3. Adicionar telemetria anônima de falhas do Service Worker sem dados operacionais.
4. Implementar Web Push somente para alertas autorizados e sem conteúdo sensível.
5. Gerar bundles CSS/JS minificados em produção mantendo fontes separadas no desenvolvimento.
