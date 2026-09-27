<?php
class ProdutoController extends BaseModuleController {
  public function dispatch(string $page): void {
    switch ($page) {
      case 'produtos-pendencias': $this->pendencias(); break;
      case 'produtos-vsm': $this->produtosVsm(); break;
      case 'produtos-pendentes-integracao': $this->produtosPendentesIntegracao(); break;
      case 'produto-pendente-integracao-comparar': $this->produtoPendenteIntegracaoComparar(); break;
      case 'produto-pendente-integracao-acao': $this->produtoPendenteIntegracaoAcao(); break;
      case 'produto-pendencia-acao': $this->produtoPendenciaAcao(); break;
      default: $this->index(); break; // produtos
    }
  }
  public static function routes(): array { return ['produtos','produtos-pendencias','produtos-vsm','produtos-pendentes-integracao','produto-pendente-integracao-comparar','produto-pendente-integracao-acao','produto-pendencia-acao']; }
  public function index(): void { PermissionService::require('produtos','visualizar'); $erro=null; $produtos=[]; try { $produtos=TenantScopeService::run('produtos_mapeamento', 'SELECT id, sku_tiny, sku_vsm, produto_tiny_id, produto_vsm_id, descricao, ativo, estoque_atual, status_tiny, ultima_sincronizacao, criado_em FROM produtos_mapeamento ORDER BY id DESC LIMIT 200')->fetchAll(); } catch(Throwable $e){ $erro=$e->getMessage(); } $pageTitle='Produtos'; $this->view('produtos',compact('pageTitle','produtos','erro')); }
  public function pendencias(): void { PermissionService::require('produtos','visualizar'); $erro=null; $pendencias=[]; $status=$_GET['status'] ?? ''; $busca=trim($_GET['busca'] ?? ''); try { $sql='SELECT * FROM produto_pendencias WHERE 1=1'; $params=[]; if($status){$sql.=' AND status=?';$params[]=$status;} if($busca){$sql.=' AND (sku LIKE ? OR motivo LIKE ? OR trace_id LIKE ?)';$params[]='%'.$busca.'%';$params[]='%'.$busca.'%';$params[]='%'.$busca.'%';} $sql.=' ORDER BY id DESC LIMIT 200'; $st=Database::forTable('produto_pendencias')->prepare($sql); $st->execute($params); $pendencias=$st->fetchAll(); } catch(Throwable $e){ $erro=$e->getMessage(); } $pageTitle='Pendências de Produtos'; $this->view('produtos_pendencias',compact('pageTitle','pendencias','erro','status','busca')); }
  private function produtosVsm(): void {
    PermissionService::require('produtos_vsm','visualizar');
    $status = $_GET['status'] ?? '';
    $busca = trim($_GET['busca'] ?? '');
    $sql = "SELECT * FROM fila_integracao WHERE tipo IN ('produto_vsm_para_tiny','produto_vsm_atualizar_tiny','produto_vsm_estoque_para_tiny','produto_vsm_status_para_tiny')"; $params=[];
    if($status !== ''){ $sql .= " AND status=?"; $params[]=$status; }
    if($busca !== ''){ $sql .= " AND (referencia LIKE ? OR trace_id LIKE ? OR payload LIKE ?)"; $params[]="%$busca%"; $params[]="%$busca%"; $params[]="%$busca%"; }
    $sql .= " ORDER BY id DESC LIMIT 300";
    $st=Database::tableConnectionForSql($sql)->prepare($sql); $st->execute($params); $produtos=$st->fetchAll();
    $mapeados=TenantScopeService::run('produtos_mapeamento', "SELECT id, sku_tiny, sku_vsm, produto_tiny_id, produto_vsm_id, descricao, ativo, estoque_atual, status_tiny, ultima_sincronizacao, criado_em FROM produtos_mapeamento ORDER BY id DESC LIMIT 100")->fetchAll();
    $eventos=TenantScopeService::run('produtos_vsm_eventos', "SELECT * FROM produtos_vsm_eventos ORDER BY id DESC LIMIT 100")->fetchAll();
    $pageTitle='Produtos recebidos da VSM';
    require __DIR__.'/../../views/produtos_vsm.php';
  }

