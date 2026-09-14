<?php require __DIR__.'/layout_top.php'; ?>
<div class="card pro-card"><div class="card-body" style="max-width:560px">
  <h2 class="h5">Troca obrigatória de senha</h2>
  <p class="text-muted">Por segurança, altere a senha inicial antes de usar o sistema.</p>
  <?php if(!empty($erro)): ?><div class="alert alert-danger"><?= e($erro) ?></div><?php endif; ?>
  <form method="post" action="index.php?page=trocar-senha">
    <?= Csrf::field() ?>
    <label class="form-label">Nova senha</label><input type="password" name="senha" class="form-control mb-3" required minlength="8">
    <label class="form-label">Confirmar nova senha</label><input type="password" name="confirma" class="form-control mb-3" required minlength="8">
    <button class="btn btn-primary">Salvar nova senha</button>
  </form>
</div></div>
<?php require __DIR__.'/layout_bottom.php'; ?>
