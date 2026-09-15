<?php require __DIR__.'/layout_top.php'; ?>
<div class="panel mb-3"><div class="panel-header"><h2>Production Ready V24</h2><span class="text-muted">Checklist operacional e pré-instalação</span></div><div class="p-3">
  <div class="row g-3 mb-3"><div class="col-md-4"><div class="kpi"><small>Score geral</small><b><?=e((string)($scores['geral'] ?? $scores['score'] ?? 0))?>%</b><span>Readiness</span></div></div><div class="col-md-4"><div class="kpi"><small>Checklist</small><b><?=count($checklist ?? [])?></b><span>Itens avaliados</span></div></div><div class="col-md-4"><div class="kpi"><small>Prechecks</small><b><?=count($prechecks ?? [])?></b><span>Ambiente</span></div></div></div>
  <h5>Checklist</h5><pre class="json-box"><?=e(json_encode($checklist ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?></pre>
  <h5>Scores</h5><pre class="json-box"><?=e(json_encode($scores ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?></pre>
</div></div><?php require __DIR__.'/layout_bottom.php'; ?>
