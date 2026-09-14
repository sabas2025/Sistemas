<?php require __DIR__.'/layout_top.php'; ?>
<div class="card-soft mb-3">
  <h4>Pendências de Produtos VSM → Tiny</h4>
  <p class="text-muted mb-0">SKUs que precisam de conferência manual antes de atualizar o Tiny. Ações disponíveis: resolver, ignorar, reprocessar, vincular SKU Tiny manualmente ou criar produto no Tiny a partir da pendência.</p>
</div>
<form class="row g-2 mb-3">
  <input type="hidden" name="page" value="produtos-pendencias">
  <div class="col-md-3"><select class="form-select" name="status"><option value="">Todos status</option><?php foreach(['aberto','resolvido','ignorado'] as $s): ?><option value="<?=$s?>" <?=$status===$s?'selected':''?>><?=$s?></option><?php endforeach; ?></select></div>
  <div class="col-md-6"><input class="form-control" name="busca" value="<?=e($busca)?>" placeholder="Buscar SKU, motivo ou Trace ID"></div>
  <div class="col-md-3"><button class="btn btn-primary w-100">Filtrar</button></div>
</form>
<div class="card-soft table-responsive">
<table class="table table-hover align-middle">
<thead><tr><th>ID</th><th>SKU</th><th>Tipo</th><th>Motivo</th><th>Status</th><th>Ações</th><th>Trace ID</th><th>Data</th></tr></thead>
<tbody><?php foreach($pendencias as $p): ?><tr>
<td><?=$p['id']?></td>
<td><b><?=e($p['sku'])?></b></td>
<td><?=e($p['tipo_evento'])?></td>
<td><span class="badge text-bg-warning"><?=e($p['motivo'])?></span><br><small><?=e($p['mensagem'])?></small></td>
<td><?=e($p['status'])?></td>
<td style="min-width:360px">
  <?php if($p['status']==='aberto'): ?>
  <div class="d-flex flex-wrap gap-1 mb-1">
    <form method="post" action="index.php?page=produto-pendencia-acao"><?=Csrf::input()?><input type="hidden" name="id" value="<?=$p['id']?>"><button class="btn btn-sm btn-success" name="acao" value="resolver">Resolver</button></form>
    <form method="post" action="index.php?page=produto-pendencia-acao"><?=Csrf::input()?><input type="hidden" name="id" value="<?=$p['id']?>"><button class="btn btn-sm btn-outline-secondary" name="acao" value="ignorar">Ignorar</button></form>
    <form method="post" action="index.php?page=produto-pendencia-acao"><?=Csrf::input()?><input type="hidden" name="id" value="<?=$p['id']?>"><button class="btn btn-sm btn-warning" name="acao" value="reprocessar">Reprocessar</button></form>
    <form method="post" action="index.php?page=produto-pendencia-acao"><?=Csrf::input()?><input type="hidden" name="id" value="<?=$p['id']?>"><button class="btn btn-sm btn-outline-primary" name="acao" value="criar_produto_tiny">Criar no Tiny</button></form>
  </div>
  <form method="post" action="index.php?page=produto-pendencia-acao" class="d-flex gap-1"><?=Csrf::input()?><input type="hidden" name="id" value="<?=$p['id']?>"><input class="form-control form-control-sm" name="sku_tiny" placeholder="SKU Tiny para vincular"><button class="btn btn-sm btn-primary" name="acao" value="vincular">Vincular</button></form>
  <?php else: ?><span class="text-muted">Sem ação</span><?php endif; ?>
</td>
<td><code><?=e($p['trace_id'])?></code></td><td><?=e($p['criado_em'])?></td>
</tr><?php endforeach; ?></tbody>
</table>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
