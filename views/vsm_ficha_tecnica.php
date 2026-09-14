<?php require __DIR__.'/layout_top.php'; ?>
<div class="panel mb-3"><div class="panel-header"><h2>Ficha Técnica VSM</h2><span class="text-muted">Endpoints, métricas, payloads e configuração atual</span></div><div class="p-3">
  <div class="row g-3 mb-3"><div class="col-md-6"><div class="kpi"><small>URL VSM</small><b><?=e($cfg['vsm_url'] ?? 'não configurada')?></b><span>Base de integração</span></div></div><div class="col-md-6"><div class="kpi"><small>Endpoints</small><b><?=count($endpoints ?? [])?></b><span>Mapeados</span></div></div></div>
  <h5>Endpoints</h5><pre class="json-box"><?=e(json_encode($endpoints ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?></pre>
  <h5>Métricas</h5><pre class="json-box"><?=e(json_encode($metricas ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?></pre>
  <h5>Payloads</h5><pre class="json-box"><?=e(json_encode($payloads ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?></pre>
</div></div><?php require __DIR__.'/layout_bottom.php'; ?>
