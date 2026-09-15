<?php require __DIR__.'/layout_top.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="m-0">Integridade de Arquivos</h2><p class="text-muted mb-0">Monitora alterações em arquivos PHP críticos do HUB.</p></div><a class="btn btn-outline-secondary" href="index.php?page=security-center">Central</a></div>
<?php if(($_GET['gerado'] ?? '')==='1'): ?><div class="alert alert-success">Manifesto gerado com sucesso.</div><?php endif; ?>
<div class="card mb-3"><div class="card-body">
  <h5>Status: <?=e(strtoupper($resultado['status']))?></h5>
  <p>Total monitorado: <b><?=e((string)$resultado['total'])?></b></p>
  <form method="post" action="index.php?page=security-fim-gerar" data-confirm="Gerar novo manifesto confiável agora? Faça isso somente após instalar uma versão limpa.">
    <?=Csrf::field()?>
    <button class="btn btn-primary"><i class="bi bi-fingerprint"></i> Gerar manifesto confiável</button>
  </form>
</div></div>
<div class="row g-3">
<?php foreach(['alterados'=>'Arquivos alterados','faltantes'=>'Arquivos faltantes','novos'=>'Arquivos novos'] as $k=>$label): ?>
  <div class="col-lg-4"><div class="card h-100"><div class="card-header"><b><?=e($label)?></b></div><div class="card-body"><ul class="small mb-0">
  <?php foreach(($resultado[$k] ?? []) as $f): ?><li><code><?=e($f)?></code></li><?php endforeach; ?>
  <?php if(empty($resultado[$k])): ?><li class="text-muted">Nenhum item.</li><?php endif; ?>
  </ul></div></div></div>
<?php endforeach; ?>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
