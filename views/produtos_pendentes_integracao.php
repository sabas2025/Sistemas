<?php require __DIR__.'/layout_top.php'; ?>
<?php if(isset($erro)): ?><div class="alert alert-danger">Erro: <?=e($erro)?></div><?php endif; ?>
<div class="alert alert-warning">
  <b>Governança de produto novo VSM → Tiny:</b> produto sem mapeamento não é criado automaticamente. Ele fica pendente para aprovar, rejeitar ou vincular a um produto Tiny existente.
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-8">
    <div class="card-soft h-100">
      <h4><i class="bi bi-shield-check"></i> Produtos pendentes de aprovação</h4>
      <p class="text-muted mb-0">Valide SKU, EAN, categoria, NCM e duplicidade antes de permitir criação no Tiny.</p>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card-soft h-100">
      <a class="btn btn-outline-primary w-100 mb-2" href="index.php?page=categorias-mapeamento"><i class="bi bi-tags"></i> Mapear categorias VSM ↔ Tiny</a>
      <a class="btn btn-outline-secondary w-100" href="index.php?page=orquestracao-integracoes"><i class="bi bi-diagram-3"></i> Configurar fluxos</a>
    </div>
  </div>
</div>

<form class="row g-2 mb-3">
  <input type="hidden" name="page" value="produtos-pendentes-integracao">
  <div class="col-md-3"><select class="form-select" name="status"><option value="">Todos</option><?php foreach(['pendente','aprovado','rejeitado','vinculado','erro'] as $s): ?><option value="<?=$s?>" <?=$status===$s?'selected':''?>><?=$s?></option><?php endforeach; ?></select></div>
  <div class="col-md-6"><input class="form-control" name="busca" value="<?=e($busca)?>" placeholder="SKU, EAN, nome, categoria ou Trace ID"></div>
  <div class="col-md-3"><button class="btn btn-primary w-100">Filtrar</button></div>
</form>

<div class="card-soft table-responsive">
<table class="table table-hover align-middle">
<thead><tr><th>ID</th><th>Produto</th><th>Categoria VSM</th><th>Categoria Tiny sugerida</th><th>Checklist V46</th><th>Status</th><th>Motivo</th><th>Ações</th><th>Payload</th></tr></thead>
<tbody>
<?php foreach(($pendentes ?? []) as $p): ?>
<tr>
  <td><?=e($p['id'])?></td>
  <td><b><?=e($p['sku'])?></b><br><small><?=e($p['nome'] ?: '-')?></small><br><small>EAN: <?=e($p['ean'] ?: '-')?></small></td>
  <td><small>ID: <?=e($p['categoria_vsm_id'] ?: '-')?></small><br><?=e($p['categoria_vsm_nome'] ?: '-')?></td>
  <td><small>ID: <?=e($p['categoria_tiny_id_sugerida'] ?: '-')?></small><br><?=e($p['categoria_tiny_nome_sugerida'] ?: 'Sem mapeamento')?></td>
  <?php $ck = class_exists('ProdutoVsmApprovalGuardService') ? ProdutoVsmApprovalGuardService::checklist($p) : ['pode_aprovar'=>false,'bloqueios'=>['Serviço V46 ausente']]; ?>
  <td><?php if($ck['pode_aprovar']): ?><span class="badge bg-success">liberável</span><?php else: ?><span class="badge bg-danger">bloqueado</span><br><small><?=e(count($ck['bloqueios']))?> pendência(s)</small><?php endif; ?></td>
  <td><span class="badge bg-<?=($p['status']==='pendente'?'warning':($p['status']==='aprovado'||$p['status']==='vinculado'?'success':($p['status']==='rejeitado'?'secondary':'danger')))?>"><?=e($p['status'])?></span><br><small><?=e($p['criado_em'])?></small></td>
  <td><span class="badge text-bg-light border"><?=e($p['motivo'] ?: '-')?></span><br><small><?=e($p['mensagem'] ?: '-')?></small><br><code><?=e($p['trace_id'])?></code></td>
  <td style="min-width:260px">
    <?php if($p['status']==='pendente'): ?>
      <a class="btn btn-sm btn-primary w-100 mb-1" href="index.php?page=produto-pendente-integracao-comparar&id=<?=e($p['id'])?>"><i class="bi bi-search"></i> Comparar e decidir</a>
      <small class="text-muted d-block">Aprovação direta foi bloqueada na V46. Revise duplicidade, EAN, NCM e categoria.</small>
    <?php else: ?>
      <a class="btn btn-sm btn-outline-secondary" href="index.php?page=produto-pendente-integracao-comparar&id=<?=e($p['id'])?>">Ver histórico</a>
    <?php endif; ?>
  </td>
  <td><details><summary>ver JSON</summary><pre class="json-box"><?=e($p['payload_json'])?></pre></details></td>
</tr>
<?php endforeach; ?>
<?php if(empty($pendentes)): ?><tr><td colspan="9" class="text-center text-muted py-4">Nenhum produto pendente encontrado.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
