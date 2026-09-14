<?php
class OperationalRouteService {
  public static function menuRoutes(): array {
    return [
      ['page'=>'dashboard','label'=>'Dashboard','module'=>'core','profile'=>'operador'],
      ['page'=>'pedidos','label'=>'Pedidos','module'=>'pedidos','profile'=>'operador'],
      ['page'=>'produtos','label'=>'Produtos','module'=>'produtos','profile'=>'operador'],
      ['page'=>'baixas-estoque','label'=>'Estoque','module'=>'estoque','profile'=>'operador'],
      ['page'=>'fiscal','label'=>'XML / NF-e','module'=>'fiscal','profile'=>'supervisor'],
      ['page'=>'integracoes','label'=>'Integrações','module'=>'core','profile'=>'admin'],
      ['page'=>'reconciliacao','label'=>'Reconciliação','module'=>'estoque','profile'=>'supervisor'],
      ['page'=>'logs','label'=>'Relatórios','module'=>'observabilidade','profile'=>'supervisor'],
      ['page'=>'configuracoes','label'=>'Configurações','module'=>'core','profile'=>'admin'],
      ['page'=>'central-tecnica','label'=>'Central Técnica','module'=>'core','profile'=>'dev'],
    ];
  }
  public static function technicalRoutes(): array {
    return ['dashboard-integridade','bancos-modulos','auditoria-codigo','oauth-v3-checklist','selftest','homologacao','teste-real-tiny','atualizador-seguro','validar-banco','mapa-banco','relatorio-prontidao-producao','production-ready-v24','production-ready-v25','production-ready-v26','hosting-infinityfree','auditoria-hash-chain'];
  }
}
