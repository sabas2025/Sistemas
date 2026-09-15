<?php require __DIR__.'/layout_top.php'; ?>
<div class="panel mb-4">
  <div class="panel-header align-items-start">
    <div>
      <h2>🧭 Análise Comercial e Técnica</h2>
      <span class="text-muted">Score consolidado para venda, implantação e produção controlada.</span>
    </div>
    <?php $st=$readiness['status']??'atencao'; $cl=$st==='pronto'?'status-sucesso':($st==='critico'?'status-erro':'status-pendente'); ?>
    <span class="badge-status <?=$cl?>">Score <?=e($readiness['score'] ?? 0)?>%</span>
  </div>
  <div class="p-3">
    <div class="row g-3">
      <?php foreach(($readiness['checks'] ?? []) as $c): ?>
        <div class="col-md-6 col-xl-3">
          <div class="health-card h-100">
            <div class="health-icon"><i class="bi <?=!empty($c['ok'])?'bi-check-circle':'bi-exclamation-triangle'?>"></i></div>
            <div>
              <b><?=!empty($c['ok'])?'🟢':'🟡'?> <?=e($c['titulo'] ?? '')?></b>
              <div class="small text-muted mt-1"><?=e($c['detalhe'] ?? '')?></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="panel mb-4">
  <div class="panel-header"><h2>📌 Sugestões analíticas</h2></div>
  <div class="p-3">
    <ul class="mb-0">
      <?php foreach(($readiness['sugestoes'] ?? []) as $s): ?><li><?=e($s)?></li><?php endforeach; ?>
    </ul>
  </div>
</div>

<div class="panel mb-4">
  <div class="panel-header"><h2>🧱 DDL Runtime / Schema</h2><span class="text-muted">CREATE/ALTER fora do núcleo deve ser migrado gradualmente.</span></div>
  <div class="p-3">
    <div class="alert alert-<?=((int)($schemaPolicy['revisar']??0)===0?'success':'warning')?>">
      Total: <?=e($schemaPolicy['total'] ?? 0)?> • Para revisar: <?=e($schemaPolicy['revisar'] ?? 0)?>
    </div>
    <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Arquivo</th><th>Status</th><th>Motivo</th></tr></thead><tbody>
      <?php foreach(array_slice(($schemaPolicy['items'] ?? []),0,60) as $i): ?><tr><td><code><?=e($i['arquivo'] ?? '')?></code></td><td><?=e($i['status'] ?? '')?></td><td><?=e($i['motivo'] ?? '')?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div>
</div>

<div class="panel mb-4">
  <div class="panel-header"><h2>🔌 Matriz de conectores</h2></div>
  <div class="p-3 table-responsive"><table class="table table-sm"><thead><tr><th>Conector</th><th>Status</th><th>Capacidades</th><th>Riscos</th></tr></thead><tbody>
    <?php foreach(($connectors ?? []) as $c): ?><tr><td><b><?=e($c['name'] ?? $c['code'] ?? '')?></b></td><td><?=e($c['status'] ?? '')?></td><td><?=e(implode(', ', $c['capabilities'] ?? []))?></td><td><?=e(implode('; ', $c['risks'] ?? []))?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
