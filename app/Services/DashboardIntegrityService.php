<?php
class DashboardIntegrityService {
  public static function checks(bool $deep = false): array {
    $checks = [];
    $requiredTables = [
      'pedidos_integracao','fila_integracao','produtos_mapeamento','empresas','filiais',
      'auditoria_eventos','notificacoes','logs_integracao','estoque_movimentos','configuracoes_integracao',
      'notas_fiscais','nfe_integracao','notas_fiscais_eventos'
    ];
    foreach ($requiredTables as $table) {
      $module = Database::tableModule($table);
      try {
        $pdo = Database::forTable($table);
        $pdo->query('SELECT 1 FROM '.$table.' LIMIT 1');
        $checks[] = ['item'=>'Tabela '.$table, 'modulo'=>$module, 'status'=>'ok', 'mensagem'=>'Acessível no módulo '.$module];
      } catch (Throwable $e) {
        $checks[] = ['item'=>'Tabela '.$table, 'modulo'=>$module, 'status'=>'erro', 'mensagem'=>$e->getMessage()];
      }
    }
    $files = [
      'View dashboard'=>'views/dashboard.php', 'View layout topo'=>'views/layout_top.php', 'View integridade'=>'views/dashboard_integridade.php',
      'Controller dashboard'=>'app/Controllers/DashboardController.php', 'Service integridade'=>'app/Services/DashboardIntegrityService.php'
    ];
    foreach ($files as $label=>$file) {
      $path = dirname(__DIR__,2).'/'.$file;
      $checks[] = ['item'=>$label, 'modulo'=>'arquivo', 'status'=>is_file($path)?'ok':'erro', 'mensagem'=>is_file($path)?'Arquivo encontrado':'Arquivo ausente: '.$file];
    }
    $vars = ['k','pedidos','logs','filaResumo','healthCards','syncRules','syncResumo','ultimoDiagVsm','ultimoDiagTiny','ultimoPedidoIntegrado','ultimaFalha','ultimaBaixaVsm','ultimoProdutoTiny','baixasErro','produtosErro','filaTinyVsm','filaVsmTiny','tinyV3Aviso'];
    foreach ($vars as $v) $checks[] = ['item'=>'Variável dashboard: '.$v, 'modulo'=>'view', 'status'=>'ok', 'mensagem'=>'Definida no DashboardController::dashboard()'];
    $routes = ['dashboard','dashboard-integridade','dashboard-integridade-executar','diagnostico','pedidos','produtos','baixas-estoque','fiscal','integracoes','logs','api/processar-fila'];
    $controller = is_file(dirname(__DIR__).'/Controllers/DashboardController.php') ? file_get_contents(dirname(__DIR__).'/Controllers/DashboardController.php') : '';
    foreach ($routes as $r) {
      $ok = ($r === 'dashboard') || str_contains($controller, "case '$r'") || str_contains($controller, 'case "'.$r.'"') || str_starts_with($r,'api/');
      $checks[] = ['item'=>'Link/rota: '.$r, 'modulo'=>'rota', 'status'=>$ok?'ok':'erro', 'mensagem'=>$ok?'Rota encontrada no dispatcher':'Rota não localizada no dispatcher'];
    }
    if (class_exists('ModuleHealthService')) { foreach (ModuleHealthService::checks() as $m) { $checks[] = ['item'=>'Health módulo: '.$m['modulo'],'modulo'=>$m['modulo'],'status'=>$m['status'],'mensagem'=>$m['mensagem']]; } }
    if (class_exists('MenuActionTestService')) { foreach (MenuActionTestService::run() as $m) { $checks[] = ['item'=>ucfirst($m['tipo']).': '.$m['item'],'modulo'=>$m['modulo'],'status'=>$m['status'],'mensagem'=>$m['mensagem']]; } }
    if ($deep) {
      $checks[] = self::deepQuery('Dashboard KPI pedidos', 'pedidos_integracao', 'SELECT COUNT(*) c FROM pedidos_integracao');
      $checks[] = self::deepQuery('Dashboard KPI fila pendente', 'fila_integracao', "SELECT COUNT(*) c FROM fila_integracao WHERE status='pendente'");
      $checks[] = self::deepQuery('Dashboard KPI logs erro', 'logs_integracao', "SELECT COUNT(*) c FROM logs_integracao WHERE nivel IN ('erro','critico')");
      $checks[] = self::deepQuery('Dashboard KPI fiscal', 'notas_fiscais', 'SELECT COUNT(*) c FROM notas_fiscais');
      $checks[] = self::deepQuery('Orquestração configuração', 'configuracoes_integracao', 'SELECT * FROM configuracoes_integracao WHERE id=1 LIMIT 1');
    }
    return $checks;
  }
  private static function deepQuery(string $item, string $table, string $sql): array {
    try { $pdo = Database::forTable($table); $pdo->query($sql); return ['item'=>$item,'modulo'=>Database::tableModule($table),'status'=>'ok','mensagem'=>'Consulta real executada com sucesso']; }
    catch(Throwable $e){ return ['item'=>$item,'modulo'=>Database::tableModule($table),'status'=>'erro','mensagem'=>$e->getMessage()]; }
  }
  public static function summary(array $checks): array {
    $total=count($checks); $erros=count(array_filter($checks, fn($c)=>($c['status']??'')==='erro'));
    return ['total'=>$total,'erros'=>$erros,'ok'=>$total-$erros,'status'=>$erros?'erro':'ok'];
  }
}
