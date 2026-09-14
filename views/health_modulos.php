<?php require __DIR__.'/layout_top.php'; ?>
<div class="panel mb-4">
  <div class="panel-header">
    <div><h2>Health Check por Módulo</h2><span class="text-muted">Validação real das conexões e tabelas críticas em cada banco modular.</span></div>
    <a class="btn btn-sm btn-primary" href="index.php?page=dashboard-integridade"><i class="bi bi-check2-circle"></i> Integridade do Dashboard</a>
  </div>
  <div class="row g-3 p-3">
    <?php $total=count($checks); $erros=count(array_filter($checks,fn($c)=>($c['status']??'')==='erro')); ?>
    <div class="col-md-4"><div class="kpi"><div class="label">Checks</div><div class="value"><?=e($total)?></div></div></div>
    <div class="col-md-4"><div class="kpi"><div class="label">OK</div><div class="value"><?=e($total-$erros)?></div></div></div>
    <div class="col-md-4"><div class="kpi"><div class="label">Erros</div><div class="value"><?=e($erros)?></div></div></div>
  </div>
</div>
<div class="panel"><div class="panel-header"><h2>Resultado</h2></div><div class="table-responsive"><table class="table table-hover"><thead><tr><th>Módulo</th><th>Status</th><th>Mensagem</th></tr></thead><tbody><?php foreach($checks as $c): ?><tr><td><b><?=e($c['modulo'])?></b></td><td><span class="badge-status <?=($c['status']==='ok'?'status-sucesso':'status-erro')?>"><?=e($c['status'])?></span></td><td><?=e($c['mensagem'])?></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php require __DIR__.'/layout_bottom.php'; ?>
