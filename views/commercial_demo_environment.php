<?php require __DIR__.'/layout_top.php'; ?>
<h2><i class="bi bi-window-stack"></i> Ambiente Demo Sem Dados Reais</h2>
<div class="d-flex justify-content-between align-items-start mb-2"><p class="text-muted mb-0">Modelo para demonstração comercial sem clientes, pedidos, tokens, notas fiscais ou dados sensíveis reais.</p><form method="post" action="index.php?page=ambiente-demo-reset"><?=Csrf::field()?><button class="btn btn-outline-danger btn-sm">Resetar demo sem dados reais</button></form></div>
<div class="alert alert-success"><b>Regra de ouro:</b> demo deve usar dados fictícios, tokens falsos, CNPJ fictício/sanitizado e conexões externas desativadas ou apontadas para sandbox.</div>
<div class="row g-3 mb-3">
<?php foreach($values as $v): ?><div class="col-md-4"><div class="card h-100"><div class="card-body"><h5><?=e($v['titulo'])?></h5><p><?=e($v['texto'])?></p></div></div></div><?php endforeach; ?>
</div>
<div class="card mb-3"><div class="card-header"><strong>Checklist do ambiente demo</strong></div><div class="card-body"><ul>
<li>Base separada da produção.</li><li>Usuário demo com permissões limitadas.</li><li>2FA opcional para demo guiada, obrigatório para admin real.</li><li>Webhooks externos desligados ou mockados.</li><li>Backups demo separados e sem dados reais.</li><li>Banner visível: “Ambiente demonstrativo sem valor fiscal”.</li></ul></div></div>
<div class="card"><div class="card-header"><b>Ambientes demo cadastrados</b></div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Nome</th><th>URL</th><th>Status</th><th>Dados reais?</th></tr></thead><tbody><?php foreach($demos as $d): ?><tr><td><?=e($d['nome'])?></td><td><?=e($d['url'] ?? '-')?></td><td><?=e($d['status'])?></td><td><?=$d['usa_dados_reais']?'Sim':'Não'?></td></tr><?php endforeach; if(empty($demos)): ?><tr><td colspan="4" class="text-muted">Nenhum ambiente demo cadastrado.</td></tr><?php endif; ?></tbody></table></div></div>
<?php require __DIR__.'/layout_bottom.php'; ?>
