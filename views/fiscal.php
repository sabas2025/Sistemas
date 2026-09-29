<?php require __DIR__.'/layout_top.php'; ?>
<?php
  $podeCiclo = class_exists('PermissionService') && PermissionService::can('pedidos','visualizar');
  $erros = (int)($resumo['erros'] ?? 0);
  $aguard = (int)($resumo['aguardando_tiny'] ?? 0);
  $semaforo = $erros>0 ? '🔴' : ($aguard>0 ? '🟡' : '🟢');
  $cicloUrl = 'index.php?page=pedido-ciclo-vida';
  if(!function_exists('fiscal_detalhe_url')){ function fiscal_detalhe_url($pedidoHubId){ return 'index.php?page=pedido-ciclo-detalhe&id='.(int)$pedidoHubId; } }
?>
<div class="panel mb-4">
  <div class="panel-header">
    <div><h2>XML / NF-e</h2><span class="text-muted">Fluxo operacional VSM → HUB → Tiny: pedido do Tiny grava no Hub, a NF-e é emitida na VSM e retorna como XML, o Hub valida e devolve ao Tiny. Dados reais do ciclo de vida do pedido.</span></div>
    <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-sm btn-outline-primary" href="index.php?page=fiscal"><i class="bi bi-arrow-clockwise"></i> Atualizar</a>
      <?php if($podeCiclo): ?><a class="btn btn-sm btn-outline-success" href="<?=e($cicloUrl)?>"><i class="bi bi-diagram-3"></i> Ciclo de Vida do Pedido</a><?php endif; ?>
    </div>
  </div>
  <?php if(!empty($erroFiscal)): ?><div class="alert alert-warning m-3">Módulo XML/NF-e ainda não instalado ou incompleto: <?=e($erroFiscal)?></div><?php endif; ?>
  <div class="row g-3 p-3">
    <div class="col-md-3"><div class="kpi"><div class="label">XML/NF-e recebidos</div><div class="value"><?=e($resumo['xml_recebidos'] ?? 0)?></div></div></div>
    <div class="col-md-3"><div class="kpi"><div class="label">Validados</div><div class="value"><?=e($resumo['validados'] ?? 0)?></div></div></div>
    <div class="col-md-3"><div class="kpi"><div class="label">Aguardando envio ao Tiny</div><div class="value"><?=e($resumo['aguardando_tiny'] ?? 0)?></div></div></div>
    <div class="col-md-3"><div class="kpi"><div class="label">Erros</div><div class="value"><?=e($resumo['erros'] ?? 0)?></div></div></div>
  </div>
</div>
<div class="panel mb-4">
  <div class="panel-header"><h2>📊 Central XML/NF-e Enterprise</h2><span class="text-muted">Foco leve em NF-e/XML no ciclo VSM → HUB → Tiny.</span></div>
  <div class="row g-3 p-3">
    <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><b>Semáforo XML/NF-e</b><p class="fs-3 mb-0"><?=$semaforo?></p><small class="text-muted">Verde: sem erros e nada pendente. Amarelo: há XML aguardando o Tiny. Vermelho: há erros.</small></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><b>Aguardando envio ao Tiny</b><p class="fs-3 mb-0"><?=e($resumo['aguardando_tiny'] ?? 0)?></p><small class="text-muted">XML validado, ainda não devolvido ao Tiny.</small></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><b>Enviados ao Tiny</b><p class="fs-3 mb-0"><?=e($resumo['enviados_tiny'] ?? 0)?></p><small class="text-muted">XML/NF-e já devolvido ao Tiny.</small></div></div></div>
  </div>
</div>
<div class="panel mb-4">
  <div class="panel-header"><h2>Fluxo fiscal (VSM → HUB → Tiny)</h2></div>
  <div class="p-3">
    <div class="row g-3">
      <div class="col-md"><div class="card border-0 shadow-sm h-100"><div class="card-body"><b>1. Tiny</b><p class="text-muted mb-0">Gera o pedido e o envia ao Hub.</p></div></div></div>
      <div class="col-md"><div class="card border-0 shadow-sm h-100"><div class="card-body"><b>2. Hub</b><p class="text-muted mb-0">Grava o pedido e aguarda a NF-e; valida XML, chave e Trace ID.</p></div></div></div>
      <div class="col-md"><div class="card border-0 shadow-sm h-100"><div class="card-body"><b>3. VSM</b><p class="text-muted mb-0">Emite a NF-e do pedido e retorna o XML ao Hub.</p></div></div></div>
      <div class="col-md"><div class="card border-0 shadow-sm h-100"><div class="card-body"><b>4. Tiny</b><p class="text-muted mb-0">Recebe o XML/NF-e do Hub e finaliza o pedido.</p></div></div></div>
    </div>
  </div>
