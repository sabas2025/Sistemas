<?php require __DIR__.'/layout_top.php'; ?>
<div class="card-soft mb-3">
  <h4>🔍 Resultados por SKU - Consulta VSM</h4>
  <?php if($execucao): ?><p class="text-muted mb-0">Execução #<?=e($execucao['id'])?> • Status <?=e($execucao['status'])?> • Trace <code><?=e($execucao['trace_id'])?></code></p><?php endif; ?>
</div>
<div class="card-soft">
  <div class="table-responsive"><table class="table table-sm align-middle">
    <thead><tr><th>ID</th><th>Execução</th><th>SKU</th><th>Status</th><th>Saldo anterior</th><th>Saldo VSM</th><th>Alterou?</th><th>Erro</th><th>Trace</th><th>Data</th></tr></thead>
    <tbody><?php foreach($resultados as $r): ?>
      <tr>
        <td><?=e($r['id'])?></td><td><?=e($r['execucao_id'])?></td><td><a href="index.php?page=estoque-sku-historico&sku=<?=urlencode($r['sku'])?>"><?=e($r['sku'])?></a></td>
        <td><span class="badge bg-<?=$r['status']==='sucesso'?'success':'danger'?>"><?=e($r['status'])?></span></td>
        <td><?=e($r['saldo_anterior'])?></td><td><?=e($r['saldo_vsm'])?></td><td><?=((int)$r['alterou']===1?'Sim':'Não')?></td><td><?=e($r['erro'])?></td><td><code><?=e($r['trace_id'])?></code></td><td><?=e($r['criado_em'])?></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
