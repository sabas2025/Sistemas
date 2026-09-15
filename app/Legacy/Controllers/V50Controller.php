<?php
class V50Controller {
  public function dispatch(string $page): void {
    Auth::requireLogin();
    LegacyRegistryService::guard('V50Controller', $page);
    switch($page){
      case 'tiny-validacao': $this->tinyValidacao(); break;
      case 'tiny-validacao-executar': $this->tinyValidacaoExecutar(); break;
      case 'vsm-simulador': $this->vsmSimulador(); break;
      case 'vsm-simulador-executar': $this->vsmSimuladorExecutar(); break;
      case 'producao-segura': $this->producaoSegura(); break;
      case 'producao-segura-executar': $this->producaoSeguraExecutar(); break;
      case 'limpeza-retencao': $this->limpezaRetencao(); break;
      case 'limpeza-retencao-executar': $this->limpezaRetencaoExecutar(); break;
      case 'atualizar-v50-producao-segura': $this->atualizarV50(); break;
      default: redirect('index.php?page=central-tecnica');
    }
  }
  private function tinyValidacao(): void {
    PermissionService::require('configuracoes','visualizar');
    $ultimo=null; $resultado=$_SESSION['tiny_validacao_resultado'] ?? null; unset($_SESSION['tiny_validacao_resultado']);
    try { $ultimo=TinyValidationService::ultimo(); } catch(Throwable $e) { $erro=$e->getMessage(); }
    $pageTitle='Validação Tiny V2/V3';
    require __DIR__.'/../../../views/tiny_validacao.php';
  }
  private function tinyValidacaoExecutar(): void {
    PermissionService::require('configuracoes','editar'); Csrf::validate();
    try { $_SESSION['tiny_validacao_resultado']=TinyValidationService::executar((string)($_POST['versao'] ?? 'v2'), $_POST); }
    catch(Throwable $e){ Audit::exception($e,'tiny.validacao.erro'); $_SESSION['form_error']=$e->getMessage(); }
    redirect('index.php?page=tiny-validacao');
  }
  private function vsmSimulador(): void {
    PermissionService::require('configuracoes','visualizar');
    $exemplos=VsmSimulatorService::exemplos();
    $resultado=$_SESSION['vsm_simulador_resultado'] ?? null; unset($_SESSION['vsm_simulador_resultado']);
    $pageTitle='Simulador VSM'; require __DIR__.'/../../../views/vsm_simulador.php';
  }
  private function vsmSimuladorExecutar(): void {
    PermissionService::require('configuracoes','visualizar'); Csrf::validate();
    try { $_SESSION['vsm_simulador_resultado']=VsmSimulatorService::simular((string)($_POST['tipo'] ?? 'pedido_novo')); }
    catch(Throwable $e){ Audit::exception($e,'vsm.simulador.erro'); $_SESSION['form_error']=$e->getMessage(); }
    redirect('index.php?page=vsm-simulador');
  }
  private function producaoSegura(): void {
    PermissionService::require('configuracoes','visualizar');
    $resultado=$_SESSION['producao_segura_resultado'] ?? null; unset($_SESSION['producao_segura_resultado']);
    $pageTitle='Checklist Produção Segura'; require __DIR__.'/../../../views/producao_segura.php';
  }
  private function producaoSeguraExecutar(): void {
    PermissionService::require('configuracoes','visualizar'); Csrf::validate();
    try { $_SESSION['producao_segura_resultado']=ProductionReadinessV50Service::executar(); }
    catch(Throwable $e){ Audit::exception($e,'producao.v50.erro'); $_SESSION['form_error']=$e->getMessage(); }
    redirect('index.php?page=producao-segura');
  }
  private function limpezaRetencao(): void {
    PermissionService::require('logs','visualizar');
    $resultado=$_SESSION['limpeza_retencao_resultado'] ?? null; unset($_SESSION['limpeza_retencao_resultado']);
    $pageTitle='Limpeza e Retenção'; require __DIR__.'/../../../views/limpeza_retencao.php';
  }
  private function limpezaRetencaoExecutar(): void {
    PermissionService::require('logs','visualizar'); Csrf::validate();
    $dryRun=empty($_POST['executar_real']);
    try { $_SESSION['limpeza_retencao_resultado']=RetentionCleanupService::executar((int)($_POST['dias'] ?? 90), $dryRun); }
    catch(Throwable $e){ Audit::exception($e,'retencao.v50.erro'); $_SESSION['form_error']=$e->getMessage(); }
    redirect('index.php?page=limpeza-retencao');
  }
  private function atualizarV50(): void {
    PermissionService::require('database','validar'); Csrf::validate();
    $mensagens=[];
    try { TinyValidationService::ensureSchema(); $mensagens[]='Tabela tiny_validacoes_execucoes OK'; } catch(Throwable $e){ $mensagens[]='Tiny validação erro: '.$e->getMessage(); }
    try { ProductionReadinessV50Service::ensureSchema(); $mensagens[]='Tabela production_readiness_v50 OK'; } catch(Throwable $e){ $mensagens[]='Production readiness erro: '.$e->getMessage(); }
    $sql=__DIR__.'/../../../database/update_v50_producao_segura_tiny_vsm.sql';
    if(is_file($sql)){
      $chunks=array_filter(array_map('trim', explode(';', file_get_contents($sql))));
      foreach($chunks as $q){ try { Database::connection('core')->exec($q); $mensagens[]='SQL OK: '.substr($q,0,90); } catch(Throwable $e){ $mensagens[]='SQL aviso: '.$e->getMessage(); } }
    }
    try { Database::recordMigration('v50_producao_segura','v50','Produção segura, Tiny validado e sistema leve'); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    SimpleCache::forget();
    $_SESSION['update_v50_resultado']=$mensagens;
    Audit::event('database.update_v50','sucesso',['mensagem'=>'V50 aplicada.','contexto'=>['mensagens'=>$mensagens]]);
    redirect('index.php?page=central-tecnica&v50=1');
  }
}
