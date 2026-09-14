<?php require __DIR__.'/layout_top.php'; ?>
<div class="cardx mb-3">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
    <div>
      <h4 class="mb-1">Configuração dos Webhooks Tiny/Olist</h4>
      <p class="text-muted mb-2">Cadastre estas URLs no Tiny/Olist conforme o tipo de evento. Use HTTPS em produção.</p>
      <?php $scriptDir = str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/public/index.php')); $base = rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http').'://'.($_SERVER['HTTP_HOST'] ?? 'localhost').$scriptDir, '/'); ?>
      <div class="codebox small">Estoque: <?=e($base.'/index.php?page=api/tiny/webhook/estoque')?><br>Produto: <?=e($base.'/index.php?page=api/tiny/webhook/produto')?><br>NF-e: <?=e($base.'/index.php?page=api/tiny/webhook/nota-fiscal')?><br>Situação pedido: <?=e($base.'/index.php?page=api/tiny/webhook/situacao-pedido')?></div>
    </div>
    <div class="text-end"><a class="btn btn-sm btn-outline-primary" href="index.php?page=configuracoes"><i class="bi bi-shield-lock"></i> Segurança dos webhooks</a><br><a class="btn btn-sm btn-outline-warning mt-2" href="index.php?page=tiny-webhooks&status=duplicado">Ver duplicados ignorados</a></div>
  </div>
</div>
<div class="cardx">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h4 class="mb-1">Webhooks Tiny/Olist recebidos</h4>
      <p class="text-muted mb-0">Histórico bruto dos eventos oficiais: estoque, produto, NF-e e situação de pedido.</p>
    </div>
    <span class="badge bg-primary"><?=count($webhooks)?> registro(s)</span>
  </div>
  <form class="row g-2 mb-3" method="get">
    <input type="hidden" name="page" value="tiny-webhooks">
    <div class="col-md-3"><select name="tipo" class="form-select"><option value="">Todos os tipos</option><?php foreach(['estoque','produto','nota_fiscal','situacao_pedido','generico'] as $t): ?><option value="<?=$t?>" <?=$tipo===$t?'selected':''?>><?=$t?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><select name="status" class="form-select"><option value="">Todos os status</option><?php foreach(['recebido','enfileirado','respondido','registrado','ignorado','duplicado','erro'] as $st): ?><option value="<?=$st?>" <?=$status===$st?'selected':''?>><?=$st?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><input class="form-control" name="busca" value="<?=e($busca)?>" placeholder="Referência, Trace ID, CNPJ ou idEcommerce"></div>
    <div class="col-md-2"><button class="btn btn-primary w-100">Filtrar</button></div>
  </form>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead><tr><th>ID</th><th>Tipo</th><th>Status</th><th>Referência</th><th>CNPJ</th><th>Repetições</th><th>Trace ID</th><th>Data</th></tr></thead>
      <tbody>
      <?php foreach($webhooks as $w): ?>
        <tr>
          <td>#<?=e($w['id'])?></td>
          <td><span class="badge bg-secondary"><?=e($w['tipo'])?></span></td>
          <td><span class="badge bg-<?=in_array($w['status'],['erro'])?'danger':(in_array($w['status'],['enfileirado','respondido','registrado'])?'success':'warning')?>"><?=e($w['status'])?></span></td>
          <td><?=e($w['referencia'] ?: '-')?></td>
          <td><?=e($w['cnpj'] ?: '-')?></td>
          <td><?=e($w['recebido_repetido'] ?? 0)?></td>
          <td><code><?=e($w['trace_id'])?></code></td>
          <td><?=e($w['criado_em'])?></td>
        </tr>
        <tr><td colspan="8"><details><summary>Ver payload/retorno</summary><div class="row mt-2"><div class="col-md-6"><b>Payload</b><pre class="jsonbox"><?=e(json_encode(json_decode($w['payload'] ?? '[]', true), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?></pre></div><div class="col-md-6"><b>Retorno</b><pre class="jsonbox"><?=e(json_encode(json_decode($w['retorno'] ?? '[]', true), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?></pre></div></div></details></td></tr>
      <?php endforeach; if(!$webhooks): ?><tr><td colspan="8" class="text-center text-muted">Nenhum webhook Tiny/Olist registrado.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
