<?php require __DIR__.'/layout_top.php'; ?>
<div class="card-soft mb-3">
  <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">
    <div><h4><i class="bi bi-file-earmark-check"></i> Evidências de Homologação</h4><p class="text-muted mb-0">Relatório imprimível por Trace ID para anexar na aprovação da homologação.</p></div>
    <?php if($trace): ?><button class="btn btn-outline-secondary js-print" type="button"><i class="bi bi-printer"></i> Imprimir / Salvar PDF</button><?php endif; ?>
  </div>
</div>
<form class="row g-2 mb-3">
  <input type="hidden" name="page" value="evidencias-homologacao">
  <div class="col-md-9"><input class="form-control" name="trace" value="<?=e($trace)?>" placeholder="Cole o Trace ID da homologação"></div>
  <div class="col-md-3"><button class="btn btn-primary w-100">Gerar evidência</button></div>
</form>
<?php if($trace): ?>
<div class="card-soft mb-3">
  <h5>Resumo</h5>
  <table class="table table-sm"><tr><th>Trace ID</th><td><code><?=e($trace)?></code></td></tr><tr><th>Eventos encontrados</th><td><?=count($eventos)?></td></tr><tr><th>Gerado em</th><td><?=date('d/m/Y H:i:s')?></td></tr></table>
</div>
<div class="card-soft table-responsive">
<table class="table table-sm align-middle"><thead><tr><th>Data</th><th>Evento</th><th>Nível</th><th>Mensagem</th></tr></thead><tbody>
<?php foreach($eventos as $ev): ?><tr><td><?=e($ev['criado_em'] ?? $ev['data_hora'] ?? '')?></td><td><code><?=e($ev['evento'] ?? $ev['tipo'] ?? '')?></code></td><td><?=e($ev['nivel'] ?? $ev['status'] ?? '')?></td><td><?=e($ev['mensagem'] ?? '')?></td></tr><?php endforeach; ?>
<?php if(empty($eventos)): ?><tr><td colspan="4" class="text-muted">Nenhum evento encontrado para este Trace ID.</td></tr><?php endif; ?>
</tbody></table>
</div>
<?php endif; ?>
<?php require __DIR__.'/layout_bottom.php'; ?>
