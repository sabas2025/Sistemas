<?php require __DIR__.'/layout_top.php'; ?>

<div class="row g-3 mb-3"><?php foreach($cb as $c): ?><div class="col-md-6 col-xl-3"><div class="cardx"><h6><?=e(strtoupper($c['sistema']))?></h6><div class="display-6"><?=e($c['status'])?></div><small>Falhas: <?=$c['falhas_consecutivas']?> | Aberto até: <?=e($c['aberto_ate'])?></small></div></div><?php endforeach; ?></div>
<div class="cardx"><h4>Métricas de API</h4><div class="table-responsive"><table class="table"><thead><tr><th>Sistema</th><th>Endpoint</th><th>HTTP</th><th>Tempo</th><th>Sucesso</th><th>Erro</th><th>Trace</th><th>Data</th></tr></thead><tbody><?php foreach($metricas as $m): ?><tr><td><?=e($m['sistema'])?></td><td><small><?=e($m['endpoint'])?></small></td><td><?=$m['http_code']?></td><td><?=$m['tempo_ms']?> ms</td><td><?=$m['sucesso']?'Sim':'Não'?></td><td><code><?=e($m['codigo_erro'])?></code></td><td><code><?=e($m['trace_id'])?></code></td><td><?=e($m['criado_em'])?></td></tr><?php endforeach; ?></tbody></table></div></div>

<?php require __DIR__.'/layout_bottom.php'; ?>
