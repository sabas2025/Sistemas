<?php require __DIR__.'/layout_top.php'; ?>
<?php $geral=$operacao['geral']??'atencao'; $cl=$geral==='online'?'status-sucesso':($geral==='erro'?'status-erro':'status-pendente'); $dot=$geral==='online'?'🟢':($geral==='erro'?'🔴':'🟡'); ?>
<div class="panel mb-4 op-hero">
  <div class="panel-header"><div><h2>🖥 Centro de Operações</h2><span class="text-muted">Painel NOC simplificado para Tiny, VSM, workers, filas, XML/NF-e, divergências e estoque.</span></div><span class="badge-status <?=$cl?> fs-6"><?=$dot?> <?=e($geral)?></span></div>
  <div class="row g-3 p-3">
    <?php foreach(($operacao['integracoes'] ?? []) as $i): $st=$i['status']; $ic=$st==='online'?'status-sucesso':($st==='erro'?'status-erro':'status-pendente'); $id=$st==='online'?'🟢':($st==='erro'?'🔴':'🟡'); ?>
      <div class="col-md-4"><div class="health-card h-100"><div class="health-icon"><i class="bi bi-broadcast-pin"></i></div><div><b><?=e($i['nome'])?></b><div class="small mt-1"><span class="badge-status <?=$ic?>"><?=$id?> <?=e($i['modo'])?></span></div><div class="small text-muted mt-1"><?=e($i['detalhe'])?></div></div></div></div>
    <?php endforeach; ?>
  </div>
</div>

<?php if(!empty($scores)): ?>
<div class="row g-3 mb-4">
  <?php foreach($scores as $nome=>$score): $c=$score>=90?'success':($score>=70?'warning':'danger'); ?>
    <div class="col-md-6 col-xl-2"><div class="kpi h-100"><div class="label"><?=e($nome)?></div><div class="value text-<?=$c?>"><?=e($score)?>%</div><div class="progress mt-2" style="height:8px"><div class="progress-bar bg-<?=$c?>" style="width:<?=$score?>%"></div></div></div></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <?php $cards=[['Pedidos recebidos',$pedidoCicloResumo['recebidos_tiny']??0,'bi-inbox'],['Pedidos enviados VSM',$pedidoCicloResumo['enviados_vsm']??0,'bi-send'],['Aguardando XML',$pedidoCicloResumo['aguardando_xml']??0,'bi-filetype-xml'],['Pedidos com erro',$pedidoCicloResumo['erros']??0,'bi-exclamation-triangle'],['Estoque pendente',$estoqueResumo['pendentes']??0,'bi-box'],['XML com erro',$fiscalResumo['xml_erros']??0,'bi-receipt-cutoff']]; foreach($cards as $c): ?>
    <div class="col-md-6 col-xl-2"><div class="kpi h-100"><div class="icon"><i class="bi <?=e($c[2])?>"></i></div><div class="label"><?=e($c[0])?></div><div class="value"><?=e($c[1])?></div></div></div>
  <?php endforeach; ?>
</div>
<div class="panel mb-4"><div class="panel-header"><h2>⚙️ Workers</h2></div><div class="row g-3 p-3"><?php foreach($workerCards as $w): $ok=$w['status']==='online'; ?><div class="col-md-6 col-xl-3"><div class="health-card"><div class="health-icon"><i class="bi <?=$ok?'bi-check-circle':'bi-x-octagon'?>"></i></div><div><b><?=$ok?'🟢':'🔴'?> <?=e($w['titulo'])?></b><div class="small text-muted"><?=e($w['arquivo'])?></div></div></div></div><?php endforeach; ?></div></div>
<div class="panel"><div class="panel-header"><div><h2>🚨 Alertas</h2><span class="text-muted">Itens que precisam de ação.</span></div><div class="d-flex gap-2"><a class="btn btn-sm btn-outline-primary" href="index.php?page=dashboard-executivo">Dashboard Executivo</a><a class="btn btn-sm btn-outline-warning" href="index.php?page=monitor-divergencias">Divergências</a><a class="btn btn-sm btn-outline-danger" href="index.php?page=alertas-operacionais">Ver alertas</a></div></div><div class="p-3"><?php if(empty($operacao['alertas'])): ?><div class="alert alert-success mb-0">🟢 Operação sem alertas críticos.</div><?php else: foreach($operacao['alertas'] as $a): ?><div class="border-bottom py-2"><b><?=($a['nivel']==='erro'?'🔴':'🟡')?> <?=e($a['titulo'])?></b><div class="small text-muted"><?=e($a['mensagem'])?></div></div><?php endforeach; endif; ?></div></div>
<?php require __DIR__.'/layout_bottom.php'; ?>
