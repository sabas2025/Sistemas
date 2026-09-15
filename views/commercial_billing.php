<?php require __DIR__.'/layout_top.php'; ?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div><h2 class="m-0"><i class="bi bi-receipt"></i> Painel de Cobrança</h2><p class="text-muted mb-0">Controle básico de faturas comerciais. Não substitui gateway de pagamento ou emissão fiscal.</p></div>
  <form method="post" action="index.php?page=painel-cobranca-demo"><?=Csrf::input()?><button class="btn btn-primary"><i class="bi bi-plus-circle"></i> Criar fatura demo</button></form>
</div>
<?php if(!empty($_SESSION['flash_success'])): ?><div class="alert alert-success"><?=e($_SESSION['flash_success']); unset($_SESSION['flash_success']);?></div><?php endif; ?>
<div class="row g-3 mb-3">
  <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Clientes licenciados</div><div class="h2"><?=e(count($licenses))?></div></div></div></div>
  <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Faturas</div><div class="h2"><?=e(count($invoices))?></div></div></div></div>
  <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Gateway</div><div class="h5">A integrar</div><small>Mercado Pago, boleto, Pix ou ERP financeiro.</small></div></div></div>
</div>
<div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
  <thead><tr><th>Cliente</th><th>Descrição</th><th>Valor</th><th>Status</th><th>Vencimento</th><th>Forma</th><th>Obs.</th></tr></thead><tbody>
  <?php if(empty($invoices)): ?><tr><td colspan="7" class="text-muted">Nenhuma fatura cadastrada.</td></tr><?php endif; ?>
  <?php foreach($invoices as $f): ?><tr><td><?=e($f['cliente_nome'] ?? '-')?></td><td><?=e($f['descricao'])?></td><td>R$ <?=number_format(((int)$f['valor_centavos'])/100,2,',','.')?></td><td><?=e($f['status'])?></td><td><?=e($f['vencimento'])?></td><td><?=e($f['forma_pagamento'])?></td><td><?=e($f['observacoes'])?></td></tr><?php endforeach; ?>
  </tbody></table></div></div>
<?php require __DIR__.'/layout_bottom.php'; ?>
