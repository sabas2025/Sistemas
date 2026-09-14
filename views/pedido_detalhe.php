<?php require __DIR__.'/layout_top.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <a class="btn btn-outline-secondary" href="index.php?page=pedidos">← Voltar</a>
  <button class="btn btn-outline-primary" type="button" data-copy-text="<?=e($pedido['trace_id'] ?? '')?>">Copiar Trace ID</button>
</div>
<div class="row g-3">
  <div class="col-lg-4"><div class="panel h-100"><div class="panel-header"><h2>Resumo</h2></div>
    <p><b>ID:</b> <?=e($pedido['id'])?></p>
    <p><b>Pedido origem:</b> <?=e($pedido['pedido_origem_id'])?></p>
    <p><b>Pedido Tiny:</b> <?=e($pedido['pedido_tiny_id'] ?: '-')?></p>
    <p><b>Cliente:</b> <?=e($pedido['cliente_nome'] ?: '-')?></p>
    <p><b>Documento:</b> <?=e($pedido['cliente_documento'] ?: '-')?></p>
    <p><b>Valor:</b> R$ <?=number_format((float)$pedido['valor_total'],2,',','.')?></p>
    <p><b>Status:</b> <span class="badge-status status-<?=e($pedido['status'])?>"><?=e($pedido['status'])?></span></p>
    <p><b>Tentativas:</b> <?=e($pedido['tentativas'])?></p>
    <p><b>Trace ID:</b><br><code><?=e($pedido['trace_id'])?></code></p>
  </div></div>
  <div class="col-lg-8"><div class="panel h-100"><div class="panel-header"><h2>Timeline de auditoria</h2></div>
    <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Data</th><th>Ação</th><th>Status</th><th>Mensagem</th></tr></thead><tbody>
    <?php foreach($timeline as $t): ?><tr><td><?=e($t['criado_em'])?></td><td><?=e($t['acao'])?></td><td><?=e($t['status'])?></td><td><?=e($t['mensagem'] ?: '-')?></td></tr><?php endforeach; ?>
    <?php if(!$timeline): ?><tr><td colspan="4" class="text-muted text-center">Sem eventos para este Trace ID.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div></div>
</div>
<div class="row g-3 mt-1">
  <div class="col-lg-6"><div class="panel"><div class="panel-header"><h2>Payload VSM</h2></div><pre class="jsonbox"><?=e(json_encode(json_decode($pedido['payload_origem'] ?? '[]', true), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?></pre></div></div>
  <div class="col-lg-6"><div class="panel"><div class="panel-header"><h2>Payload Tiny</h2></div><pre class="jsonbox"><?=e(json_encode(json_decode($pedido['payload_tiny'] ?? '[]', true), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?></pre></div></div>
  <div class="col-12"><div class="panel"><div class="panel-header"><h2>Retorno Tiny / Erro</h2></div><pre class="jsonbox"><?=e(json_encode(json_decode(($pedido['retorno_tiny'] ?: $pedido['erro']) ?? '[]', true), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?></pre></div></div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
