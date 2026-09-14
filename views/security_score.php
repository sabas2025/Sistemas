<?php require __DIR__.'/layout_top.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="m-0">Security Score Real</h2><p class="text-muted mb-0">Pontuação baseada em configuração, headers, ambiente, storage, FIM e proteção ativa.</p></div><a class="btn btn-outline-secondary" href="index.php?page=security-center">Central</a></div>
<div class="card mb-3"><div class="card-body d-flex align-items-center justify-content-between"><div><div class="display-4"><?=e((string)$score['score'])?>/100</div><b>Classificação: <?=e(strtoupper($score['classificacao']))?></b></div><i class="bi bi-shield-check display-1 text-muted"></i></div></div>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Check</th><th>Status</th><th>Peso</th><th>Risco</th></tr></thead><tbody>
<?php foreach($score['checks'] as $c): ?><tr><td><?=e($c['label'])?></td><td><?= $c['ok'] ? '🟢 OK' : '🔴 Ajustar' ?></td><td><?=e((string)$c['peso'])?></td><td><?=e($c['risco'])?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php require __DIR__.'/layout_bottom.php'; ?>
