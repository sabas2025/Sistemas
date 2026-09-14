<?php require __DIR__.'/layout_top.php'; ?>
<div class="panel mb-4">
  <div class="panel-header">
    <div><h2>XML / NF-e</h2><span class="text-muted">Fluxo operacional VSM → HUB → Tiny: XML recebido, chave NF-e, vínculo com pedido, retorno Tiny e reprocessamento.</span></div>
    <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-sm btn-outline-primary" href="index.php?page=fiscal"><i class="bi bi-arrow-clockwise"></i> Atualizar</a>
      <a class="btn btn-sm btn-outline-success" href="index.php?page=fiscal-dashboard">📊 Dashboard XML/NF-e</a>
      <a class="btn btn-sm btn-outline-dark" href="index.php?page=fiscal-xml">📄 XML</a>
      <a class="btn btn-sm btn-outline-warning" href="index.php?page=fiscal-reconciliacao">🧠 Reconciliação</a>
      <a class="btn btn-sm btn-outline-info" href="index.php?page=fiscal-health">🟢 Saúde</a>
      <?php if(App::isLocal()): ?><form method="post" action="index.php?page=atualizar-v43-fiscal-dashboard-install"><?= Csrf::input() ?><button class="btn btn-sm btn-primary"><i class="bi bi-database-check"></i> Aplicar estrutura fiscal</button></form><?php endif; ?>
    </div>
  </div>
  <?php if(!empty($erroFiscal)): ?><div class="alert alert-warning m-3">Módulo XML/NF-e ainda não instalado ou incompleto: <?=e($erroFiscal)?></div><?php endif; ?>
  <div class="row g-3 p-3">
    <div class="col-md-3"><div class="kpi"><div class="label">NF-e registradas</div><div class="value"><?=e($resumoFiscal['total'] ?? 0)?></div></div></div>
    <div class="col-md-3"><div class="kpi"><div class="label">Autorizadas</div><div class="value"><?=e($resumoFiscal['autorizadas'] ?? 0)?></div></div></div>
    <div class="col-md-3"><div class="kpi"><div class="label">Pendentes VSM</div><div class="value"><?=e($resumoFiscal['pendentes'] ?? 0)?></div></div></div>
    <div class="col-md-3"><div class="kpi"><div class="label">Erros</div><div class="value"><?=e($resumoFiscal['erros'] ?? 0)?></div></div></div>
  </div>
</div>
<div class="panel mb-4">
  <div class="panel-header"><h2>📊 Central XML/NF-e Enterprise</h2><span class="text-muted">Sem SNGPC/ANVISA: foco leve em NF-e/XML Tiny ↔ VSM.</span></div>
  <div class="row g-3 p-3">
    <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><b>Semáforo XML/NF-e</b><p class="fs-3 mb-0"><?=($dashboardFiscal['status']??'ok')==='ok'?'🟢':(($dashboardFiscal['status']??'')==='atencao'?'🟡':'🔴')?></p><small class="text-muted">Baseado em pendências, XML e erros.</small></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><b>XML sem envio</b><p class="fs-3 mb-0"><?=e($reconciliacaoFiscal['xml_sem_envio']??0)?></p><small class="text-muted">XML salvo sem retorno Tiny.</small></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><b>Divergências</b><p class="fs-3 mb-0"><?=e(count($reconciliacaoFiscal['divergencias']??[]))?></p><small class="text-muted">Acompanhe em Reconciliação XML/NF-e.</small></div></div></div>
  </div>
</div>
<div class="panel mb-4">
  <div class="panel-header"><h2>Fluxo fiscal recomendado</h2></div>
  <div class="p-3">
    <div class="row g-3">
      <div class="col-md"><div class="card border-0 shadow-sm h-100"><div class="card-body"><b>1. Tiny</b><p class="text-muted mb-0">Gera e autoriza NF-e.</p></div></div></div>
      <div class="col-md"><div class="card border-0 shadow-sm h-100"><div class="card-body"><b>2. Hub</b><p class="text-muted mb-0">Salva chave, XML, status e Trace ID.</p></div></div></div>
      <div class="col-md"><div class="card border-0 shadow-sm h-100"><div class="card-body"><b>3. VSM</b><p class="text-muted mb-0">Recebe NF-e autorizada, sem criar produto novo.</p></div></div></div>
      <div class="col-md"><div class="card border-0 shadow-sm h-100"><div class="card-body"><b>4. Auditoria</b><p class="text-muted mb-0">Registra retorno, erro e reenvio.</p></div></div></div>
    </div>
  </div>