  private function produtosPendentesIntegracao(): void {
    PermissionService::require('produtos_vsm','visualizar');
    $status = $_GET['status'] ?? 'pendente';
    $busca = trim((string)($_GET['busca'] ?? ''));
    $pendentes = [];
    $categorias = [];
    try {
      $pdo = Database::forTable('produtos_pendentes_integracao');
      $sql = "SELECT * FROM produtos_pendentes_integracao WHERE 1=1"; $params=[];
      if($status !== ''){ $sql .= " AND status=?"; $params[]=$status; }
      if($busca !== ''){ $sql .= " AND (sku LIKE ? OR ean LIKE ? OR nome LIKE ? OR trace_id LIKE ? OR categoria_vsm_nome LIKE ?)"; for($i=0;$i<5;$i++) $params[]='%'.$busca.'%'; }
      $sql .= " ORDER BY id DESC LIMIT 300";
      $st=$pdo->prepare($sql); $st->execute($params); $pendentes=$st->fetchAll();
    } catch(Throwable $e){ $erro = $e->getMessage(); }
    try { $categorias = TenantScopeService::run('categorias_mapeamento', 'SELECT * FROM categorias_mapeamento WHERE ativo=1 ORDER BY prioridade DESC, nome_categoria_vsm ASC LIMIT 500')->fetchAll(); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    $pageTitle = 'Produtos Pendentes VSM';
    require __DIR__.'/../../views/produtos_pendentes_integracao.php';
  }

  private function produtoPendenteIntegracaoComparar(): void {
    PermissionService::require('produtos_vsm','visualizar');
    $id=(int)($_GET['id'] ?? 0);
    $pdo = Database::forTable('produtos_pendentes_integracao');
    $st = TenantScopeService::run('produtos_pendentes_integracao', 'SELECT * FROM produtos_pendentes_integracao WHERE id=? LIMIT 1', [$id]); $p=$st->fetch(PDO::FETCH_ASSOC);
    if(!$p) redirect('index.php?page=produtos-pendentes-integracao&erro=nao_encontrado');
    $check = ProdutoVsmApprovalGuardService::checklist($p);
    $historico=[];
    try { $h = TenantScopeService::run('produtos_aprovacao_historico', 'SELECT * FROM produtos_aprovacao_historico WHERE produto_pendente_id=? ORDER BY id DESC LIMIT 50', [$id]); $historico=$h->fetchAll(); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    $pageTitle = 'Comparar Produto VSM x Tiny';
    require __DIR__.'/../../views/produto_pendente_comparar.php';
  }

  private function produtoPendenteIntegracaoAcao(): void {
    PermissionService::require('produtos_vsm','simular');
    Csrf::validate();
    $id=(int)($_POST['id'] ?? 0);
    $acao=(string)($_POST['acao'] ?? '');
    $pdo = Database::forTable('produtos_pendentes_integracao');
    $st = TenantScopeService::run('produtos_pendentes_integracao', 'SELECT * FROM produtos_pendentes_integracao WHERE id=? LIMIT 1', [$id]); $p=$st->fetch(PDO::FETCH_ASSOC);
    if(!$p) redirect('index.php?page=produtos-pendentes-integracao&erro=nao_encontrado');
    $userId = Auth::user()['id'] ?? null;
    $check = ProdutoVsmApprovalGuardService::checklist($p);

    if($acao === 'rejeitar'){
      $motivo=trim((string)($_POST['motivo_rejeicao'] ?? 'Rejeitado manualmente'));
      TenantScopeService::run('produtos_pendentes_integracao', "UPDATE produtos_pendentes_integracao SET status='rejeitado', rejeitado_por=?, rejeitado_em=NOW(), atualizado_em=NOW() WHERE id=?", [$userId,$id]);
      ProdutoVsmApprovalGuardService::registrarHistorico($id,'rejeitar','sucesso','Produto rejeitado manualmente.',['motivo'=>$motivo]);
      Audit::event('produto_vsm.pendente.rejeitado','alerta',['entidade'=>'produtos_pendentes_integracao','entidade_id'=>$id,'mensagem'=>'Produto novo VSM rejeitado manualmente.','contexto'=>['motivo'=>$motivo]]);

    } elseif($acao === 'vincular'){
      $skuTiny=trim((string)($_POST['sku_tiny'] ?? ''));
      $produtoTinyId=trim((string)($_POST['produto_tiny_id'] ?? ''));
      if($skuTiny === '') redirect('index.php?page=produto-pendente-integracao-comparar&id='.$id.'&erro=sku_tiny_obrigatorio');
      TenantScopeService::run('produtos_mapeamento', 'INSERT INTO produtos_mapeamento(sku_vsm, sku_tiny, produto_tiny_id, descricao, ativo, ultima_sincronizacao) VALUES(?,?,?,?,1,NOW()) ON DUPLICATE KEY UPDATE sku_tiny=VALUES(sku_tiny), produto_tiny_id=COALESCE(VALUES(produto_tiny_id),produto_tiny_id), descricao=COALESCE(VALUES(descricao),descricao), ativo=1, ultima_sincronizacao=NOW()', [$p['sku'],$skuTiny,$produtoTinyId ?: null,$p['nome'] ?: null]);
      TenantScopeService::run('produtos_pendentes_integracao', "UPDATE produtos_pendentes_integracao SET status='vinculado', aprovado_por=?, aprovado_em=NOW(), atualizado_em=NOW() WHERE id=?", [$userId,$id]);
      ProdutoVsmApprovalGuardService::registrarHistorico($id,'vincular','sucesso','Produto vinculado a item Tiny existente.',['sku_vsm'=>$p['sku'],'sku_tiny'=>$skuTiny,'produto_tiny_id'=>$produtoTinyId]);
      Audit::event('produto_vsm.pendente.vinculado','sucesso',['entidade'=>'produtos_pendentes_integracao','entidade_id'=>$id,'mensagem'=>'Produto VSM vinculado a produto Tiny existente.','contexto'=>['sku_vsm'=>$p['sku'],'sku_tiny'=>$skuTiny,'produto_tiny_id'=>$produtoTinyId]]);

    } elseif($acao === 'aprovar_criar_tiny'){
      if(!$check['pode_aprovar']){
        ProdutoVsmApprovalGuardService::registrarHistorico($id,'aprovar_criar_tiny','bloqueado','Aprovação bloqueada pelo checklist V46.',['bloqueios'=>$check['bloqueios']]);
        redirect('index.php?page=produto-pendente-integracao-comparar&id='.$id.'&erro=checklist_bloqueou');
      }
      $payload=ProdutoVsmApprovalGuardService::payloadFromPending($p) ?: ['sku'=>$p['sku']];
      $categoriaTiny=trim((string)($_POST['categoria_tiny_id'] ?? ($p['categoria_tiny_id_sugerida'] ?? '')));
      if($categoriaTiny !== '') $payload['categoria_tiny_id'] = $categoriaTiny;
      $payload['hub_aprovado_manual'] = true;
      $payload['hub_aprovado_por'] = Auth::user()['id'] ?? 'admin';
      $payload['hub_aprovado_em'] = date('c');
      $payload['hub_aprovacao_checklist'] = $check['items'];
      $payload['hub_ncm_validado'] = $check['ncm'];
      $payload['hub_ean_validado'] = $check['ean'];
      $trace=RequestContext::id();
      TenantScopeService::run('fila_integracao', "INSERT INTO fila_integracao(tipo,referencia,payload,status,trace_id) VALUES('produto_vsm_para_tiny',?,?, 'pendente', ?)", [$p['sku'], json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), $trace]);
      $filaId=(int)Database::forTable('fila_integracao')->lastInsertId();
      TenantScopeService::run('produtos_pendentes_integracao', "UPDATE produtos_pendentes_integracao SET status='aprovado', fila_id=?, aprovado_por=?, aprovado_em=NOW(), atualizado_em=NOW() WHERE id=?", [$filaId,$userId,$id]);
      ProdutoVsmApprovalGuardService::registrarHistorico($id,'aprovar_criar_tiny','sucesso','Produto aprovado com checklist V46 e enviado para fila Tiny.',['fila_id'=>$filaId,'sku'=>$p['sku'],'checklist'=>$check['items']]);
      Audit::event('produto_vsm.pendente.aprovado_criar_tiny','sucesso',['entidade'=>'produtos_pendentes_integracao','entidade_id'=>$id,'mensagem'=>'Produto novo aprovado manualmente com camadas extras V46 e enviado para fila Tiny.','contexto'=>['fila_id'=>$filaId,'sku'=>$p['sku']]]);
    }
    redirect('index.php?page=produtos-pendentes-integracao');
  }

  private function produtoPendenciaAcao(): void {
    PermissionService::require('produtos_vsm','simular');
    Csrf::validate();
    $id=(int)($_POST['id'] ?? 0);
    $acao=(string)($_POST['acao'] ?? '');
    $st = TenantScopeService::run('produto_pendencias', 'SELECT * FROM produto_pendencias WHERE id=? LIMIT 1', [$id]); $p=$st->fetch();
    if(!$p) redirect('index.php?page=produtos-pendencias&erro=nao_encontrado');
    if($acao==='resolver') {
      TenantScopeService::run('produto_pendencias', "UPDATE produto_pendencias SET status='resolvido', resolvido_por=?, resolvido_em=NOW(), atualizado_em=NOW() WHERE id=?", [Auth::user()['id']??null,$id]);
      Audit::event('produto.pendencia.resolvida','sucesso',['entidade'=>'produto_pendencias','entidade_id'=>$id,'mensagem'=>'Pendência marcada como resolvida manualmente.']);
    } elseif($acao==='ignorar') {
      TenantScopeService::run('produto_pendencias', "UPDATE produto_pendencias SET status='ignorado', atualizado_em=NOW() WHERE id=?", [$id]);
      Audit::event('produto.pendencia.ignorada','alerta',['entidade'=>'produto_pendencias','entidade_id'=>$id,'mensagem'=>'Pendência ignorada manualmente.']);
    } elseif($acao==='reprocessar' && !empty($p['fila_id'])) {
      QueueService::reprocessar((int)$p['fila_id']);
      Audit::event('produto.pendencia.reprocessar','info',['entidade'=>'produto_pendencias','entidade_id'=>$id,'mensagem'=>'Fila da pendência reenviada para processamento.']);
    } elseif($acao==='criar_produto_tiny') {
      $payload = json_decode((string)($p['payload'] ?? '{}'), true) ?: ['sku'=>$p['sku']];
      $payload['hub_aprovado_manual'] = true;
      $payload['hub_aprovado_por'] = Auth::user()['id'] ?? 'admin';
      $payload['hub_aprovado_em'] = date('c');
      TenantScopeService::run('fila_integracao', "INSERT INTO fila_integracao(tipo,referencia,payload,status,trace_id) VALUES('produto_vsm_para_tiny',?,?, 'pendente', ?)", [$p['sku'], json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), RequestContext::id()]);
      TenantScopeService::run('produto_pendencias', "UPDATE produto_pendencias SET status='resolvido', resolucao='Criada fila para criar produto no Tiny a partir da pendência.', resolvido_por=?, resolvido_em=NOW(), atualizado_em=NOW() WHERE id=?", [Auth::user()['id']??null,$id]);
      Audit::event('produto.pendencia.criar_produto_tiny','info',['entidade'=>'produto_pendencias','entidade_id'=>$id,'mensagem'=>'Criada fila para criar produto no Tiny a partir da pendência.','payload'=>$payload]);
    } elseif($acao==='vincular') {
      $skuTiny=trim($_POST['sku_tiny'] ?? '');
      if($skuTiny !== '') {
        TenantScopeService::run('produtos_mapeamento', 'INSERT INTO produtos_mapeamento(sku_vsm, sku_tiny, ativo) VALUES(?,?,1) ON DUPLICATE KEY UPDATE sku_tiny=VALUES(sku_tiny), ativo=1', [$p['sku'],$skuTiny]);
        TenantScopeService::run('produto_pendencias', "UPDATE produto_pendencias SET status='resolvido', atualizado_em=NOW() WHERE id=?", [$id]);
        Audit::event('produto.pendencia.vinculada','sucesso',['entidade'=>'produto_pendencias','entidade_id'=>$id,'mensagem'=>'SKU VSM vinculado manualmente a SKU Tiny.','contexto'=>['sku_vsm'=>$p['sku'],'sku_tiny'=>$skuTiny]]);
      }
    }
    redirect('index.php?page=produtos-pendencias');
  }

}
