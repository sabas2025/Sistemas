<?php require __DIR__.'/layout_top.php'; ?>
<div class="panel mb-3"><div class="panel-header"><h2>Fila / DLQ Analytics V24</h2><span class="text-muted">Análise de filas, falhas e reprocessamento</span></div><div class="p-3">
  <pre class="json-box"><?=e(json_encode($analytics ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?></pre>
</div></div><?php require __DIR__.'/layout_bottom.php'; ?>
