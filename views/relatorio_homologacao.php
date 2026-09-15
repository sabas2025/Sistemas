<?php require __DIR__.'/layout_top.php'; ?>
<div class="card-soft mb-3 d-print-none">
  <h4>Relatório de Homologação</h4>
  <p class="text-muted mb-2">Relatório HTML imprimível com checklist, ambiente, evidências e pendências.</p>
  <button class="btn btn-primary js-print" type="button"><i class="bi bi-printer"></i> Imprimir / Salvar PDF</button>
</div>
<div class="card-soft">
  <h3>Relatório de Homologação Hub de Integração</h3>
  <p><b>Data:</b> <?=date('d/m/Y H:i:s')?> &nbsp; <b>Ambiente:</b> <?=e($cfg['ambiente'] ?? '')?> &nbsp; <b>Tiny:</b> <?=e($cfg['tiny_versao'] ?? 'v2')?></p>
  <div class="row g-2 mb-3">
    <div class="col-md-3"><div class="kpi"><span>Total</span><b><?=$resumo['total']?></b></div></div>
    <div class="col-md-3"><div class="kpi"><span>OK</span><b><?=$resumo['ok']?></b></div></div>
    <div class="col-md-3"><div class="kpi"><span>Falha</span><b><?=$resumo['falha']?></b></div></div>
    <div class="col-md-3"><div class="kpi"><span>Pendente</span><b><?=$resumo['pendente']?></b></div></div>
  </div>
  <table class="table table-bordered align-middle">
    <thead><tr><th>Item</th><th>Status</th><th>Evidência</th><th>Trace ID</th></tr></thead>
    <tbody><?php foreach($itens as $i): ?><tr>
      <td><b><?=e($i['titulo'])?></b><br><small><?=e($i['descricao'])?></small></td>
      <td><?=e($i['status'])?></td>
      <td><?=nl2br(e($i['resultado'] ?? ''))?></td>
      <td><code><?=e($i['trace_id'] ?? '')?></code></td>
    </tr><?php endforeach; ?></tbody>
  </table>
  <h5>Conclusão técnica</h5>
  <p>Para produção, todos os itens críticos devem estar como <b>OK</b>, principalmente Tiny Token, VSM Conexão, Produto VSM→Tiny, Estoque VSM→Tiny, Baixa Tiny→VSM, Auditoria e DLQ.</p>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
