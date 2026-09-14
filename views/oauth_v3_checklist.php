<?php require __DIR__.'/layout_top.php'; ?>
<div class="panel">
  <div class="panel-header"><div><h2>Checklist OAuth Tiny V3</h2><span class="text-muted">Passo a passo para evitar invalid_scope, redirect_uri inválida e ativação indevida da V3</span></div><span class="badge bg-primary">Score <?=e($score)?>%</span></div>
  <div class="p-4">
    <div class="alert alert-info"><b>Regra importante:</b> deixe <b>Escopos OAuth</b> vazio, salvo se o Tiny/Olist informar explicitamente os scopes oficiais do seu aplicativo. O erro <code>invalid_scope</code> acontece quando o Hub envia permissões que o Tiny não reconhece.</div>
    <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Etapa</th><th>Status</th><th>Ação recomendada</th></tr></thead><tbody><?php foreach($passos as $p): ?><tr><td><b><?=e($p['titulo'])?></b></td><td><?= $p['ok'] ? '<span class="badge bg-success">OK</span>' : '<span class="badge bg-warning text-dark">Pendente</span>' ?></td><td><?=e($p['acao'])?></td></tr><?php endforeach; ?></tbody></table></div>
    <div class="d-flex gap-2 flex-wrap"><a class="btn btn-primary" href="index.php?page=tiny-v3-ficha">Abrir Ficha Tiny V3</a><a class="btn btn-outline-primary" href="index.php?page=homologacao">Checklist Homologação</a><a class="btn btn-outline-secondary" href="index.php?page=validar-banco">Validar Banco</a></div>
  </div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
