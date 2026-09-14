<?php require __DIR__.'/layout_top.php'; ?>
<div class="card-soft mb-3">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <h4>📡 Histórico de Consultas de Estoque VSM</h4>
      <p class="text-muted mb-0">Acompanha cada execução automática/manual da consulta de estoque na VSM, que é a fonte real do saldo.</p>
    </div>
    <a class="btn btn-outline-secondary" href="index.php?page=estoque-config">⚙️ Configurar consulta</a>
  </div>
</div>
<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="card-soft"><small>Modo</small><h5><?=e($config['consulta_vsm_modo'] ?? 'automatico')?></h5></div></div>
  <div class="col-md-3"><div class="card-soft"><small>Intervalo</small><h5><?=e($config['consulta_vsm_intervalo_minutos'] ?? '60')?> min</h5></div></div>
  <div class="col-md-3"><div class="card-soft"><small>Qtd. por execução</small><h5><?=e($config['consulta_vsm_quantidade_produtos'] ?? '100')?></h5></div></div>
  <div class="col-md-3"><div class="card-soft"><small>Status</small><h5><?=($config['consulta_vsm_ativa'] ?? '1')==='1'?'Ativa':'Inativa'?></h5></div></div>
</div>
<div class="card-soft">
  <div class="table-responsive"><table class="table table-sm align-middle">
    <thead><tr><th>ID</th><th>Modo</th><th>Status</th><th>Total</th><th>Sucesso</th><th>Erro</th><th>Alterados</th><th>Enfileirados Tiny</th><th>Início</th><th>Fim</th><th></th></tr></thead>
    <tbody><?php foreach($execucoes as $e): ?>
      <tr>
        <td><?=e($e['id'])?></td><td><?=e($e['modo'])?></td><td><span class="badge bg-<?=($e['status']==='concluido'?'success':($e['status']==='parcial'?'warning':($e['status']==='erro'?'danger':'secondary')))?>"><?=e($e['status'])?></span></td>
        <td><?=e($e['total_produtos'])?></td><td><?=e($e['total_sucesso'])?></td><td><?=e($e['total_erro'])?></td><td><?=e($e['total_alterados'])?></td><td><?=e($e['total_enfileirados_tiny'])?></td>
        <td><?=e($e['iniciado_em'])?></td><td><?=e($e['finalizado_em'])?></td>
        <td><a class="btn btn-sm btn-outline-primary" href="index.php?page=estoque-consulta-vsm-resultados&execucao_id=<?=e($e['id'])?>">Ver SKUs</a></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
