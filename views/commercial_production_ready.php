<?php require __DIR__.'/layout_top.php'; ?>
<?php $status = $readiness['status'] ?? 'alerta'; $badge = $status==='ok'?'success':($status==='erro'?'danger':'warning'); ?>
<div class="row g-3 mb-3">
  <div class="col-md-4"><div class="kpi"><span>Versão</span><b><?=e($readiness['versao']['version'] ?? 'V104.16')?></b><small><?=e($readiness['versao']['codename'] ?? '')?></small></div></div>
  <div class="col-md-4"><div class="kpi"><span>Status comercial</span><b class="text-<?=$badge?>"><?=e(strtoupper($status))?></b><small>Prontidão produção comercial</small></div></div>
  <div class="col-md-4"><div class="kpi"><span>Licença</span><b><?=e(strtoupper($license['status'] ?? 'indefinido'))?></b><small><?=e($license['mensagem'] ?? '')?></small></div></div>
</div>
<div class="panel mb-3">
  <div class="panel-header"><h2><i class="bi bi-clipboard-check"></i> Checklist produção comercial</h2><span class="badge text-bg-<?=$badge?>"><?=e($status)?></span></div>
  <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Grupo</th><th>Item</th><th>Status</th><th>Detalhe</th><th>Ação recomendada</th></tr></thead><tbody>
    <?php foreach(($readiness['items'] ?? []) as $it): $b=$it['status']==='ok'?'success':($it['status']==='erro'?'danger':'warning'); ?>
    <tr><td><?=e($it['grupo'])?></td><td><b><?=e($it['nome'])?></b></td><td><span class="badge text-bg-<?=$b?>"><?=e($it['status'])?></span></td><td><?=e($it['detalhe'])?></td><td><?=e($it['acao'])?></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
</div>
<div class="panel">
  <div class="panel-header"><h2><i class="bi bi-plug"></i> Conectores plugáveis reais</h2><span><?=count($connectors)?> catalogados</span></div>
  <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Código</th><th>Nome</th><th>Categoria</th><th>Status</th><th>Capacidades</th><th>Requisitos</th></tr></thead><tbody>
  <?php foreach($connectors as $c): ?><tr><td><code><?=e($c['codigo'])?></code></td><td><?=e($c['nome'])?></td><td><?=e($c['categoria'])?></td><td><?=e($c['status'])?></td><td><?=e(implode(', ', $c['capabilities'] ?? []))?></td><td><?=e(implode('; ', $c['requirements'] ?? []))?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
