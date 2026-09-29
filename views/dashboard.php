<?php require __DIR__.'/layout_top.php'; ?>
<?php if(!empty($tinyV3Aviso)): ?><div class="alert alert-warning"><b>🟡 Tiny V3 = Homologação:</b> Tiny V3 está selecionado, mas ainda não está operacional. Mantenha <b>🟢 Tiny V2 = Produção</b> até concluir OAuth, produto, estoque, pedido e fiscal.</div><?php endif; ?>

<?php
  $geral = $operacao['geral'] ?? 'atencao';
  $geralClass = $geral === 'online' ? 'status-sucesso' : ($geral === 'erro' ? 'status-erro' : 'status-pendente');
  $totalAlertas = count($operacao['alertas'] ?? []);
  // D-01: o modo de aprovação de produto novo é o portão que REALMENTE segura o produto vindo da VSM.
  // Sem ele na tela, "Criar produto Tiny: Ativo" sugeria automação que a governança bloqueia.
  $modoProdutoNovo = strtolower((string)($cfgInt['produto_novo_aprovacao_modo'] ?? 'manual')) === 'automatico' ? 'automatico' : 'manual';
?>

<!-- ===== ESTADO (sempre visível) ===== -->
<div class="panel mb-4 op-hero">
  <div class="panel-header align-items-start">
    <div>
      <h2>Centro Executivo da Operação</h2>
      <span class="text-muted">Estado das integrações no topo; o detalhe fica nas abas abaixo.</span>
    </div>
    <span class="badge-status <?=$geralClass?> fs-6">● <?= $geral === 'online' ? 'Operacional' : ($geral === 'erro' ? 'Crítico' : 'Atenção') ?></span>
  </div>
  <div class="row g-3 p-3">
    <?php foreach(($operacao['integracoes'] ?? []) as $i):
      $st=$i['status'] ?? 'atencao'; $cl=$st==='online'?'status-sucesso':($st==='erro'?'status-erro':'status-pendente');
    ?>
      <div class="col-md-4">
        <div class="health-card h-100">
          <div class="health-icon"><i class="bi bi-plug"></i></div>
          <div class="flex-grow-1">
            <div class="d-flex justify-content-between align-items-center gap-2"><b><?=e($i['nome'])?></b><span class="badge-status <?=$cl?>">● <?=e($i['modo'])?></span></div>
            <div class="small text-muted mt-1"><?=e($i['detalhe'])?></div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-3 col-md-6"><div class="kpi"><div class="icon"><i class="bi bi-arrow-repeat"></i></div><div class="label">Fila pendente</div><div class="value"><?=e($k['Fila pendente'] ?? 0)?></div></div></div>
  <div class="col-lg-3 col-md-6"><div class="kpi"><div class="icon"><i class="bi bi-bell"></i></div><div class="label">Erros / alertas</div><div class="value"><?=e($totalAlertas)?></div></div></div>
  <div class="col-lg-3 col-md-6"><div class="kpi"><div class="icon"><i class="bi bi-boxes"></i></div><div class="label">Estoque sincronizado</div><div class="value"><?=e($estoqueResumo['sincronizados'] ?? 0)?></div></div></div>
  <div class="col-lg-3 col-md-6"><div class="kpi"><div class="icon"><i class="bi bi-receipt"></i></div><div class="label">XML processados</div><div class="value"><?=e($fiscalResumo['xml_processados'] ?? 0)?></div></div></div>
</div>

