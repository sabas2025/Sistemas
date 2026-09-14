<?php $canEditVsm=PermissionService::can('configuracoes','editar'); require __DIR__.'/layout_top.php'; ?>
<div class="panel"><div class="panel-header"><h2><i class="bi bi-play-circle"></i> Testes de Endpoints VSM</h2><span class="text-muted">Execução individual com parâmetros</span></div><div class="p-4">
<?php if($erro): ?>
<div class="alert alert-danger">
  <b>Estrutura VSM indisponível.</b><br><?=e($erro)?>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <a class="btn btn-sm btn-warning" href="index.php?page=mapa-banco"><i class="bi bi-database-add"></i> Abrir reparo do banco</a>
    <a class="btn btn-sm btn-outline-secondary" href="index.php?page=enterprise-core"><i class="bi bi-building-gear"></i> Enterprise Core</a>
  </div>
</div>
<?php endif; ?>
<div class="alert alert-warning"><b>Segurança:</b> esta tela nunca envia POST, PUT, PATCH ou DELETE real. Métodos mutáveis são convertidos em uma sonda GET identificada e não comprovam a prontidão do método original.</div>
<?php if(!$canEditVsm): ?><div class="alert alert-info">Seu perfil possui acesso somente de leitura. A execução de sondas exige permissão para editar configurações.</div><?php endif; ?>
<div class="row g-3"><?php foreach($endpoints as $ep): $placeholders=VsmEndpointService::placeholders((string)$ep['endpoint']); ?><div class="col-md-6"><div class="card-soft h-100"><h5><?=e($ep['nome'])?></h5><p class="small text-muted"><span class="badge bg-secondary"><?=e($ep['metodo_http'])?></span> <code><?=e($ep['endpoint'])?></code></p><?php if($canEditVsm): ?><form method="post" action="index.php?page=vsm-endpoint-testar" class="row g-2"><?=Csrf::input()?><input type="hidden" name="id" value="<?=e($ep['id'])?>"><input type="hidden" name="modo_seguro" value="1"><?php foreach($placeholders as $placeholder): ?><div class="col-md-4"><input class="form-control form-control-sm" name="param_<?=e($placeholder)?>" placeholder="<?=e($placeholder)?>" required></div><?php endforeach; ?><div class="col-md-4"><button class="btn btn-sm btn-outline-success w-100" <?=empty($ep['contract_verified'])?'disabled title="Valide o contrato antes de testar"':''?>>Testar seguro</button></div></form><?php else: ?><span class="badge bg-light text-dark border">Sonda indisponível em modo leitura</span><?php endif; ?></div></div><?php endforeach; ?></div>
</div></div>
<?php require __DIR__.'/layout_bottom.php'; ?>
