<?php
class VisualProfileService {
  public static function current(): string {
    $u = $_SESSION['user'] ?? [];
    $perfil = strtolower((string)($u['perfil'] ?? 'admin'));
    if (isset($_GET['modo']) && in_array($_GET['modo'], ['operador','supervisor','admin','dev'], true)) {
      $_SESSION['visual_profile'] = $_GET['modo'];
      return $_GET['modo'];
    }
    if (!empty($_SESSION['visual_profile'])) return (string)$_SESSION['visual_profile'];
    return match($perfil) {
      'operador','caixa','vendedor' => 'operador',
      'supervisor','gerente' => 'supervisor',
      'dev','desenvolvedor','developer' => 'dev',
      default => 'admin',
    };
  }
  public static function allowedPages(string $mode): array {
    $base=['dashboard','centro-operacoes','dashboard-executivo','alertas-operacionais','pedidos','produtos','estoque-dashboard','monitor-divergencias','fiscal','baixas-estoque','notificacoes'];
    $super=array_merge($base,['reconciliacao','divergencia-estoque','fila','fila-morta','logs','auditoria','evidencias-homologacao','fiscal-dashboard','estoque-consultas-vsm']);
    $admin=array_merge($super,['integracoes','orquestracao-integracoes','regras-sincronizacao','tiny-webhooks','usuarios','configuracoes','backups','homologacao','homologacao-automatica','teste-real-tiny','central-tecnica']);
    $dev=array_values(array_unique(array_merge($admin, RouteModuleRegistry::technicalPages())));
    return match($mode){ 'operador'=>$base, 'supervisor'=>$super, 'admin'=>$admin, default=>$dev };
  }
  public static function canSee(string $page, ?string $mode=null): bool {
    $mode = $mode ?: self::current();
    if ($mode === 'dev') return true;
    return in_array($page, self::allowedPages($mode), true);
  }
  public static function label(string $mode): string { return ['operador'=>'Operador','supervisor'=>'Supervisor','admin'=>'Admin','dev'=>'Desenvolvedor'][$mode] ?? 'Admin'; }
}
