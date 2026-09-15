<?php require __DIR__.'/layout_top.php'; function pretty($v){ if(!$v)return '-'; $j=json_decode($v,true); return $j?json_encode($j,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):$v; } ?>
<div class="row g-3 mb-3">
  <div class="col-md-8"><div class="panel p-3"><h2 class="h5 mb-2">Resumo do erro/evento</h2><p><b>Trace ID:</b> <code><?=e($evento['trace_id'])?></code></p><p><b>Ação:</b> <?=e($evento['acao'])?></p><p><b>Status:</b> <span class="badge-status status-<?=e($evento['status'])?>"><?=e($evento['status'])?></span></p><p><b>Mensagem:</b> <?=e($evento['mensagem'])?></p></div></div>
  <div class="col-md-4"><div class="panel p-3"><h2 class="h5 mb-2">Diagnóstico rápido</h2><p><b>Código:</b> <?=e($evento['codigo_erro'] ?: '-')?></p><p><b>Causa provável:</b><br><?=e($evento['causa_provavel'] ?: '-')?></p><p><b>Ação recomendada:</b><br><?=e($evento['acao_recomendada'] ?: '-')?></p></div></div>
</div>
<div class="panel mb-3"><div class="panel-header"><h2>Linha do tempo do Trace ID</h2><a class="btn btn-sm btn-outline-secondary" href="index.php?page=auditoria&trace=<?=urlencode($evento['trace_id'])?>">Ver filtrado</a></div><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Data</th><th>Status</th><th>Ação</th><th>Mensagem</th></tr></thead><tbody><?php foreach($timeline as $t): ?><tr><td><?=e($t['criado_em'])?></td><td><span class="badge-status status-<?=e($t['status'])?>"><?=e($t['status'])?></span></td><td><?=e($t['acao'])?></td><td><?=e($t['mensagem'])?></td></tr><?php endforeach; ?></tbody></table></div></div>
<div class="row g-3">
  <div class="col-md-6"><div class="panel"><div class="panel-header"><h2>Payload de entrada</h2></div><pre class="codebox"><?=e(pretty($evento['payload']))?></pre></div></div>
  <div class="col-md-6"><div class="panel"><div class="panel-header"><h2>Retorno/Saída</h2></div><pre class="codebox"><?=e(pretty($evento['retorno']))?></pre></div></div>
  <div class="col-12"><div class="panel"><div class="panel-header"><h2>Contexto técnico</h2></div><pre class="codebox"><?=e(pretty($evento['contexto']))?></pre></div></div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
