<?php require __DIR__.'/layout_top.php'; ?>
<div class="panel mb-3"><div class="panel-header"><h2>Production Ready V26 / Hospedagem</h2><span class="text-muted">Compatibilidade e entrada segura em produção</span></div><div class="p-3">
  <div class="alert alert-info">Esta tela consolida verificações de hospedagem, permissões, storage, workers, banco e hardening para evitar erro 500 antes da produção.</div>
  <pre class="json-box"><?=e(json_encode($resumo ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?></pre>
</div></div><?php require __DIR__.'/layout_bottom.php'; ?>