<!-- ===== ABAS POR DOMÍNIO ===== -->
<div class="panel">
  <div class="p-3 pb-0">
    <div class="nav nav-pills integration-tabs" data-hub-tabs role="tablist" aria-label="Domínios do dashboard">
      <button class="nav-link active" type="button" role="tab" data-hub-tab="op" aria-selected="true"><i class="bi bi-diagram-3"></i> Operação</button>
      <button class="nav-link" type="button" role="tab" data-hub-tab="fila" aria-selected="false"><i class="bi bi-arrow-repeat"></i> Fila</button>
      <button class="nav-link" type="button" role="tab" data-hub-tab="prod" aria-selected="false"><i class="bi bi-box-seam"></i> Produtos &amp; Estoque</button>
      <button class="nav-link" type="button" role="tab" data-hub-tab="fiscal" aria-selected="false"><i class="bi bi-file-earmark-code"></i> Fiscal</button>
      <button class="nav-link" type="button" role="tab" data-hub-tab="saude" aria-selected="false"><i class="bi bi-activity"></i> Saúde</button>
    </div>
  </div>

  <!-- ABA: OPERAÇÃO -->
  <section class="p-3" data-hub-pane="op" role="tabpanel">
    <div class="panel mb-4">
      <div class="panel-header"><div><h2>Ciclo Operacional do Pedido</h2><span class="text-muted">Tiny → Hub → VSM → Hub → Tiny</span></div><a class="btn btn-sm btn-outline-primary" href="index.php?page=pedido-ciclo-vida"><i class="bi bi-diagram-3"></i> Ver ciclo</a></div>
      <?php if(!empty($pedidoCicloResumo['erro_consulta'])): ?><div class="alert alert-warning m-3">Tabela de ciclo ainda não instalada: <?=e($pedidoCicloResumo['erro_consulta'])?></div><?php endif; ?>
      <div class="row g-3 p-3">
        <?php $cardsCiclo=[['recebidos_tiny','Recebidos Tiny','bi-inbox'],['enviados_vsm','Enviados VSM','bi-send'],['aguardando_xml','Aguardando XML','bi-file-earmark-code'],['enviados_tiny','Enviados Tiny','bi-send-check'],['concluidos','Concluídos','bi-check-circle'],['erros','Com erro','bi-exclamation-triangle']]; foreach($cardsCiclo as $cc): ?>
        <div class="col-md-4 col-xl-2"><div class="kpi h-100"><div class="icon"><i class="bi <?=e($cc[2])?>"></i></div><div class="label"><?=e($cc[1])?></div><div class="value"><?=e($pedidoCicloResumo[$cc[0]] ?? 0)?></div></div></div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <?php $icons=['Pedidos integrados'=>'bi-bag-check','Pedidos com erro'=>'bi-exclamation-triangle','Fila pendente'=>'bi-arrow-repeat','Produtos mapeados'=>'bi-box-seam','Empresas'=>'bi-buildings','Eventos de auditoria'=>'bi-clipboard-data','Notificações não lidas'=>'bi-bell']; ?>
      <?php foreach($k as $nome=>$valor): ?>
        <div class="col-md-6 col-xl-3"><div class="kpi"><div class="icon"><i class="bi <?=e($icons[$nome]??'bi-graph-up')?>"></i></div><div class="label"><?=e($nome)?></div><div class="value"><?=e($valor)?></div></div></div>
      <?php endforeach; ?>
    </div>

    <div class="panel">
      <div class="panel-header"><h2>Últimos pedidos</h2><a class="btn btn-sm btn-primary" href="index.php?page=pedidos">Ver todos</a></div>
      <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Origem</th><th>Pedido VSM</th><th>Tiny</th><th>Cliente</th><th>Valor</th><th>Status</th><th>Data</th></tr></thead><tbody><?php foreach($pedidos as $p): ?><tr><td><?=e($p['origem'])?></td><td><b><?=e($p['pedido_origem_id'])?></b></td><td><?=e($p['pedido_tiny_id'] ?: '-')?></td><td><?=e($p['cliente_nome'] ?: '-')?></td><td>R$ <?=number_format((float)$p['valor_total'],2,',','.')?></td><td><span class="badge-status status-<?=e($p['status'])?>"><?=e($p['status'])?></span></td><td><?=e($p['criado_em'])?></td></tr><?php endforeach; if(!$pedidos): ?><tr><td colspan="7" class="text-center text-muted py-4">Nenhum pedido recebido ainda.</td></tr><?php endif; ?></tbody></table></div>
    </div>
  </section>

  <!-- ABA: FILA -->
  <section class="p-3" data-hub-pane="fila" role="tabpanel" hidden>
    <div class="row g-4">
      <div class="col-xl-6">
        <div class="panel h-100"><div class="panel-header"><h2>Fila por status</h2></div><div class="p-3"><?php foreach($filaResumo as $f): ?><div class="d-flex justify-content-between border-bottom py-2"><span><span class="badge-status status-<?=e($f['status'])?>"><?=e($f['status'])?></span></span><b><?=e($f['total'])?></b></div><?php endforeach; if(!$filaResumo): ?><p class="text-muted mb-0">Fila vazia.</p><?php endif; ?></div></div>
      </div>
      <div class="col-xl-6">
        <div class="panel h-100"><div class="panel-header"><h2>Direção &amp; ação</h2></div><div class="p-3">
          <div class="d-flex justify-content-between border-bottom py-2"><span>Tiny → VSM (baixas pendentes)</span><b><?=e($filaTinyVsm ?? 0)?></b></div>
          <div class="d-flex justify-content-between border-bottom py-2"><span>VSM → Tiny (produtos pendentes)</span><b><?=e($filaVsmTiny ?? 0)?></b></div>
          <form method="post" action="index.php?page=api/processar-fila" class="mt-3"><?=Csrf::input()?><button class="btn btn-success w-100"><i class="bi bi-play-circle"></i> Processar 1 item</button></form>
        </div></div>
      </div>
    </div>
  </section>

  <!-- ABA: PRODUTOS & ESTOQUE -->
  <section class="p-3" data-hub-pane="prod" role="tabpanel" hidden>
    <div class="panel mb-4">
      <div class="panel-header">
        <div><h2>Regras de Sincronização</h2><span class="text-muted">VSM → Tiny · governança de produto novo</span></div>
        <a class="btn btn-sm btn-outline-primary" href="index.php?page=regras-sincronizacao"><i class="bi bi-toggles"></i> Ajustar regras</a>
      </div>
      <div class="p-3">
        <!-- D-01: portão real do produto novo, antes ausente da tela -->
        <div class="health-card mb-3">
          <div class="health-icon"><i class="bi bi-shield-check"></i></div>
          <div class="flex-grow-1">
            <div class="d-flex justify-content-between align-items-center gap-2">
              <b>Aprovação de produto novo (VSM → Tiny)</b>
              <span class="badge-status <?= $modoProdutoNovo==='automatico' ? 'status-sucesso' : 'status-pendente' ?>"><?= $modoProdutoNovo==='automatico' ? 'Automático' : 'Manual' ?></span>
            </div>
            <div class="small text-muted mt-1"><?= $modoProdutoNovo==='automatico'
              ? 'Modo automático: produto novo recebido da VSM é criado no Tiny sem aprovação manual (sujeito ao checklist de qualidade).'
              : 'Modo manual: produto novo recebido da VSM <b>aguarda aprovação no Hub</b> antes de ser criado no Tiny. As regras abaixo só valem depois da aprovação.' ?></div>
          </div>
        </div>
        <div class="row g-3">
          <?php foreach(($syncResumo ?? []) as $r): $on=!empty($syncRules[$r['chave']]); ?>
            <div class="col-md-6 col-xl-3">
              <div class="health-card">
                <div class="health-icon"><i class="bi <?= $on ? 'bi-check-circle' : 'bi-pause-circle' ?>"></i></div>
                <div class="flex-grow-1">
                  <div class="d-flex justify-content-between align-items-center gap-2">
                    <b><?=e($r['titulo'])?></b>
                    <span class="badge-status <?= $on ? 'status-sucesso' : 'status-pendente' ?>"><?= $on ? 'Ativo' : 'Desligado' ?></span>
                  </div>
                  <div class="small text-muted mt-1"><?=e(SyncRulesService::explain($r['chave']))?></div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-md-6 col-xl-3"><div class="kpi"><div class="icon"><i class="bi bi-boxes"></i></div><div class="label">Estoque sincronizado</div><div class="value"><?=e($estoqueResumo['sincronizados'] ?? 0)?></div></div></div>
      <div class="col-md-6 col-xl-3"><div class="kpi"><div class="icon"><i class="bi bi-exclamation-diamond"></i></div><div class="label">Divergências estoque</div><div class="value"><?=e($estoqueResumo['divergencias'] ?? 0)?></div></div></div>
      <div class="col-md-6 col-xl-3"><div class="kpi"><div class="icon"><i class="bi bi-hourglass-split"></i></div><div class="label">Estoque pendente</div><div class="value"><?=e($estoqueResumo['pendentes'] ?? 0)?></div></div></div>
      <div class="col-md-6 col-xl-3"><div class="kpi"><div class="icon"><i class="bi bi-x-octagon"></i></div><div class="label">Produtos com erro</div><div class="value"><?=e($produtosErro ?? 0)?></div></div></div>
    </div>

    <div class="row g-3">
      <div class="col-md-3"><div class="card pro-card h-100"><div class="card-body"><small class="text-muted">Última baixa VSM</small><h3 class="h6 mt-2"><?= $ultimaBaixaVsm ? e(($ultimaBaixaVsm['sku'] ?? '').' - '.($ultimaBaixaVsm['status'] ?? '')) : 'Nenhuma baixa' ?></h3><p class="metric-mini mb-0"><?= $ultimaBaixaVsm ? e($ultimaBaixaVsm['referencia'].' | '.$ultimaBaixaVsm['criado_em']) : 'Aguardando evento Tiny.' ?></p></div></div></div>
      <div class="col-md-3"><div class="card pro-card h-100"><div class="card-body"><small class="text-muted">Último produto Tiny</small><h3 class="h6 mt-2"><?= $ultimoProdutoTiny ? e($ultimoProdutoTiny['sku_vsm'] ?: $ultimoProdutoTiny['sku_tiny']) : 'Nenhum produto' ?></h3><p class="metric-mini mb-0"><?= $ultimoProdutoTiny ? e('Tiny ID: '.($ultimoProdutoTiny['produto_tiny_id'] ?: '-')) : 'Aguardando produto VSM.' ?></p></div></div></div>
      <div class="col-md-3"><div class="card pro-card h-100"><div class="card-body"><small class="text-muted">Baixas com erro</small><h3 class="h4 mt-2"><?=e($baixasErro ?? 0)?></h3><p class="metric-mini mb-0">Verificar tela Baixas Estoque</p></div></div></div>
      <div class="col-md-3"><div class="card pro-card h-100"><div class="card-body"><small class="text-muted">Estoque com falha na fila</small><h3 class="h4 mt-2"><?=e($estoqueResumo['falhas'] ?? 0)?></h3><p class="metric-mini mb-0">Fila exclusiva de estoque</p></div></div></div>
    </div>
  </section>

  <!-- ABA: FISCAL -->
  <section class="p-3" data-hub-pane="fiscal" role="tabpanel" hidden>
    <div class="row g-3 mb-4">
      <div class="col-md-6 col-xl-3"><div class="kpi"><div class="icon"><i class="bi bi-file-earmark-text"></i></div><div class="label">NF-e/XML recebidas</div><div class="value"><?=e($fiscalResumo['recebidas'] ?? 0)?></div></div></div>
      <div class="col-md-6 col-xl-3"><div class="kpi"><div class="icon"><i class="bi bi-file-earmark-check"></i></div><div class="label">XML processados</div><div class="value"><?=e($fiscalResumo['xml_processados'] ?? 0)?></div></div></div>
      <div class="col-md-6 col-xl-3"><div class="kpi"><div class="icon"><i class="bi bi-file-earmark-x"></i></div><div class="label">XML com erro</div><div class="value"><?=e($fiscalResumo['xml_erros'] ?? 0)?></div></div></div>
      <div class="col-md-6 col-xl-3"><div class="kpi"><div class="icon"><i class="bi bi-arrow-repeat"></i></div><div class="label">Reprocessos na fila</div><div class="value"><?=e($fiscalResumo['reenvios'] ?? 0)?></div></div></div>
    </div>
    <div class="panel"><div class="panel-header"><div><h2>Fluxo fiscal</h2><span class="text-muted">VSM → Hub → Tiny</span></div><a class="btn btn-sm btn-outline-primary" href="index.php?page=fiscal"><i class="bi bi-diagram-3"></i> Abrir fiscal</a></div>
      <div class="p-3"><p class="text-muted mb-0">A NF-e/XML é gerada na VSM, validada no Hub (chave NF-e) e enviada ao Tiny, vinculada ao pedido. Acompanhe cada documento em <a href="index.php?page=pedido-ciclo-vida">Ciclo de vida do pedido</a>.</p></div>
    </div>
  </section>

  <!-- ABA: SAÚDE -->
  <section class="p-3" data-hub-pane="saude" role="tabpanel" hidden>
    <div class="panel mb-4">
      <div class="panel-header"><div><h2>Saúde dos Workers</h2><span class="text-muted">Arquivos prontos para cron/Agendador de Tarefas.</span></div><a class="btn btn-sm btn-outline-primary" href="index.php?page=centro-operacoes">Centro de Operações</a></div>
      <div class="row g-3 p-3">
        <?php foreach(($workerCards ?? []) as $w): $ok=($w['status']??'')==='online'; ?>
          <div class="col-md-6 col-xl-3"><div class="health-card h-100"><div class="health-icon"><i class="bi <?=$ok?'bi-cpu':'bi-exclamation-octagon'?>"></i></div><div><b>● <?=e($w['titulo'])?></b><div class="small text-muted"><?=e($w['arquivo'])?></div><div class="small mt-1"><?=e($w['detalhe'])?></div></div></div></div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="panel mb-4">
      <div class="panel-header">
        <div><h2>Dashboard de Saúde</h2><span class="text-muted">Monitoramento rápido do Hub, integrações e fila</span></div>
        <div class="d-flex gap-2"><a class="btn btn-sm btn-outline-secondary" href="index.php?page=dashboard-integridade"><i class="bi bi-check2-square"></i> Verificar dashboard</a><a class="btn btn-sm btn-outline-primary" href="index.php?page=diagnostico"><i class="bi bi-activity"></i> Diagnóstico completo</a></div>
      </div>
      <div class="row g-3 p-3">
        <?php foreach(($healthCards ?? []) as $h): ?>
          <?php $status = $h['status'] ?? 'atencao'; $badge = $status === 'online' ? 'status-sucesso' : ($status === 'erro' ? 'status-erro' : 'status-pendente'); ?>
          <div class="col-md-6 col-xl-4">
            <div class="health-card">
              <div class="health-icon"><i class="bi <?=e($h['icone'] ?? 'bi-activity')?>"></i></div>
              <div class="flex-grow-1">
                <div class="d-flex justify-content-between align-items-center gap-2"><b><?=e($h['titulo'])?></b><span class="badge-status <?=$badge?>">● <?=e($status)?></span></div>
                <div class="small text-muted mt-1"><?=e($h['detalhe'] ?? '')?></div>
                <div class="small mt-1"><b>Ação:</b> <?=e($h['acao'] ?? 'OK')?></div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-md-3"><div class="card pro-card h-100"><div class="card-body"><small class="text-muted">Último teste VSM</small><h3 class="h6 mt-2"><?= $ultimoDiagVsm ? e(strtoupper($ultimoDiagVsm['status']).' - '.$ultimoDiagVsm['criado_em']) : 'Sem teste registrado' ?></h3><p class="small text-muted mb-0"><?= $ultimoDiagVsm ? e($ultimoDiagVsm['mensagem']) : 'Execute Teste VSM em Configurações.' ?></p></div></div></div>
      <div class="col-md-3"><div class="card pro-card h-100"><div class="card-body"><small class="text-muted">Último teste Tiny</small><h3 class="h6 mt-2"><?= $ultimoDiagTiny ? e(strtoupper($ultimoDiagTiny['status']).' - '.$ultimoDiagTiny['criado_em']) : 'Sem teste registrado' ?></h3><p class="small text-muted mb-0"><?= $ultimoDiagTiny ? e($ultimoDiagTiny['mensagem']) : 'Execute Teste Tiny em Configurações.' ?></p></div></div></div>
      <div class="col-md-3"><div class="card pro-card h-100"><div class="card-body"><small class="text-muted">Último pedido</small><h3 class="h6 mt-2"><?= $ultimoPedidoIntegrado ? e(($ultimoPedidoIntegrado['pedido_origem_id'] ?? '').' - '.($ultimoPedidoIntegrado['status'] ?? '')) : 'Nenhum pedido' ?></h3><p class="small text-muted mb-0"><?= $ultimoPedidoIntegrado ? e($ultimoPedidoIntegrado['criado_em']) : 'Aguardando primeiro pedido.' ?></p></div></div></div>
      <div class="col-md-3"><div class="card pro-card h-100"><div class="card-body"><small class="text-muted">Última falha</small><h3 class="h6 mt-2"><?= $ultimaFalha ? e($ultimaFalha['codigo_erro'] ?: $ultimaFalha['tipo']) : 'Sem falhas' ?></h3><p class="small text-muted mb-0"><?= $ultimaFalha ? e(mb_substr($ultimaFalha['mensagem'],0,90)) : 'Nenhuma falha registrada nos logs.' ?></p></div></div></div>
    </div>

    <div class="panel mb-4"><div class="panel-header"><h2>Endpoints</h2></div><div class="p-3"><div class="codebox small">Tiny estoque: POST /public/index.php?page=api/tiny/webhook/estoque<br>VSM produto novo: POST /public/index.php?page=api/webhook/vsm/produto<br>Processar fila: POST /public/index.php?page=api/processar-fila<br>CLI: php public/worker_fila.php</div></div></div>

    <div class="panel"><div class="panel-header"><h2>Últimos logs</h2><a class="btn btn-sm btn-outline-primary" href="index.php?page=logs">Abrir logs</a></div><div class="table-responsive"><table class="table table-hover"><thead><tr><th>Nível</th><th>Tipo</th><th>Mensagem</th><th>Data</th></tr></thead><tbody><?php foreach($logs as $l): ?><tr><td><span class="badge-status status-<?= $l['nivel']==='erro'?'erro':($l['nivel']==='alerta'?'pendente':'sucesso') ?>"><?=e($l['nivel'])?></span></td><td><?=e($l['tipo'])?></td><td><?=e($l['mensagem'])?></td><td><?=e($l['criado_em'])?></td></tr><?php endforeach; if(!$logs): ?><tr><td colspan="4" class="text-center text-muted py-4">Nenhum log registrado.</td></tr><?php endif; ?></tbody></table></div></div>
  </section>
</div>

<script nonce="<?=App::cspNonce()?>">
(function(){
  var nav=document.querySelector('[data-hub-tabs]');
  if(!nav) return;
  var btns=nav.querySelectorAll('[data-hub-tab]');
  var panes=document.querySelectorAll('[data-hub-pane]');
  function activate(name){
    btns.forEach(function(b){var on=b.getAttribute('data-hub-tab')===name;b.classList.toggle('active',on);b.setAttribute('aria-selected',on?'true':'false');});
    panes.forEach(function(p){p.hidden=p.getAttribute('data-hub-pane')!==name;});
  }
  nav.addEventListener('click',function(e){var b=e.target.closest('[data-hub-tab]');if(!b)return;e.preventDefault();activate(b.getAttribute('data-hub-tab'));});
  var valid=false, initial=(location.hash||'').replace('#','');
  btns.forEach(function(b){ if(b.getAttribute('data-hub-tab')===initial) valid=true; });
  activate(valid?initial:'op');
})();
</script>
<?php require __DIR__.'/layout_bottom.php'; ?>
