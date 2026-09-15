<?php
class DivergenceMonitorController extends BaseModuleController {
  public function dispatch(string $page): void {
    Auth::requireLogin();
    switch($page){
      case 'monitor-divergencias':
      case 'divergencia-estoque': $this->index(); break;
      case 'divergencia-acao': $this->acao(); break;
      default: $this->index();
    }
  }
  public static function routes(): array { return ['monitor-divergencias','divergencia-estoque','divergencia-acao']; }
  public function index(): void {
    PermissionService::require('reconciliacao','visualizar');
    $status = $_GET['status'] ?? '';
    $busca = trim($_GET['busca'] ?? '');
    $sql = "SELECT * FROM estoque_divergencias WHERE 1=1"; $params=[];
    if($status !== ''){ $sql .= " AND status=?"; $params[]=$status; }
    if($busca !== ''){ $sql .= " AND (sku LIKE ? OR trace_id LIKE ?)"; $params[]="%$busca%"; $params[]="%$busca%"; }
    // Melhoria 1 da seção 8: o escopo de empresa entra DEPOIS do WHERE montado pelos filtros e
    // ANTES do ORDER BY/LIMIT — applyToSelect() cuida da posição do parâmetro.
    [$sql, $params] = TenantScopeService::applyToSelect('estoque_divergencias', $sql, $params);
    $sql .= " ORDER BY id DESC LIMIT 300";
    try { $st=Database::tableConnectionForSql($sql)->prepare($sql); $st->execute($params); $divergencias=$st->fetchAll(); }
    catch(Throwable $e){ $divergencias=[]; $_SESSION['flash_error']='Tabela estoque_divergencias indisponível. Rode Validar Banco / Schema V82+'; }
    $resumo = [
      'abertas'=>OperationCenterService::safeCount('estoque_divergencias', "status='aberto'"),
      'corrigidas'=>OperationCenterService::safeCount('estoque_divergencias', "status='corrigido'"),
      'ignoradas'=>OperationCenterService::safeCount('estoque_divergencias', "status='ignorado'"),
      'total'=>OperationCenterService::safeCount('estoque_divergencias'),
    ];
    $pageTitle='Monitor de Divergências Tiny x VSM';
    $this->view('divergencia_estoque', compact('pageTitle','divergencias','resumo','status','busca'));
  }
  private function acao(): void {
    PermissionService::require('reconciliacao','executar'); Csrf::validate();
    $id=(int)($_POST['id'] ?? 0); $acao=(string)($_POST['acao'] ?? '');
    $pdo=Database::forTable('estoque_divergencias');
    $st = TenantScopeService::run('estoque_divergencias', 'SELECT * FROM estoque_divergencias WHERE id=? LIMIT 1', [$id]); $d=$st->fetch();
    if(!$d) redirect('index.php?page=monitor-divergencias&erro=nao_encontrada');
    if($acao==='ignorar') {
      TenantScopeService::run('estoque_divergencias', "UPDATE estoque_divergencias SET status='ignorado', atualizado_em=NOW() WHERE id=?", [$id]);
      Audit::event('estoque.divergencia.ignorada','alerta',['entidade'=>'estoque_divergencias','entidade_id'=>$id,'mensagem'=>'Divergência ignorada manualmente.']);
    } elseif($acao==='marcar_corrigido') {
      TenantScopeService::run('estoque_divergencias', "UPDATE estoque_divergencias SET status='corrigido', atualizado_em=NOW() WHERE id=?", [$id]);
      Audit::event('estoque.divergencia.corrigida','sucesso',['entidade'=>'estoque_divergencias','entidade_id'=>$id,'mensagem'=>'Divergência marcada como corrigida manualmente.']);
    } elseif($acao==='corrigir_tiny') {
      $payload=['sku'=>$d['sku'],'estoque'=>(float)$d['estoque_vsm'],'origem'=>'monitor_divergencias','divergencia_id'=>$id,'acao'=>'corrigir_tiny'];
      TenantScopeService::run('fila_integracao', "INSERT INTO fila_integracao(tipo,referencia,payload,status,trace_id) VALUES('produto_vsm_estoque_para_tiny',?,?, 'pendente', ?)", [$d['sku'], json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), RequestContext::id()]);
      Audit::event('estoque.divergencia.corrigir_tiny','info',['entidade'=>'estoque_divergencias','entidade_id'=>$id,'mensagem'=>'Gerada fila para corrigir estoque do Tiny com saldo da VSM.','payload'=>$payload]);
    }
    redirect('index.php?page=monitor-divergencias');
  }
}
