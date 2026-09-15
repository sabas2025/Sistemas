<?php require __DIR__.'/layout_top.php'; ?>

<div class="cardx mb-3"><h4>Self-Test do Sistema</h4><p class="text-muted">Verifica banco, configurações, permissões de pasta, fila e circuit breakers.</p><form method="post" action="index.php?page=selftest-executar"><?=Csrf::input()?><button class="btn btn-primary">Executar self-test agora</button></form></div>
<div class="cardx"><h4>Relatórios recentes</h4><div class="table-responsive"><table class="table"><thead><tr><th>ID</th><th>Status</th><th>Resumo</th><th>Trace</th><th>Data</th></tr></thead><tbody><?php foreach($relatorios as $r): ?><tr><td><?=$r['id']?></td><td><span class="badge bg-<?=($r['status']==='ok'?'success':($r['status']==='erro'?'danger':'warning'))?>"><?=e($r['status'])?></span></td><td><?=e($r['resumo'])?></td><td><code><?=e($r['trace_id'])?></code></td><td><?=e($r['criado_em'])?></td></tr><tr><td colspan="5"><pre class="small bg-light p-2 rounded"><?=e($r['detalhes'])?></pre></td></tr><?php endforeach; ?></tbody></table></div></div>

<?php require __DIR__.'/layout_bottom.php'; ?>
