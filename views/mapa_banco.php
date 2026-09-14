<?php require __DIR__.'/layout_top.php'; ?>
<div class="card-soft mb-3">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
    <div>
      <h4>Mapa do Banco</h4>
      <p class="text-muted mb-0">Mostra cada tabela conhecida pelo sistema, módulo/banco esperado, existência e colunas ausentes em relação ao SQL oficial. A abertura da tela é somente leitura; o reparo ocorre apenas por ação POST explícita, protegida por CSRF e confirmação.</p>
    </div>
    <?php if(isset($mapa['modo'])): ?><span class="badge text-bg-secondary"><?=e($mapa['modo'])?></span><?php endif; ?>
  </div>
</div>

<?php if(!empty($resultadoReparoMapa) && is_array($resultadoReparoMapa)): ?>
<div class="alert alert-<?=empty($resultadoReparoMapa['errors'])?'success':'warning'?>">
  <b>Resultado do reparo de estrutura:</b>
  <?=empty($resultadoReparoMapa['errors'])?'concluído e verificado.':'concluído com pendências.'?>
  <div class="small mt-1">Aplicados: <?=e((string)count($resultadoReparoMapa['applied'] ?? []))?> · Ignorados: <?=e((string)count($resultadoReparoMapa['skipped'] ?? []))?> · Erros: <?=e((string)count($resultadoReparoMapa['errors'] ?? []))?> · Trace: <code><?=e((string)($resultadoReparoMapa['trace_id'] ?? ''))?></code></div>
  <?php if(!empty($resultadoReparoMapa['errors'])): ?><pre class="json-box small mt-2 mb-0"><?=e(implode(PHP_EOL, $resultadoReparoMapa['errors']))?></pre><?php endif; ?>
</div>
<?php endif; ?>

<?php if(!empty($erroMapaBanco)): ?>
  <div class="alert alert-danger"><b>Erro ao carregar o mapa.</b><br><?=e($erroMapaBanco)?></div>
<?php endif; ?>
<?php if(isset($mapa) && is_array($mapa)): ?>
<?php
$enterpriseRecoveryTables = class_exists('SchemaMigrationService') ? SchemaMigrationService::enterpriseRecoveryTables() : [];
$enterprisePending = [];
foreach (($mapa['linhas'] ?? []) as $linhaMapa) {
  if (($linhaMapa['status'] ?? '') === 'erro' && in_array((string)($linhaMapa['tabela'] ?? ''), $enterpriseRecoveryTables, true)) {
    $enterprisePending[] = (string)$linhaMapa['tabela'];
  }
}
?>
<?php foreach(($mapa['warnings'] ?? []) as $w): ?>
  <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> <?=e($w)?></div>
<?php endforeach; ?>
<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="card-soft"><b>Total</b><h3><?=e($mapa['total'])?></h3></div></div>
  <div class="col-md-3"><div class="card-soft"><b>OK</b><h3><?=e($mapa['ok'])?></h3></div></div>
  <div class="col-md-3"><div class="card-soft"><b>Atenção</b><h3><?=e($mapa['atencao'])?></h3></div></div>
  <div class="col-md-3"><div class="card-soft"><b>Erro</b><h3><?=e($mapa['erro'])?></h3></div></div>
</div>
<div class="card-soft table-responsive">
<table class="table table-sm table-hover align-middle">
<thead><tr><th>Status</th><th>Tabela</th><th>Módulo</th><th>Banco</th><th>Colunas</th><th>Ausentes</th></tr></thead>
<tbody>
<?php foreach(($mapa['linhas'] ?? []) as $r): ?>
<tr>
  <td><span class="badge <?=($r['status']==='ok'?'text-bg-success':($r['status']==='atencao'?'text-bg-warning':'text-bg-danger'))?>"><?=e(strtoupper($r['status']))?></span></td>
  <td><code><?=e($r['tabela'])?></code></td>
  <td><?=e($r['modulo'])?></td>
  <td><?=e($r['banco'])?></td>
  <td><?=e($r['colunas'])?></td>
  <td><?=e(implode(', ', $r['colunas_ausentes'] ?? []))?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
<?php if(!empty($enterprisePending)): ?>
<div class="card-soft mt-3 border border-danger-subtle">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
      <h5 class="text-danger mb-1"><i class="bi bi-database-exclamation"></i> Recuperação Enterprise necessária</h5>
      <p class="text-muted small mb-2">Foram identificadas <?=e((string)count($enterprisePending))?> das 12 tabelas Enterprise pendentes. Esta ação cria somente essas estruturas, uma por vez, na conexão oficial de cada módulo, e valida na mesma conexão.</p>
      <div class="small"><b>Pendentes:</b> <code><?=e(implode(', ', $enterprisePending))?></code></div>
    </div>
    <form method="post" action="index.php?page=mapa-banco" data-confirm="Criar e verificar as tabelas Enterprise pendentes? Confirme que existe backup recente.">
      <?=Csrf::input()?>
      <input type="hidden" name="acao" value="apply_enterprise_tables">
      <button class="btn btn-danger" type="submit"><i class="bi bi-wrench-adjustable-circle"></i> Corrigir tabelas Enterprise</button>
    </form>
  </div>
  <div class="alert alert-light border small mt-3 mb-0">Alternativa pelo phpMyAdmin: execute <code>database/migrations/20260713_007_enterprise_map_recovery.sql</code>. O SQL é idempotente e não apaga dados.</div>
</div>
<?php endif; ?>

<div class="card-soft mt-3">
  <div class="alert alert-warning small mb-3">
    <b>Tabelas em ERRO:</b> aplique a estrutura abaixo após confirmar um backup recente. A ação usa somente CREATE TABLE IF NOT EXISTS, ALTER idempotente e índices ausentes; não apaga dados nem altera contratos Tiny/VSM.
  </div>
  <div class="d-flex flex-wrap gap-2">
    <form method="post" action="index.php?page=mapa-banco" data-confirm="Aplicar as correções de schema pendentes agora? Confirme que existe backup recente.">
      <?=Csrf::input()?>
      <input type="hidden" name="acao" value="apply_enterprise_core">
      <button class="btn btn-warning" type="submit"><i class="bi bi-database-add"></i> Aplicar tabelas e migrations pendentes</button>
    </form>
  <a class="btn btn-primary" href="index.php?page=validar-banco">Validar/Reparar Banco</a>
  <a class="btn btn-outline-primary" href="index.php?page=health-modulos">Health de Módulos</a>
  <a class="btn btn-outline-secondary" href="index.php?page=mapa-banco">Recarregar Mapa</a>
  <?php if(isset($mapa['gerado_em'])): ?><span class="text-muted small align-self-center">Gerado em <?=e($mapa['gerado_em'])?> | Trace <?=e($mapa['trace_id'] ?? '')?></span><?php endif; ?>
  </div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
