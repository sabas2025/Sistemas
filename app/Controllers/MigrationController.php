<?php
class MigrationController extends BaseModuleController {
  public function dispatch(string $page): void {
    Auth::requireLogin();
    if (str_starts_with($page, 'atualizar-v')) { $this->legacyBlocked($page); return; }
    if ($page === 'migracoes-seguras') { $this->index(); return; }
    if ($page === 'migracao-aplicar') { $this->apply(); return; }
    redirect('index.php?page=central-tecnica');
  }

  private function index(): void {
    PermissionService::require('database','validar');
    $dir = dirname(__DIR__,2).'/database/migrations';
    $files = array_map('basename', glob($dir.'/*.sql') ?: []);
    rsort($files);
    $pageTitle = 'Migrações Seguras';
    require dirname(__DIR__,2).'/views/migracoes_seguras.php';
  }

  private function apply(): void {
    PermissionService::require('database','validar');
    Csrf::validate();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Método não permitido.'); }
    $confirm = trim((string)($_POST['confirmar'] ?? ''));
    if ($confirm !== 'APLICAR MIGRACAO') {
      $_SESSION['flash_error'] = 'Migração bloqueada: digite APLICAR MIGRACAO para confirmar.';
      redirect('index.php?page=migracoes-seguras');
    }
    $file = basename((string)($_POST['arquivo'] ?? ''));
    if (!preg_match('/^(?:\d{8}_\d{3}|\d{4}_\d{2}_\d{2})_[a-z0-9_\-]+\.sql$/i', $file)) {
      $_SESSION['flash_error'] = 'Arquivo de migração inválido.';
      redirect('index.php?page=migracoes-seguras');
    }
    $path = dirname(__DIR__,2).'/database/migrations/'.$file;
    if (!is_file($path)) {
      $_SESSION['flash_error'] = 'Migração não encontrada.';
      redirect('index.php?page=migracoes-seguras');
    }
    SecurityEventService::log('migration.manual_review_required','medio','Migração manual solicitada; aplique no módulo indicado no cabeçalho do SQL.', ['file'=>$file]);
    $_SESSION['flash_error'] = 'Por segurança, a '.(class_exists('SystemVersionService')?SystemVersionService::label():'V104.16').' não executa SQL arbitrário pelo navegador. Baixe/aplique '.$file.' no módulo indicado ou use Validar Banco/instalador revisado.';
    redirect('index.php?page=migracoes-seguras');
  }

  private function legacyBlocked(string $page): void {
    PermissionService::require('database','validar');
    SecurityEventService::log('migration.legacy_route_blocked','alto','Rota histórica de atualização bloqueada.', ['page'=>$page]);
    Audit::event('database.update_legado.bloqueado','alerta',[
      'mensagem'=>'Rota de update legado bloqueada pela '.(class_exists('SystemVersionService')?SystemVersionService::label():'V104.16').'.',
      'contexto'=>['page'=>$page],
      'acao_recomendada'=>'Usar database/migrations versionado, Validar Banco ou reinstalação com schema consolidado '.(class_exists('SystemVersionService')?SystemVersionService::label():'V104.16').'.'
    ]);
    $_SESSION['flash_error'] = 'Update legado bloqueado na '.(class_exists('SystemVersionService')?SystemVersionService::label():'V104.16').'. Use Migrações Seguras, Validar Banco ou o schema consolidado current.';
    redirect('index.php?page=migracoes-seguras');
  }
}
