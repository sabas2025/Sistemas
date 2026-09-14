<?php require __DIR__.'/layout_top.php'; ?>
<?php $status=$hardening['status'] ?? 'alerta'; $badge=$status==='ok'?'success':($status==='erro'?'danger':'warning'); ?>
<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="kpi"><span>Versão</span><b><?=e($hardening['version']['version'] ?? SystemVersionService::VERSION)?></b><small><?=e($hardening['version']['codename'] ?? '')?></small></div></div>
  <div class="col-md-3"><div class="kpi"><span>Score final</span><b><?=e((string)($hardening['score'] ?? 0))?>%</b><small>Checklist alta/média</small></div></div>
  <div class="col-md-3"><div class="kpi"><span>Status</span><b class="text-<?=$badge?>"><?=e(strtoupper($status))?></b><small>Produção comercial</small></div></div>
  <div class="col-md-3"><div class="kpi"><span>Conectores</span><b><?=e((string)($connectorSummary['total'] ?? 0))?></b><small><?=e(json_encode($connectorSummary['status'] ?? [], JSON_UNESCAPED_UNICODE))?></small></div></div>
</div>

<div class="panel mb-3">
  <div class="panel-header"><h2><i class="bi bi-shield-check"></i> Checklist final — Prioridade alta e média</h2><span class="badge text-bg-<?=$badge?>"><?=e($status)?></span></div>
  <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Grupo</th><th>Item</th><th>Status</th><th>Detalhe</th><th>Ação recomendada</th></tr></thead><tbody>
  <?php foreach(($hardening['items'] ?? []) as $it): $b=$it['status']==='ok'?'success':($it['status']==='erro'?'danger':'warning'); ?>
    <tr><td><?=e($it['grupo'])?></td><td><b><?=e($it['item'])?></b></td><td><span class="badge text-bg-<?=$b?>"><?=e($it['status'])?></span></td><td><?=e($it['detalhe'])?></td><td><?=e($it['acao'])?></td></tr>
  <?php endforeach; ?>
  </tbody></table></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-4"><div class="panel h-100"><div class="panel-header"><h2><i class="bi bi-key"></i> Licença remota</h2></div><p><b>Status:</b> <?=e($licenseRemote['status'] ?? '')?></p><p><?=e($licenseRemote['mensagem'] ?? '')?></p></div></div>
  <div class="col-lg-4"><div class="panel h-100"><div class="panel-header"><h2><i class="bi bi-credit-card"></i> Cobrança</h2></div><p><b>Status:</b> <?=e($billing['status'] ?? '')?></p><p><?=e($billing['mensagem'] ?? '')?></p></div></div>
  <div class="col-lg-4"><div class="panel h-100"><div class="panel-header"><h2><i class="bi bi-git"></i> CI/CD</h2></div><p><b>Status:</b> <?=e($cicd['status'] ?? '')?></p><p><?=e($cicd['mensagem'] ?? '')?></p></div></div>
</div>

<div class="panel mb-3">
  <div class="panel-header"><h2><i class="bi bi-building"></i> Auditoria multiempresa/multifilial</h2><span class="badge text-bg-<?=($tenantAudit['status'] ?? '')==='ok'?'success':'warning'?>"><?=e($tenantAudit['status'] ?? '')?></span></div>
  <p><?=e($tenantAudit['summary'] ?? '')?></p>
  <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Tabela</th><th>Existe</th><th>empresa_id</th><th>Status</th></tr></thead><tbody>
  <?php foreach(($tenantAudit['items'] ?? []) as $row): ?><tr><td><code><?=e($row['table'])?></code></td><td><?=!empty($row['exists'])?'sim':'não'?></td><td><?=!empty($row['empresa_id'])?'sim':'não'?></td><td><?=e($row['status'])?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</div>

<div class="panel">
  <div class="panel-header"><h2><i class="bi bi-plug"></i> Matriz operacional de conectores</h2><span><?=e((string)($connectorSummary['total'] ?? 0))?> conectores</span></div>
  <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Código</th><th>Nome</th><th>Status</th><th>Operações</th><th>Riscos principais</th></tr></thead><tbody>
  <?php foreach($connectorMatrix as $c): ?><tr><td><code><?=e($c['codigo'])?></code></td><td><?=e($c['nome'])?></td><td><?=e($c['status'])?></td><td><?=e(json_encode($c['operations'], JSON_UNESCAPED_UNICODE))?></td><td><?=e(implode('; ', $c['risks'] ?? []))?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
