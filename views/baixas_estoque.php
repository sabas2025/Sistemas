<?php require __DIR__.'/layout_top.php'; ?>
<div class="row g-3 mb-4">
  <?php foreach($resumo as $r): ?>
    <div class="col-md-3"><div class="card-soft"><small>Status</small><h4><?=e($r['status'])?></h4><b><?=e($r['total'])?></b></div></div>
  <?php endforeach; ?>
</div>
<div class="card-soft mb-4">
  <h5><i class="bi bi-lightning-charge"></i> Simular baixa Tiny → VSM</h5>
  <p class="text-muted mb-3">Use para homologar o fluxo onde o Tiny gerou pedido/NF-e e o Hub envia a baixa de estoque para a VSM.</p>
  <form class="row g-2" method="post" action="index.php?page=simular-baixa-tiny">
    <?= Csrf::input() ?>
    <div class="col-md-2"><label class="form-label">SKU</label><input class="form-control" name="sku" value="TESTE001" required></div>
    <div class="col-md-2"><label class="form-label">Quantidade</label><input class="form-control" name="quantidade" type="number" step="0.001" value="1" required></div>
    <div class="col-md-3"><label class="form-label">Referência Tiny</label><input class="form-control" name="referencia" value="TINY-TESTE-<?=date('Ymd-His')?>"></div>
    <div class="col-md-3"><label class="form-label">Descrição</label><input class="form-control" name="descricao" value="Produto teste baixa Tiny"></div>
    <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100"><i class="bi bi-plus-circle"></i> Criar baixa teste</button></div>
  </form>
</div>
<div class="card-soft mb-3">
  <form class="row g-2">
    <input type="hidden" name="page" value="baixas-estoque">
    <div class="col-md-3"><input class="form-control" name="busca" placeholder="SKU, referência ou Trace ID" value="<?=e($_GET['busca']??'')?>"></div>
    <div class="col-md-2"><select class="form-select" name="status"><option value="">Todos</option><?php foreach(['pendente','sucesso','erro','falha_definitiva'] as $st): ?><option value="<?=$st?>" <?=($_GET['status']??'')===$st?'selected':''?>><?=$st?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filtrar</button></div>
  </form>
</div>
<div class="card-soft">
  <h5>Baixas enviadas/registradas</h5>
  <div class="table-responsive"><table class="table table-hover align-middle">
    <thead><tr><th>ID</th><th>Referência</th><th>SKU</th><th>Qtd</th><th>Status</th><th>Trace ID</th><th>Data</th><th>Retorno</th></tr></thead>
    <tbody><?php foreach($movimentos as $m): ?><tr>
      <td><?=e($m['id'])?></td><td><?=e($m['referencia'])?></td><td><b><?=e($m['sku'])?></b></td><td><?=e($m['quantidade'])?></td>
      <td><span class="badge bg-<?=($m['status']==='sucesso'?'success':($m['status']==='erro'?'danger':'secondary'))?>"><?=e($m['status'])?></span></td>
      <td><code><?=e($m['trace_id'])?></code></td><td><?=e($m['criado_em'])?></td>
      <td><details><summary>ver</summary><pre class="json-box"><?=e($m['retorno_vsm'] ?: $m['payload_origem'])?></pre></details></td>
    </tr><?php endforeach; ?></tbody>
  </table></div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
