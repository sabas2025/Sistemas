<?php require __DIR__.'/layout_top.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div><h2>SLA e Suporte</h2><p class="text-muted mb-0">Painel comercial para acompanhar chamados, prioridade e prazos de resposta/resolução.</p></div>
  <form method="post" action="index.php?page=suporte-sla-demo"><?=Csrf::field()?><button class="btn btn-primary">Criar chamado demo</button></form>
</div>
<div class="card">
  <div class="card-header"><b>Chamados</b></div>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead><tr><th>ID</th><th>Cliente</th><th>Título</th><th>Prioridade</th><th>Status</th><th>Aberto</th><th>Resposta</th><th>Resolução</th></tr></thead>
      <tbody>
      <?php foreach($tickets as $t): ?>
        <tr><td><?=e((string)$t['id'])?></td><td><?=e($t['cliente_nome'] ?? '-')?></td><td><?=e($t['titulo'])?></td><td><?=e($t['prioridade'])?></td><td><?=e($t['status'])?></td><td><?=e($t['aberto_em'])?></td><td><?=e($t['prazo_resposta_em'] ?? '-')?></td><td><?=e($t['prazo_resolucao_em'] ?? '-')?></td></tr>
      <?php endforeach; if(empty($tickets)): ?><tr><td colspan="8" class="text-muted">Nenhum chamado cadastrado.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<div class="alert alert-info mt-3 mb-0">Para produção comercial, integre este painel a e-mail, WhatsApp, portal do cliente ou ferramenta de chamados.</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
