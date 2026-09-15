<?php require __DIR__.'/layout_top.php'; ?>
<div class="panel mb-4"><div class="panel-header"><div><h2>🧾 Timeline XML/NF-e</h2><span class="text-muted">Linha do tempo da NF-e/XML com Trace ID.</span></div><form class="d-flex gap-2" method="get"><input type="hidden" name="page" value="fiscal-timeline"><input class="form-control form-control-sm" name="id" value="<?=e($id??'')?>" placeholder="ID NF-e"><button class="btn btn-sm btn-primary">Buscar</button></form></div></div>
<?php if(!empty($erro)): ?><div class="alert alert-warning"><?=e($erro)?></div><?php endif; ?>
<?php if($nota): ?><div class="panel mb-4"><div class="p-3"><b>NF-e:</b> <?=e(($nota['numero']??'-').'/'.($nota['serie']??''))?> · <b>Chave:</b> <?=e($nota['chave_acesso']??'-')?> · <b>Status:</b> <?=e($nota['status']??'-')?></div></div><?php endif; ?>
<div class="panel"><div class="p-3">
<?php if(empty($eventos)): ?><p class="text-muted">Informe uma NF-e ou aguarde eventos fiscais.</p><?php endif; ?>
<div class="timeline">
<?php foreach(($eventos??[]) as $ev): ?><div class="timeline-item mb-3 p-3 rounded border"><div><b>● <?=e($ev['tipo']??'evento')?></b> <small class="text-muted"><?=e($ev['criado_em']??'')?></small></div><div><?=e($ev['mensagem']??'')?></div><code><?=e($ev['trace_id']??'-')?></code></div><?php endforeach; ?>
</div></div></div>
<?php require __DIR__.'/layout_bottom.php'; ?>
