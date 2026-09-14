<?php require __DIR__.'/layout_top.php'; ?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3"><div><h2><i class="bi bi-file-earmark-text"></i> <?=e($file)?></h2><p class="text-muted">Documento editável em Markdown dentro do pacote.</p></div><a class="btn btn-outline-secondary" href="index.php?page=documentos-comerciais">Voltar</a></div>
<div class="card"><div class="card-body"><pre class="mb-0" style="white-space:pre-wrap;font-family:var(--bs-font-sans-serif);"><?=e($content)?></pre></div></div>
<?php require __DIR__.'/layout_bottom.php'; ?>
