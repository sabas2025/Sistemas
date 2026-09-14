# V85 — Identidade Hub de Integração no sistema inteiro

## Objetivo
Aplicar a identidade **Hub de Integração** em todo o sistema, não apenas na tela de login, mantendo VSM e Tiny apenas como nomes operacionais das integrações.

## Alterações aplicadas

### Identidade visual
- Criado logo SVG reutilizável em `public/assets/img/hub-integracao-logo.svg`.
- Logo aplicada no menu lateral.
- Logo mantida na tela de login.
- Criada identidade visual para página de erro.
- Criada tela base de manutenção.
- Rodapé ajustado para `Hub de Integração Enterprise`.

### Login
- Login continua futurista.
- Criado `public/assets/login.css` para separar estilo do login.
- Criado `public/assets/bootstrap-login-lite.css` para o login não depender do Bootstrap via CDN.
- Título e textos padronizados como `Hub de Integração`.

### Sistema interno
- Menu lateral alterado para `Hub de Integração`.
- Título padrão alterado para `Hub de Integração`.
- Topbar ajustada para texto institucional genérico.
- Página de erro agora usa visual do Hub com Trace ID.
- Tela de manutenção criada em `views/manutencao.php`.
- Rota `index.php?page=manutencao` criada.

### Instalador
- `public/install.php` agora usa `Hub de Integração`.
- `app_name` padrão alterado para `Hub de Integração Enterprise`.
- Instalador recebeu logo no cabeçalho.
- Criado `database/install_final_v85.sql`.
- Referências técnicas de instalação atualizadas para V85.

### Relatórios e serviços
- Backups e relatórios foram renomeados para `Hub de Integração`.
- Emissor 2FA alterado para `Hub de Integração`.
- Títulos de relatório de homologação ajustados.

## Observação importante
As expressões `VSM ↔ Tiny` continuam em telas técnicas onde representam o fluxo real de integração, por exemplo mapeamento de categorias e regras de produto. Isso não é identidade visual; é regra operacional.

## Validação
- Todos os arquivos PHP passaram no `php -l`.
- ZIP validado com `unzip -t`.
- Nenhuma alteração de banco obrigatória para aplicar a identidade.

## Sugestões futuras
1. Criar tema claro/escuro configurável.
2. Criar upload de logo por empresa.
3. Criar white-label por filial ou cliente.
4. Remover Bootstrap CDN também das telas internas, usando assets locais.
5. Criar tela de preferências visuais no painel.
6. Criar manifest/PWA com ícone do Hub.
7. Padronizar todos os nomes antigos de arquivos `fiscal_*` para `xml_nfe_*`.
