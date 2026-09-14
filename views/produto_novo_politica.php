<?php require __DIR__.'/layout_top.php'; ?>
<?php if(!empty($_SESSION['form_success'])): ?><div class="alert alert-success"><?=e($_SESSION['form_success']); unset($_SESSION['form_success']);?></div><?php endif; ?>
<?php if(!empty($_SESSION['form_error'])): ?><div class="alert alert-danger"><?=e($_SESSION['form_error']); unset($_SESSION['form_error']);?></div><?php endif; ?>
<div class="panel"><div class="panel-header"><h2><i class="bi bi-shield-check"></i> Política Produto Novo e Pedido Tiny → VSM</h2><span class="text-muted">V51 - escolha manual/automático com fallback seguro</span></div>
<div class="p-4"><form method="post" action="index.php?page=produto-novo-politica-salvar"><?=Csrf::input()?>
  <div class="row g-3">
    <div class="col-lg-6"><div class="card-soft h-100 border border-primary-subtle"><h5>Produto novo VSM → Tiny</h5>
      <div class="alert alert-warning small"><b>Recomendado para farmácia:</b> Manual. Automático só libera se EAN, NCM, categoria e duplicidade estiverem OK.</div>
      <label class="form-label">Modo de aprovação</label>
      <select class="form-select mb-3" name="produto_novo_aprovacao_modo"><option value="manual" <?=($cfg['produto_novo_aprovacao_modo']==='manual'?'selected':'')?>>Manual - produto vai para aprovação</option><option value="automatico" <?=($cfg['produto_novo_aprovacao_modo']==='automatico'?'selected':'')?>>Automático com checklist rígido</option></select>
      <?php foreach(['produto_novo_auto_fallback_manual'=>'Se automático falhar, mandar para aprovação manual','produto_novo_auto_exigir_ean'=>'Exigir EAN/GTIN','produto_novo_auto_exigir_ncm'=>'Exigir NCM','produto_novo_auto_exigir_categoria'=>'Exigir categoria VSM ↔ Tiny mapeada','produto_novo_auto_bloquear_duplicidade'=>'Bloquear automático se detectar duplicidade'] as $k=>$label): ?>
        <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="<?=$k?>" name="<?=$k?>" value="1" <?=!empty($cfg[$k])?'checked':''?>><label class="form-check-label" for="<?=$k?>"><?=e($label)?></label></div>
      <?php endforeach; ?>
    </div></div>
    <div class="col-lg-6"><div class="card-soft h-100 border border-success-subtle"><h5>Pedido Tiny → Hub → VSM</h5>
      <div class="alert alert-info small">O pedido do Tiny fica retido no Hub, é validado e só depois segue para a VSM.</div>
      <?php foreach(['pedido_tiny_vsm_validacao_obrigatoria'=>'Validação obrigatória antes da VSM','pedido_tiny_vsm_aprovacao_manual'=>'Exigir aprovação manual para enviar','pedido_tiny_vsm_auto_enviar_validos'=>'Enviar automaticamente se 100% válido','pedido_tiny_vsm_exigir_sku_mapeado'=>'Exigir SKU mapeado','pedido_tiny_vsm_exigir_cliente_documento'=>'Exigir CPF/CNPJ do cliente','pedido_tiny_vsm_exigir_endereco'=>'Exigir endereço completo'] as $k=>$label): ?>
        <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="<?=$k?>" name="<?=$k?>" value="1" <?=!empty($cfg[$k])?'checked':''?>><label class="form-check-label" for="<?=$k?>"><?=e($label)?></label></div>
      <?php endforeach; ?>
      <label class="form-label mt-2">Status Tiny permitidos para VSM</label><input class="form-control mb-2" name="pedido_tiny_vsm_status_permitidos" value="<?=e($cfg['pedido_tiny_vsm_status_permitidos'])?>">
      <label class="form-label">Endpoint relativo de pedido na VSM</label><input class="form-control" name="vsm_endpoint_pedido" value="<?=e($cfg['vsm_endpoint_pedido'])?>"><small class="text-muted">Use caminho relativo. Exemplo: /api/pedidos</small>
    </div></div>
  </div>
  <div class="mt-4 d-flex gap-2 flex-wrap"><button class="btn btn-primary"><i class="bi bi-save"></i> Salvar política V51</button><a class="btn btn-outline-primary" href="index.php?page=pedidos-validacao-vsm">Ver pedidos validados</a><a class="btn btn-outline-warning" href="index.php?page=produtos-pendentes-integracao">Produtos pendentes</a></div>
</form></div></div>
<?php require __DIR__.'/layout_bottom.php'; ?>
