<?php require __DIR__.'/layout_top.php'; ?>
<div class="cardx p-4 mb-3">
  <h3>Auditoria com Hash Chain SHA256</h3>
  <p>Cada evento é ligado ao hash anterior. Isso ajuda a detectar alteração manual no histórico.</p>
  <div class="alert alert-<?=($resultado['valida']?'success':'danger')?>">Status: <b><?= $resultado['valida']?'Cadeia válida':'Cadeia com inconsistência' ?></b> — Eventos assinados: <?=e($resultado['total'])?></div>
  <form method="post" action="index.php?page=auditoria-hash-chain"><?=Csrf::input()?><input type="hidden" name="acao" value="assinar"><button class="btn btn-primary">Assinar próximos eventos</button></form>
</div>
<?php if(!$resultado['valida']): ?><div class="cardx p-3"><h5>Erros encontrados</h5><ul><?php foreach($resultado['erros'] as $e): ?><li><?=e($e)?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php require __DIR__.'/layout_bottom.php'; ?>
