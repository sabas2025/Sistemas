<?php
class DatabaseMaintenanceController extends BaseModuleController {
  public function dispatch(string $page): void {
    Auth::requireLogin();
    if ($page === 'validar-banco') { $this->validarBanco(); return; }
    if ($page === 'health-modulos') { $this->healthModulos(); return; }
    if ($page === 'mapa-banco') { $this->mapaBanco(); return; }
    if (str_starts_with($page, 'atualizar-v')) { $this->updateLegadoBloqueado($page); return; }
    redirect('index.php?page=central-tecnica');
  }
  public static function routes(): array { return ['validar-banco','health-modulos','mapa-banco']; }

  private function validarBanco(): void {
    PermissionService::require('database','validar');
    $reparar = false;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
      Csrf::validate();
      $acao = (string)($_POST['acao'] ?? '');
      if ($acao === 'repair_schema') {
        $reparar = true;
        Audit::event('database.schema_guard.reparo_manual.solicitado','alerta',[
          'mensagem'=>'Reparo manual de banco solicitado pela tela Validar Banco.',
          'acao_recomendada'=>'Confirmar backup recente antes de executar reparos de schema em produção.'
        ]);
      }
    }

    try {
      $resultado = (new DatabaseValidationService(Database::connection('core'), [
        'validation_max_seconds'=>(int)cfg('security.database_validation_max_seconds',20),
        'repair_max_seconds'=>(int)cfg('security.database_repair_max_seconds',90),
        'max_checks'=>(int)cfg('security.database_validation_max_checks',320),
      ]))->executar($reparar);
    } catch (Throwable $e) {
      try { Audit::exception($e, 'database.validacao.tela.erro'); } catch(Throwable $ignored){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $ignored); }
      $resultado = [
        'status'=>'erro',
        'total'=>1,
        'ok'=>0,
        'atencao'=>0,
        'erro'=>1,
        'checks'=>[[
          'status'=>'erro',
          'titulo'=>'Falha ao validar banco',
          'mensagem'=>'A validação não conseguiu finalizar: '.$e->getMessage(),
          'acao'=>'Consultar Auditoria pelo Trace ID, conferir config.php e permissões MySQL.'
        ]],
        'schema_guard'=>[],
        'auto_repair'=>[],
        'trace_id'=>RequestContext::id(),
        'executado_em'=>date('Y-m-d H:i:s'),
        'modo'=>$reparar ? 'reparo_manual' : 'validacao_leitura_segura',
        'parcial'=>false,
        'duracao_ms'=>0,
        'acao_recomendada'=>'Corrigir erro informado e consultar auditoria pelo Trace ID.',
      ];
    }

    // V104.31: navegador/painel sempre recebe HTML. JSON fica explícito somente para AJAX/API.
    if ($this->wantsJson()) {
      header('Content-Type: application/json; charset=UTF-8');
      echo json_encode($resultado, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
      return;
    }

    $pageTitle = $reparar ? 'Reparar Banco de Dados' : 'Validar Banco de Dados';
    $this->view('validar_banco', compact('pageTitle','resultado','reparar'));
  }

  private function wantsJson(): bool {
    $format = strtolower((string)($_GET['format'] ?? $_POST['format'] ?? ''));
    $ajax = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    return $format === 'json' || $ajax || (isset($_GET['json']) && (string)$_GET['json'] === '1');
  }

  private function healthModulos(): void {
    PermissionService::require('configuracoes','visualizar');
    $checks = ModuleHealthService::checks();
    $pageTitle = 'Health Check por Módulo';
    $this->view('health_modulos', compact('pageTitle','checks'));
  }
  private function mapaBanco(): void {
    PermissionService::require('database','validar');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
      Csrf::validate();
      $acao = (string)($_POST['acao'] ?? '');
      if (in_array($acao, ['apply_enterprise_core','apply_enterprise_tables'], true)) {
        try {
          $resultadoReparoMapa = $acao === 'apply_enterprise_tables'
            ? SchemaMigrationService::applyEnterpriseTableRecovery(false)
            : SchemaMigrationService::applyEnterpriseCore(false);
          $_SESSION['mapa_banco_reparo_resultado'] = $resultadoReparoMapa;
          $_SESSION[empty($resultadoReparoMapa['errors']) ? 'form_success' : 'form_error'] = empty($resultadoReparoMapa['errors'])
            ? ($acao === 'apply_enterprise_tables' ? 'As 12 tabelas Enterprise foram criadas e verificadas.' : 'Estrutura ausente aplicada e verificada com sucesso.')
            : 'Reparo executado com pendências. Consulte os detalhes no Mapa do Banco.';
        } catch (Throwable $e) {
          try { Audit::exception($e, 'database.mapa_banco.reparo.erro'); } catch(Throwable $ignored){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $ignored); }
          $_SESSION['mapa_banco_reparo_resultado'] = ['errors'=>[$e->getMessage()],'trace_id'=>RequestContext::id(),'applied'=>[],'skipped'=>[]];
          $_SESSION['form_error'] = 'Falha ao aplicar estrutura: '.$e->getMessage();
        }
        redirect('index.php?page=mapa-banco');
      }
    }
    $resultadoReparoMapa = $_SESSION['mapa_banco_reparo_resultado'] ?? null;
    unset($_SESSION['mapa_banco_reparo_resultado']);
    $pageTitle = 'Mapa do Banco';
    try {
      // Mapa em modo leitura para evitar carregamento infinito/loop.
      // Reparos ficam concentrados em Central Técnica > Validar/Reparar Banco.
      $mapa = DatabaseMapService::resumo(false);
      $erroMapaBanco = null;
    } catch (Throwable $e) {
      $mapa = null;
      $erroMapaBanco = 'Não foi possível montar o Mapa do Banco: '.$e->getMessage();
      try { Audit::exception($e, 'database.mapa_banco.erro'); } catch(Throwable $ignored){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $ignored); }
    }
    $this->view('mapa_banco', compact('pageTitle','mapa','erroMapaBanco','resultadoReparoMapa'));
  }

  private function updateLegadoBloqueado(string $page): void {
    PermissionService::require('database','validar');
    Audit::event('database.update_legado.bloqueado','info',[
      'mensagem'=>'Rota de update legado bloqueada pela '.(class_exists('SystemVersionService')?SystemVersionService::label():'V104.31').'.',
      'contexto'=>['page'=>$page],
      'acao_recomendada'=>'Usar database/install_final_current.sql em instalação nova, database/repair_current.sql para reparo, Validar Banco ou Health de Módulos em base existente.'
    ]);
    $_SESSION['flash_error'] = 'Update legado bloqueado na '.(class_exists('SystemVersionService')?SystemVersionService::label():'V104.31').'. Use Validar Banco, Health de Módulos ou o schema consolidado current.';
    redirect('index.php?page=central-tecnica');
  }
}
