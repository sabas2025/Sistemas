<?php require __DIR__.'/layout_top.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h2>Diagnóstico de Configuração Real</h2>
    <p class="text-muted mb-0">Mostra o banco realmente conectado por módulo, sem expor senha, token ou secret.</p>
  </div>
  <a class="btn btn-outline-primary" href="index.php?page=validar-banco">Validar Banco</a>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="card"><div class="card-body"><small>Versão</small><h5><?=e($diagnostic['version'])?></h5></div></div></div>
  <div class="col-md-3"><div class="card"><div class="card-body"><small>Modo configurado</small><h5><?=e($diagnostic['mode_configured'])?></h5></div></div></div>
  <div class="col-md-3"><div class="card"><div class="card-body"><small>Banco único efetivo</small><h5><?=$diagnostic['single_database_effective']?'Sim':'Não'?></h5></div></div></div>
  <div class="col-md-3"><div class="card"><div class="card-body"><small>Banco principal</small><h5><?=e($diagnostic['base_database'])?></h5></div></div></div>
</div>

<?php if(!empty($diagnostic['risk'])): ?>
<div class="alert alert-warning">
  <b>Atenção:</b>
  <ul class="mb-0"><?php foreach($diagnostic['risk'] as $r): ?><li><?=e($r)?></li><?php endforeach; ?></ul>
</div>
<?php else: ?>
<div class="alert alert-success">Configuração coerente. Nenhum risco crítico de banco/modular detectado neste diagnóstico.</div>
<?php endif; ?>

<div class="card">
  <div class="card-header"><b>Módulos e bancos conectados</b></div>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead><tr><th>Módulo</th><th>Status</th><th>Esperado</th><th>Conectado</th><th>Tabelas no banco</th><th>Oficiais</th><th>Ausentes</th></tr></thead>
      <tbody>
      <?php foreach($diagnostic['modules'] as $m): ?>
        <tr>
          <td><code><?=e($m['module'])?></code></td>
          <td><span class="badge bg-<?=$m['status']==='ok'?'success':($m['status']==='erro'?'danger':'warning')?>"><?=e($m['status'])?></span></td>
          <td><?=e($m['expected_database'])?></td>
          <td><?=e($m['connected_database'] ?: '-')?></td>
          <td><?=e((string)$m['table_count'])?></td>
          <td><?=e((string)$m['known_tables'])?></td>
          <td><?php if(!empty($m['missing_tables'])): ?><small><?=e(implode(', ', $m['missing_tables']))?></small><?php else: ?>—<?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="alert alert-info mt-3 mb-0"><b>Ação recomendada:</b> <?=e($diagnostic['recommendation'])?></div>
<?php require __DIR__.'/layout_bottom.php'; ?>
