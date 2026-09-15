<?php require __DIR__.'/layout_top.php'; ?>
<form class="filterbar row g-2" method="get">
  <input type="hidden" name="page" value="auditoria">
  <div class="col-md-2"><select name="status" class="form-select"><option value="">Todos status</option><?php foreach(['sucesso','erro','alerta','info'] as $s): ?><option value="<?=$s?>" <?=($_GET['status']??'')===$s?'selected':''?>><?=$s?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><input class="form-control" name="trace" placeholder="Trace ID" value="<?=e($_GET['trace']??'')?>"></div>
  <div class="col-md-4"><input class="form-control" name="busca" placeholder="Ação, erro, mensagem ou entidade" value="<?=e($_GET['busca']??'')?>"></div>
  <div class="col-md-2"><button class="btn btn-primary w-100">Filtrar</button></div>
  <div class="col-md-1"><a class="btn btn-outline-secondary w-100" href="index.php?page=auditoria">Limpar</a></div>
</form>
<div class="panel">
  <div class="panel-header"><h2>Eventos auditáveis</h2><span class="text-muted">Últimos 300 registros</span></div>
  <div class="table-responsive"><table class="table table-hover align-middle">
    <thead><tr><th>ID</th><th>Status</th><th>Trace ID</th><th>Ação</th><th>Código</th><th>Mensagem</th><th>Causa provável</th><th>Data</th><th></th></tr></thead>
    <tbody><?php foreach($eventos as $ev): ?><tr>
      <td><?=e($ev['id'])?></td>
      <td><span class="badge-status status-<?=e($ev['status'])?>"><?=e($ev['status'])?></span></td>
      <td><code><?=e($ev['trace_id'])?></code></td>
      <td><?=e($ev['acao'])?></td>
      <td><?=e($ev['codigo_erro'] ?: '-')?></td>
      <td><?=e(mb_strimwidth($ev['mensagem'] ?? '',0,70,'...'))?></td>
      <td><?=e(mb_strimwidth($ev['causa_provavel'] ?? '-',0,70,'...'))?></td>
      <td><?=e($ev['criado_em'])?></td>
      <td><a class="btn btn-sm btn-outline-primary" href="index.php?page=auditoria-detalhe&id=<?=e($ev['id'])?>">Detalhe</a></td>
    </tr><?php endforeach; if(!$eventos): ?><tr><td colspan="9" class="text-center text-muted py-4">Nenhum evento encontrado.</td></tr><?php endif; ?></tbody>
  </table></div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
