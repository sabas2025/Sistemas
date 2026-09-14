<?php require __DIR__.'/layout_top.php'; ?>
<?php
  $geral=$operacao['geral']??'atencao';
  $badge=$geral==='online'?'success':($geral==='erro'?'danger':'warning');
  $scoreCards = $scoresDetalhados ?? [];
  if (!$scoreCards && !empty($scores)) { foreach($scores as $nome=>$score){ $scoreCards[$nome]=['score'=>$score,'real'=>false,'fonte'=>'legado','detalhe'=>'Score legado sem fonte detalhada']; } }
  $fontes = $operacao['fontes'] ?? [];
  $fontesReais = array_values(array_filter($fontes, fn($f)=>!empty($f['real'])));
  $fontesAusentes = array_values(array_filter($fontes, fn($f)=>empty($f['real'])));
?>
<div class="card-soft mb-3 border border-primary-subtle">
  <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
    <div>
      <h4 class="mb-1"><i class="bi bi-graph-up-arrow"></i> Dashboard Executivo</h4>
      <p class="text-muted mb-0">Visão gerencial da operação VSM → HUB → Tiny. A V92 informa se cada indicador vem de dado real do banco ou apenas configuração.</p>
    </div>
    <span class="badge text-bg-<?=$badge?> fs-6"><?= $geral==='online'?'🟢':($geral==='erro'?'🔴':'🟡') ?> <?=e(strtoupper($geral))?></span>
  </div>
</div>

<div class="alert alert-info border">
  <b>Conferência de realidade dos dados:</b>
  <?=count($fontesReais)?> fonte(s) reais encontrada(s) no banco e <?=count($fontesAusentes)?> fonte(s) ausente(s)/não consultável(eis).
  Quando não há teste ou tabela, o card fica marcado como <b>Configuração/estimado</b>, evitando mostrar número como se fosse produção real.
</div>

<div class="row g-3 mb-3">
<?php foreach($scoreCards as $nome=>$info): $score=(int)($info['score']??0); $c=$score>=90?'success':($score>=70?'warning':'danger'); $real=!empty($info['real']); ?>
  <div class="col-md-6 col-xl-2">
    <div class="kpi h-100">
      <div class="label d-flex justify-content-between gap-2"><span><?=e($nome)?></span><span class="badge <?=$real?'bg-success':'bg-secondary'?>"><?=$real?'Real':'Config.'?></span></div>
      <div class="value text-<?=$c?>"><?=e($score)?>%</div>
      <div class="progress mt-2" style="height:8px"><div class="progress-bar bg-<?=$c?>" style="width:<?=$score?>%"></div></div>
      <div class="small text-muted mt-2"><b>Fonte:</b> <?=e($info['fonte'] ?? '-')?></div>
      <div class="small text-muted"><?=e($info['detalhe'] ?? '')?></div>
    </div>
  </div>
<?php endforeach; ?>
</div>

<div class="row g-3">
  <div class="col-lg-6"><div class="card-soft h-100"><h5>📦 Pedidos <span class="badge bg-success">Banco real</span></h5><table class="table table-sm"><tr><th>Recebidos Tiny</th><td><?=e($operacao['pedidos']['recebidos_tiny']??0)?></td></tr><tr><th>Enviados VSM</th><td><?=e($operacao['pedidos']['enviados_vsm']??0)?></td></tr><tr><th>Aguardando XML</th><td><?=e($operacao['pedidos']['aguardando_xml']??0)?></td></tr><tr><th>Concluídos</th><td><?=e($operacao['pedidos']['concluidos']??0)?></td></tr><tr><th>Erros</th><td><?=e($operacao['pedidos']['erros']??0)?></td></tr></table><div class="small text-muted">Fonte: <code>pedidos_hub</code>. Se a tabela estiver vazia, os valores reais serão zero.</div></div></div>
  <div class="col-lg-6"><div class="card-soft h-100"><h5>🧾 XML/NF-e <span class="badge bg-success">Banco real</span></h5><table class="table table-sm"><tr><th>Recebidas</th><td><?=e($operacao['xml_nfe']['recebidas']??0)?></td></tr><tr><th>Processadas</th><td><?=e($operacao['xml_nfe']['xml_processados']??0)?></td></tr><tr><th>Com erro</th><td><?=e($operacao['xml_nfe']['xml_erros']??0)?></td></tr><tr><th>Reenvios pendentes</th><td><?=e($operacao['xml_nfe']['reenvios']??0)?></td></tr></table><div class="small text-muted">Fonte: <code>pedidos_nfe_xml</code> e <code>fila_fiscal</code>.</div></div></div>
  <div class="col-lg-6"><div class="card-soft h-100"><h5>📊 Estoque <span class="badge bg-success">Banco real</span></h5><table class="table table-sm"><tr><th>Sincronizados</th><td><?=e($operacao['estoque']['sincronizados']??0)?></td></tr><tr><th>Divergências abertas</th><td><?=e($operacao['estoque']['divergencias_abertas']??0)?></td></tr><tr><th>Pendentes</th><td><?=e($operacao['estoque']['pendentes']??0)?></td></tr><tr><th>Falhas</th><td><?=e($operacao['estoque']['falhas']??0)?></td></tr></table><a class="btn btn-sm btn-outline-primary" href="index.php?page=monitor-divergencias">Abrir divergências</a><div class="small text-muted mt-2">Fonte: <code>estoque_movimentos</code>, <code>estoque_divergencias</code> e <code>fila_estoque</code>.</div></div></div>
  <div class="col-lg-6"><div class="card-soft h-100"><h5>🚨 Alertas</h5><?php if(empty($operacao['alertas'])): ?><div class="alert alert-success mb-0">Nenhum alerta crítico.</div><?php else: foreach($operacao['alertas'] as $a): ?><div class="border-bottom py-2"><b><?=($a['nivel']==='erro'?'🔴':'🟡')?> <?=e($a['titulo'])?></b><div class="small text-muted"><?=e($a['mensagem'])?></div><?php if(!empty($a['acao'])): ?><a class="small" href="<?=e($a['acao'])?>">Abrir ação recomendada</a><?php endif; ?></div><?php endforeach; endif; ?></div></div>
</div>

<div class="card-soft mt-3">
  <h5><i class="bi bi-database-check"></i> Auditoria das fontes do Dashboard</h5>
  <p class="text-muted small mb-2">Use esta área para confirmar se o indicador está vindo de tabela real, tabela vazia ou fallback de configuração.</p>
  <div class="table-responsive"><table class="table table-sm align-middle">
    <thead><tr><th>Fonte/Tabela</th><th>Status</th><th>Registros</th><th>Detalhe</th></tr></thead>
    <tbody>
      <?php foreach($fontes as $f): ?>
        <tr>
          <td><code><?=e($f['fonte'] ?? '-')?></code></td>
          <td><span class="badge <?=!empty($f['real'])?'bg-success':'bg-warning text-dark'?>"><?=!empty($f['real'])?'Real':'Ausente/Fallback'?></span></td>
          <td><?=e($f['valor'] ?? 0)?></td>
          <td class="small text-muted"><?=e($f['detalhe'] ?? '')?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
