<?php
class LegacyRouteGuardService {
  public static function inspect(string $page, string $controller): void {
    if (!in_array($controller, ['V50Controller','V51Controller'], true)) return;
    try {
      Audit::event('rota.legado.controlado','info',[
        'mensagem'=>'Rota legada V50/V51 acessada sob guarda operacional.',
        'contexto'=>['page'=>$page,'controller'=>$controller],
        'acao_recomendada'=>'Migrar a funcionalidade para controller novo quando possível. Manter permissão e auditoria ativas.'
      ]);
    } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    $cfg = App::config()['security'] ?? [];
    if (!empty($cfg['disable_legacy_routes'])) {
      PermissionService::require('database','validar');
      $_SESSION['flash_error'] = 'Rota legada bloqueada por configuração de segurança. Use a Central Técnica ou migração segura.';
      redirect('index.php?page=central-tecnica');
    }
  }
}
