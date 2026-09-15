<?php require __DIR__.'/layout_top.php'; ?>
<h2><i class="bi bi-file-earmark-text"></i> Documentos Comerciais e Técnicos</h2>
<p class="text-muted">Manuais, contrato, SLA, política de backup e documentação VSM para vender e implantar o HUB.</p>
<div class="row g-3">
<?php foreach($docs as $d): ?><div class="col-md-6"><div class="card h-100"><div class="card-body"><h5><?=e($d['titulo'])?></h5><p><?=e($d['resumo'])?></p><a class="btn btn-sm btn-outline-primary" href="index.php?page=documento-comercial&file=<?=e($d['arquivo'])?>">Abrir</a></div></div></div><?php endforeach; ?>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
