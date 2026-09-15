<?php require __DIR__.'/layout_top.php'; ?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div><h2><i class="bi bi-activity"></i> Observabilidade Enterprise</h2><p class="text-muted mb-0">Métricas operacionais de filas, DLQ, eventos, workers e schema.</p></div>
  <a class="btn btn-outline-secondary" href="index.php?page=enterprise-core">Enterprise Core</a>
</div>
<div class="row g-3 mb-3">
  <?php foreach(($snapshot['cards'] ?? []) as $c): $st=$c['status'] ?? 'ok'; $cls=$st==='ok'?'success':($st==='erro'?'danger':'warning'); ?>
    <div class="col-md-3 col-6"><div class="card-soft h-100"><small class="text-muted"><?=e($c['title'])?></small><h3 class="text-<?=$cls?>"><?=e((string)$c['value'])?></h3><p class="small mb-0"><?=e($c['message'])?></p></div></div>
  <?php endforeach; ?>
</div>
<div class="row g-3">
  <div class="col-lg-6"><div class="card-soft table-responsive"><h5>Workers</h5><table class="table table-sm responsive-table"><thead><tr><th>Worker</th><th>Status</th><th>Último heartbeat</th><th>Erro</th></tr></thead><tbody><?php foreach(($snapshot['workers'] ?? []) as $w): ?><tr><td data-label="Worker"><b><?=e($w['worker_name'] ?? '')?></b></td><td data-label="Status"><?=e($w['status'] ?? '')?></td><td data-label="Último"><?=e($w['last_seen_at'] ?? '')?></td><td data-label="Erro"><?=e($w['last_error'] ?? '')?></td></tr><?php endforeach; ?><?php if(empty($snapshot['workers'])):?><tr><td colspan="4" class="text-muted">Nenhum heartbeat registrado ainda.</td></tr><?php endif; ?></tbody></table></div></div>
  <div class="col-lg-6"><div class="card-soft table-responsive"><h5>Eventos recentes</h5><table class="table table-sm responsive-table"><thead><tr><th>Evento</th><th>Status</th><th>Origem → Destino</th><th>Trace</th></tr></thead><tbody><?php foreach(($snapshot['recent_events'] ?? []) as $ev): ?><tr><td data-label="Evento"><b><?=e($ev['operation'] ?? '')?></b><br><small><?=e($ev['entity_type'] ?? '')?> <?=e($ev['entity_id'] ?? '')?></small></td><td data-label="Status"><?=e($ev['status'] ?? '')?></td><td data-label="Fluxo"><?=e(($ev['source_system'] ?? '').' → '.($ev['target_system'] ?? ''))?></td><td data-label="Trace"><code><?=e($ev['trace_id'] ?? '')?></code></td></tr><?php endforeach; ?><?php if(empty($snapshot['recent_events'])):?><tr><td colspan="4" class="text-muted">Nenhum evento registrado ainda.</td></tr><?php endif; ?></tbody></table></div></div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
