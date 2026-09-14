# V104.48 — PWA Qualidade, Automação e Telemetria Segura

## Aplicado

- Versão unificada em `104.48.0` e cache `hub-integracao-v104-48`.
- Assets próprios CSS/JS minificados e mantidos também em fonte original para manutenção.
- Pipeline de build reproduzível com CleanCSS e Terser.
- Playwright dedicado ao manifesto, Service Worker, tela offline e endpoint de telemetria.
- GitHub Actions executando build, PHP lint, Playwright e Lighthouse.
- Limites Lighthouse: performance 0,85; acessibilidade 0,95; boas práticas 0,90; SEO 0,80.
- Telemetria PWA somente por lista branca, sem cookies, tokens, senhas, formulários ou conteúdo operacional.
- Redação automática de nomes de segredos, limite de 4 KB, rate limit por IP/minuto e armazenamento JSONL fora de `public`.
- Eventos técnicos do Service Worker: falha na instalação, ativação e reconstrução do cache.
- Eventos técnicos do navegador: erro JavaScript, rejeição de Promise e falha de registro do Service Worker.
- Endpoint recusa GET, origem cross-site e eventos desconhecidos.

## Segurança preservada

- Nenhum pedido, estoque, XML/NF-e, token Tiny/VSM, cliente, backup ou conteúdo de formulário é enviado à telemetria.
- Páginas PHP e APIs continuam em `network-only/no-store` no Service Worker.
- Assets somente são cacheados após validação de status e `Content-Type`.

## Validação desta entrega

- PHP lint completo em `app`, `public` e `views`.
- Sintaxe de todos os JavaScript validada pelo Node.
- Manifesto validado como JSON.
- Referências locais de assets verificadas.
- Testes Playwright locais: 4 aprovados; teste visual preparado para CI com Chromium.

## Operação

1. Execute `npm ci`.
2. Execute `npm run build:pwa` antes de publicar.
3. Execute `npm run test:pwa` com `HUB_BASE_URL` apontando para o ambiente.
4. Execute `npm run lighthouse:pwa` com o servidor local ativo na porta configurada.
5. Consulte telemetria em `storage/security-reports/pwa-telemetry-AAAA-MM.jsonl`.
