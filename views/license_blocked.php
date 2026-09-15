<?php require __DIR__.'/layout_top.php'; ?>
<div class="panel">
  <div class="panel-header"><h2><i class="bi bi-key"></i> Licença necessária</h2><span class="badge text-bg-danger">Bloqueado</span></div>
  <div class="p-4">
    <p class="lead">O acesso foi bloqueado porque a licença comercial não está válida para este ambiente.</p>
    <div class="alert alert-warning"><b>Status:</b> <?=e($licenseStatus['status'] ?? 'indefinido')?> — <?=e($licenseStatus['mensagem'] ?? 'Sem detalhes')?></div>
    <p>Entre em contato com o responsável do sistema para cadastrar ou renovar a licença.</p>
    <a class="btn btn-outline-primary" href="index.php?page=licencas-clientes">Ver licenças</a>
    <a class="btn btn-outline-secondary" href="index.php?page=security-assisted-test">Teste assistido</a>
  </div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
