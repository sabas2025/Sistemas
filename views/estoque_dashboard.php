<?php require __DIR__.'/layout_top.php'; ?>
<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="card-soft"><small>📦 Movimentos hoje</small><h2><?=e($totais['movimentos_hoje'])?></h2></div></div>
  <div class="col-md-3"><div class="card-soft"><small>📬 Fila estoque</small><h2><?=e($totais['fila_pendente'])?></h2></div></div>
  <div class="col-md-3"><div class="card-soft"><small>⚠ Divergências</small><h2><?=e($totais['divergencias'])?></h2></div></div>
  <div class="col-md-3"><div class="card-soft"><small>🔴 Erros</small><h2><?=e($totais['erros'])?></h2></div></div>
</div>
<div class="card-soft mb-3">
  <h4>📦 Estoque Enterprise</h4>
  <p class="text-muted">Controle de estoque mestre, fila exclusiva, reconciliação e auditoria por SKU.</p>
  <div class="d-flex flex-wrap gap-2">
    <a class="btn btn-primary" href="index.php?page=baixas-estoque">Movimentos</a>
    <a class="btn btn-outline-primary" href="index.php?page=divergencia-estoque">Divergências</a>
    <a class="btn btn-outline-primary" href="index.php?page=estoque-alertas">Alertas</a>
    <a class="btn btn-outline-primary" href="index.php?page=estoque-sku-historico">Histórico por SKU</a>
    <a class="btn btn-outline-primary" href="index.php?page=estoque-consultas-vsm">Consultas VSM</a>
    <a class="btn btn-outline-secondary" href="index.php?page=estoque-config">Configurações</a>
    <form method="post" action="index.php?page=estoque-reconciliar-agora" class="d-inline"><?=Csrf::input()?><button class="btn btn-warning">🔄 Reconciliar agora</button></form>
  </div>
</div>
<div class="row g-3">
  <div class="col-lg-5"><div class="card-soft"><h5>⚙️ Política atual</h5><table class="table table-sm"><tr><td>Estoque mestre</td><td><b><?=e(strtoupper($config['estoque_mestre'] ?? 'tiny'))?></b></td></tr><tr><td>VSM → Tiny</td><td><?=($config['permitir_vsm_tiny']??'0')==='1'?'Ativo':'Bloqueado'?></td></tr><tr><td>Reconciliação automática</td><td><?=($config['reconciliacao_automatica']??'1')==='1'?'Ativa':'Inativa'?></td></tr><tr><td>Retenção</td><td><?=e($config['retencao_dias']??'90')?> dias</td></tr></table></div></div>
  <div class="col-lg-7"><div class="card-soft"><h5>⚠ Divergências abertas</h5><div class="table-responsive"><table class="table table-sm"><thead><tr><th>SKU</th><th>Tiny</th><th>VSM</th><th>Diferença</th><th>Status</th></tr></thead><tbody><?php foreach($divergencias as $d): ?><tr><td><?=e($d['sku'])?></td><td><?=e($d['estoque_tiny'])?></td><td><?=e($d['estoque_vsm'])?></td><td><?=e($d['diferenca'])?></td><td><?=e($d['status'])?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
</div>
<div class="card-soft mt-3"><h5>📈 Últimos movimentos</h5><div class="table-responsive"><table class="table table-sm"><thead><tr><th>ID</th><th>SKU</th><th>Origem</th><th>Qtd</th><th>Status</th><th>Trace</th><th>Data</th></tr></thead><tbody><?php foreach($movimentos as $m): ?><tr><td><?=e($m['id'])?></td><td><a href="index.php?page=estoque-sku-historico&sku=<?=urlencode($m['sku'])?>"><?=e($m['sku'])?></a></td><td><?=e($m['origem'])?></td><td><?=e($m['quantidade'])?></td><td><?=e($m['status'])?></td><td><code><?=e($m['trace_id'])?></code></td><td><?=e($m['criado_em'])?></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php require __DIR__.'/layout_bottom.php'; ?>
