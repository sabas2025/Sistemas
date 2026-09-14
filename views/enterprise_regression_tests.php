<?php require __DIR__.'/layout_top.php'; ?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div><h2><i class="bi bi-check2-square"></i> Testes de Regressão Enterprise</h2><p class="text-muted mb-0">Validação rápida pós-atualização. Não chama Tiny/VSM real e não altera dados operacionais.</p></div>
  <div class="d-flex gap-2 flex-wrap"><a class="btn btn-outline-secondary" href="index.php?page=enterprise-core">Enterprise Core</a><a class="btn btn-primary" href="index.php?page=enterprise-regression-tests"><i class="bi bi-arrow-clockwise"></i> Executar novamente</a></div>
</div>
<div class="row g-3 mb-3">
  <div class="col-md-3 col-6"><div class="metric-orb"><small>Score</small><strong><?=e((string)($resultado['score'] ?? 0))?>%</strong><span>regressão</span></div></div>
  <div class="col-md-3 col-6"><div class="metric-orb"><small>OK</small><strong class="text-success"><?=e((string)($resultado['ok'] ?? 0))?></strong><span>passaram</span></div></div>
  <div class="col-md-3 col-6"><div class="metric-orb"><small>Erros</small><strong class="text-danger"><?=e((string)($resultado['erro'] ?? 0))?></strong><span>corrigir</span></div></div>
  <div class="col-md-3 col-6"><div class="metric-orb"><small>Trace</small><strong style="font-size:1rem"><code><?=e((string)($resultado['trace_id'] ?? ''))?></code></strong><span>auditoria</span></div></div>
</div>

<?php if (($resultado['erro'] ?? 0) > 0): ?>
<div class="alert alert-warning mt-3">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div><b>Estrutura incompleta detectada.</b><br><small>A reparação usa os SQLs oficiais, não apaga dados e também corrige o status <code>ignorado</code> da fila.</small></div>
    <form method="post" action="index.php?page=enterprise-core-aplicar" data-confirm="Aplicar reparação segura do Enterprise Core agora?">
      <?=Csrf::input()?>
      <button class="btn btn-warning" type="submit"><i class="bi bi-database-check"></i> Reparar Enterprise Core</button>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card-soft table-responsive">
  <table class="table responsive-table align-middle"><thead><tr><th>Teste</th><th>Status</th><th>Mensagem</th><th>Duração</th></tr></thead><tbody>
  <?php foreach(($resultado['tests'] ?? []) as $t): $ok=($t['status'] ?? '')==='ok'; ?>
    <tr><td data-label="Teste"><b><?=e($t['label'] ?? '')?></b><br><small class="text-muted"><?=e($t['key'] ?? '')?></small></td><td data-label="Status"><span class="badge <?=$ok?'bg-success':'bg-danger'?>"><?=e(strtoupper($t['status'] ?? ''))?></span></td><td data-label="Mensagem"><?=e($t['message'] ?? '')?></td><td data-label="Duração"><?=e((string)($t['duration_ms'] ?? ''))?> ms</td></tr>
  <?php endforeach; ?>
  </tbody></table>
</div>
<div class="alert alert-info mt-3 small"><b>Opinião técnica:</b> mantenha estes testes com 100% antes de refatorar controllers, alterar workers ou liberar produção. Eles são smoke tests; para grande porte, evolua depois para PHPUnit + testes de integração com mocks Tiny/VSM.</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
