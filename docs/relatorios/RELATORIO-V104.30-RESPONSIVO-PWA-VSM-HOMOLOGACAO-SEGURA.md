# RELATÓRIO V104.30 — Responsividade Enterprise + VSM Homologação Segura

## Objetivo
Aplicar melhorias solicitadas sem remover funcionalidades, sem quebrar Tiny/VSM e sem alterar regras de negócio dos fluxos.

## Melhorias aplicadas

### 1. Dashboard > VSM
- Removido comportamento conceitual incorreto de mostrar `Produção` apenas porque existe URL configurada.
- Novo status usa `VsmEnvironmentService::dashboardStatus()`.
- Padrão inicial: `Homologação` quando URL contém homologação.
- Produção só aparece como liberada quando:
  - URL não é homologação;
  - `vsm_ambiente = producao`;
  - `vsm_producao_liberada = 1`;
  - último teste VSM está OK.

### 2. Configurações VSM
- Adicionado seletor `Ambiente VSM`.
- Adicionada chave `VSM liberou produção oficialmente`.
- Adicionado campo informativo do último teste VSM.
- Em URL de homologação, o sistema força homologação e bloqueia liberação de produção.
- Se tentar liberar produção sem token, a liberação é bloqueada e gera notificação.

### 3. Banco de dados
Novas colunas opcionais em `configuracoes_integracao`:
- `vsm_ambiente`
- `vsm_producao_liberada`
- `vsm_ultimo_teste_ok`
- `vsm_ultimo_teste_em`
- `vsm_host_producao_liberado`
- `vsm_producao_liberada_em`
- `vsm_producao_liberada_por`

Também foi criado `database/update_v104_30_responsivo_vsm_seguro.sql`.

### 4. Responsividade PC/iOS/Android
- Criada camada final de CSS V104.30 no `public/assets/app.css`.
- Melhorado comportamento de topbar em telas pequenas.
- Adicionado menu de ações rápidas no mobile.
- Adicionado suporte a `safe-area-inset` para iPhone/iPad com notch.
- Botões e inputs agora respeitam área mínima de toque.
- Tabelas viram cards em telas menores que 768px.

### 5. PWA
- Service Worker atualizado de V87 para V104.30.
- Cache PWA agora inclui assets locais de Bootstrap/ícones.
- Bootstrap e ícones deixam de depender de CDN para abrir no PWA/offline.
- CSP foi ajustado para `self`, reduzindo dependência externa e melhorando segurança.

## Arquivos alterados
- `app/Controllers/DashboardController.php`
- `app/Services/IntegrationConfig.php`
- `app/Services/SystemVersionService.php`
- `app/Services/VsmEnvironmentService.php`
- `config/config.php`
- `views/configuracoes.php`
- `views/layout_top.php`
- `views/layout_bottom.php`
- `public/assets/app.css`
- `public/assets/pwa.js`
- `public/sw.js`
- `public/assets/vendor/bootstrap/bootstrap.min.css`
- `public/assets/vendor/bootstrap/bootstrap.bundle.min.js`
- `public/assets/vendor/bootstrap-icons/bootstrap-icons.css`
- `database/install_final_current.sql`
- `database/repair_current.sql`
- `database/update_v104_30_responsivo_vsm_seguro.sql`

## Impacto
- Tiny: não altera fluxo, token, webhook ou OAuth.
- VSM: não altera envio/recebimento; apenas corrige ambiente/status e liberação de produção.
- Banco: adiciona colunas opcionais com default seguro.
- APIs: não altera contrato.
- PWA: limpa cache antigo por nova versão de cache.
- Produção: reduz risco de enviar operação real achando que homologação é produção.

## Rollback
1. Voltar ao ZIP V104.29.
2. Opcionalmente manter as colunas novas, pois são compatíveis e não interferem no sistema antigo.
3. Para reverter a exibição VSM, restaurar `DashboardController.php`, `views/configuracoes.php` e `VsmEnvironmentService.php` da versão anterior.

## Opinião técnica
A correção é necessária. O Hub não deve declarar VSM como produção apenas por URL configurada. A nova versão deixa a operação mais segura, principalmente para ambientes em que Tiny V2 está produtivo, Tiny V3 está em homologação e VSM ainda aguarda liberação oficial.
