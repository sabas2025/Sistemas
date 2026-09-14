<?php require __DIR__.'/layout_top.php'; ?>
<div class="panel mb-3"><div class="panel-header"><h2>Ficha Técnica Tiny V2</h2><span class="text-muted">Observabilidade, erros e configuração</span></div><div class="p-3">
  <div class="row g-3 mb-3"><div class="col-md-6"><div class="kpi"><small>Token Tiny V2</small><b><?=!empty($cfg['tiny_v2_token'])?'Configurado':'Pendente'?></b><span>Produção</span></div></div><div class="col-md-6"><div class="kpi"><small>Erros catalogados</small><b><?=count($erros ?? [])?></b><span>Tratamento operacional</span></div></div></div>
  <h5>Métricas</h5><pre class="json-box"><?=e(json_encode($metricas ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?></pre>
  <h5>Erros</h5><pre class="json-box"><?=e(json_encode($erros ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?></pre>
</div></div><?php require __DIR__.'/layout_bottom.php'; ?>
