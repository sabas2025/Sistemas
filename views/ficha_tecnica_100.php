<?php require __DIR__.'/layout_top.php'; ?>
<div class="panel mb-3">
  <div class="panel-header"><h2>Ficha Técnica 100%</h2><span class="text-muted">Diagnóstico consolidado de instalação, filas, Tiny e VSM</span></div>
  <div class="p-3">
    <div class="row g-3 mb-3">
      <div class="col-md-3"><div class="kpi"><small>Score instalação</small><b><?=e((string)($preScore ?? 0))?>%</b><span>Pré-checks técnicos</span></div></div>
      <div class="col-md-3"><div class="kpi"><small>Filas</small><b><?=e((string)($queue['total'] ?? ($queue['pendentes'] ?? 0)))?></b><span>Itens monitorados</span></div></div>
      <div class="col-md-3"><div class="kpi"><small>Tiny V2</small><b><?=!empty($cfg['tiny_v2_token'])?'OK':'Pendente'?></b><span>Token produção</span></div></div>
      <div class="col-md-3"><div class="kpi"><small>VSM</small><b><?=!empty($cfg['vsm_url'])?'OK':'Pendente'?></b><span>URL base</span></div></div>
    </div>
    <h5>Pré-checks</h5>
    <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Item</th><th>Status</th><th>Detalhe</th></tr></thead><tbody>
      <?php foreach(($prechecks ?? []) as $k=>$v): $ok=is_array($v)?($v['ok']??$v['status']??false):$v; ?>
      <tr><td><?=e((string)$k)?></td><td><?=!empty($ok)?'<span class="badge bg-success">OK</span>':'<span class="badge bg-warning text-dark">Atenção</span>'?></td><td><code><?=e(is_array($v)?json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):(string)$v)?></code></td></tr>
      <?php endforeach; if(empty($prechecks)): ?><tr><td colspan="3" class="text-muted">Nenhum pré-check retornado.</td></tr><?php endif; ?>
    </tbody></table></div>
    <h5>Catálogo de erros Tiny V2</h5>
    <pre class="json-box"><?=e(json_encode($tinyV2Errors ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?></pre>
  </div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
