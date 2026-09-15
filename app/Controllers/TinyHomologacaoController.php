<?php
class TinyHomologacaoController extends BaseModuleController {
  public function dispatch(string $page): void {
    Auth::requireLogin();
    switch ($page) {
      case 'tiny-v2-homologacao': $this->v2(); break;
      case 'tiny-v2-homologacao-executar': $this->v2Executar(); break;
      case 'tiny-v3-homologacao': $this->v3(); break;
      case 'tiny-v3-homologacao-executar': $this->v3Executar(); break;
      default: redirect('index.php?page=central-homologacao');
    }
  }
  public static function routes(): array { return ['tiny-v2-homologacao','tiny-v2-homologacao-executar','tiny-v3-homologacao','tiny-v3-homologacao-executar']; }
  private function v2(): void {
    PermissionService::require('homologacao','visualizar');
    $cfg = IntegrationConfig::get();
    $skuPadrao = trim((string)($_GET['sku'] ?? 'HUB-TESTE-001'));
    $pedidoPadrao = trim((string)($_GET['pedido_teste'] ?? ''));
    $estoquePadrao = trim((string)($_GET['estoque_homologacao'] ?? ''));
    $resumo = TinyV2HomologationService::resumo($cfg);
    $ultimoResultado = $_SESSION['tiny_v2_homologacao_ultimo'] ?? null;
    unset($_SESSION['tiny_v2_homologacao_ultimo']);
    $pageTitle = 'Tiny V2 - Homologação';
    $this->view('tiny_v2_homologacao', compact('pageTitle','cfg','skuPadrao','pedidoPadrao','estoquePadrao','resumo','ultimoResultado'));
  }
  private function v2Executar(): void {
    PermissionService::require('homologacao','executar');
    Csrf::validate();
    try { $_SESSION['tiny_v2_homologacao_ultimo'] = TinyV2HomologationService::executar($_POST); }
    catch (Throwable $e) {
      $_SESSION['tiny_v2_homologacao_ultimo'] = ['aprovado'=>false, 'status_final'=>'Erro ao executar homologação Tiny V2', 'trace_id'=>RequestContext::id(), 'erro'=>$e->getMessage(), 'executado_em'=>date('Y-m-d H:i:s')];
      Audit::exception($e, 'tiny.v2.homologacao.erro', ['codigo_erro'=>'TINY_V2_HOMOLOGATION_ERROR']);
    }
    redirect('index.php?page=tiny-v2-homologacao&executado=1');
  }
  private function v3(): void {
    PermissionService::require('homologacao','visualizar');
    $cfg = IntegrationConfig::get();
    $skuPadrao = trim((string)($_GET['sku'] ?? 'HUB-TESTE-001'));
    $pedidoPadrao = trim((string)($_GET['pedido_teste'] ?? ''));
    $estoquePadrao = trim((string)($_GET['estoque_homologacao'] ?? ''));
    $resumo = TinyV3HomologationService::resumo($cfg);
    $ultimoResultado = $_SESSION['tiny_v3_homologacao_ultimo'] ?? null;
    unset($_SESSION['tiny_v3_homologacao_ultimo']);
    $pageTitle = 'Tiny V3 - Homologação';
    $this->view('tiny_v3_homologacao', compact('pageTitle','cfg','skuPadrao','pedidoPadrao','estoquePadrao','resumo','ultimoResultado'));
  }
  private function v3Executar(): void {
    PermissionService::require('homologacao','executar');
    Csrf::validate();
    try { $_SESSION['tiny_v3_homologacao_ultimo'] = TinyV3HomologationService::executar($_POST); }
    catch (Throwable $e) {
      $_SESSION['tiny_v3_homologacao_ultimo'] = ['aprovado'=>false, 'status_final'=>'Erro ao executar homologação Tiny V3', 'trace_id'=>RequestContext::id(), 'erro'=>$e->getMessage(), 'executado_em'=>date('Y-m-d H:i:s')];
      Audit::exception($e, 'tiny.v3.homologacao.erro', ['codigo_erro'=>'TINY_V3_HOMOLOGATION_ERROR']);
    }
    redirect('index.php?page=tiny-v3-homologacao&executado=1');
  }
}
