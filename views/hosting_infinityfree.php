<?php require __DIR__.'/layout_top.php'; ?>
<div class="panel mb-3"><div class="panel-header"><h2>Hospedagem / InfinityFree</h2><span class="text-muted">Compatibilidade de ambiente e recomendações</span></div><div class="p-3">
  <h5>Ambiente detectado</h5><pre class="json-box"><?=e(json_encode($compat ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?></pre>
  <h5>Recomendações</h5><pre class="json-box"><?=e(json_encode($recomendacoes ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?></pre>
</div></div><?php require __DIR__.'/layout_bottom.php'; ?>
