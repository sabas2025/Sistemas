<?php require __DIR__.'/layout_top.php'; ?>
<?php
$totalBackups = count($backups ?? []);
$ultimoBackup = $backups[0] ?? null;
$totalBytes = 0;
$sucesso = 0;
foreach(($backups ?? []) as $bk){ $totalBytes += (int)($bk['tamanho_bytes'] ?? 0); if(($bk['status'] ?? '')==='sucesso') $sucesso++; }
$flashSuccess = $_SESSION['flash_success'] ?? null; unset($_SESSION['flash_success']);
$flashError = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);
function backup_size_fmt($bytes){ $bytes=(int)$bytes; if($bytes>=1073741824) return number_format($bytes/1073741824,2,',','.').' GB'; if($bytes>=1048576) return number_format($bytes/1048576,2,',','.').' MB'; return number_format($bytes/1024,2,',','.').' KB'; }
?>

<?php if($flashSuccess): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> <?=e($flashSuccess)?></div><?php endif; ?>
<?php if($flashError): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?=e($flashError)?></div><?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="panel h-100"><div class="text-muted small">Backups registrados</div><div class="display-6 fw-bold"><?=e($totalBackups)?></div><span class="badge bg-primary-subtle text-primary">Histórico local</span></div></div>
  <div class="col-md-3"><div class="panel h-100"><div class="text-muted small">Último backup</div><div class="h5 mb-1"><?= $ultimoBackup ? e(date('d/m/Y H:i', strtotime($ultimoBackup['criado_em']))) : '—' ?></div><span class="badge bg-success-subtle text-success"><?= $ultimoBackup ? e($ultimoBackup['status']) : 'Sem registro' ?></span></div></div>
  <div class="col-md-3"><div class="panel h-100"><div class="text-muted small">Volume armazenado</div><div class="h4 mb-1"><?=e(backup_size_fmt($totalBytes))?></div><span class="badge bg-secondary-subtle text-secondary">storage/backups</span></div></div>
  <div class="col-md-3"><div class="panel h-100"><div class="text-muted small">Integridade operacional</div><div class="h4 mb-1"><?= $totalBackups ? e(round(($sucesso/max(1,$totalBackups))*100)) : 0 ?>%</div><span class="badge bg-warning-subtle text-warning">Validar antes de restaurar</span></div></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-4">
    <div class="panel h-100">
      <div class="panel-header"><div><h2><i class="bi bi-cloud-arrow-down"></i> Gerar backup</h2><p class="text-muted mb-0">Cria um ZIP com SQL completo do banco atual.</p></div></div>
      <div class="alert alert-info small mb-3">Recomendado antes de qualquer alteração crítica, importação, restauração ou atualização de versão.</div>
      <?php if(PermissionService::can('backup','gerar')): ?>
      <form method="post" action="index.php?page=backup"><?=Csrf::field()?><button class="btn btn-primary w-100"><i class="bi bi-download"></i> Gerar backup ZIP agora</button></form>
      <?php else: ?><div class="text-muted">Seu usuário não possui permissão para gerar backup.</div><?php endif; ?>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="panel h-100">
      <div class="panel-header"><div><h2><i class="bi bi-upload"></i> Importar backup</h2><p class="text-muted mb-0">Envia um arquivo .zip ou .sql para a área segura.</p></div></div>
      <div class="alert alert-warning small mb-3">Importar não restaura automaticamente. O arquivo entra no histórico para conferência.</div>
      <?php if(PermissionService::can('backup','gerar')): ?>
      <form method="post" action="index.php?page=backup-importar" enctype="multipart/form-data">
        <?=Csrf::field()?>
        <label class="form-label">Arquivo de backup</label>
        <input class="form-control mb-3" type="file" name="backup_arquivo" accept=".zip,.sql" required>
        <button class="btn btn-outline-primary w-100"><i class="bi bi-box-arrow-in-down"></i> Importar backup</button>
      </form>
      <?php else: ?><div class="text-muted">Seu usuário não possui permissão para importar backup.</div><?php endif; ?>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="panel h-100 border border-danger-subtle">
      <div class="panel-header"><div><h2><i class="bi bi-arrow-counterclockwise"></i> Restaurar backup</h2><p class="text-muted mb-0">A restauração substitui dados do banco.</p></div></div>
      <div class="alert alert-danger small mb-3"><b>Atenção:</b> antes de restaurar, o sistema gera um backup automático de segurança. Mesmo assim, use somente com arquivo confiável.</div>
      <ol class="small text-muted mb-0">
        <li>Importe ou escolha um backup existente.</li>
        <li>Revise arquivo, tamanho, data e Trace ID.</li>
        <li>Digite <b>RESTAURAR</b> na linha correspondente.</li>
        <li>Arquivos <b>importados</b> vêm de origem externa: a assinatura local prova apenas que o Hub os validou, não que a origem seja confiável. Por isso exigem <b>RESTAURAR IMPORTADO</b>.</li>
      </ol>
    </div>
  </div>
