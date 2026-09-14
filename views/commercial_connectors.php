<?php require __DIR__.'/layout_top.php'; ?>
<h2><i class="bi bi-plug"></i> Conectores Plugáveis</h2>
<p class="text-muted">Catálogo para vender o HUB como plataforma adaptável, com conectores ativos, opcionais e planejados.</p>
<div class="row g-3">
<?php foreach($connectors as $c): ?>
  <div class="col-md-6 col-xl-4"><div class="card h-100">
    <div class="card-body">
      <div class="d-flex justify-content-between gap-2"><h5><?=e($c['nome'])?></h5><span class="badge text-bg-<?=($c['status']==='ativo'?'success':($c['status']==='opcional'?'warning':'secondary'))?>"><?=e($c['status'])?></span></div>
      <div class="small text-muted mb-2"><?=e($c['categoria'])?> · <code><?=e($c['codigo'])?></code></div>
      <p><?=e($c['descricao'])?></p>
      <div class="small"><b>Requisitos:</b> <?=e($c['requisitos'])?></div>
    </div>
  </div></div>
<?php endforeach; ?>
</div>
<div class="alert alert-info mt-3"><b>Modelo comercial:</b> cada novo conector pode ser vendido como módulo adicional, com setup próprio e mensalidade por suporte/monitoramento.</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