</div>
<div class="panel mb-4"><div class="panel-header"><h2>Checklist do ciclo XML/NF-e</h2></div><div class="table-responsive"><table class="table"><thead><tr><th>Tabela</th><th>Status</th></tr></thead><tbody><?php foreach(($resumo['tabelas'] ?? []) as $t=>$st): ?><tr><td><?=e($t)?></td><td><?=str_starts_with((string)$st,'ok')?'<span class="badge-status status-sucesso">ok</span>':'<span class="badge-status status-erro">'.e($st).'</span>'?></td></tr><?php endforeach; ?><?php if(empty($resumo['tabelas'])): ?><tr><td colspan="2" class="text-muted">Sem informação de schema.</td></tr><?php endif; ?></tbody></table></div></div>
<div class="panel mb-4"><div class="panel-header"><h2>XML/NF-e aguardando envio ao Tiny</h2><span class="text-muted">A ação de reenvio fica no detalhe do Ciclo de Vida do Pedido.</span></div><div class="table-responsive"><table class="table table-hover"><thead><tr><th>XML</th><th>Pedido</th><th>Chave NF-e</th><th>Nº/Série</th><th>Status</th><th>Ação</th></tr></thead><tbody><?php foreach(($aguardando ?? []) as $i): ?><tr><td><?=e($i['id'])?></td><td><?=e($i['numero_pedido'] ?? $i['pedido_tiny_id'] ?? '-')?></td><td><?=e($i['chave_nfe'] ?? '-')?></td><td><?=e(($i['numero_nfe'] ?? '-').'/'.($i['serie'] ?? ''))?></td><td><span class="badge-status status-pendente"><?=e($i['status_xml'] ?? 'pendente')?></span></td><td><?php if($podeCiclo): ?><a class="btn btn-sm btn-outline-primary" href="<?=e(fiscal_detalhe_url($i['pedido_hub_id'] ?? 0))?>">Ver no Ciclo</a><?php else: ?><span class="text-muted">—</span><?php endif; ?></td></tr><?php endforeach; ?><?php if(empty($aguardando)): ?><tr><td colspan="6" class="text-muted">Nenhum XML/NF-e aguardando envio ao Tiny.</td></tr><?php endif; ?></tbody></table></div></div>
<div class="panel"><div class="panel-header"><h2>Últimas NF-e</h2></div><div class="table-responsive"><table class="table table-hover"><thead><tr><th>XML</th><th>Pedido</th><th>Chave</th><th>Status</th><th>Enviado ao Tiny</th><th>Trace</th></tr></thead><tbody><?php foreach(($ultimas ?? []) as $n): ?><tr><td><?=e($n['id'])?></td><td><?php if($podeCiclo): ?><a href="<?=e(fiscal_detalhe_url($n['pedido_hub_id'] ?? 0))?>"><?=e($n['numero_pedido'] ?? $n['pedido_tiny_id'] ?? '-')?></a><?php else: ?><?=e($n['numero_pedido'] ?? $n['pedido_tiny_id'] ?? '-')?><?php endif; ?></td><td><?=e($n['chave_nfe'] ?? '-')?></td><td><span class="badge-status status-<?=(($n['status_xml'] ?? '')==='erro_xml' || ($n['status_xml'] ?? '')==='erro_envio_tiny')?'erro':(((int)($n['validado'] ?? 0)===1)?'sucesso':'pendente')?>"><?=e($n['status_xml'] ?? '-')?></span></td><td><?=e($n['enviado_tiny_em'] ?? '—')?></td><td><?=e($n['trace_id'] ?? '-')?></td></tr><?php endforeach; ?><?php if(empty($ultimas)): ?><tr><td colspan="6" class="text-muted">Nenhuma NF-e registrada ainda.</td></tr><?php endif; ?></tbody></table></div></div>
<?php require __DIR__.'/layout_bottom.php'; ?>
