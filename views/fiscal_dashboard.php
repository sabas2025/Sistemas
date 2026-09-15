<?php require __DIR__.'/layout_top.php'; ?>
<div class="panel mb-4">
  <div class="panel-header"><div><h2>📊 Dashboard XML/NF-e</h2><span class="text-muted">Visão executiva do fluxo XML/NF-e VSM → HUB → Tiny.</span></div><a class="btn btn-sm btn-outline-primary" href="index.php?page=fiscal">Voltar XML/NF-e</a></div>
  <div class="row g-3 p-3">
    <div class="col-md-2"><div class="kpi"><div class="label">NF-e recebidas</div><div class="value"><?=e($dashboard['recebidas']??0)?></div></div></div>
    <div class="col-md-2"><div class="kpi"><div class="label">Pendentes</div><div class="value"><?=e($dashboard['pendentes']??0)?></div></div></div>
    <div class="col-md-2"><div class="kpi"><div class="label">XML com erro</div><div class="value"><?=e($dashboard['xml_erro']??0)?></div></div></div>
    <div class="col-md-2"><div class="kpi"><div class="label">Reenviadas</div><div class="value"><?=e($dashboard['reenviadas']??0)?></div></div></div>
    <div class="col-md-2"><div class="kpi"><div class="label">Concluídas</div><div class="value"><?=e($dashboard['concluidas']??0)?></div></div></div>
    <div class="col-md-2"><div class="kpi"><div class="label">Semáforo</div><div class="value"><?=($dashboard['status']??'ok')==='ok'?'🟢':(($dashboard['status']??'')==='atencao'?'🟡':'🔴')?></div></div></div>
  </div>
</div>
<div class="panel"><div class="panel-header"><h2>🧠 Reconciliação XML/NF-e Rápida</h2></div><div class="table-responsive"><table class="table"><tr><th>NF-e sem XML</th><td><?=e($reconciliacao['nf_sem_xml']??0)?></td></tr><tr><th>XML sem envio</th><td><?=e($reconciliacao['xml_sem_envio']??0)?></td></tr><tr><th>Erros de integração</th><td><?=e($reconciliacao['erro_integracao']??0)?></td></tr></table></div></div>
<?php require __DIR__.'/layout_bottom.php'; ?>
