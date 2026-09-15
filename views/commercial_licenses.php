<?php require __DIR__.'/layout_top.php'; ?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div><h2 class="m-0"><i class="bi bi-key"></i> Licenças por Cliente</h2><p class="text-muted mb-0">Controle comercial por cliente, plano, ambiente, limites e expiração.</p></div>
  <form method="post" action="index.php?page=licencas-clientes-demo"><?=Csrf::input()?><button class="btn btn-primary"><i class="bi bi-plus-circle"></i> Gerar licença demo</button></form>
</div>
<?php if(!empty($_SESSION['flash_success'])): ?><div class="alert alert-success"><?=e($_SESSION['flash_success']); unset($_SESSION['flash_success']);?></div><?php endif; ?>
<div class="alert alert-warning small"><b>Importante:</b> a chave real é exibida uma única vez. O banco guarda apenas HMAC/hash para conferência comercial.</div>
<div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
  <thead><tr><th>Cliente</th><th>Documento</th><th>Plano</th><th>Status</th><th>Ambiente</th><th>Limites</th><th>Validade</th><th>Hash licença</th></tr></thead>
  <tbody>
  <?php if(empty($licenses)): ?><tr><td colspan="8" class="text-muted">Nenhuma licença cadastrada. Gere uma licença demo para validar a tela.</td></tr><?php endif; ?>
  <?php foreach($licenses as $l): ?><tr>
    <td><b><?=e($l['cliente_nome'])?></b><br><small><?=e($l['email_responsavel'])?></small></td>
    <td><?=e($l['documento'])?></td><td><?=e($l['plano'])?></td><td><span class="badge text-bg-<?=($l['status']==='ativo'?'success':($l['status']==='trial'?'warning':'secondary'))?>"><?=e($l['status'])?></span></td>
    <td><?=e($l['ambiente'])?></td><td><?=e($l['limite_empresas'])?> empresa(s), <?=e($l['limite_filiais'])?> filial(is), <?=e($l['limite_conectores'])?> conector(es)</td>
    <td><?=e($l['data_inicio'])?> até <?=e($l['data_expiracao'])?></td><td><code><?=e(substr((string)$l['license_key_hash'],0,20))?>...</code></td>
  </tr><?php endforeach; ?>
  </tbody>
</table></div></div>
<?php require __DIR__.'/layout_bottom.php'; ?>
