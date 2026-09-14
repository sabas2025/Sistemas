<?php
/** V104.19 - Dashboard executivo fora do DashboardController legado. */
class DashboardHomeController {
  public function dispatch(string $page): void { Auth::requireLogin(); $this->index(); }
  public function index(): void {
    PermissionService::require('dashboard','visualizar');
    extract(DashboardMetricsService::collect(), EXTR_SKIP);
    require __DIR__.'/../../views/dashboard.php';
  }
}
