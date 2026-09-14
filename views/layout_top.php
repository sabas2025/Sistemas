<?php
$u = $_SESSION['user'] ?? ['nome'=>'Usuário','perfil'=>''];
$active=$_GET['page']??'dashboard';
$ambientePainel='local';
$isProd = class_exists('App') ? App::isProduction() : false;
try { $ambientePainel = IntegrationConfig::get()['ambiente'] ?? App::env(); } catch(Throwable $e){ $ambientePainel=App::env(); }
$modoVisual = class_exists('VisualProfileService') ? VisualProfileService::current() : 'admin';
$pageDescriptions = [
 'dashboard'=>'Visão consolidada da operação e dos alertas que exigem atenção.',
 'centro-operacoes'=>'Acompanhe pedidos, estoque, fiscal e filas em um único lugar.',
 'pedidos'=>'Acompanhe o ciclo Tiny → HUB → VSM com rastreabilidade.',
 'produtos'=>'Consulte produtos, vínculos, pendências e sincronizações.',
 'estoque-dashboard'=>'Monitore saldos, movimentos e divergências de estoque.',
 'fiscal'=>'Acompanhe NF-e, XML, retornos e falhas do fluxo fiscal.',
 'fila'=>'Monitore itens pendentes, retries, processamento e falhas.',
 'fila-morta'=>'Analise falhas definitivas antes de reprocessar.',
 'logs'=>'Pesquise eventos operacionais e Trace IDs.',
 'vsm-saude'=>'Verifique disponibilidade e latência dos endpoints VSM.',
 'configuracoes'=>'Gerencie integrações e parâmetros operacionais com segurança.',
 'backups'=>'Gere, valide e restaure backups de forma controlada.',
 'notificacoes'=>'Veja alertas e eventos que precisam de atenção.'
];
$pageDescription = $pageDescription ?? ($pageDescriptions[$active] ?? 'Operação segura, rastreável e integrada entre Tiny, HUB e VSM.');
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex,nofollow,noarchive,nosnippet,noimageindex">
<meta name="theme-color" content="#2563eb" id="hubThemeColor">
<meta name="color-scheme" content="light" id="hubColorScheme">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Hub Integração">
<link rel="manifest" href="manifest.webmanifest?v=104.49.3">
<link rel="icon" href="assets/img/favicon-32.png" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" sizes="180x180" href="assets/img/apple-touch-icon.png">
<meta name="application-name" content="Hub de Integração">
<meta name="msapplication-TileColor" content="#2563eb">

<title><?=e($pageTitle ?? 'Hub de Integração')?></title>
<link href="assets/vendor/bootstrap/bootstrap.min.css?v=5" rel="stylesheet">
<link href="assets/vendor/bootstrap-icons/bootstrap-icons.css?v=1" rel="stylesheet">
<link href="assets/app.min.css?v=104.49.3" rel="stylesheet">
<link href="assets/responsive-enterprise.min.css?v=104.49.3" rel="stylesheet">
<link href="assets/scroll-enterprise.min.css?v=104.49.3" rel="stylesheet">
<link href="assets/minimalist-enterprise.min.css?v=104.49.3" rel="stylesheet">
<link href="assets/integration-center.min.css?v=104.49.3" rel="stylesheet">
<script nonce="<?=App::cspNonce()?>">window.HUB_NOTIF_ENABLED=true;</script>
<script src="assets/pwa.min.js?v=104.49.3" defer></script>
</head>
<body data-hub-page="<?=e($active)?>" data-hub-density="comfortable" data-hub-theme="light">
<a class="hub-skip-link" href="#hubMainContent">Ir para o conteúdo principal</a>
<div class="hub-connection-status" data-hub-connection role="status" aria-live="polite" hidden></div>
<div class="hub-command-backdrop" data-hub-command-backdrop hidden></div>
<section class="hub-command-palette" data-hub-command-palette role="dialog" aria-modal="true" aria-labelledby="hubCommandTitle" hidden>
  <div class="hub-command-head"><div><strong id="hubCommandTitle">Buscar no Hub</strong><small>Digite o nome de uma página ou função</small></div><kbd>Esc</kbd></div>
  <label class="visually-hidden" for="hubCommandInput">Buscar página</label>
  <div class="hub-command-input-wrap"><i class="bi bi-search" aria-hidden="true"></i><input id="hubCommandInput" data-hub-command-input type="search" autocomplete="off" placeholder="Pedidos, estoque, fila, Trace ID..."></div>
  <div class="hub-command-results" data-hub-command-results role="listbox"></div>
  <div class="hub-command-foot"><span><kbd>↑</kbd><kbd>↓</kbd> navegar</span><span><kbd>Enter</kbd> abrir</span><span><kbd>Ctrl</kbd> + <kbd>K</kbd> buscar</span></div>
