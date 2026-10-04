<?php
class FiscalController extends BaseModuleController {
  public function dispatch(string $page): void {
    // As sub-telas fiscais do subsistema Modelo A (fiscal-dashboard/xml/timeline/
    // reconciliacao/health/reenviar) foram removidas: liam notas_fiscais/nfe_integracao,
    // que nenhum fluxo alimenta. A tela fiscal real é a index() (VSM → HUB → Tiny).
    $this->index();
  }
  public static function routes(): array { return ['fiscal']; }
  public function index(): void {
    PermissionService::require('fiscal','visualizar');
    // A tela fiscal reflete o fluxo REAL VSM → HUB → Tiny (ciclo de vida do pedido:
    // pedidos_hub / pedidos_nfe_xml), e não o subsistema notas_fiscais/nfe_integracao,
    // que nenhum fluxo alimenta. Somente leitura.
    $erroFiscal=null; $resumo=[]; $aguardando=[]; $ultimas=[];
    try {
      $resumo     = PedidoCicloVidaService::resumoFiscal();
      $aguardando = PedidoCicloVidaService::aguardandoTiny(50);
      $ultimas    = PedidoCicloVidaService::ultimasNfe(50);
    } catch(Throwable $e){ $erroFiscal=$e->getMessage(); }
    $pageTitle='XML / NF-e Enterprise';
    $this->view('fiscal',compact('pageTitle','resumo','aguardando','ultimas','erroFiscal'));
  }
}
