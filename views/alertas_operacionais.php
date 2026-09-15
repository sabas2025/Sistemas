<?php require __DIR__.'/layout_top.php'; ?>
<div class="panel">
  <div class="panel-header"><div><h2>🚨 Alertas Operacionais</h2><span class="text-muted">Pendências que podem afetar a integração Tiny ↔ HUB ↔ VSM.</span></div><a class="btn btn-sm btn-outline-primary" href="index.php?page=dashboard">Voltar</a></div>
  <div class="p-3">
    <?php if(empty($operacao['alertas'])): ?>
      <div class="alert alert-success mb-0">🟢 Nenhum alerta crítico encontrado.</div>
    <?php else: ?>
      <div class="row g-3">
        <?php foreach($operacao['alertas'] as $a): $erro=($a['nivel']??'')==='erro'; ?>
          <div class="col-md-6"><div class="health-card h-100"><div class="health-icon"><i class="bi <?=$erro?'bi-x-octagon':'bi-exclamation-triangle'?>"></i></div><div><b><?=$erro?'🔴':'🟡'?> <?=e($a['titulo'])?></b><div class="small text-muted mt-1"><?=e($a['mensagem'])?></div></div></div></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
