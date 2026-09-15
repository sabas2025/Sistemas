<?php require __DIR__.'/layout_top.php'; ?>
<div class="panel">
  <div class="panel-header"><div><h2>Produção Segura</h2><span class="text-muted">Checklist operacional para liberar o HUB com segurança.</span></div></div>
  <div class="p-3">
    <div class="alert <?=($resultado['status']==='pronto'?'alert-success':($resultado['status']==='atenção'?'alert-warning':'alert-danger'))?>">
      <b>Status:</b> <?=e(strtoupper($resultado['status']))?> · <b>Score:</b> <?=e((string)$resultado['score'])?>%
    </div>
    <div class="table-responsive"><table class="table table-hover align-middle responsive-table"><thead><tr><th>Grupo</th><th>Item</th><th>Status</th><th>Ação recomendada</th></tr></thead><tbody>
    <?php foreach($resultado['checks'] as $c): ?><tr><td data-label="Grupo"><?=e($c['grupo'])?></td><td data-label="Item"><?=e($c['item'])?></td><td data-label="Status"><?= $c['ok'] ? '<span class="badge bg-success">OK</span>' : '<span class="badge bg-danger">Atenção</span>' ?></td><td data-label="Ação"><?=e($c['acao'])?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