</div>

<div class="panel">
  <div class="panel-header">
    <div>
      <h2><i class="bi bi-database-check"></i> Histórico de backups</h2>
      <p class="text-muted mb-0">Download, exclusão e restauração com confirmação manual.</p>
    </div>
    <a class="btn btn-outline-secondary" href="index.php?page=health-modulos"><i class="bi bi-heart-pulse"></i> Validar módulos</a>
  </div>
  <div class="table-responsive"><table class="table table-hover align-middle">
    <thead><tr><th>ID</th><th>Arquivo</th><th>Tamanho</th><th>Status</th><th>Mensagem</th><th>Trace ID</th><th>Data</th><th>Ações</th></tr></thead>
    <tbody>
    <?php foreach($backups as $b): ?>
      <tr>
        <td><?=e($b['id'])?></td>
        <td><div class="fw-semibold"><?=e($b['arquivo'])?></div><small class="text-muted"><?=str_ends_with((string)$b['arquivo'], '.zip') ? 'Backup ZIP' : 'Backup SQL'?></small></td>
        <td><?=e(backup_size_fmt($b['tamanho_bytes']))?></td>
        <td><span class="badge <?=($b['status']==='sucesso'?'bg-success-subtle text-success':'bg-danger-subtle text-danger')?>"><?=e($b['status'])?></span></td>
        <td class="small"><?=e($b['mensagem'])?></td>
        <td><code><?=e($b['trace_id'])?></code></td>
        <td><?=e(date('d/m/Y H:i', strtotime($b['criado_em'])))?></td>
        <td class="text-nowrap" style="min-width:310px">
          <a class="btn btn-sm btn-outline-primary" href="index.php?page=backup-download&id=<?=e($b['id'])?>"><i class="bi bi-download"></i> Baixar</a>
          <?php if(PermissionService::can('backup','gerar')): ?>
            <form method="post" action="index.php?page=backup-excluir" class="d-inline" data-confirm="Excluir este backup do histórico e do storage?">
              <?=Csrf::input()?><input type="hidden" name="id" value="<?=e($b['id'])?>">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
            <form method="post" action="index.php?page=backup-restaurar" class="mt-2 d-flex gap-1" data-confirm="Confirma restauração deste backup? Esta ação altera o banco de dados.">
              <?=Csrf::input()?>
              <input type="hidden" name="id" value="<?=e($b['id'])?>">
              <?php /* A-11: arquivo de origem externa exige frase reforçada, distinta da normal. */
                    // Melhoria 9 da seção 8: a origem passa a vir da coluna proveniencia (gravada
                    // a partir da assinatura). O prefixo do nome fica só como fallback para linhas
                    // gravadas antes da migration 20260914_009.
                    $ehImportado = isset($b['proveniencia'])
                      ? ((string)$b['proveniencia'] === 'imported_untrusted')
                      : str_starts_with((string)($b['arquivo'] ?? ''), 'importado_'); ?>
              <input class="form-control form-control-sm" name="confirmacao" placeholder="<?=$ehImportado?'Digite RESTAURAR IMPORTADO':'Digite RESTAURAR'?>" autocomplete="off">
              <button class="btn btn-sm btn-danger"><i class="bi bi-arrow-counterclockwise"></i> Restaurar</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if(!$backups): ?><tr><td colspan="8" class="text-muted text-center py-4">Nenhum backup registrado.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>

<div class="panel mt-3">
  <div class="panel-header"><div><h2><i class="bi bi-shield-lock"></i> Boas práticas de restauração</h2></div></div>
  <div class="row g-3 small">
    <div class="col-md-3"><b>1. Ambiente</b><br><span class="text-muted">Prefira restaurar primeiro em homologação.</span></div>
    <div class="col-md-3"><b>2. Backup automático</b><br><span class="text-muted">O sistema gera ponto de retorno antes da restauração.</span></div>
    <div class="col-md-3"><b>3. Health check</b><br><span class="text-muted">Após restaurar, valide banco, filas e módulos.</span></div>
    <div class="col-md-3"><b>4. Auditoria</b><br><span class="text-muted">Toda ação registra Trace ID para rastreabilidade.</span></div>
  </div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
