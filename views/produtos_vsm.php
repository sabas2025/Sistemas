<?php require __DIR__.'/layout_top.php'; ?>
<div class="alert alert-info">
  <b>Fluxo VSM → Tiny:</b> produto novo não deve criar no Tiny automaticamente. Use pendências e mapeamento de categorias antes da aprovação manual.
</div>

<div class="d-flex gap-2 flex-wrap mb-3"><a class="btn btn-warning" href="index.php?page=produtos-pendentes-integracao"><i class="bi bi-shield-check"></i> Aprovação de produtos novos</a><a class="btn btn-outline-primary" href="index.php?page=categorias-mapeamento"><i class="bi bi-tags"></i> Categorias VSM ↔ Tiny</a><a class="btn btn-outline-secondary" href="index.php?page=orquestracao-integracoes">Fluxos ativos</a></div>

<div class="row g-3 mb-4">
  <div class="col-lg-4"><div class="card-soft h-100">
    <h5><i class="bi bi-box-seam"></i> Simular produto novo/atualização</h5>
    <p class="text-muted mb-3">Produto novo deve cair como pendência manual; atualização só deve seguir com SKU mapeado.</p>
    <form class="row g-2" method="post" action="index.php?page=simular-produto-vsm">
      <?= Csrf::input() ?>
      <div class="col-5"><label class="form-label">SKU</label><input class="form-control" name="sku" value="VSM<?=date('His')?>" required></div>
      <div class="col-7"><label class="form-label">Nome</label><input class="form-control" name="nome" value="Produto Teste VSM" required></div>
      <div class="col-6"><label class="form-label">EAN/GTIN</label><input class="form-control" name="ean" value="7890000000000"></div>
      <div class="col-6"><label class="form-label">NCM</label><input class="form-control" name="ncm" value="00000000"></div>
      <div class="col-4"><label class="form-label">Un.</label><input class="form-control" name="unidade" value="UN"></div>
      <div class="col-4"><label class="form-label">Preço</label><input class="form-control" name="preco_venda" type="number" step="0.01" value="10.00"></div>
      <div class="col-4"><label class="form-label">Estoque</label><input class="form-control" name="estoque" type="number" step="0.001" value="5"></div>
      <div class="col-12"><button class="btn btn-primary w-100">Criar/Atualizar Produto</button></div>
    </form>
  </div></div>

  <div class="col-lg-4"><div class="card-soft h-100">
    <h5><i class="bi bi-boxes"></i> Simular estoque VSM → Tiny</h5>
    <p class="text-muted mb-3">Atualiza saldo/estoque do produto no Tiny com base na VSM.</p>
    <form class="row g-2" method="post" action="index.php?page=simular-estoque-vsm">
      <?= Csrf::input() ?>
      <div class="col-7"><label class="form-label">SKU</label><input class="form-control" name="sku" value="VSMEST<?=date('His')?>" required></div>
      <div class="col-5"><label class="form-label">Estoque</label><input class="form-control" name="estoque" type="number" step="0.001" value="10" required></div>
      <div class="col-12"><button class="btn btn-outline-primary w-100">Atualizar Estoque</button></div>
    </form>
  </div></div>

  <div class="col-lg-4"><div class="card-soft h-100">
    <h5><i class="bi bi-toggle-on"></i> Simular ativo/inativo VSM → Tiny</h5>
    <p class="text-muted mb-3">Ativa ou inativa o produto no Tiny conforme status enviado pela VSM.</p>
    <form class="row g-2" method="post" action="index.php?page=simular-status-vsm">
      <?= Csrf::input() ?>
      <div class="col-7"><label class="form-label">SKU</label><input class="form-control" name="sku" value="VSMSTS<?=date('His')?>" required></div>
      <div class="col-5"><label class="form-label">Status</label><select class="form-select" name="ativo"><option value="1">Ativo</option><option value="0">Inativo</option></select></div>
      <div class="col-12"><button class="btn btn-outline-primary w-100">Atualizar Status</button></div>
    </form>
  </div></div>
</div>

<div class="card-soft mb-3">
  <form class="row g-2">
    <input type="hidden" name="page" value="produtos-vsm">
    <div class="col-md-3"><input class="form-control" name="busca" placeholder="SKU, Trace ID ou payload" value="<?=e($_GET['busca']??'')?>"></div>
    <div class="col-md-2"><select class="form-select" name="status"><option value="">Todos status</option><?php foreach(['pendente','processando','sucesso','erro','falha_definitiva'] as $st): ?><option value="<?=$st?>" <?=($_GET['status']??'')===$st?'selected':''?>><?=$st?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filtrar</button></div>
  </form>
</div>

<div class="row g-3">
  <div class="col-lg-8"><div class="card-soft"><h5>Eventos VSM na fila</h5><div class="table-responsive"><table class="table table-hover align-middle">
    <thead><tr><th>ID</th><th>Tipo</th><th>SKU</th><th>Status</th><th>Tent.</th><th>Trace ID</th><th>Criado em</th><th>Payload/retorno</th><th>Ação</th></tr></thead>
    <tbody><?php foreach($produtos as $p): ?><tr>
      <td><?=e($p['id'])?></td><td><span class="badge bg-secondary"><?=e($p['tipo'])?></span></td><td><b><?=e($p['referencia'])?></b></td><td><span class="badge bg-<?=($p['status']==='sucesso'?'success':($p['status']==='erro'||$p['status']==='falha_definitiva'?'danger':'warning'))?>"><?=e($p['status'])?></span></td>
      <td><?=e($p['tentativas'])?></td><td><code><?=e($p['trace_id'])?></code></td><td><?=e($p['criado_em'])?></td>
      <td><details><summary>ver</summary><pre class="json-box"><?=e($p['retorno'] ?: $p['payload'])?></pre></details></td>
      <td><?php if(in_array($p['status'],['erro','falha_definitiva','processando'],true) && PermissionService::can('fila','reprocessar')): ?><form method="post" action="index.php?page=fila-reprocessar"><?=Csrf::input()?><input type="hidden" name="id" value="<?=e($p['id'])?>"><button class="btn btn-sm btn-outline-primary">Reprocessar</button></form><?php endif; ?></td>
    </tr><?php endforeach; ?></tbody>
  </table></div></div></div>
  <div class="col-lg-4"><div class="card-soft mb-3"><h5>Produtos mapeados</h5><?php foreach($mapeados as $m): ?><div class="border-bottom py-2"><b><?=e($m['sku_vsm'])?></b> <span class="badge bg-<?=((int)$m['ativo']===1?'success':'secondary')?>"><?=((int)$m['ativo']===1?'Ativo':'Inativo')?></span><br><small>Tiny: <?=e($m['produto_tiny_id'] ?: '-')?></small><br><small><?=e($m['descricao'])?></small></div><?php endforeach; ?></div>
  <div class="card-soft"><h5>Histórico VSM</h5><?php foreach(($eventos ?? []) as $ev): ?><div class="border-bottom py-2"><b><?=e($ev['sku'])?></b> <small><?=e($ev['tipo_evento'])?></small><br><small>Status VSM: <?=e($ev['status_vsm'] ?: '-')?> | Estoque: <?=e($ev['estoque_vsm'] ?? '-')?></small><br><small><?=e($ev['criado_em'])?></small></div><?php endforeach; ?></div></div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
