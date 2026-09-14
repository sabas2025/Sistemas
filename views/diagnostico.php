<?php require __DIR__.'/layout_top.php'; ?>
<div class="panel">
  <div class="panel-header"><h2>Diagnóstico do ambiente</h2><span class="text-muted">Verificações úteis para XAMPP e integração</span></div>
  <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Item</th><th>Status</th><th>Detalhe</th><th>Ação recomendada</th></tr></thead><tbody>
  <?php foreach($checks as $c): ?><tr><td><?=e($c['nome'])?></td><td><span class="badge-status <?=$c['ok']?'status-sucesso':'status-erro'?>"><?=$c['ok']?'OK':'Atenção'?></span></td><td><?=e($c['detalhe'])?></td><td><?=e($c['acao'])?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</div>
<div class="alert alert-info mt-3"><b>Dica:</b> quando ocorrer erro, copie o <b>Trace ID</b> exibido na tela/API e pesquise na página Auditoria.</div>

<div class="panel mt-3">
  <div class="panel-header"><h2>Histórico de diagnóstico das APIs</h2><span class="text-muted">Últimos testes Tiny/VSM registrados</span></div>
  <div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Data</th><th>Sistema</th><th>Status</th><th>HTTP</th><th>Tempo</th><th>Mensagem</th><th>Trace</th></tr></thead><tbody>
  <?php foreach(($historicoDiagnostico ?? []) as $d): ?><tr><td><?=e($d['criado_em'])?></td><td><?=e(strtoupper($d['sistema']))?></td><td><span class="badge <?= $d['status']==='online'?'bg-success':($d['status']==='erro'?'bg-danger':'bg-warning text-dark') ?>"><?=e($d['status'])?></span></td><td><?=e($d['http_code'] ?? '-')?></td><td><?=e($d['tempo_ms'] ? $d['tempo_ms'].'ms' : '-')?></td><td><?=e($d['mensagem'])?></td><td><code><?=e($d['trace_id'])?></code></td></tr><?php endforeach; ?>
  <?php if(empty($historicoDiagnostico)): ?><tr><td colspan="7" class="text-muted">Nenhum diagnóstico registrado ainda.</td></tr><?php endif; ?>
  </tbody></table></div>
</div>

<?php require __DIR__.'/layout_bottom.php'; ?>
