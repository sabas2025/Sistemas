<?php
class MenuActionTestService {
  public static function run(): array {
    $items=[]; $controllerPath = dirname(__DIR__).'/Controllers/DashboardController.php';
    $controller = is_file($controllerPath) ? file_get_contents($controllerPath) : '';
    foreach (OperationalRouteService::menuRoutes() as $r) {
      $page = $r['page'];
      $hasCase = ($page === 'dashboard') || str_contains($controller, "case '$page'") || str_contains($controller, 'case "'.$page.'"');
      $view = str_replace('-', '_', $page);
      if ($page === 'baixas-estoque') $view='baixas_estoque';
      if ($page === 'central-tecnica') $view='central_tecnica';
      $viewPath = dirname(__DIR__,2).'/views/'.$view.'.php';
      $items[] = ['tipo'=>'menu','item'=>$r['label'], 'page'=>$page, 'modulo'=>$r['module'], 'status'=>($hasCase && is_file($viewPath))?'ok':'erro', 'mensagem'=>($hasCase?'rota ok':'rota ausente').'; '.(is_file($viewPath)?'view ok':'view ausente')];
    }
    $forms = [
      ['page'=>'dashboard-integridade-executar','label'=>'Verificar dashboard','view'=>'dashboard_integridade.php'],
      ['page'=>'backup','label'=>'Backup ZIP','view'=>'layout_top.php'],
      ['page'=>'api/processar-fila','label'=>'Processar fila','view'=>'layout_top.php'],
      ['page'=>'atualizar-v43-fiscal-dashboard-install','label'=>'Aplicar estrutura fiscal','view'=>'fiscal.php'],
    ];
    foreach($forms as $f){
      $viewPath=dirname(__DIR__,2).'/views/'.$f['view']; $view=is_file($viewPath)?file_get_contents($viewPath):'';
      $hasAction = str_contains($view, 'page='.$f['page']) || str_contains($controller, "case '{$f['page']}'") || str_starts_with($f['page'],'api/');
      $hasCsrf = str_contains($view, 'Csrf::input()');
      $items[]=['tipo'=>'botao','item'=>$f['label'],'page'=>$f['page'],'modulo'=>'interface','status'=>($hasAction && ($hasCsrf || str_starts_with($f['page'],'api/')))?'ok':'erro','mensagem'=>($hasAction?'ação encontrada':'ação ausente').'; '.($hasCsrf?'CSRF ok':'CSRF não detectado/rota API')];
    }
    return $items;
  }
}