</section>
<div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>
<div class="app-shell">
  <aside class="sidebar" id="sidebar" aria-label="Menu principal">
    <div class="brand brand-hub"><div class="brand-icon brand-logo"><img src="assets/img/hub-integracao-logo.svg" alt=""></div><div class="brand-text"><b>Hub de Integração</b><small>Middleware Enterprise</small></div><button class="sidebar-close" id="sidebarClose" type="button" aria-label="Fechar menu"><i class="bi bi-x-lg"></i></button></div>
    <div class="menu-search-wrap"><i class="bi bi-search"></i><input id="menuSearch" type="search" placeholder="Buscar menu..." aria-label="Buscar item do menu"></div>
    <nav class="menu" id="mainMenu">
      <?php
      /*
       * V42 - MENU PROFISSIONAL POR PERFIL VISUAL
       * Operador vê só operação. Supervisor ganha reconciliação/relatórios.
       * Admin vê integrações/configurações. Desenvolvedor vê Central Técnica completa.
       */
      $items=[
        'dashboard'=>['bi-speedometer2','Dashboard','dashboard','visualizar'],
        'centro-operacoes'=>['bi-display','Centro de Operações','dashboard','visualizar'],
        'dashboard-executivo'=>['bi-graph-up-arrow','Dashboard Executivo','dashboard','visualizar'],
        'monitor-divergencias'=>['bi-arrow-left-right','Divergências','reconciliacao','visualizar'],
        'evidencias-homologacao'=>['bi-file-earmark-check','Evidências','auditoria','visualizar'],
        'alertas-operacionais'=>['bi-exclamation-triangle','Alertas','dashboard','visualizar'],
        'pedidos'=>['bi-bag-check','Pedidos','pedidos','visualizar'],
        'produtos'=>['bi-box-seam','Produtos','produtos','visualizar'],
        'estoque-dashboard'=>['bi-box-arrow-down','Estoque','estoque','visualizar'],
        'fiscal'=>['bi-filetype-xml','XML / NF-e','configuracoes','visualizar'],
        'central-homologacao'=>['bi-diagram-3','Central de Homologação','homologacao','visualizar'],
        'integracoes'=>['bi-diagram-3','Integrações','configuracoes','visualizar'],
        'tiny-v2-homologacao'=>['bi-check2-circle','Homologação Tiny V2','homologacao','visualizar'],
        'tiny-v3-homologacao'=>['bi-shield-check','Homologação Tiny V3','homologacao','visualizar'],
        'reconciliacao'=>['bi-diagram-2','Reconciliação','reconciliacao','visualizar'],
        'logs'=>['bi-journal-text','Relatórios','logs','visualizar'],
        'configuracoes'=>['bi-sliders','Configurações','configuracoes','visualizar'],
        'backups'=>['bi-database-down','Backups','backup','visualizar'],
        'entrada-producao'=>['bi-rocket-takeoff','Entrada em Produção','configuracoes','visualizar'],
        'producao-ready'=>['bi-shield-check','Produção Segura','configuracoes','visualizar'],
        'central-tecnica'=>['bi-tools','Central Técnica','configuracoes','visualizar'],
        'diagnostico-config-real'=>['bi-hdd-network','Diagnóstico Config Real','configuracoes','visualizar'],
        'sobre'=>['bi-info-circle','Sobre','configuracoes','visualizar'],
        'pwa-status'=>['bi-phone-flip','PWA do Hub','configuracoes','visualizar'],
        'seguranca-extrema'=>['bi-shield-lock','Segurança Extrema','seguranca','visualizar'],
        'security-center'=>['bi-shield-exclamation','Central de Segurança','seguranca','visualizar'],
        'security-soc'=>['bi-broadcast-pin','SOC','seguranca','visualizar'],
        'security-code-audit'=>['bi-terminal','Auditoria de Código','seguranca','visualizar'],
        'security-inventory'=>['bi-archive','Inventário Legado/Banco','seguranca','visualizar'],
        'security-backup-trust'=>['bi-database-check','Backup Trust','seguranca','visualizar'],
        'security-health'=>['bi-heart-pulse','Health Real Time','seguranca','visualizar'],
        'security-audit-signatures'=>['bi-file-earmark-lock','Assinaturas Auditoria','seguranca','visualizar'],
        'security-events'=>['bi-activity','Eventos de Segurança','seguranca','visualizar'],
        'security-ips'=>['bi-ban','IPs Bloqueados','seguranca','visualizar'],
        'security-circuit-breakers'=>['bi-plug','Circuit Breakers','seguranca','visualizar'],
        'security-hardening'=>['bi-hammer','Hardening Produção','seguranca','visualizar'],
        'security-ssl'=>['bi-lock','Certificados SSL','seguranca','visualizar'],
        'security-user-audit'=>['bi-person-check','Auditoria de Usuários','seguranca','visualizar'],
        'security-pentest'=>['bi-clipboard-check','Pentest Checklist','seguranca','visualizar'],
        'security-score'=>['bi-speedometer2','Security Score','seguranca','visualizar'],
        'security-assisted-test'=>['bi-shield-check','Teste Segurança Assistido','seguranca','visualizar'],
        'security-fim'=>['bi-fingerprint','Integridade de Arquivos','seguranca','visualizar'],
        'tutorial-sistema'=>['bi-book','Tutorial','configuracoes','visualizar'],
        'planos-comerciais'=>['bi-cash-coin','Planos Comerciais','configuracoes','visualizar'],
        'licencas-clientes'=>['bi-key','Licenças por Cliente','configuracoes','visualizar'],
        'conectores-plugaveis'=>['bi-plug','Conectores Plugáveis','configuracoes','visualizar'],
        'painel-cobranca'=>['bi-receipt','Painel de Cobrança','configuracoes','visualizar'],
        'ambiente-demo'=>['bi-window-stack','Ambiente Demo','configuracoes','visualizar'],
        'documentos-comerciais'=>['bi-file-earmark-text','Docs Comerciais','configuracoes','visualizar'],
        'producao-comercial'=>['bi-rocket','Produção Comercial','configuracoes','visualizar'],
        'producao-comercial-final'=>['bi-patch-check','Checklist Final Comercial','configuracoes','visualizar'],
        'analise-comercial-tecnica'=>['bi-clipboard2-pulse','Análise Comercial/Técnica','configuracoes','visualizar'],
        'cliente-portal'=>['bi-person-workspace','Portal Cliente','configuracoes','visualizar'],
        'suporte-sla'=>['bi-headset','SLA e Suporte','configuracoes','visualizar'],
        'notificacoes'=>['bi-bell','Notificações','notificacoes','visualizar'],
      ];
      $technicalPages = class_exists('RouteModuleRegistry') ? RouteModuleRegistry::technicalPages() : [];
      $centralActive = in_array($active, $technicalPages, true);
      $groups = [
        'Operação' => ['dashboard','centro-operacoes','dashboard-executivo','alertas-operacionais','pedidos','produtos','estoque-dashboard','monitor-divergencias','fiscal','notificacoes'],
        'Gestão' => ['central-homologacao','integracoes','diagnostico-config-real','tiny-v2-homologacao','tiny-v3-homologacao','evidencias-homologacao','reconciliacao','logs','configuracoes','backups','entrada-producao','producao-ready','central-tecnica'],
        'Comercial' => ['planos-comerciais','licencas-clientes','conectores-plugaveis','painel-cobranca','ambiente-demo','cliente-portal','suporte-sla','documentos-comerciais','producao-comercial','producao-comercial-final','analise-comercial-tecnica'],
        'Sistema' => ['sobre','pwa-status','tutorial-sistema'],
        'Segurança' => ['seguranca-extrema','security-center','security-soc','security-events','security-ips','security-circuit-breakers','security-assisted-test','security-code-audit','security-inventory','security-backup-trust','security-health','security-audit-signatures','security-fim','security-score','security-hardening','security-ssl','security-user-audit','security-pentest'],
      ];
      ?>
      <?php if(!$isProd): ?>
      <div class="mode-switch">
        <span><i class="bi bi-person-badge"></i> <?=e(VisualProfileService::label($modoVisual))?></span>
        <div class="mode-links">
          <a href="index.php?page=<?=e($active)?>&modo=operador">Operador</a>
          <a href="index.php?page=<?=e($active)?>&modo=supervisor">Supervisor</a>
          <a href="index.php?page=<?=e($active)?>&modo=admin">Admin</a>
          <a href="index.php?page=<?=e($active)?>&modo=dev">Dev</a>
        </div>
      </div>
      <?php endif; ?>
      <?php
      foreach($groups as $groupName=>$keys):
        $printed=false;
        foreach($keys as $key):
          if(!isset($items[$key])) continue;
          if(class_exists('VisualProfileService') && !VisualProfileService::canSee($key,$modoVisual)) continue;
          $it=$items[$key]; if(!PermissionService::can($it[2],$it[3])) continue;
          if(!$printed){ echo '<div class="menu-section" data-menu-label="'.e($groupName).'">'.e($groupName).'</div>'; $printed=true; }
          $isActive = ($active===$key || ($centralActive && $key==='central-tecnica') || (in_array($active, ['orquestracao-integracoes','regras-sincronizacao','tiny-webhooks'], true) && $key==='integracoes') || (in_array($active, ['fila','fila-morta','divergencia-estoque'], true) && $key==='reconciliacao') || ($active===''&&$key==='dashboard') || (in_array($active, ['baixas-estoque','divergencia-estoque','estoque-config','estoque-alertas','estoque-sku-historico','estoque-consultas-vsm','estoque-consulta-vsm-resultados'], true) && $key==='estoque-dashboard'));
      ?><a data-menu-group="<?=e($groupName)?>" class="<?=$isActive?'active':''?>" href="index.php?page=<?=$key?>"><i class="bi <?=$it[0]?>"></i><span><?=$it[1]?></span></a><?php endforeach; endforeach; ?>
      <?php if(PermissionService::can('fila','reprocessar')): ?><form method="post" action="index.php?page=api/processar-fila" class="px-3 mt-2"><?= Csrf::input() ?><button class="btn btn-sm btn-outline-light w-100"><i class="bi bi-play-circle"></i> Processar Fila</button></form><?php endif; ?>
    <?php if(PermissionService::can('backup','gerar')): ?><form method="post" action="index.php?page=backup" class="px-3 mt-2"><?= Csrf::input() ?><button class="btn btn-sm btn-outline-light w-100">Backup ZIP</button></form><?php endif; ?></nav>
    <div class="sidebar-footer"><small>Ambiente: <b><?=e(strtoupper($ambientePainel))?></b></small><br><small>Versão: <b><?=e(class_exists('SystemVersionService') ? SystemVersionService::label() : 'V104.49.3')?></b></small></div>
  </aside>
  <main class="main" id="hubMainContent" tabindex="-1">
    <header class="topbar">
      <div class="topbar-title">
        <button class="sidebar-toggle" id="sidebarToggle" type="button" aria-label="Abrir menu lateral" aria-controls="sidebar" aria-expanded="false"><i class="bi bi-list"></i></button>
        <div><h1><?=e($pageTitle ?? 'Dashboard')?></h1><?php if(trim((string)$pageDescription)!==''): ?><p><?=e($pageDescription)?></p><?php endif; ?></div>
      </div>
      <div class="topbar-actions"><div class="env-badge <?=($ambientePainel==='producao'?'env-prod':'env-homolog')?>"><?=e(strtoupper($ambientePainel))?></div><div class="userbox">
        <a class="btn btn-sm btn-outline-secondary" href="index.php?page=pwa-status" title="Status do PWA"><i class="bi bi-phone-flip"></i> PWA</a>
        <button class="btn btn-sm btn-outline-primary pwa-install-btn d-none" type="button" data-pwa-install title="Instalar aplicativo do Hub"><i class="bi bi-phone"></i> Instalar App</button>
        <button class="btn btn-sm btn-outline-secondary" type="button" data-pwa-refresh title="Atualizar PWA" aria-label="Atualizar aplicativo PWA"><i class="bi bi-arrow-clockwise"></i></button>
        <div class="notif-widget">
          <button class="notif-btn" id="notifToggle" type="button" title="Notificações">
            <i class="bi bi-bell"></i><span id="notifBadge" class="notif-badge d-none">0</span>
          </button>
          <div class="notif-dropdown d-none" id="notifDropdown">
            <div class="notif-head"><b>Notificações</b><a href="index.php?page=notificacoes">Ver todas</a></div>
            <div id="notifList" class="notif-list"><div class="notif-empty">Carregando...</div></div>
          </div>
        </div>
        <div class="avatar"><?=strtoupper(substr($u['nome']??'A',0,1))?></div><div class="userbox-ident"><b><?=e($u['nome']??'Admin')?></b><small><?=e($u['perfil']??'admin')?></small></div><a class="btn btn-sm btn-outline-danger btn-logout" href="index.php?page=logout">Sair</a></div><div class="topbar-more"><button class="topbar-more-btn" type="button" aria-label="Abrir ações rápidas" aria-expanded="false" data-topbar-more><i class="bi bi-three-dots-vertical"></i></button><div class="topbar-more-menu" data-topbar-menu><a href="index.php?page=pwa-status"><i class="bi bi-phone-flip"></i> Status PWA</a><button type="button" data-pwa-install><i class="bi bi-phone"></i> Instalar App</button><button type="button" data-pwa-refresh><i class="bi bi-arrow-clockwise"></i> Atualizar PWA</button><button type="button" data-hub-favorite><i class="bi bi-star"></i> Fixar página</button><button type="button" data-hub-command-open><i class="bi bi-search"></i> Buscar páginas <kbd>Ctrl K</kbd></button><button type="button" data-hub-density-toggle><i class="bi bi-layout-text-window"></i> Densidade confortável</button><button type="button" data-hub-theme-toggle><i class="bi bi-moon-stars"></i> Tema escuro</button><a href="index.php?page=logout" class="text-danger"><i class="bi bi-box-arrow-right"></i> Sair</a></div></div></div>
    </header>
    <section class="content">
      <nav class="hub-breadcrumb" aria-label="Navegação estrutural">
        <a href="index.php?page=dashboard"><i class="bi bi-house-door" aria-hidden="true"></i><span>Início</span></a>
        <i class="bi bi-chevron-right" aria-hidden="true"></i>
        <span aria-current="page"><?=e($pageTitle ?? 'Dashboard')?></span>
      </nav>
