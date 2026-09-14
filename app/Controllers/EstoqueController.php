<?php
class EstoqueController {
  public function dispatch(string $page): void {
    Auth::requireLogin();
    switch ($page) {
      case 'estoque-dashboard': $this->dashboard(); break;
      case 'estoque-config': $this->config(); break;
      case 'estoque-config-salvar': $this->salvarConfig(); break;
      case 'estoque-alertas': $this->alertas(); break;
      case 'estoque-sku-historico': $this->skuHistorico(); break;
      case 'estoque-consultas-vsm': $this->consultasVsm(); break;
      case 'estoque-consulta-vsm-resultados': $this->consultaVsmResultados(); break;
      case 'estoque-consulta-vsm-testar-sku': $this->testarConsultaVsmSku(); break;
      case 'estoque-reconciliar-agora': $this->reconciliarAgora(); break;
      case 'estoque-consulta-vsm-executar': $this->executarConsultaVsm(); break;
      case 'estoque-consulta-tiny-executar': $this->executarConsultaVsm(); break; // compatibilidade V63
      default: redirect('index.php?page=estoque-dashboard');
    }
  }

  private function dashboard(): void {
    PermissionService::require('estoque','visualizar');
    $dbEstoque = Database::forTable('estoque_movimentos');
    $dbFila = Database::forTable('fila_estoque');
    $totais = [
      'movimentos_hoje' => (int)TenantScopeService::run('estoque_movimentos', "SELECT COUNT(*) FROM estoque_movimentos WHERE DATE(criado_em)=CURDATE()")->fetchColumn(),
      'erros' => (int)TenantScopeService::run('estoque_movimentos', "SELECT COUNT(*) FROM estoque_movimentos WHERE status IN ('erro','falha','falha_definitiva')")->fetchColumn(),
      'divergencias' => (int)TenantScopeService::run('estoque_divergencias', "SELECT COUNT(*) FROM estoque_divergencias WHERE status='aberto'")->fetchColumn(),
      'fila_pendente' => (int)TenantScopeService::run('fila_estoque', "SELECT COUNT(*) FROM fila_estoque WHERE status='pendente'")->fetchColumn(),
    ];
    $movimentos = TenantScopeService::run('estoque_movimentos', "SELECT id, origem, referencia, sku, quantidade, tipo_movimento, status, trace_id, criado_em, atualizado_em FROM estoque_movimentos ORDER BY id DESC LIMIT 80")->fetchAll();
    $divergencias = TenantScopeService::run('estoque_divergencias', "SELECT id, sku, estoque_vsm, estoque_tiny, diferenca, origem, status, acao_recomendada, trace_id, criado_em, atualizado_em FROM estoque_divergencias WHERE status='aberto' ORDER BY id DESC LIMIT 50")->fetchAll();
    $config = EstoqueEnterpriseService::config();
    $pageTitle = 'Dashboard de Estoque';
    require __DIR__.'/../../views/estoque_dashboard.php';
  }

  private function config(): void {
    PermissionService::require('configuracoes','visualizar');
    $config = EstoqueEnterpriseService::config();
    $pageTitle = 'Configurações de Estoque Enterprise';
    require __DIR__.'/../../views/estoque_config.php';
  }

