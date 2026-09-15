<?php require __DIR__.'/layout_top.php'; ?>
<?php
$checks = [];
$statusFinal = 'atencao';
$modo = 'validacao_leitura_segura';
$parcial = false;
$duracao = 0;
$traceId = '';
$total = ['ok'=>0,'atencao'=>0,'erro'=>0,'total'=>0];
if (isset($resultado) && is_array($resultado)) {
  $checks = array_map(function($c){
    $status = $c['status'] ?? 'atencao';
    return [
      'nome'=>$c['titulo'] ?? ($c['nome'] ?? ''),
      'ok'=>$status==='ok' || !empty($c['ok']),
      'status'=>$status,
      'mensagem'=>$c['mensagem'] ?? '',
      'acao'=>$c['acao'] ?? '',
      'detalhe'=>trim(($c['mensagem'] ?? '').' '.($c['acao'] ?? '')),
    ];
  }, $resultado['checks'] ?? []);
  $statusFinal = $resultado['status'] ?? 'atencao';
  $modo = $resultado['modo'] ?? 'validacao_leitura_segura';
  $parcial = !empty($resultado['parcial']);
  $duracao = (int)($resultado['duracao_ms'] ?? 0);
  $traceId = (string)($resultado['trace_id'] ?? '');
  $total = [
    'ok'=>(int)($resultado['ok'] ?? 0),
    'atencao'=>(int)($resultado['atencao'] ?? 0),
    'erro'=>(int)($resultado['erro'] ?? 0),
    'total'=>(int)($resultado['total'] ?? count($checks)),
  ];
}
$isRepair = ($modo === 'reparo_manual') || !empty($reparar);
$statusClass = $statusFinal==='ok' ? 'success' : ($statusFinal==='erro' ? 'danger' : 'warning');
$statusLabel = $statusFinal==='ok' ? 'OK' : ($statusFinal==='erro' ? 'ERRO' : 'ATENÇÃO');
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div>
    <h2><i class="bi bi-database-check"></i> <?= $isRepair ? 'Reparar Banco de Dados' : 'Validar Banco de Dados' ?></h2>
    <p class="text-muted mb-0">
      <?= $isRepair
        ? 'Reparo manual executado com CSRF, permissão e limite de segurança.'
        : 'Validação em modo leitura segura. O sistema não executa CREATE/ALTER automaticamente ao abrir esta tela.' ?>
    </p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-outline-primary" href="index.php?page=mapa-banco"><i class="bi bi-diagram-3"></i> Mapa do Banco</a>
    <a class="btn btn-outline-secondary" href="index.php?page=validar-banco"><i class="bi bi-arrow-clockwise"></i> Revalidar</a>
    <?php if($traceId): ?><a class="btn btn-outline-dark" href="index.php?page=auditoria&trace=<?=urlencode($traceId)?>"><i class="bi bi-search"></i> Auditoria</a><?php endif; ?>
  </div>
</div>

<?php if(isset($resultado)): ?>
<div class="row g-3 mb-3 validar-banco-summary">
  <div class="col-md-3 col-6"><div class="card-soft h-100 border-<?=$statusClass?>"><small class="text-muted">Status</small><h3 class="text-<?=$statusClass?> mb-0"><?=e($statusLabel)?></h3></div></div>
  <div class="col-md-3 col-6"><div class="card-soft h-100"><small class="text-muted">Erros críticos</small><h3 class="text-danger mb-0"><?=e((string)$total['erro'])?></h3></div></div>
  <div class="col-md-3 col-6"><div class="card-soft h-100"><small class="text-muted">Atenções</small><h3 class="text-warning mb-0"><?=e((string)$total['atencao'])?></h3></div></div>
  <div class="col-md-3 col-6"><div class="card-soft h-100"><small class="text-muted">Duração</small><h3 class="mb-0"><?=e((string)$duracao)?> ms</h3></div></div>
</div>

<div class="alert alert-<?=$statusClass?>">
  <div><b>Resultado:</b> <?=e($statusLabel)?> — OK <?=e((string)$total['ok'])?>, Atenção <?=e((string)$total['atencao'])?>, Erro <?=e((string)$total['erro'])?>.</div>
  <div class="small mt-1"><b>Modo:</b> <?=e($modo)?> · <b>Trace ID:</b> <code><?=e($traceId)?></code></div>
  <?php if($parcial): ?><div class="small mt-1"><b>Aviso:</b> validação parcial por limite de segurança. Isso evita loop/travamento em hospedagem compartilhada.</div><?php endif; ?>
  <?php if($statusFinal!=='erro' && $parcial): ?><div class="small mt-1">Não é erro fatal: é uma validação curta. Para diagnóstico profundo, use Mapa do Banco ou reparo manual em janela controlada.</div><?php endif; ?>
</div>
<?php endif; ?>

<?php if(!$isRepair): ?>
<div class="card-soft mb-3 border border-warning-subtle">
  <h5 class="mb-2"><i class="bi bi-tools"></i> Reparo de schema</h5>
  <p class="text-muted small mb-2">
    O reparo não roda automaticamente. Use somente após backup recente, pois pode executar CREATE/ALTER/índices para alinhar o banco ao SQL oficial.
    Isso preserva Tiny/VSM, mas pode exigir permissão MySQL de alteração de schema.
  </p>
  <form method="post" action="index.php?page=validar-banco" data-confirm="Executar reparo manual do banco agora? Confirme que existe backup recente antes de continuar." class="d-flex gap-2 flex-wrap">
    <?=Csrf::input()?>
    <input type="hidden" name="acao" value="repair_schema">
    <button type="submit" class="btn btn-warning"><i class="bi bi-tools"></i> Reparar Schema Manualmente</button>
    <a class="btn btn-outline-dark" href="index.php?page=backups"><i class="bi bi-shield-check"></i> Abrir Backups</a>
  </form>
</div>
<?php else: ?>
<div class="alert alert-warning">Reparo manual executado. Revise os itens abaixo e valide novamente em modo leitura segura.</div>
<?php endif; ?>

<div class="card-soft table-responsive">
<table class="table table-hover align-middle responsive-table validar-banco-table">
<thead><tr><th>Item</th><th>Status</th><th>Mensagem</th><th>Ação recomendada</th></tr></thead>
<tbody>
<?php foreach($checks ?? [] as $c): ?>
  <?php $st=$c['status'] ?? ($c['ok']?'ok':'erro'); $cls=$st==='ok'?'bg-success':($st==='atencao'?'bg-warning text-dark':'bg-danger'); $label=$st==='atencao'?'ATENÇÃO':strtoupper($st); ?>
  <tr>
    <td data-label="Item"><b><?=e($c['nome'])?></b></td>
    <td data-label="Status"><span class="badge <?=$cls?>"><?=e($label)?></span></td>
    <td data-label="Mensagem"><?=e($c['mensagem'] ?? '')?></td>
    <td data-label="Ação"><?=e($c['acao'] ?? '')?></td>
  </tr>
<?php endforeach; ?>
<?php if(empty($checks)): ?><tr><td colspan="4" class="text-muted">Nenhum item retornado pela validação.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
<div class="card-soft mt-3">
  <h5>Ação recomendada</h5>
  <p><?=e($resultado['acao_recomendada'] ?? 'Se existir falha, faça backup, revise config.php/permissões MySQL e aplique reparo em ambiente controlado.')?></p>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
