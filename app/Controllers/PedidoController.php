<?php
class PedidoController extends BaseModuleController {
  public function dispatch(string $page): void { $page === 'pedido-detalhe' ? $this->detalhe() : $this->index(); }
  public static function routes(): array { return ['pedidos','pedido-detalhe']; }
  public function index(): void {
    PermissionService::require('pedidos','visualizar');
    $status = $_GET['status'] ?? ''; $busca = trim($_GET['busca'] ?? '');
    $sql = "SELECT id,origem,pedido_origem_id,pedido_tiny_id,cliente_nome,cliente_documento,valor_total,status,tentativas,trace_id,criado_em FROM pedidos_integracao WHERE 1=1"; $params=[];
    if($status){ $sql .= " AND status=?"; $params[]=$status; }
    if($busca){ $sql .= " AND (pedido_origem_id LIKE ? OR pedido_tiny_id LIKE ? OR cliente_nome LIKE ?)"; $params[]="%$busca%"; $params[]="%$busca%"; $params[]="%$busca%"; }
    $sql .= " ORDER BY id DESC LIMIT 100";
    $st = TenantScopeService::run('pedidos_integracao', $sql, $params); $pedidos=$st->fetchAll();
    $pageTitle='Pedidos'; $this->view('pedidos', compact('pageTitle','pedidos','status','busca'));
  }
  public function detalhe(): void {
    PermissionService::require('pedidos','detalhe'); $id=(int)($_GET['id'] ?? 0);
    $st = TenantScopeService::run('pedidos_integracao', 'SELECT * FROM pedidos_integracao WHERE id=? LIMIT 1', [$id]); $pedido=$st->fetch();
    if(!$pedido){ http_response_code(404); echo 'Pedido não encontrado'; return; }
    $trace=$pedido['trace_id'] ?? ''; $timeline=[]; $fila=[];
    if($trace){ $st=Database::forTable('auditoria_eventos')->prepare('SELECT * FROM auditoria_eventos WHERE trace_id=? ORDER BY id ASC'); $st->execute([$trace]); $timeline=$st->fetchAll(); $st = TenantScopeService::run('fila_integracao', 'SELECT * FROM fila_integracao WHERE trace_id=? OR referencia=? ORDER BY id DESC', [$trace,$pedido['pedido_origem_id']]); $fila=$st->fetchAll(); }
    $pageTitle='Detalhe do Pedido'; $this->view('pedido_detalhe', compact('pageTitle','pedido','trace','timeline','fila'));
  }
}