</div>
<div class="panel mb-4"><div class="panel-header"><h2>Checklist XML/NF-e</h2></div><div class="table-responsive"><table class="table"><thead><tr><th>Tabela</th><th>Status</th></tr></thead><tbody><?php foreach(($resumoFiscal['tabelas'] ?? []) as $t=>$st): ?><tr><td><?=e($t)?></td><td><?=str_starts_with($st,'ok')?'<span class="badge-status status-sucesso">ok</span>':'<span class="badge-status status-erro">erro</span>'?> <?=e($st)?></td></tr><?php endforeach; ?></tbody></table></div></div>
<div class="panel mb-4"><div class="panel-header"><h2>Fila NF-e → VSM</h2><span class="text-muted">Use reenvio quando a VSM retornar erro temporário.</span></div><div class="table-responsive"><table class="table table-hover"><thead><tr><th>ID</th><th>NF-e</th><th>Chave</th><th>Destino</th><th>Status</th><th>Tentativas</th><th>Erro</th><th>Ação</th></tr></thead><tbody><?php foreach(($nfeIntegracoes ?? []) as $i): ?><tr><td><?=e($i['id'])?></td><td><?=e(($i['numero']??'-').'/'.($i['serie']??''))?></td><td><?=e($i['chave_acesso']??'-')?></td><td><?=e($i['destino']??'vsm')?></td><td><span class="badge-status status-<?=($i['status']==='erro'?'erro':(($i['status']==='pendente')?'pendente':'sucesso'))?>"><?=e($i['status'])?></span></td><td><?=e($i['tentativas']??0)?></td><td><?=e($i['ultimo_erro']??'-')?></td><td><form method="post" action="index.php?page=fiscal-reenviar" class="d-inline"><?=Csrf::input()?><input type="hidden" name="id" value="<?=e($i['id'])?>"><button class="btn btn-sm btn-outline-primary">Reenviar</button></form></td></tr><?php endforeach; ?><?php if(empty($nfeIntegracoes)): ?><tr><td colspan="8" class="text-muted">Nenhum envio fiscal pendente.</td></tr><?php endif; ?></tbody></table></div></div>
<div class="panel"><div class="panel-header"><h2>Últimas NF-e</h2><form class="d-flex gap-2" method="get"><input type="hidden" name="page" value="fiscal"><select name="status" class="form-select form-select-sm"><option value="">Todos status</option><?php foreach(['autorizada','pendente','erro','cancelada'] as $st): ?><option value="<?=$st?>" <?=($_GET['status']??'')===$st?'selected':''?>><?=$st?></option><?php endforeach; ?></select><button class="btn btn-sm btn-outline-primary">Filtrar</button></form></div><div class="table-responsive"><table class="table table-hover"><thead><tr><th>ID</th><th>Número</th><th>Chave</th><th>Status</th><th>Valor</th><th>Trace</th></tr></thead><tbody><?php foreach($notas as $n): ?><tr><td><?=e($n['id'])?></td><td><?=e(($n['numero']??'-').'/'.($n['serie']??''))?></td><td><?=e($n['chave_acesso']??'-')?></td><td><span class="badge-status status-<?=($n['status']==='erro'?'erro':'sucesso')?>"><?=e($n['status'])?></span></td><td>R$ <?=number_format((float)($n['valor_total']??0),2,',','.')?></td><td><?=e($n['trace_id']??'-')?></td></tr><?php endforeach; ?><?php if(empty($notas)): ?><tr><td colspan="6" class="text-muted">Nenhuma NF-e registrada ainda.</td></tr><?php endif; ?></tbody></table></div></div>
<?php require __DIR__.'/layout_bottom.php'; ?>
