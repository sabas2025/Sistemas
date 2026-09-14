<?php require __DIR__.'/layout_top.php'; ?>
<?php $resultado = $_SESSION['enterprise_core_resultado'] ?? null; unset($_SESSION['enterprise_core_resultado']); ?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div>
    <h2><i class="bi bi-building-gear"></i> Enterprise Core</h2>
    <p class="text-muted mb-0">Camada para grande porte: migrações versionadas, event store, idempotência, DLQ classificada, observabilidade e governança LLM.</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-outline-primary" href="index.php?page=enterprise-observabilidade"><i class="bi bi-activity"></i> Observabilidade</a>
    <a class="btn btn-outline-dark" href="index.php?page=integration-events"><i class="bi bi-diagram-3"></i> Eventos</a>
    <a class="btn btn-outline-secondary" href="index.php?page=llm-governance"><i class="bi bi-robot"></i> LLM</a>
    <a class="btn btn-outline-success" href="index.php?page=enterprise-regression-tests"><i class="bi bi-check2-square"></i> Testes</a>
    <a class="btn btn-outline-info" href="index.php?page=design-system-enterprise"><i class="bi bi-stars"></i> Visual</a>
  </div>
</div>

<?php $dbd = !empty($resultado['database_diagnostics']) ? $resultado['database_diagnostics'] : ($status['connection'] ?? []); ?>
<?php if($resultado): ?>
<div class="alert alert-<?=empty($resultado['errors'])?'success':'warning'?>">
  <b>Resultado da aplicação:</b> <?=empty($resultado['errors'])?'sem erros.':'com avisos.'?>
  <div class="small mt-1"><b>Trace ID:</b> <code><?=e((string)($resultado['trace_id'] ?? ''))?></code></div>
</div>
<?php if(!empty($resultado['recommendation'])): ?>
<div class="alert alert-danger small"><b>Ação necessária:</b> <?=e((string)$resultado['recommendation'])?></div>
<?php endif; ?>
<div class="row g-3 mb-3">
  <div class="col-md-4"><div class="card-soft h-100"><small class="text-muted">Aplicados</small><h3><?=e((string)count($resultado['applied'] ?? []))?></h3><pre class="json-box small"><?=e(implode("\n", $resultado['applied'] ?? []))?></pre></div></div>
  <div class="col-md-4"><div class="card-soft h-100"><small class="text-muted">Ignorados</small><h3><?=e((string)count($resultado['skipped'] ?? []))?></h3><pre class="json-box small"><?=e(implode("\n", $resultado['skipped'] ?? []))?></pre></div></div>
  <div class="col-md-4"><div class="card-soft h-100"><small class="text-muted">Erros/Avisos</small><h3><?=e((string)count($resultado['errors'] ?? []))?></h3><pre class="json-box small"><?=e(implode("\n", $resultado['errors'] ?? []))?></pre></div></div>
</div>
<?php endif; ?>

<?php if(!empty($dbd)): ?>
<div class="card-soft mb-3 border border-info-subtle">
  <h5><i class="bi bi-database-check"></i> Conexão usada no diagnóstico/reparo</h5>
  <div class="row g-2 small">
    <div class="col-md-3"><b>Banco selecionado:</b><br><code><?=e((string)($dbd['database'] ?? ''))?></code></div>
    <div class="col-md-3"><b>Banco configurado:</b><br><code><?=e((string)($dbd['configured_database'] ?? ''))?></code></div>
    <div class="col-md-3"><b>Usuário MySQL:</b><br><code><?=e((string)($dbd['current_user'] ?? ''))?></code></div>
    <div class="col-md-3"><b>Modo:</b><br><code><?=e((string)($dbd['storage_mode'] ?? ''))?></code></div>
  </div>
  <?php if(!empty($dbd['error'])): ?><div class="alert alert-danger small mt-2 mb-0"><?=e((string)$dbd['error'])?></div><?php endif; ?>
  <p class="small text-muted mb-0 mt-2">O banco selecionado deve ser igual ao configurado. Se forem iguais e o CREATE falhar, atribua privilégios CREATE, ALTER, INDEX, SELECT, INSERT, UPDATE e DELETE ao usuário desse banco no cPanel.</p>
