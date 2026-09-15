<?php require __DIR__.'/layout_top.php'; ?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div>
    <h2><i class="bi bi-rocket-takeoff"></i> Entrada em Produção</h2>
    <p class="text-muted mb-0">Checklist executivo para liberar o Hub de Integração com segurança.</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-outline-secondary" href="index.php?page=central-homologacao"><i class="bi bi-diagram-3"></i> Central de Homologação</a>
    <a class="btn btn-outline-secondary" href="index.php?page=backups"><i class="bi bi-database-down"></i> Backups</a>
  </div>
</div>

<?php if(!empty($_SESSION['form_error'])): ?><div class="alert alert-danger"><?=e($_SESSION['form_error']); unset($_SESSION['form_error']);?></div><?php endif; ?>
<?php if(!empty($erro)): ?><div class="alert alert-warning"><?=e($erro)?></div><?php endif; ?>
<?php if(!empty($lockResultado)): ?><div class="alert alert-success"><b><?=e($lockResultado['mensagem'] ?? 'Lock criado.')?></b><br><small><?=e($lockResultado['arquivo'] ?? '')?></small></div><?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-md-8">
    <div class="card-soft h-100">
      <h5 class="mb-2">Validação antes de virar produção</h5>
      <p class="text-muted">Executa checagens de ambiente, segurança, Tiny V2/V3, VSM, XML/NF-e, backups, auditoria e PWA.</p>
      <form method="post" action="index.php?page=entrada-producao-validar" class="d-flex gap-2 flex-wrap">
        <?=Csrf::input()?>
        <button class="btn btn-success"><i class="bi bi-shield-check"></i> Validar Entrada em Produção</button>
        <a class="btn btn-outline-primary" href="index.php?page=tiny-v2-homologacao">Tiny V2</a>
        <a class="btn btn-outline-primary" href="index.php?page=tiny-v3-homologacao">Tiny V3</a>
      </form>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card-soft h-100 border border-warning-subtle">
      <h5><i class="bi bi-lock"></i> Proteger instalador</h5>
      <p class="text-muted small">Depois de instalar, crie o lock. Em produção, também remova ou bloqueie <code>public/install.php</code>.</p>
      <form method="post" action="index.php?page=entrada-producao-lock-install">
        <?=Csrf::input()?>
        <input class="form-control form-control-sm mb-2" name="confirmacao" placeholder="Digite BLOQUEAR">
        <button class="btn btn-warning btn-sm w-100"><i class="bi bi-lock-fill"></i> Criar lock de instalação</button>
      </form>
    </div>
  </div>
</div>

<?php if($resultado): ?>
  <?php $badge = $resultado['status']==='apto'?'bg-success':($resultado['status']==='apto_com_alertas'?'bg-warning text-dark':'bg-danger'); ?>
  <div class="card-soft mb-3">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
      <div>
        <span class="badge <?=$badge?> fs-6"><?=e(strtoupper(str_replace('_',' ', $resultado['status'])))?></span>
        <h4 class="mt-2 mb-1">Score: <?=e((string)$resultado['score'])?>%</h4>
        <p class="mb-0 text-muted"><?=e($resultado['resumo'])?></p>
      </div>
      <div class="text-end">
        <small class="text-muted d-block">Trace ID</small>
        <code><?=e($resultado['trace_id'])?></code>
        <button class="btn btn-sm btn-outline-secondary ms-1" type="button" data-copy-text="<?=e($resultado['trace_id'])?>"><i class="bi bi-clipboard"></i></button>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card-soft"><small class="text-muted">Bloqueios</small><h3 class="text-danger"><?=e((string)$resultado['bloqueios'])?></h3></div></div>
    <div class="col-md-4"><div class="card-soft"><small class="text-muted">Alertas</small><h3 class="text-warning"><?=e((string)$resultado['alertas'])?></h3></div></div>
    <div class="col-md-4"><div class="card-soft"><small class="text-muted">Executado em</small><h6><?=e($resultado['executado_em'])?></h6></div></div>
  </div>

  <?php $grupos=[]; foreach($resultado['checks'] as $c){ $grupos[$c['grupo']][]=$c; } ?>
  <?php foreach($grupos as $grupo=>$checks): ?>
    <div class="card-soft mb-3">
      <h5 class="mb-3"><?=e($grupo)?></h5>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead><tr><th>Item</th><th>Status</th><th>Risco</th><th>Ação recomendada</th></tr></thead>
          <tbody>
            <?php foreach($checks as $c): ?>
              <?php $cls=$c['status']==='ok'?'bg-success':($c['status']==='alerta'?'bg-warning text-dark':'bg-danger'); ?>
              <tr>
                <td><b><?=e($c['nome'])?></b></td>
                <td><span class="badge <?=$cls?>"><?=e(strtoupper($c['status']))?></span></td>
                <td><?=e($c['risco'])?></td>
                <td><?=e($c['acao'])?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endforeach; ?>
<?php else: ?>
  <div class="alert alert-info">Execute a validação para saber se o Hub está apto para produção. O sistema só deve ser liberado sem bloqueios críticos.</div>
<?php endif; ?>

<div class="card-soft mb-3">
  <h5>Histórico recente</h5>
  <div class="table-responsive">
    <table class="table table-sm">
      <thead><tr><th>Data</th><th>Status</th><th>Score</th><th>Bloqueios</th><th>Alertas</th><th>Trace</th></tr></thead>
      <tbody>
        <?php foreach($historico as $h): ?>
          <tr><td><?=e($h['criado_em'] ?? '')?></td><td><?=e($h['status'] ?? '')?></td><td><?=e((string)($h['score'] ?? ''))?>%</td><td><?=e((string)($h['bloqueios'] ?? ''))?></td><td><?=e((string)($h['alertas'] ?? ''))?></td><td><code><?=e($h['trace_id'] ?? '')?></code></td></tr>
        <?php endforeach; if(empty($historico)): ?><tr><td colspan="6" class="text-muted">Nenhuma validação registrada.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
