<?php require __DIR__.'/layout_top.php'; ?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
  <div><h2 class="h4 mb-1">Auditoria de Código</h2><p class="text-muted mb-0">Detector simples de serviços e telas possivelmente órfãos. Não exclua nada sem revisão manual.</p></div>
  <span class="badge bg-info text-dark">Análise local</span>
</div>
<div class="alert alert-warning"><b>Atenção:</b> classes chamadas dinamicamente podem aparecer como órfãs. Use este painel para reduzir poluição com segurança, não como apagador automático.</div>
<div class="row g-3 mb-3">
  <?php foreach(($codigoAuditoria['totais'] ?? []) as $k=>$v): ?><div class="col-md-3"><div class="kpi"><div class="label"><?=e($k)?></div><div class="value"><?=e($v)?></div></div></div><?php endforeach; ?>
</div>
<div class="panel mb-3"><div class="panel-header"><h2>Services possivelmente não usados</h2></div><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Classe</th><th>Arquivo</th><th>Ocorrências</th></tr></thead><tbody><?php foreach(($codigoAuditoria['possiveis_orfaos'] ?? []) as $r): ?><tr><td><code><?=e($r['nome'])?></code></td><td><?=e($r['arquivo'])?></td><td><?=e($r['ocorrencias'])?></td></tr><?php endforeach; if(empty($codigoAuditoria['possiveis_orfaos'])): ?><tr><td colspan="3" class="text-muted">Nenhum service órfão evidente.</td></tr><?php endif; ?></tbody></table></div></div>
<div class="panel"><div class="panel-header"><h2>Views sem rota aparente</h2></div><div class="table-responsive"><table class="table table-sm"><thead><tr><th>View</th><th>Arquivo</th><th>Ação recomendada</th></tr></thead><tbody><?php foreach(($codigoAuditoria['views_sem_rota_aparente'] ?? []) as $r): ?><tr><td><code><?=e($r['nome'])?></code></td><td><?=e($r['arquivo'])?></td><td>Conferir se há include dinâmico antes de remover.</td></tr><?php endforeach; if(empty($codigoAuditoria['views_sem_rota_aparente'])): ?><tr><td colspan="3" class="text-muted">Nenhuma view sem rota aparente.</td></tr><?php endif; ?></tbody></table></div></div>
<?php require __DIR__.'/layout_bottom.php'; ?>
