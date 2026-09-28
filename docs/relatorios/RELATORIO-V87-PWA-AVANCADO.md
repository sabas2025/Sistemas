# RELATÓRIO V87 — PWA Avançado do Hub de Integração

## Objetivo
Evoluir o PWA criado na V86 com atualização segura, suporte iOS/Android melhorado, controle de cache, status visual e proteção contra cache de dados administrativos sensíveis.

## Alterações aplicadas

### 1. Service Worker V87
- Cache atualizado para `hub-integracao-v87`.
- Versão interna `87.0.0`.
- Remoção automática de caches antigos.
- Tratamento de mensagens para consultar versão e forçar atualização.
- Política segura: páginas administrativas e endpoints não são cacheados.
- Requisições POST continuam fora do cache.
- Navegação offline direcionada para `offline.html`.

### 2. Manifesto PWA melhorado
- `start_url` ajustado para `index.php?page=login&utm_source=pwa`.
- Adicionado `display_override`.
- Adicionado `prefer_related_applications=false`.
- Adicionadas screenshots básicas usando ícone do Hub.

### 3. JavaScript PWA reforçado
- Aviso de nova versão disponível.
- Botão `Atualizar agora`.
- Botão `Atualizar PWA` no painel.
- Consulta de versão/cache do service worker.
- Indicador online/offline.
- Fluxo de instalação mantido.

### 4. Nova tela PWA do Hub
Criada rota:

```text
index.php?page=pwa-status
```

Tela criada:

```text
views/pwa_status.php
```

Mostra:
- conexão;
- versão PWA;
- cache ativo;
- suporte iOS/Android;
- política segura de cache;
- botão de atualização;
- validação de manifesto, service worker e offline.

### 5. Suporte iOS melhorado
- `apple-touch-icon` com tamanho 180x180.
- `apple-mobile-web-app-capable`.
- `apple-mobile-web-app-title`.
- `apple-mobile-web-app-status-bar-style`.
- `application-name`.
- `msapplication-TileColor`.

### 6. Tela offline melhorada
- Mensagem mais clara.
- Aviso de segurança sobre dados operacionais.
- Data/hora da tentativa.
- Botão tentar novamente.
- Identidade visual do Hub.

### 7. Menu e topo
- Adicionado item `PWA do Hub` no grupo Sistema.
- Adicionado botão rápido PWA no topo.
- Adicionado botão de atualização rápida do PWA.

## Arquivos criados ou alterados
- `public/sw.js`
- `public/assets/pwa.js`
- `public/manifest.webmanifest`
- `public/offline.html`
- `public/assets/app.css`
- `app/Controllers/PwaController.php`
- `views/pwa_status.php`
- `views/layout_top.php`
- `views/login.php`
- `public/install.php`
- `public/index.php`

## Validação
- PHP validado com `php -l`.
- Manifesto JSON validado.
- Service worker validado com `node -c`.
- PWA JS validado com `node -c`.

## Observação importante
O PWA foi mantido conservador para não cachear dados sensíveis de operação. Isso é correto para um sistema administrativo com pedidos, estoque, backups, auditoria e XML/NF-e.

## Melhorias futuras sugeridas
- Push notification para alertas críticos.
- Badge de notificações no ícone do app.
- Modo white-label do PWA por empresa/filial.
- Tela de permissões PWA.
- Diagnóstico Lighthouse automático.
