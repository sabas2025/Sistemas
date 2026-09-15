<?php
class BackupController extends BaseModuleController {
  public function dispatch(string $page): void {
    Auth::requireLogin();
    switch ($page) {
      case 'backup': $this->gerar(); break;
      case 'backup-download': $this->download(); break;
      case 'backup-excluir': $this->excluir(); break;
      case 'backup-importar': $this->importar(); break;
      case 'backup-restaurar': $this->restaurar(); break;
      case 'backups': default: $this->index(); break;
    }
  }
  public static function routes(): array { return ['backup','backup-download','backup-excluir','backup-importar','backup-restaurar','backups']; }
  public function index(): void {
    PermissionService::require('backup','visualizar');
    BackupSchemaService::ensure();
    $backups = BackupSchemaService::list(100);
    $pageTitle = 'Backups';
    $this->view('backups', compact('pageTitle','backups'));
  }
  private function gerar(): void {
    PermissionService::require('backup','gerar'); Csrf::validate();
    $file = BackupService::gerarZip();
    header('Content-Type: '.(str_ends_with($file,'.zip') ? 'application/zip' : 'application/sql'));
    header('Content-Disposition: attachment; filename="'.basename($file).'"');
    readfile($file); exit;
  }
  private function download(): void {
    PermissionService::require('backup','baixar');
    BackupSchemaService::ensure();
    $id=(int)($_GET['id'] ?? 0); $pdo = Database::forTable('backups_banco');
    $st=$pdo->prepare('SELECT id, arquivo, nome_arquivo, caminho, tamanho_bytes, hash_sha256, hmac_sha256, trust_score, status, mensagem, trace_id, criado_em FROM backups_banco WHERE id=? LIMIT 1'); $st->execute([$id]); $b=$st->fetch();
    if(!$b){ http_response_code(404); exit('Backup não encontrado.'); }
    $file=dirname(__DIR__,2).'/storage/backups/'.basename($b['arquivo']);
    if(!is_file($file)){ http_response_code(404); exit('Arquivo físico não encontrado em storage/backups.'); }
    Audit::event('backup.download','sucesso',['mensagem'=>'Backup baixado','entidade'=>'backups_banco','entidade_id'=>$id]);
    header('Content-Type: '.(str_ends_with($file,'.zip') ? 'application/zip' : 'application/sql')); header('Content-Disposition: attachment; filename="'.basename($file).'"'); header('Content-Length: '.filesize($file)); readfile($file); exit;
  }
  private function excluir(): void {
    PermissionService::require('backup','excluir'); Csrf::validate();
    BackupSchemaService::ensure();
    $id=(int)($_POST['id'] ?? 0); $pdo=Database::forTable('backups_banco');
    $st=$pdo->prepare('SELECT id, arquivo, nome_arquivo, caminho, tamanho_bytes, hash_sha256, hmac_sha256, trust_score, status, mensagem, trace_id, criado_em FROM backups_banco WHERE id=? LIMIT 1'); $st->execute([$id]); $b=$st->fetch();
    if($b){ $file=dirname(__DIR__,2).'/storage/backups/'.basename($b['arquivo']); if(is_file($file)) @unlink($file); $pdo->prepare('DELETE FROM backups_banco WHERE id=?')->execute([$id]); Audit::event('backup.excluir','sucesso',['mensagem'=>'Backup excluído','entidade'=>'backups_banco','entidade_id'=>$id]); }
    redirect('index.php?page=backups');
  }
  private function importar(): void {
    PermissionService::require('backup','importar'); Csrf::validate();
    BackupSchemaService::ensure();
    try{ BackupService::importarUpload($_FILES['backup_arquivo'] ?? []); $_SESSION['flash_success']='Backup importado com sucesso. Confira o histórico antes de restaurar.'; }
    catch(Throwable $e){ $_SESSION['flash_error']=$e->getMessage(); Audit::event('backup.importar','erro',['mensagem'=>$e->getMessage()]); }
    redirect('index.php?page=backups');
  }
  private function restaurar(): void {
    PermissionService::require('backup','restaurar'); Csrf::validate();
    BackupSchemaService::ensure();
    $id=(int)($_POST['id'] ?? 0); $confirmacao=(string)($_POST['confirmacao'] ?? '');
    try{ BackupService::restaurarPorId($id, $confirmacao); $_SESSION['flash_success']='Backup restaurado com sucesso. Revise o Health do Sistema e valide as integrações.'; }
    catch(Throwable $e){ $_SESSION['flash_error']=$e->getMessage(); Audit::event('backup.restaurar','erro',['mensagem'=>$e->getMessage(),'entidade'=>'backups_banco','entidade_id'=>$id]); }
    redirect('index.php?page=backups');
  }
}
