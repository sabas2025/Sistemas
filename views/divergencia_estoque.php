<?php require __DIR__.'/layout_top.php'; ?>
<div class="card-soft mb-3">
  <h4>Monitor de Divergências Tiny x VSM</h4>
  <p class="text-muted mb-0">Controle de diferenças entre saldo da VSM e saldo do Tiny. Permite ignorar, marcar corrigido ou gerar fila para corrigir o Tiny com o saldo da VSM.</p>
</div>
<?php if(isset($resumo)): ?><div class="row g-3 mb-3"><div class="col-md-3"><div class="kpi"><div class="label">Abertas</div><div class="value text-danger"><?=e($resumo['abertas']??0)?></div></div></div><div class="col-md-3"><div class="kpi"><div class="label">Corrigidas</div><div class="value text-success"><?=e($resumo['corrigidas']??0)?></div></div></div><div class="col-md-3"><div class="kpi"><div class="label">Ignoradas</div><div class="value"><?=e($resumo['ignoradas']??0)?></div></div></div><div class="col-md-3"><div class="kpi"><div class="label">Total</div><div class="value"><?=e($resumo['total']??0)?></div></div></div></div><?php endif; ?>
<form class="row g-2 mb-3">
  <input type="hidden" name="page" value="divergencia-estoque">
  <div class="col-md-3"><select class="form-select" name="status"><option value="">Todos status</option><?php foreach(['aberto','corrigido','ignorado'] as $s): ?><option value="<?=$s?>" <?=$status===$s?'selected':''?>><?=$s?></option><?php endforeach; ?></select></div>
  <div class="col-md-6"><input class="form-control" name="busca" value="<?=e($busca)?>" placeholder="Buscar SKU ou Trace ID"></div>
  <div class="col-md-3"><button class="btn btn-primary w-100">Filtrar</button></div>
</form>
<div class="card-soft table-responsive">
<table class="table table-hover align-middle">
<thead><tr><th>SKU</th><th>VSM</th><th>Tiny</th><th>Diferença</th><th>Status</th><th>Ação recomendada</th><th>Ações</th><th>Trace ID</th><th>Data</th></tr></thead>
<tbody><?php foreach($divergencias as $d): ?><tr>
<td><b><?=e($d['sku'])?></b></td><td><?=e($d['estoque_vsm'])?></td><td><?=e($d['estoque_tiny'])?></td><td><span class="badge <?=abs((float)$d['diferenca'])>0?'text-bg-danger':'text-bg-success'?>"><?=e($d['diferenca'])?></span></td><td><?=e($d['status'])?></td>
<td><small><?=abs((float)$d['diferenca'])>0?'Conferir origem da diferença e corrigir o sistema definido como referência.':'Sem divergência.'?></small></td>
<td>
<?php if($d['status']==='aberto'): ?><div class="d-flex flex-wrap gap-1">
<form method="post" action="index.php?page=divergencia-acao"><?=Csrf::input()?><input type="hidden" name="id" value="<?=$d['id']?>"><button class="btn btn-sm btn-primary" name="acao" value="corrigir_tiny">Corrigir Tiny</button></form>
<form method="post" action="index.php?page=divergencia-acao"><?=Csrf::input()?><input type="hidden" name="id" value="<?=$d['id']?>"><button class="btn btn-sm btn-success" name="acao" value="marcar_corrigido">Corrigido</button></form>
<form method="post" action="index.php?page=divergencia-acao"><?=Csrf::input()?><input type="hidden" name="id" value="<?=$d['id']?>"><button class="btn btn-sm btn-outline-secondary" name="acao" value="ignorar">Ignorar</button></form>
</div><?php else: ?><span class="text-muted">Sem ação</span><?php endif; ?>
</td>
<td><code><?=e($d['trace_id'])?></code><br><?php if(!empty($d['trace_id'])): ?><a class="small" href="index.php?page=evidencias-homologacao&trace=<?=urlencode($d['trace_id'])?>">Evidência</a><?php endif; ?></td><td><?=e($d['criado_em'])?></td>
</tr><?php endforeach; ?></tbody>
</table>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
