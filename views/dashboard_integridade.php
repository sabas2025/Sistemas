<?php require __DIR__.'/layout_top.php'; ?>
<div class="panel mb-4">
  <div class="panel-header">
    <div>
      <h2>Integridade do Dashboard</h2>
      <span class="text-muted">Verificação minuciosa das tabelas, módulos, variáveis da view e rotas usadas pelo dashboard.</span>
    </div>
    <div class="d-flex gap-2"><form method="post" action="index.php?page=dashboard-integridade-executar"><?= Csrf::input() ?><button class="btn btn-sm btn-success"><i class="bi bi-check2-circle"></i> Verificar dashboard</button></form><a class="btn btn-sm btn-primary" href="index.php?page=dashboard"><i class="bi bi-speedometer2"></i> Voltar ao dashboard</a></div>
  </div>
  <div class="row g-3 p-3">
    <div class="col-md-4"><div class="kpi"><div class="label">Verificações</div><div class="value"><?=e($summary['total'])?></div></div></div>
    <div class="col-md-4"><div class="kpi"><div class="label">OK</div><div class="value"><?=e($summary['ok'])?></div></div></div>
    <div class="col-md-4"><div class="kpi"><div class="label">Erros</div><div class="value"><?=e($summary['erros'])?></div></div></div>
  </div>
</div>
<div class="panel">
  <div class="panel-header"><h2>Resultado técnico</h2></div>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead><tr><th>Item</th><th>Módulo</th><th>Status</th><th>Mensagem</th></tr></thead>
      <tbody>
        <?php foreach($checks as $c): ?>
        <tr>
          <td><b><?=e($c['item'])?></b></td>
          <td><?=e($c['modulo'])?></td>
          <td><span class="badge-status <?=($c['status']==='ok'?'status-sucesso':'status-erro')?>"><?=e($c['status'])?></span></td>
          <td><?=e($c['mensagem'])?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