</div>
<?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-md-4"><div class="card-soft h-100"><small class="text-muted">Schema Enterprise</small><h3 class="<?=($status['status'] ?? '')==='ok'?'text-success':'text-warning'?>"><?=e(strtoupper($status['status'] ?? 'ATENCAO'))?></h3><p><?=e((string)($status['ok'] ?? 0))?>/<?=e((string)($status['total'] ?? 0))?> contratos de tabela, coluna, índice e conexão verificados.</p></div></div>
  <div class="col-md-4"><div class="card-soft h-100"><small class="text-muted">Quality Gate</small><h3 class="<?=($quality['status'] ?? '')==='ok'?'text-success':(($quality['status'] ?? '')==='bloqueio'?'text-danger':'text-warning')?>"><?=e((string)($quality['score'] ?? 0))?>%</h3><p>Status: <b><?=e(strtoupper($quality['status'] ?? 'ATENCAO'))?></b></p></div></div>
  <div class="col-md-4"><div class="card-soft h-100"><small class="text-muted">Migração</small><h5><?=e($status['migration'] ?? '')?></h5><p class="small text-muted mb-0">Aplicação idempotente. Pode rodar novamente sem duplicar tabelas/índices.</p></div></div>
</div>


<div class="alert alert-info small">
  <b>Como destravar tabelas PENDENTES:</b> primeiro clique em <b>Aplicar Enterprise Core</b>.
  Se o servidor/hospedagem bloquear a execução pelo painel, execute no phpMyAdmin o arquivo
  os arquivos <code>database/migrations/20260712_001_queue_oauth_concurrency.sql</code>, <code>20260712_002_schema_runtime_vsm_contract.sql</code>, <code>20260712_003_missing_core_tables.sql</code>, <code>20260712_004_vsm_security_selftest_recovery.sql</code> e <code>20260713_007_enterprise_map_recovery.sql</code>, nesta ordem.
  Esse SQL usa <code>CREATE TABLE IF NOT EXISTS</code>, não apaga dados e pode ser executado novamente.
</div>

<div class="card-soft mb-3 border border-warning-subtle">
  <h5><i class="bi bi-database-add"></i> Aplicar estrutura enterprise</h5>
  <p class="text-muted small">Cria ou repara as tabelas-base e enterprise exigidas pelos testes, além dos índices opcionais para grande porte. Não remove dados, não muda Tiny/VSM e mantém compatibilidade. Faça backup antes em produção. Em banco existente, esta ação cria as tabelas pendentes exibidas abaixo.</p>
  <form method="post" action="index.php?page=enterprise-core-aplicar" class="d-flex gap-2 flex-wrap">
    <?=Csrf::input()?>
    <button class="btn btn-primary" type="submit" data-confirm="Aplicar Enterprise Core agora? Confirme que existe backup recente."><i class="bi bi-check2-circle"></i> Aplicar Enterprise Core</button>
    <button class="btn btn-outline-secondary" type="submit" name="dry_run" value="1"><i class="bi bi-eye"></i> Simular</button>
    <a class="btn btn-outline-dark" href="index.php?page=backups"><i class="bi bi-shield-check"></i> Abrir Backups</a>
  </form>
</div>

<div class="card-soft table-responsive">
  <table class="table responsive-table align-middle">
    <thead><tr><th>Item</th><th>Status</th><th>Mensagem</th></tr></thead><tbody>
    <?php foreach(($status['checks'] ?? []) as $c): $ok=($c['status'] ?? '')==='ok'; ?>
      <tr><td data-label="Item"><b><?=e($c['item'] ?? '')?></b></td><td data-label="Status"><span class="badge <?=$ok?'bg-success':'bg-warning text-dark'?>"><?=e(strtoupper($c['status'] ?? ''))?></span></td><td data-label="Mensagem"><?=e($c['mensagem'] ?? '')?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
