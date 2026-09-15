<?php require __DIR__.'/layout_top.php'; ?>
<?php
$status = $resultado['status'] ?? null;
$score = (int)($resultado['score'] ?? 0);
$statusClass = $status === 'aprovado' ? 'success' : ($status === 'atencao' ? 'warning' : ($status === 'bloqueado' ? 'danger' : 'secondary'));
$statusLabel = $status ? strtoupper(str_replace('_',' ', $status)) : 'NÃO EXECUTADO';
$checks = $resultado['checks'] ?? [];
$grupos = [];
foreach ($checks as $c) { $grupos[$c['grupo'] ?? 'Produção'][] = $c; }
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div>
    <h2><i class="bi bi-shield-lock"></i> Checklist Produção Segura</h2>
    <p class="text-muted mb-0">Diagnóstico analítico antes de ligar Tiny/VSM em produção. Bloqueio aqui é proteção operacional, não necessariamente erro de código.</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-outline-secondary" href="index.php?page=central-tecnica">Central Técnica</a>
    <a class="btn btn-outline-primary" href="index.php?page=entrada-producao">Entrada em Produção</a>
  </div>
</div>

<div class="alert alert-primary d-flex justify-content-between align-items-center flex-wrap gap-2">
  <div><b>Regra segura:</b> VSM começa em homologação. Produção só deve liberar com URL oficial, token, teste real OK, checklist aprovado e autorização da VSM.</div>
  <a class="btn btn-primary btn-sm" href="index.php?page=configuracoes"><i class="bi bi-sliders"></i> Abrir Configurações</a>
</div>

<?php if(!empty($_SESSION['form_error'])): ?><div class="alert alert-danger"><?=e($_SESSION['form_error']); unset($_SESSION['form_error']);?></div><?php endif; ?>
<?php if(!empty($_SESSION['form_success'])): ?><div class="alert alert-success"><?=e($_SESSION['form_success']); unset($_SESSION['form_success']);?></div><?php endif; ?>

<form method="post" action="index.php?page=producao-segura-executar" class="mb-3 d-flex gap-2 flex-wrap">
  <?=Csrf::input()?>
  <button class="btn btn-success"><i class="bi bi-shield-check"></i> Validar produção segura</button>
  <a class="btn btn-outline-secondary" href="index.php?page=validar-banco"><i class="bi bi-database-check"></i> Validar Banco</a>
  <a class="btn btn-outline-secondary" href="index.php?page=auditoria"><i class="bi bi-search"></i> Auditoria</a>
</form>

<?php if($resultado): ?>
<div class="row g-3 mb-3 producao-segura-summary">
  <div class="col-md-3 col-6"><div class="card-soft h-100 border-<?=$statusClass?>"><small class="text-muted">Status</small><h3 class="text-<?=$statusClass?> mb-0"><?=e($statusLabel)?></h3></div></div>
  <div class="col-md-3 col-6"><div class="card-soft h-100"><small class="text-muted">Score</small><h3 class="mb-0"><?=e((string)$score)?>%</h3></div></div>
  <div class="col-md-2 col-4"><div class="card-soft h-100"><small class="text-muted">OK</small><h3 class="text-success mb-0"><?=e((string)($resultado['ok'] ?? 0))?></h3></div></div>
  <div class="col-md-2 col-4"><div class="card-soft h-100"><small class="text-muted">Atenção</small><h3 class="text-warning mb-0"><?=e((string)($resultado['alertas'] ?? 0))?></h3></div></div>
  <div class="col-md-2 col-4"><div class="card-soft h-100"><small class="text-muted">Bloqueios</small><h3 class="text-danger mb-0"><?=e((string)($resultado['bloqueios'] ?? 0))?></h3></div></div>
</div>

<div class="alert alert-<?=$statusClass?>">
  <div><b>Resumo:</b> <?=e($resultado['resumo'] ?? '')?></div>
  <div class="small mt-1"><b>Próxima ação:</b> <?=e($resultado['proxima_acao'] ?? '')?></div>
  <div class="small mt-1"><b>Trace ID:</b> <code><?=e($resultado['trace_id'] ?? '')?></code> <?php if(!empty($resultado['trace_id'])): ?><a href="index.php?page=auditoria&trace=<?=urlencode($resultado['trace_id'])?>" class="ms-1">abrir auditoria</a><?php endif; ?></div>
</div>

<?php foreach($grupos as $grupo=>$items): ?>
<div class="card-soft mb-3">
  <h5 class="mb-3"><?=e($grupo)?></h5>
  <div class="table-responsive">
    <table class="table table-sm align-middle responsive-table producao-segura-table">
      <thead><tr><th>Item</th><th>Status</th><th>Risco</th><th>Ação</th><th>Detalhe</th></tr></thead>
      <tbody>
      <?php foreach($items as $c): ?>
        <?php
          $st = $c['status'] ?? (!empty($c['ok']) ? 'ok' : 'bloqueio');
          $cls = $st === 'ok' ? 'bg-success' : ($st === 'atencao' ? 'bg-warning text-dark' : 'bg-danger');
          $label = $st === 'atencao' ? 'ATENÇÃO' : ($st === 'bloqueio' ? 'BLOQUEIO' : 'OK');
        ?>
        <tr>
          <td data-label="Item"><b><?=e($c['nome'] ?? '')?></b></td>
          <td data-label="Status"><span class="badge <?=$cls?>"><?=e($label)?></span></td>
          <td data-label="Risco"><?=e($c['risco'] ?? '')?></td>
          <td data-label="Ação"><?=e($c['acao'] ?? '')?></td>
          <td data-label="Detalhe"><?=e($c['detalhe'] ?? '')?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endforeach; ?>
<?php else: ?>
<div class="card-soft">
  <div class="alert alert-info mb-0">
    Execute a validação para ver se o HUB pode ser ligado em produção com segurança. O sistema deve iniciar em homologação e só passar para produção após checklist sem bloqueios.
  </div>
</div>
<?php endif; ?>
<?php require __DIR__.'/layout_bottom.php'; ?>
