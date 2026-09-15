<?php require __DIR__.'/layout_top.php'; ?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <div>
    <h2 class="mb-1">PWA do Hub</h2>
    <p class="text-muted mb-0">Instalação no celular/desktop, cache seguro, atualização e suporte offline controlado.</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <button class="btn btn-primary d-none" type="button" data-pwa-install><i class="bi bi-phone"></i> Instalar App</button>
    <button class="btn btn-outline-primary" type="button" data-pwa-check><i class="bi bi-search"></i> Verificar</button><button class="btn btn-outline-primary" type="button" data-pwa-refresh><i class="bi bi-arrow-clockwise"></i> Atualizar PWA</button>
  </div>
</div>

<div class="pwa-status-grid mb-4">
  <div class="pwa-status-card"><div class="icon"><i class="bi bi-wifi"></i></div><div class="text-muted">Conexão</div><div class="pwa-status-value" data-pwa-online><?= $status['https'] ? 'Online' : 'Verificar HTTPS' ?></div><small>O PWA exige HTTPS em produção.</small></div>
  <div class="pwa-status-card"><div class="icon"><i class="bi bi-box-arrow-down"></i></div><div class="text-muted">Versão PWA</div><div class="pwa-status-value" data-pwa-version><?=e($status['versao'])?></div><small>Versão do service worker/cache.</small></div>
  <div class="pwa-status-card"><div class="icon"><i class="bi bi-hdd-network"></i></div><div class="text-muted">Cache ativo</div><div class="pwa-status-value" data-pwa-cache><?=e($status['cache'])?></div><small>Cache restrito a assets e tela offline.</small></div>
  <div class="pwa-status-card"><div class="icon"><i class="bi bi-apple"></i></div><div class="text-muted">iOS/Android</div><div class="pwa-status-value">Pronto</div><small>Manifest, apple-touch-icon e metatags configuradas.</small></div>
  <div class="pwa-status-card"><div class="icon"><i class="bi bi-gear-wide-connected"></i></div><div class="text-muted">Service Worker</div><div class="pwa-status-value" data-pwa-sw-state>Verificando</div><small>Estado real do registro no navegador.</small></div>
  <div class="pwa-status-card"><div class="icon"><i class="bi bi-window"></i></div><div class="text-muted">Modo de execução</div><div class="pwa-status-value" data-pwa-mode>Navegador</div><small>Standalone quando instalado como aplicativo.</small></div>
<div class="pwa-status-card"><div class="icon"><i class="bi bi-shield-check"></i></div><div class="text-muted">HTTPS</div><div class="pwa-status-value" data-pwa-https>Verificando</div><small>Obrigatório fora do localhost.</small></div><div class="pwa-status-card"><div class="icon"><i class="bi bi-diagram-2"></i></div><div class="text-muted">Página controlada</div><div class="pwa-status-value" data-pwa-controlled>Verificando</div><small>Controle pelo Service Worker atual.</small></div></div>

<div class="card shadow-sm border-0 mb-4"><div class="card-body"><h5 class="fw-bold mb-3"><i class="bi bi-activity"></i> Diagnóstico do navegador</h5><div class="row g-3"><div class="col-md-5"><small class="text-muted d-block">Escopo registrado</small><code class="pwa-diagnostic-code" data-pwa-scope>Verificando...</code></div><div class="col-md-7"><small class="text-muted d-block">Política operacional</small><strong>Páginas PHP e APIs sempre usam rede e nunca entram no cache.</strong></div></div></div></div>