  private function salvarConfig(): void {
    PermissionService::require('configuracoes','editar');
    Csrf::validate();
    $dados = [
      'estoque_mestre' => in_array($_POST['estoque_mestre'] ?? 'vsm', ['tiny','vsm'], true) ? $_POST['estoque_mestre'] : 'vsm',
      'permitir_vsm_tiny' => isset($_POST['permitir_vsm_tiny']) ? '1' : '0',
      'permitir_tiny_vsm' => isset($_POST['permitir_tiny_vsm']) ? '1' : '0',
      'estrategia_estoque' => in_array($_POST['estrategia_estoque'] ?? 'vsm_fonte_real', ['vsm_fonte_real','tiny_fonte_real','manual'], true) ? $_POST['estrategia_estoque'] : 'vsm_fonte_real',
      'ignorar_retorno_espelhado_minutos' => (string)max(1, min(120, (int)($_POST['ignorar_retorno_espelhado_minutos'] ?? 10))),
      'bloquear_loop_bidirecional' => isset($_POST['bloquear_loop_bidirecional']) ? '1' : '0',
      'reconciliacao_automatica' => isset($_POST['reconciliacao_automatica']) ? '1' : '0',
      'alertar_estoque_negativo' => isset($_POST['alertar_estoque_negativo']) ? '1' : '0',
      'alertar_produto_sem_mapeamento' => isset($_POST['alertar_produto_sem_mapeamento']) ? '1' : '0',
      'retencao_dias' => (string)max(7, min(365, (int)($_POST['retencao_dias'] ?? 90))),
      'retry_minutos' => trim($_POST['retry_minutos'] ?? '5,15,30,60'),
      'consulta_vsm_ativa' => isset($_POST['consulta_vsm_ativa']) ? '1' : '0',
      'consulta_vsm_modo' => in_array($_POST['consulta_vsm_modo'] ?? 'automatico', ['automatico','manual'], true) ? $_POST['consulta_vsm_modo'] : 'automatico',
      'consulta_vsm_intervalo_minutos' => (string)max(5, min(1440, (int)($_POST['consulta_vsm_intervalo_minutos'] ?? 60))),
      'consulta_vsm_quantidade_produtos' => (string)max(1, min(1000, (int)($_POST['consulta_vsm_quantidade_produtos'] ?? 100))),
      'consulta_vsm_enviar_tiny_se_alterou' => isset($_POST['consulta_vsm_enviar_tiny_se_alterou']) ? '1' : '0',
      'consulta_vsm_apenas_produtos_ativos' => isset($_POST['consulta_vsm_apenas_produtos_ativos']) ? '1' : '0',
      'consulta_vsm_ordem' => in_array($_POST['consulta_vsm_ordem'] ?? 'menos_recente', ['menos_recente','sku'], true) ? $_POST['consulta_vsm_ordem'] : 'menos_recente',
      'consulta_vsm_variacao_minima' => (string)max(0, (float)str_replace(',', '.', (string)($_POST['consulta_vsm_variacao_minima'] ?? 0))),
      'consulta_vsm_metodo_http' => in_array(strtoupper($_POST['consulta_vsm_metodo_http'] ?? 'POST'), ['GET','POST'], true) ? strtoupper($_POST['consulta_vsm_metodo_http']) : 'POST',
      'consulta_vsm_endpoint' => VsmEndpointSecurityService::sanitizePath($_POST['consulta_vsm_endpoint'] ?? '/api/estoque/consulta'),
      'consulta_vsm_payload_template' => trim($_POST['consulta_vsm_payload_template'] ?? '{"sku":"{{sku}}","trace_id":"{{trace_id}}"}'),
      'consulta_vsm_timeout_segundos' => (string)max(5, min(120, (int)($_POST['consulta_vsm_timeout_segundos'] ?? 30))),
      'consulta_vsm_alerta_falhas_percentual' => (string)max(1, min(100, (int)($_POST['consulta_vsm_alerta_falhas_percentual'] ?? 30))),
    ];
    EstoqueEnterpriseService::salvarConfig($dados);
    Audit::event('estoque.config.salva','sucesso',['mensagem'=>'Configurações de estoque enterprise atualizadas.','contexto'=>$dados]);
    redirect('index.php?page=estoque-config&ok=1');
  }

  private function alertas(): void {
    PermissionService::require('estoque','visualizar');
    $status = $_GET['status'] ?? '';
    $sql = "SELECT * FROM estoque_alertas WHERE 1=1"; $params=[];
    if ($status !== '') { $sql .= " AND status=?"; $params[] = $status; }
    $sql .= " ORDER BY id DESC LIMIT 300";
    $st = TenantScopeService::run('estoque_alertas', $sql, $params);
    $alertas = $st->fetchAll();
    $pageTitle = 'Alertas de Estoque';
    require __DIR__.'/../../views/estoque_alertas.php';
  }

