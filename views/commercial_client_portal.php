<?php require __DIR__.'/layout_top.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div><h2>Portal Self-Service do Cliente</h2><p class="text-muted mb-0">Resumo seguro de licença, faturas, conectores e chamados sem expor tokens.</p></div>
  <a class="btn btn-outline-primary" href="index.php?page=security-assisted-test">Teste Segurança Assistido</a>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-4"><div class="card"><div class="card-body"><small>Status da licença</small><h4><?=e($licenseStatus['status'] ?? '-')?></h4><p class="mb-0 text-muted"><?=e($licenseStatus['mensagem'] ?? '')?></p></div></div></div>
  <div class="col-md-4"><div class="card"><div class="card-body"><small>Licenças cadastradas</small><h4><?=count($licenses)?></h4><a href="index.php?page=licencas-clientes">Gerenciar licenças</a></div></div></div>
  <div class="col-md-4"><div class="card"><div class="card-body"><small>Conectores catalogados</small><h4><?=count($connectors)?></h4><a href="index.php?page=conectores-plugaveis">Ver conectores</a></div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-6"><div class="card"><div class="card-header"><b>Faturas recentes</b></div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Descrição</th><th>Status</th><th>Vencimento</th><th>Valor</th></tr></thead><tbody><?php foreach(array_slice($invoices,0,10) as $f): ?><tr><td><?=e($f['descricao'])?></td><td><?=e($f['status'])?></td><td><?=e($f['vencimento'] ?? '-')?></td><td>R$ <?=number_format(((int)$f['valor_centavos'])/100,2,',','.')?></td></tr><?php endforeach; if(empty($invoices)): ?><tr><td colspan="4" class="text-muted">Nenhuma fatura.</td></tr><?php endif; ?></tbody></table></div></div></div>
  <div class="col-lg-6"><div class="card"><div class="card-header"><b>Chamados/SLA</b></div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Título</th><th>Prioridade</th><th>Status</th><th>Prazo resolução</th></tr></thead><tbody><?php foreach(array_slice($tickets,0,10) as $t): ?><tr><td><?=e($t['titulo'])?></td><td><?=e($t['prioridade'])?></td><td><?=e($t['status'])?></td><td><?=e($t['prazo_resolucao_em'] ?? '-')?></td></tr><?php endforeach; if(empty($tickets)): ?><tr><td colspan="4" class="text-muted">Nenhum chamado.</td></tr><?php endif; ?></tbody></table></div></div></div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
