<?php
$health = isset($health) && is_array($health) ? $health : [];
$health = array_values(array_filter($health, static fn($row): bool => is_array($row)));
$statuses = array_map(static fn(array $row): string => (string)($row['health_status'] ?? 'nao_testado'), $health);
$counts = array_count_values($statuses);
require __DIR__.'/layout_top.php';
?>
<div class="panel">
  <div class="panel-header">
    <h2><i class="bi bi-heart-pulse"></i> Saúde da Integração VSM</h2>
    <span class="text-muted">Status por endpoint configurado</span>
  </div>
  <div class="p-4">
    <?php if ($erro): ?><div class="alert alert-danger"><?= e($erro) ?></div><?php endif; ?>

    <div class="row g-3 mb-3">
      <?php foreach (['online'=>'Online','redirecionado'=>'Redirecionado','erro'=>'Erro','inativo'=>'Inativo','nao_verificado'=>'Não verificado','nao_testado'=>'Não testado'] as $k=>$label): ?>
        <div class="col-6 col-md">
          <div class="card-soft h-100">
            <div class="text-muted small"><?= e($label) ?></div>
            <h3><?= e($counts[$k] ?? 0) ?></h3>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($health === []): ?>
      <div class="alert alert-info mb-3">Nenhum endpoint VSM configurado ou testado até o momento.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead><tr><th>Categoria</th><th>Endpoint</th><th>Status</th><th>HTTP</th><th>Tempo</th><th>Última execução</th><th>Erro</th></tr></thead>
          <tbody>
            <?php foreach ($health as $h):
              $status = (string)($h['health_status'] ?? 'nao_testado');
              $cls = ['online'=>'bg-success','redirecionado'=>'bg-warning text-dark','erro'=>'bg-danger','inativo'=>'bg-secondary','nao_verificado'=>'bg-warning text-dark','nao_testado'=>'bg-info text-dark'][$status] ?? 'bg-secondary';
            ?>
              <tr>
                <td data-label="Categoria"><?= e($h['categoria'] ?? '-') ?></td>
                <td data-label="Endpoint"><b><?= e($h['nome'] ?? '-') ?></b><br><code><?= e($h['endpoint'] ?? '-') ?></code></td>
                <td data-label="Status"><span class="badge <?= e($cls) ?>"><?= e($status) ?></span></td>
                <td data-label="HTTP"><?= e($h['ultimo_status_http'] ?? '-') ?></td>
                <td data-label="Tempo"><?= e(isset($h['ultimo_tempo_ms']) ? $h['ultimo_tempo_ms'].' ms' : '-') ?></td>
                <td data-label="Última execução"><?= e($h['ultima_execucao_em'] ?? 'Nunca') ?></td>
                <td data-label="Erro" class="small text-danger"><?= e($h['ultimo_erro'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <div class="mt-3"><a class="btn btn-primary" href="index.php?page=vsm-endpoints">Testar endpoints</a></div>
  </div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
