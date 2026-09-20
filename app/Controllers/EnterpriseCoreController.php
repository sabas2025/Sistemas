<?php
class EnterpriseCoreController extends BaseModuleController {
  public function dispatch(string $page): void {
    Auth::requireLogin();
    switch ($page) {
      case 'enterprise-core': $this->index(); return;
      case 'enterprise-core-aplicar': $this->apply(); return;
      case 'enterprise-observabilidade': $this->observabilidade(); return;
      case 'integration-events': $this->events(); return;
      case 'llm-governance': $this->llm(); return;
      case 'enterprise-regression-tests': $this->regressionTests(); return;
      case 'design-system-enterprise': $this->designSystem(); return;
      default: redirect('index.php?page=central-tecnica');
    }
  }

  private function index(): void {
    PermissionService::require('configuracoes','visualizar');
    $status = SchemaMigrationService::status();
    $quality = EnterpriseQualityGateService::evaluate();
    $pageTitle = 'Enterprise Core';
    $this->view('enterprise_core', compact('pageTitle','status','quality'));
  }

  private function apply(): void {
    PermissionService::require('database','validar');
    if (RequestContext::method() !== 'POST') {
      http_response_code(405);
      header('Allow: POST');
      exit('Método não permitido. Use POST com token CSRF válido.');
    }
    Csrf::validate();
    $dryRun = !empty($_POST['dry_run']);
    $resultado = SchemaMigrationService::applyEnterpriseCore($dryRun);
    $_SESSION['enterprise_core_resultado'] = $resultado;
    $_SESSION[empty($resultado['errors']) ? 'form_success' : 'form_error'] = empty($resultado['errors']) ? 'Enterprise Core aplicado com sucesso.' : 'Enterprise Core aplicado com avisos. Consulte detalhes na tela.';
    redirect('index.php?page=enterprise-core');
  }

  private function observabilidade(): void {
    PermissionService::require('dashboard','visualizar');
    $snapshot = EnterpriseObservabilityService::snapshot();
    $pageTitle = 'Observabilidade Enterprise';
    $this->view('enterprise_observabilidade', compact('pageTitle','snapshot'));
  }

  private function events(): void {
    PermissionService::require('logs','visualizar');
    $events = IntegrationEventService::recent(100);
    $pageTitle = 'Eventos de Integração';
    $this->view('integration_events', compact('pageTitle','events'));
  }

  private function regressionTests(): void {
    PermissionService::require('configuracoes','visualizar');
    $resultado = EnterpriseRegressionTestService::run();
    $pageTitle = 'Testes de Regressão Enterprise';
    $this->view('enterprise_regression_tests', compact('pageTitle','resultado'));
  }

  private function designSystem(): void {
    PermissionService::require('configuracoes','visualizar');
    $pageTitle = 'Design System Enterprise';
    $this->view('design_system_enterprise', compact('pageTitle'));
  }

  private function llm(): void {
    LlmPolicyService::requireAccess(false);
    $resultadoPrompt = null;
    $resultadoAcao = null;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
      Csrf::validate();
      $acao = (string)($_POST['acao_llm'] ?? 'validate_prompt');
      try {
        if ($acao === 'save_policy') {
          $resultadoAcao = LlmPolicyService::savePolicy($_POST);
          $_SESSION['form_success'] = 'Política LLM salva com segurança.';
          redirect('index.php?page=llm-governance');
        } elseif ($acao === 'store_api_key') {
          $resultadoAcao = LlmPolicyService::storeApiKey((string)($_POST['provider'] ?? ''), (string)($_POST['environment'] ?? 'homologacao'), (string)($_POST['api_key'] ?? ''));
          $_SESSION[$resultadoAcao['success'] ? 'form_success' : 'form_error'] = $resultadoAcao['message'] ?? 'Operação LLM concluída.';
          redirect('index.php?page=llm-governance');
        } elseif ($acao === 'create_approval') {
          $resultadoAcao = LlmGatewayService::createApprovalFromPrompt((string)($_POST['prompt_teste'] ?? ''), (string)($_POST['prompt_key'] ?? 'manual'));
          $_SESSION['form_success'] = 'Solicitação enviada para aprovação humana: '.($resultadoAcao['approval_uuid'] ?? '');
          redirect('index.php?page=llm-governance');
        } elseif ($acao === 'approval_decision') {
          $resultadoAcao = LlmApprovalService::decide((int)($_POST['approval_id'] ?? 0), (string)($_POST['decision'] ?? ''), (string)($_POST['decision_reason'] ?? ''));
          $_SESSION[$resultadoAcao['success'] ? 'form_success' : 'form_error'] = $resultadoAcao['message'] ?? 'Decisão registrada.';
          redirect('index.php?page=llm-governance');
        } elseif ($acao === 'execute_homologacao') {
          $resultadoAcao = LlmRealExecutionService::executeHomologacao((int)($_POST['approval_id'] ?? 0), (string)($_POST['prompt_teste'] ?? ''));
          $_SESSION[!empty($resultadoAcao['ok']) ? 'form_success' : 'form_error'] = !empty($resultadoAcao['ok'])
            ? ('Chamada real em homologação concluída ('.(int)($resultadoAcao['usage']['input_tokens'] ?? 0).' in / '.(int)($resultadoAcao['usage']['output_tokens'] ?? 0).' out, custo ~$'.number_format((float)($resultadoAcao['cost'] ?? 0), 4).').')
            : ('Execução real não realizada: '.(implode(' · ', $resultadoAcao['blocked'] ?? []) ?: (string)($resultadoAcao['erro'] ?? 'bloqueada')));
          $_SESSION['llm_exec_resultado'] = $resultadoAcao;
          redirect('index.php?page=llm-governance');
        } else {
          $resultadoPrompt = LlmGatewayService::validatePrompt((string)($_POST['prompt_teste'] ?? ''), (string)($_POST['prompt_key'] ?? 'teste_governanca'));
        }
      } catch (Throwable $e) {
        Audit::exception($e, 'llm.governance.error');
        $_SESSION['form_error'] = 'Falha na Governança LLM: '.$e->getMessage();
        redirect('index.php?page=llm-governance');
      }
    }
    $readiness = LlmGatewayService::readiness();
    $policy = $readiness['config'] ?? LlmPolicyService::policy();
    $approvals = $readiness['approvals'] ?? [];
    $usage = $readiness['usage'] ?? [];
    $resultadoExec = $_SESSION['llm_exec_resultado'] ?? null;
    unset($_SESSION['llm_exec_resultado']);
    $pageTitle = 'Governança LLM';
    $this->view('llm_governance', compact('pageTitle','readiness','resultadoPrompt','resultadoAcao','policy','approvals','usage','resultadoExec'));
  }
}
