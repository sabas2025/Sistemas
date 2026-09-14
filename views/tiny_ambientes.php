<?php require __DIR__.'/layout_top.php'; ?>
<div class="card-soft mb-3">
  <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">
    <div>
      <h4><i class="bi bi-diagram-3"></i> Tiny V2 e Tiny V3 - Configurações e Homologação Separadas</h4>
      <p class="text-muted mb-0">Tela executiva para não misturar Tiny V2 em produção com Tiny V3 em homologação/OAuth.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-outline-primary btn-sm" href="index.php?page=configuracoes">Configurações</a>
      <a class="btn btn-outline-warning btn-sm" href="index.php?page=tiny-v3-ficha">Ficha Tiny V3</a>
      <a class="btn btn-outline-success btn-sm" href="index.php?page=homologacao">Checklist</a>
      <a class="btn btn-success btn-sm" href="index.php?page=tiny-v2-homologacao">Homologação Tiny V2</a>
      <a class="btn btn-warning btn-sm" href="index.php?page=tiny-v3-homologacao">Homologação Tiny V3</a>
    </div>
  </div>
</div>
<div class="row g-3">
  <div class="col-lg-6">
    <div class="card-soft h-100 border border-success-subtle">
      <h5>🟢 Tiny V2</h5>
      <p class="text-muted">Canal recomendado para produção enquanto V3 não estiver 100% homologado.</p>
      <table class="table table-sm">
        <tr><th>URL</th><td><code><?=e($cfg['tiny_v2_url'] ?? '')?></code></td></tr>
        <tr><th>Token</th><td><?=trim((string)($cfg['tiny_v2_token'] ?? ''))!==''?'Configurado':'<span class="text-danger">Ausente</span>'?></td></tr>
        <tr><th>Status</th><td><span class="badge <?=($analise['v2']['status']==='ok'?'text-bg-success':'text-bg-warning')?>"><?=e($analise['v2']['status'])?></span></td></tr>
      </table>
      <?php if($analise['v2']['pendencias']): ?><ul class="small text-danger"><?php foreach($analise['v2']['pendencias'] as $p): ?><li><?=e($p)?></li><?php endforeach; ?></ul><?php endif; ?>
      <div class="alert alert-light border small mb-0"><?=e($analise['v2']['recomendacao'])?></div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card-soft h-100 border border-warning-subtle">
      <h5>🟡 Tiny V3</h5>
      <p class="text-muted">Canal separado para OAuth, homologação e piloto. Não marcar operacional sem evidências.</p>
      <table class="table table-sm">
        <tr><th>Ambiente V3</th><td><?=e($cfg['tiny_v3_ambiente'] ?? 'homologacao')?></td></tr>
        <tr><th>Base API</th><td><code><?=e($cfg['tiny_v3_url'] ?? '')?></code></td></tr>
        <tr><th>Client ID</th><td><?=trim((string)($cfg['tiny_v3_client_id'] ?? ''))!==''?'Configurado':'<span class="text-danger">Ausente</span>'?></td></tr>
        <tr><th>Redirect URI</th><td><code><?=e($cfg['tiny_v3_redirect_uri'] ?? '')?></code></td></tr>
        <tr><th>Operacional</th><td><?=!empty($cfg['tiny_v3_operacional'])?'<span class="badge text-bg-success">Sim</span>':'<span class="badge text-bg-warning">Não</span>'?></td></tr>
        <tr><th>Status</th><td><span class="badge <?=($analise['v3']['status']==='ok'?'text-bg-success':'text-bg-warning')?>"><?=e($analise['v3']['status'])?></span></td></tr>
      </table>
      <?php if($analise['v3']['pendencias']): ?><ul class="small text-danger"><?php foreach($analise['v3']['pendencias'] as $p): ?><li><?=e($p)?></li><?php endforeach; ?></ul><?php endif; ?>
      <div class="alert alert-light border small mb-0"><?=e($analise['v3']['recomendacao'])?></div>
    </div>
  </div>
</div>
<div class="card-soft mt-3">
  <h5>Regra operacional recomendada</h5>
  <ol class="mb-0">
    <li><b>Tiny V2</b> fica como produção/fallback até finalizar V3.</li>
    <li><b>Tiny V3</b> fica em homologação, com OAuth separado, Redirect URI validada e checklist próprio.</li>
    <li>Produto, estoque, pedido e fiscal devem ter evidência com Trace ID antes de ativar V3 operacional.</li>
    <li>Nunca misturar token manual legado V3 com produção.</li>
  </ol>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
