<?php require __DIR__.'/layout_top.php'; ?>
<?php
  $totalFluxos = count($fluxos ?? []);
  $ativos = $ativos ?? 0;
  $riscos = [];
  $estoqueTiny = !empty($rules['fluxo_estoque_tiny_enviar_vsm']);
  $estoqueVsm = !empty($rules['fluxo_estoque_vsm_enviar_tiny']);
  if($estoqueTiny && $estoqueVsm) $riscos[]='Estoque bidirecional ativo: defina fonte mestre para evitar loop.';
  if(empty($rules['sync_exigir_mapeamento_sku'])) $riscos[]='SKU mapeado não está obrigatório: risco de produto novo/incorreto.';
  if(empty($rules['sync_exigir_nfe_autorizada'])) $riscos[]='NF-e pode ser enviada sem status autorizado: risco fiscal.';
?>
<div class="panel">
  <div class="panel-header"><h2>Central de Integrações Tiny ⇄ VSM</h2><span class="text-muted">Painel único para operação, riscos e configuração</span></div>
  <div class="p-4">
    <div class="row g-3 mb-3">
      <div class="col-md-3"><div class="card-soft"><div class="text-muted small">Fluxos ativos</div><h3><?=e($ativos)?>/<?=e($totalFluxos)?></h3></div></div>
      <div class="col-md-3"><div class="card-soft"><div class="text-muted small">Pedidos</div><h3><?=!empty($rules['fluxo_pedido_vsm_enviar_tiny'])?'Ativo':'Parado'?></h3></div></div>
      <div class="col-md-3"><div class="card-soft"><div class="text-muted small">NF-e Tiny → VSM</div><h3><?=!empty($rules['fluxo_nfe_tiny_enviar_vsm'])?'Ativo':'Parado'?></h3></div></div>
      <div class="col-md-3"><div class="card-soft"><div class="text-muted small">Estoque</div><h3><?=($estoqueTiny&&$estoqueVsm)?'Bidirecional':($estoqueTiny?'Tiny→VSM':($estoqueVsm?'VSM→Tiny':'Parado'))?></h3></div></div>
    </div>

    <?php if($riscos): ?><div class="alert alert-warning"><b>Atenção operacional:</b><ul class="mb-0"><?php foreach($riscos as $r): ?><li><?=e($r)?></li><?php endforeach; ?></ul></div><?php else: ?><div class="alert alert-success">Modelo seguro ativo: SKU obrigatório, NF-e autorizada e travas principais habilitadas.</div><?php endif; ?>

    <div class="d-flex flex-wrap gap-2 mb-3">
      <a class="btn btn-primary" href="index.php?page=orquestracao-integracoes"><i class="bi bi-ui-checks-grid"></i> Escolher fluxos ativos</a>
      <a class="btn btn-outline-primary" href="index.php?page=regras-sincronizacao"><i class="bi bi-toggles"></i> Regras de sincronização</a>
      <a class="btn btn-outline-primary" href="index.php?page=tiny-webhooks"><i class="bi bi-broadcast"></i> Webhooks Tiny</a>
      <a class="btn btn-outline-primary" href="index.php?page=vsm-endpoints"><i class="bi bi-plug"></i> Endpoints VSM</a>
      <a class="btn btn-outline-primary" href="index.php?page=vsm-campos"><i class="bi bi-arrow-left-right"></i> Campos VSM</a>
      <a class="btn btn-outline-info" href="index.php?page=vsm-saude"><i class="bi bi-heart-pulse"></i> Saúde VSM</a>
      <a class="btn btn-outline-success" href="index.php?page=homologacao-automatica"><i class="bi bi-magic"></i> Homologar</a>
    </div>

    <div class="card-soft mb-3">
      <h5><i class="bi bi-table"></i> Matriz operacional dos fluxos</h5>
      <div class="table-responsive"><table class="table table-sm align-middle">
        <thead><tr><th>Ordem</th><th>Fluxo</th><th>Origem/Destino</th><th>Tipo</th><th>Status</th><th>Risco/controle</th></tr></thead>
        <tbody>
        <?php foreach($ordem as $idx=>$id): foreach($fluxos as $f): if($f['id']!==$id) continue; $on=!empty($f['ativo']); ?>
          <tr class="<?=$on?'':'table-light'?>">
            <td><?=e($idx+1)?></td><td><b><?=e($f['titulo'])?></b></td><td><?=e($f['grupo'])?> → <?=e($f['destino'])?></td><td><span class="badge bg-light text-dark border"><?=e($f['tipo'])?></span></td><td><span class="badge <?=$on?'bg-success':'bg-secondary'?>"><?=$on?'Ativo':'Desativado'?></span></td><td class="small"><?=e($f['risco'])?></td>
          </tr>
        <?php endforeach; endforeach; ?>
        </tbody></table></div>
    </div>

    <div class="card-soft">
      <h5><i class="bi bi-diagram-3"></i> Separação arquitetural V42</h5>
      <p class="text-muted small mb-2">Mapa dos controllers planejados para reduzir o DashboardController e manter o sistema organizado por domínio.</p>
      <?php foreach($arquitetura as $controller=>$rotas): ?><span class="architecture-chip"><i class="bi bi-folder2-open"></i><b><?=e($controller)?></b> <?=e(count($rotas))?> rotas</span><?php endforeach; ?>
    </div>
  </div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
