<?php require __DIR__.'/layout_top.php'; ?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div>
    <h2 class="m-0"><i class="bi bi-cash-coin"></i> Planos Comerciais</h2>
    <p class="text-muted mb-0">Posicionamento comercial, frases de venda e pacotes de implantação do HUB.</p>
  </div>
  <a class="btn btn-outline-primary" href="index.php?page=produto-institucional" target="_blank"><i class="bi bi-box-arrow-up-right"></i> Página institucional</a>
</div>
<?php if(!empty($_SESSION['flash_success'])): ?><div class="alert alert-success"><?=e($_SESSION['flash_success']); unset($_SESSION['flash_success']);?></div><?php endif; ?>
<div class="card mb-3">
  <div class="card-header"><strong>Frases comerciais prontas</strong></div>
  <div class="card-body">
    <div class="row g-2">
      <?php foreach($phrases as $p): ?><div class="col-md-6"><div class="p-3 border rounded bg-light h-100"><i class="bi bi-megaphone"></i> <?=e($p)?></div></div><?php endforeach; ?>
    </div>
  </div>
</div>
<div class="row g-3 mb-3">
  <?php foreach($plans as $pl): ?>
  <div class="col-lg-3 col-md-6"><div class="card h-100 shadow-sm">
    <div class="card-body">
      <h4><?=e($pl['nome'])?></h4>
      <div class="h5 text-primary"><?=e($pl['preco'])?></div>
      <div class="small text-muted mb-2">Setup: <?=e($pl['setup'])?></div>
      <p><?=e($pl['perfil'])?></p>
      <ul class="small"><?php foreach($pl['inclui'] as $i): ?><li><?=e($i)?></li><?php endforeach; ?></ul>
    </div>
  </div></div>
  <?php endforeach; ?>
</div>
<div class="card">
  <div class="card-header"><strong>Diferenciais para proposta</strong></div>
  <div class="table-responsive"><table class="table table-sm align-middle mb-0"><tbody>
    <?php foreach($values as $v): ?><tr><td style="width:240px"><b><?=e($v['titulo'])?></b></td><td><?=e($v['texto'])?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
