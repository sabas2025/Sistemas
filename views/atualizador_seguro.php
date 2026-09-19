<?php require __DIR__.'/layout_top.php'; ?>
<div class="panel">
  <div class="panel-header">
    <div><h2>Atualizador Seguro Universal</h2><span class="text-muted">Executa updates V12–V31 com verificação de coluna/índice antes de aplicar</span></div>
    <a class="btn btn-sm btn-outline-secondary" href="index.php?page=validar-banco">Validar Banco</a>
  </div>
  <div class="p-4">
    <div class="alert alert-warning"><b>Uso recomendado:</b> execute depois de subir uma versão nova em banco antigo. O atualizador ignora colunas/índices já existentes e registra tudo em Auditoria.</div>
    <form method="post" action="index.php?page=atualizador-seguro-executar" class="mb-4"><?=Csrf::input()?><button class="btn btn-primary"><i class="bi bi-database-gear"></i> Executar todos os updates com segurança</button></form>
    <h5>Scripts encontrados</h5>
    <div class="table-responsive"><table class="table"><thead><tr><th>Arquivo</th><th>Status</th></tr></thead><tbody><?php foreach($arquivos as $a): ?><tr><td><?=e(basename($a))?></td><td><span class="badge bg-info">detectado</span></td></tr><?php endforeach; ?></tbody></table></div>
    <?php if(!empty($_SESSION['upgrade_report_v32'])): $r=$_SESSION['upgrade_report_v32']; ?>
      <hr><h5>Último relatório</h5>
      <?php /* Achado I-11: esta tela lia $r['ok'] e $r['ignorados'], chaves que o serviço NUNCA
         devolveu, e passava $r['erros'] (array) para e(), que faz cast para string. O atualizador
         está desativado por decisão de projeto — o serviço só informa isso — então a tela mostra
         o que ele realmente responde, em vez de contadores que não existem. */ ?>
      <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="kpi"><div class="label">Erros</div><div class="value"><?=e(count($r['erros'] ?? []))?></div></div></div>
        <div class="col-md-4"><div class="kpi"><div class="label">Concluído em</div><div class="small"><?=e($r['finalizado_em'] ?? '-')?></div></div></div>
        <div class="col-md-4"><div class="kpi"><div class="label">Trace</div><div class="small"><?=e($r['trace_id'] ?? '-')?></div></div></div>
      </div>
      <?php foreach(($r['mensagens'] ?? []) as $m): ?><div class="alert alert-info py-2"><?=e($m)?></div><?php endforeach; ?>
      <?php foreach(($r['erros'] ?? []) as $m): ?><div class="alert alert-danger py-2"><?=e($m)?></div><?php endforeach; ?>
      <pre class="json-box"><?=e(json_encode($r, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?></pre>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