  private function skuHistorico(): void {
    PermissionService::require('estoque','visualizar');
    $sku = trim($_GET['sku'] ?? '');
    $movimentos = [];
    if ($sku !== '') {
      $st = TenantScopeService::run('estoque_movimentos', "SELECT * FROM estoque_movimentos WHERE sku=? ORDER BY id DESC LIMIT 200", [$sku]); $movimentos = $st->fetchAll();
    }
    $pageTitle = 'Histórico de Estoque por SKU';
    require __DIR__.'/../../views/estoque_sku_historico.php';
  }

  private function executarConsultaVsm(): void {
    PermissionService::require('estoque','editar');
    Csrf::validate();
    $limite = isset($_POST['limite']) && $_POST['limite'] !== '' ? (int)$_POST['limite'] : null;
    try {
      $res = EstoqueVsmSchedulerService::executarConsultaProgramada(true, $limite, 'manual');
      $_SESSION['flash_ok'] = 'Consulta VSM executada: '.json_encode($res, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
      $_SESSION['flash_error'] = 'Erro ao executar consulta VSM: '.$e->getMessage();
      Audit::exception($e, 'estoque.vsm.consulta_manual.erro');
    }
    redirect('index.php?page=estoque-config');
  }


  private function consultasVsm(): void {
    PermissionService::require('estoque','visualizar');
    $db = Database::forTable('estoque_consulta_vsm_execucoes');
    $execucoes = $db->query("SELECT * FROM estoque_consulta_vsm_execucoes ORDER BY id DESC LIMIT 200")->fetchAll();
    $config = EstoqueVsmSchedulerService::config();
    $pageTitle = 'Histórico de Consultas VSM';
    require __DIR__.'/../../views/estoque_consultas_vsm.php';
  }

  private function consultaVsmResultados(): void {
    PermissionService::require('estoque','visualizar');
    $execId = (int)($_GET['execucao_id'] ?? 0);
    $db = Database::forTable('estoque_consulta_vsm_resultados');
    $execucao = null;
    if ($execId > 0) {
      $st = Database::forTable('estoque_consulta_vsm_execucoes')->prepare("SELECT * FROM estoque_consulta_vsm_execucoes WHERE id=?");
      $st->execute([$execId]);
      $execucao = $st->fetch();
    }
    $sql = "SELECT * FROM estoque_consulta_vsm_resultados";
    $params = [];
    if ($execId > 0) { $sql .= " WHERE execucao_id=?"; $params[] = $execId; }
    $sql .= " ORDER BY id DESC LIMIT 500";
    $st = $db->prepare($sql); $st->execute($params);
    $resultados = $st->fetchAll();
    $pageTitle = 'Resultados por SKU - Consulta VSM';
    require __DIR__.'/../../views/estoque_consulta_vsm_resultados.php';
  }

  private function testarConsultaVsmSku(): void {
    PermissionService::require('estoque','editar');
    Csrf::validate();
    $sku = trim($_POST['sku'] ?? '');
    if ($sku === '') { $_SESSION['flash_error']='Informe um SKU para testar.'; redirect('index.php?page=estoque-config'); }
    try {
      $ret = (new VsmService())->consultarEstoque($sku);
      $saldo = EstoqueVsmSchedulerService::extrairSaldo($ret);
      Audit::event('estoque.vsm.teste_sku','sucesso',['mensagem'=>'Teste de consulta VSM por SKU executado.','contexto'=>['sku'=>$sku,'saldo'=>$saldo,'retorno'=>$ret]]);
      $_SESSION['flash_ok'] = 'Teste SKU '.$sku.' executado. Saldo extraído: '.($saldo === null ? 'não identificado' : $saldo).' | Retorno: '.json_encode($ret, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
      $_SESSION['flash_error'] = 'Erro no teste SKU '.$sku.': '.$e->getMessage();
      Audit::exception($e, 'estoque.vsm.teste_sku.erro');
    }
    redirect('index.php?page=estoque-config');
  }

  private function reconciliarAgora(): void {
    PermissionService::require('reconciliacao','executar');
    Csrf::validate();
    $res = EstoqueEnterpriseService::criarReconciliacaoManual();
    redirect('index.php?page=estoque-dashboard&reconciliacao='.$res['id']);
  }
}
