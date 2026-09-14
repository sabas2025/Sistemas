<?php require __DIR__.'/layout_top.php'; ?>

<div class="cardx">
  <div class="d-flex justify-content-between align-items-center mb-3"><h4>Fila Morta (Dead Letter Queue)</h4><span class="badge bg-danger">Análise manual</span></div>
  <p class="text-muted">Itens que falharam o limite de tentativas ficam preservados aqui com payload, retorno, Trace ID e botão de reprocessamento controlado.</p>
  <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>ID</th><th>Tipo</th><th>Referência</th><th>Erro</th><th>Tent.</th><th>Status</th><th>Trace</th><th>Ação</th></tr></thead><tbody>
  <?php foreach($itens as $i): ?><tr>
    <td><?=$i['id']?></td><td><?=e($i['tipo'])?></td><td><?=e($i['referencia'])?></td><td><code><?=e($i['codigo_erro'])?></code><br><small><?=e($i['motivo'])?></small></td><td><?=$i['tentativas']?></td><td><span class="badge bg-<?=($i['status']==='aberto'?'danger':'secondary')?>"><?=e($i['status'])?></span></td><td><code><?=e($i['trace_id'])?></code></td>
    <td><?php if(PermissionService::can('fila_morta','reprocessar') && $i['status']==='aberto'): ?><form method="post" action="index.php?page=fila-morta-reprocessar"><?=Csrf::input()?><input type="hidden" name="id" value="<?=$i['id']?>"><button class="btn btn-sm btn-primary">Reprocessar</button></form><?php endif; ?></td>
  </tr><?php endforeach; ?>
  <?php if(!$itens): ?><tr><td colspan="8" class="text-center text-muted">Nenhum item na fila morta.</td></tr><?php endif; ?>
  </tbody></table></div>
</div>

<?php require __DIR__.'/layout_bottom.php'; ?>