<div class="card shadow-sm border-0 mb-4"><div class="card-body"><h5 class="fw-bold mb-3"><i class="bi bi-database-check"></i> Integridade do cache</h5><div class="row g-3"><div class="col-md-3"><small class="text-muted d-block">Assets encontrados</small><strong data-pwa-assets-found>Verificando</strong></div><div class="col-md-3"><small class="text-muted d-block">Assets esperados</small><strong data-pwa-assets-expected>Verificando</strong></div><div class="col-md-6"><small class="text-muted d-block">Assets ausentes</small><span data-pwa-assets-missing>Verificando</span></div><div class="col-12"><small class="text-muted">Última checagem: <span data-pwa-last-check>—</span></small></div></div><div class="d-flex flex-wrap gap-2 mt-3"><button class="btn btn-outline-secondary" type="button" data-pwa-copy-diagnostics><i class="bi bi-clipboard"></i> Copiar diagnóstico</button><button class="btn btn-outline-secondary" type="button" data-pwa-test-offline><i class="bi bi-wifi-off"></i> Testar tela offline</button></div></div></div>
<div class="card shadow-sm border-0 mb-4"><div class="card-body">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h5 class="fw-bold mb-1"><i class="bi bi-devices"></i> Compatibilidade multiplataforma</h5><p class="text-muted mb-0">Plataforma detectada: <strong data-pwa-platform>Navegador</strong> • Estado: <strong data-pwa-installed>Disponível</strong></p></div>
  </div>
  <div class="table-responsive"><table class="table platform-compat-table mb-0"><thead><tr><th>Plataforma</th><th>Forma de uso</th><th>Compatibilidade</th></tr></thead><tbody>
    <tr><td>Android</td><td>PWA instalável pelo Chrome ou Edge.</td><td><span class="badge text-bg-success">Compatível</span></td></tr>
    <tr><td>iPhone/iPad</td><td>Safari → Compartilhar → Adicionar à Tela de Início.</td><td><span class="badge text-bg-success">Compatível</span></td></tr>
    <tr><td>Windows</td><td>Instalação pelo Edge ou Chrome.</td><td><span class="badge text-bg-success">Compatível</span></td></tr>
    <tr><td>macOS</td><td>Instalação pelo Safari, Chrome ou Edge.</td><td><span class="badge text-bg-success">Compatível</span></td></tr>
    <tr><td>Navegador</td><td>Sistema web responsivo, sem necessidade de instalação.</td><td><span class="badge text-bg-success">Compatível</span></td></tr>
  </tbody></table></div>
  <div class="alert alert-primary pwa-ios-help mt-3 d-none" data-pwa-ios-help><strong>iPhone/iPad:</strong> abra esta página no Safari, toque no ícone Compartilhar e selecione <em>Adicionar à Tela de Início</em>.</div>
</div></div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card shadow-sm border-0 h-100"><div class="card-body">
      <h5 class="fw-bold mb-3"><i class="bi bi-shield-lock"></i> Política de cache segura</h5>
      <ul class="pwa-safe-list mb-0">
        <li>Não cacheia páginas administrativas sensíveis.</li>
        <li>Não cacheia POST, APIs, pedidos, estoque, backups, auditoria, XML/NF-e ou dashboard.</li>
        <li>Cacheia apenas CSS, JS, logo, ícones, manifesto e página offline.</li>
        <li>Quando offline, mostra aviso claro e bloqueia dependência de dados operacionais.</li>
      </ul>
    </div></div>
  </div>
  <div class="col-lg-5">
    <div class="pwa-refresh-panel h-100">
      <h5 class="fw-bold mb-2"><i class="bi bi-arrow-repeat"></i> Atualização</h5>
      <p class="text-muted">Use este botão após publicar uma nova versão para limpar cache antigo e forçar o Hub a carregar os assets mais recentes.</p>
      <button class="btn btn-primary w-100" type="button" data-pwa-refresh>🔄 Atualizar PWA agora</button>
      <hr>
      <small class="text-muted">Host atual: <?=e($status['host'])?></small><br>
      <small class="text-muted">Manifesto: <?= $status['manifesto'] ? 'OK' : 'Ausente' ?> • SW: <?= $status['service_worker'] ? 'OK' : 'Ausente' ?> • Offline: <?= $status['offline'] ? 'OK' : 'Ausente' ?></small>
    </div>
  </div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
