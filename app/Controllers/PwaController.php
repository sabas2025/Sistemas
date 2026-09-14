<?php
class PwaController extends BaseModuleController {
  public function dispatch(string $page): void {
    Auth::requireLogin();
    PermissionService::require('configuracoes','visualizar');
    $pageTitle = 'PWA do Hub';
    $status = [
      'versao' => '104.49.3',
      'cache' => 'hub-integracao-v104-48',
      'manifesto' => is_file(__DIR__.'/../../public/manifest.webmanifest'),
      'service_worker' => is_file(__DIR__.'/../../public/sw.js'),
      'offline' => is_file(__DIR__.'/../../public/offline.html'),
      'https' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
      'host' => $_SERVER['HTTP_HOST'] ?? 'localhost',
    ];
    $this->view('pwa_status', compact('pageTitle','status'));
  }
}
