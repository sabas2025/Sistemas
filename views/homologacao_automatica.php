<?php require __DIR__.'/layout_top.php'; ?>
<div class="panel mb-4">
  <div class="panel-header">
    <div><h2>Homologação Automática</h2><span class="text-muted">Assistente analítico para validar Tiny V3, VSM, fila, auditoria e liberar operação com segurança.</span></div>
    <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-outline-primary" href="index.php?page=tiny-v3-ficha"><i class="bi bi-file-earmark-code"></i> Ficha Tiny V3</a>
      <a class="btn btn-outline-primary" href="index.php?page=validar-banco"><i class="bi bi-database-check"></i> Validar Banco</a>
    </div>
  </div>
  <div class="p-3">
    <div class="alert alert-info"><b>Como funciona:</b> o Hub executa os testes obrigatórios, registra auditoria/Trace ID, gera relatório HTML/JSON e só libera Tiny V3 operacional quando todos os itens passarem.</div>
    <form method="post" action="index.php?page=homologacao-automatica-executar" class="row g-3 align-items-end">
      <?=Csrf::input()?>
      <div class="col-md-4"><label class="form-label">SKU real para produto/estoque</label><input class="form-control" name="sku_homologacao" placeholder="Ex: SKU123"></div>
      <div class="col-md-4"><label class="form-label">ID pedido Tiny para teste</label><input class="form-control" name="id_pedido_homologacao" placeholder="opcional, mas recomendado"></div>
      <div class="col-md-4 form-check mt-4"><input class="form-check-input" type="checkbox" name="liberar_se_aprovado" id="liberar"><label class="form-check-label" for="liberar">Liberar Tiny V3 automaticamente se tudo passar</label></div>
      <div class="col-12"><button class="btn btn-primary"><i class="bi bi-play-circle"></i> Executar Homologação Completa</button></div>
    </form>
  </div>
</div>
<?php if(!empty($ultimoRelatorio)): $r=json_decode($ultimoRelatorio['relatorio_json'] ?? '{}', true) ?: []; ?>
<div class="panel mb-4">
  <div class="panel-header"><div><h2>Último relatório</h2><span class="text-muted">Trace ID: <?=e($ultimoRelatorio['trace_id'] ?? '')?></span></div><span class="badge <?=!empty($ultimoRelatorio['aprovado'])?'bg-success':'bg-warning text-dark'?>"><?=!empty($ultimoRelatorio['aprovado'])?'APROVADO':'PENDENTE/FALHA'?></span></div>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Teste</th><th>Status</th><th>Mensagem</th><th>Ação recomendada</th></tr></thead><tbody>
    <?php foreach(($r['testes'] ?? []) as $t): ?>
      <?php $st=$t['status']??'pendente'; $cls=$st==='ok'?'bg-success':($st==='falha'?'bg-danger':'bg-warning text-dark'); ?>
      <tr><td><b><?=e($t['nome']??'')?></b></td><td><span class="badge <?=$cls?>"><?=e(strtoupper($st))?></span></td><td><?=e($t['mensagem']??'')?></td><td><?=e($t['acao_recomendada']??'')?></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
</div>
<?php endif; ?>
<div class="panel">
  <div class="panel-header"><div><h2>Histórico de homologações automáticas</h2><span class="text-muted">Últimos 30 relatórios gerados.</span></div></div>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Data</th><th>Trace ID</th><th>Status</th><th>Resumo</th><th>Tiny V3 liberado</th></tr></thead><tbody>
    <?php foreach(($relatorios ?? []) as $row): $res=json_decode($row['resumo_json'] ?? '{}', true) ?: []; ?>
      <tr><td><?=e($row['criado_em'] ?? '')?></td><td><code><?=e($row['trace_id'] ?? '')?></code></td><td><?=!empty($row['aprovado'])?'<span class="badge bg-success">Aprovado</span>':'<span class="badge bg-warning text-dark">Pendente/Falha</span>'?></td><td>OK: <?=e($res['ok']??0)?> · Pend.: <?=e($res['pendente']??0)?> · Falha: <?=e($res['falha']??0)?></td><td><?=!empty($row['liberado_tiny_v3'])?'Sim':'Não'?></td></tr>
    <?php endforeach; ?>
    <?php if(empty($relatorios)): ?><tr><td colspan="5" class="text-muted">Nenhuma homologação automática executada ainda.</td></tr><?php endif; ?>
  </tbody></table></div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
