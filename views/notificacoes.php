<?php require __DIR__.'/layout_top.php'; ?>
<div class="row g-3 mb-4">
  <?php foreach(['info'=>'Info','sucesso'=>'Sucesso','alerta'=>'Alerta','erro'=>'Erro'] as $sev=>$label): ?>
    <?php $total=0; foreach($resumo as $r){ if(($r['severidade']??'')===$sev) $total=(int)$r['total']; } ?>
    <div class="col-md-3"><div class="kpi notif-kpi sev-<?=$sev?>"><div class="label"><?=e($label)?> não lidas</div><div class="value"><?=e($total)?></div></div></div>
  <?php endforeach; ?>
</div>

<div class="filterbar">
  <div class="row g-2 align-items-end">
    <form class="col-md-9 row g-2 align-items-end" method="get">
      <input type="hidden" name="page" value="notificacoes">
      <div class="col-md-6"><label class="form-label">Tipo</label><select name="tipo" class="form-select"><option value="">Todos</option><?php foreach(['pedido_novo'=>'Pedido novo','pedido_integrado'=>'Pedido integrado','erro_integracao'=>'Erro integração','fila'=>'Fila','estoque'=>'Estoque','nota_fiscal'=>'Nota fiscal','sistema'=>'Sistema'] as $k=>$v): ?><option value="<?=$k?>" <?=($_GET['tipo']??'')===$k?'selected':''?>><?=$v?></option><?php endforeach; ?></select></div>
      <div class="col-md-4"><label class="form-label">Status</label><select name="lida" class="form-select"><option value="">Todas</option><option value="0" <?=($_GET['lida']??'')==='0'?'selected':''?>>Não lidas</option><option value="1" <?=($_GET['lida']??'')==='1'?'selected':''?>>Lidas</option></select></div>
      <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-filter"></i> Filtrar</button><a class="btn btn-outline-secondary" href="index.php?page=notificacoes">Limpar</a></div>
    </form>
    <div class="col-md-3 text-md-end">
      <form method="post" action="index.php?page=notificacao-lida">
        <?=Csrf::input()?>
        <input type="hidden" name="todas" value="1">
        <button class="btn btn-success w-100"><i class="bi bi-check2-all"></i> Marcar todas como lidas</button>
      </form>
    </div>
  </div>
</div>

<div class="panel">
  <div class="panel-header"><h2>Central de notificações</h2><span class="text-muted">Push visual no painel + alerta sonoro + API para evoluir e-mail/WhatsApp</span></div>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead><tr><th>Status</th><th>Tipo</th><th>Mensagem</th><th>Trace ID</th><th>Data</th><th>Ação</th></tr></thead>
      <tbody>
      <?php foreach($notificacoes as $n): ?>
        <tr class="<?=((int)$n['lida']===0)?'table-warning':''?>">
          <td><span class="badge-status status-<?=e($n['severidade'])?>"><?=((int)$n['lida']===0)?'Nova':'Lida'?></span></td>
          <td><b><?=e($n['titulo'])?></b><br><small class="text-muted"><?=e($n['tipo'])?> / <?=e($n['severidade'])?></small></td>
          <td><?=e($n['mensagem'])?></td>
          <td><?php if($n['trace_id']): ?><a href="index.php?page=auditoria&trace=<?=urlencode($n['trace_id'])?>"><code><?=e($n['trace_id'])?></code></a><?php else: ?>-<?php endif; ?></td>
          <td><?=e($n['criada_em'])?></td>
          <td class="text-nowrap"><?php if($n['link']): ?><a class="btn btn-sm btn-outline-primary" href="<?=e($n['link'])?>">Abrir</a><?php endif; ?><?php if(!(int)$n['lida']): ?><form method="post" action="index.php?page=notificacao-lida" class="d-inline"><?=Csrf::input()?><input type="hidden" name="id" value="<?=$n['id']?>"><button class="btn btn-sm btn-outline-success">Lida</button></form><?php endif; ?></td>
        </tr>
      <?php endforeach; if(!$notificacoes): ?><tr><td colspan="6" class="text-center text-muted py-4">Nenhuma notificação encontrada.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="alert alert-info mt-4">
  <b>Como funciona:</b> quando a VSM envia um pedido, o sistema cria uma notificação de pedido novo. Se a fila falhar ao enviar para o Tiny, cria uma notificação de erro com Trace ID para auditoria.
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
