<?php require __DIR__.'/layout_top.php'; ?>
<?php $resumo = $report['resumo'] ?? ['score'=>0,'status'=>'indisponivel','ok'=>0,'atencao'=>0,'erro'=>0,'total'=>0]; ?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div>
    <h2 class="m-0"><i class="bi bi-shield-check"></i> Teste de Segurança Assistido</h2>
    <p class="text-muted mb-0">Auditoria segura e não invasiva do HUB publicado. Não executa ataque, força bruta, fuzzing ou scanner agressivo.</p>
  </div>
  <form method="post" action="index.php?page=security-assisted-test-run" class="d-inline">
    <?= Csrf::input() ?>
    <button class="btn btn-primary"><i class="bi bi-clipboard2-check"></i> Gerar relatório seguro</button>
  </form>
</div>

<?php if(!empty($_SESSION['flash_success'])): ?><div class="alert alert-success"><?=e($_SESSION['flash_success']); unset($_SESSION['flash_success']);?></div><?php endif; ?>
<?php if(!empty($_SESSION['flash_error'])): ?><div class="alert alert-danger"><?=e($_SESSION['flash_error']); unset($_SESSION['flash_error']);?></div><?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Security Score</div><div class="display-6 fw-bold"><?=e($resumo['score'] ?? 0)?>%</div><span class="badge <?=($resumo['erro']??0)>0?'text-bg-danger':(($resumo['atencao']??0)>0?'text-bg-warning':'text-bg-success')?>"><?=e(strtoupper($resumo['status'] ?? ''))?></span></div></div></div>
  <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Checks OK</div><div class="h2 text-success"><?=e($resumo['ok'] ?? 0)?></div></div></div></div>
  <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Atenções</div><div class="h2 text-warning"><?=e($resumo['atencao'] ?? 0)?></div></div></div></div>
  <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Críticos/Erros</div><div class="h2 text-danger"><?=e($resumo['erro'] ?? 0)?></div></div></div></div>
</div>

<div class="alert alert-info">
  <b>Trace ID:</b> <?=e($report['trace_id'] ?? '')?> · <b>Gerado em:</b> <?=e($report['gerado_em'] ?? '')?> · <b>Modo:</b> <?=e($report['modo'] ?? 'nao_invasivo')?>
</div>

<?php foreach(($report['categorias'] ?? []) as $cat): ?>
<div class="card mb-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <strong><?=e($cat['nome'] ?? '')?></strong>
    <span class="small text-muted">✅ <?=e($cat['ok'] ?? 0)?> · 🟡 <?=e($cat['atencao'] ?? 0)?> · 🔴 <?=e($cat['erro'] ?? 0)?></span>
  </div>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead><tr><th style="width:110px">Status</th><th>Check</th><th style="width:120px">Risco</th><th>Ação recomendada</th><th>Detalhes seguros</th></tr></thead>
      <tbody>
      <?php foreach(($cat['checks'] ?? []) as $c): ?>
        <?php $st=$c['status'] ?? 'atencao'; ?>
        <tr>
          <td><span class="badge <?=$st==='ok'?'text-bg-success':($st==='atencao'?'text-bg-warning':'text-bg-danger')?>"><?=e(strtoupper($st))?></span></td>
          <td><b><?=e($c['titulo'] ?? '')?></b><br><code class="small"><?=e($c['key'] ?? '')?></code></td>
          <td><?=e($c['risco'] ?? '')?></td>
          <td><?=e($c['acao'] ?? '')?></td>
          <td><code class="small"><?=e(!empty($c['detalhes']) ? json_encode($c['detalhes'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : '-')?></code></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endforeach; ?>

<div class="card mb-3">
  <div class="card-header"><strong>Relatórios gerados</strong></div>
  <div class="table-responsive">
    <table class="table table-sm mb-0 align-middle">
      <thead><tr><th>Data</th><th>Score</th><th>Status</th><th>Trace ID</th><th>Download</th></tr></thead>
      <tbody>
      <?php if(empty($reports)): ?><tr><td colspan="5" class="text-muted">Nenhum relatório persistido ainda. Clique em “Gerar relatório seguro”.</td></tr><?php endif; ?>
      <?php foreach($reports as $r): ?>
        <tr>
          <td><?=e($r['gerado_em'])?></td>
          <td><?=e($r['score'])?>%</td>
          <td><?=e($r['status'])?></td>
          <td><code><?=e($r['trace_id'])?></code></td>
          <td>
            <a class="btn btn-sm btn-outline-primary" href="index.php?page=security-assisted-test-download&file=<?=e($r['arquivo'])?>">JSON</a>
            <a class="btn btn-sm btn-outline-secondary" href="index.php?page=security-assisted-test-download&file=<?=e($r['markdown'])?>">Markdown</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="alert alert-secondary small mb-0">
  <b>Segurança:</b> este relatório não mostra access_token, refresh_token, client_secret, senha, API key, HMAC ou Authorization. Para pentest real, use ambiente de homologação e autorização formal.
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
