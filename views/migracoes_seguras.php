<?php require __DIR__.'/layout_top.php'; ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div>
    <h1 class="h3 mb-1">Migrações Seguras</h1>
    <p class="text-muted mb-0">Rotas antigas `atualizar-vXX` ficam bloqueadas em produção. Use SQL versionado, backup assinado e validação do banco.</p>
  </div>
  <a class="btn btn-outline-secondary" href="index.php?page=validar-banco">Validar Banco</a>
</div>
<?php if(!empty($_SESSION['flash_error'])): ?><div class="alert alert-warning"><?=h($_SESSION['flash_error']); unset($_SESSION['flash_error']);?></div><?php endif; ?>
<div class="card shadow-sm"><div class="card-body">
  <h2 class="h5">Arquivos de migração encontrados</h2>
  <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Arquivo</th><th>Status</th><th>Ação recomendada</th></tr></thead><tbody>
  <?php foreach($files as $f): ?><tr><td><code><?=h($f)?></code></td><td><span class="badge bg-info">versionado</span></td><td>Aplicar no módulo indicado no cabeçalho, após backup assinado e fora do horário crítico.</td></tr><?php endforeach; ?>
  <?php if(empty($files)): ?><tr><td colspan="3" class="text-muted">Nenhuma migração encontrada.</td></tr><?php endif; ?>
  </tbody></table></div>
  <div class="alert alert-info mb-0"><b>Proteção:</b> esta tela não executa SQL arbitrário no navegador. Ela bloqueia rotas antigas, registra auditoria e orienta aplicação controlada.</div>
</div></div>
<?php require __DIR__.'/layout_bottom.php'; ?>
