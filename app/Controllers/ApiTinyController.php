<?php
/**
 * V54 - API Tiny Controller
 * Wrapper modular para os webhooks Tiny. A lógica legado permanece no ApiController
 * para compatibilidade, mas as rotas públicas passam por este controller dedicado.
 */
class ApiTinyController extends ApiController {
  public function dispatch(string $page): void {
    switch($page){
      case 'api/tiny/webhook/estoque': $this->webhookTinyEstoque(); break;
      case 'api/tiny/webhook/produto': $this->webhookTinyProduto(); break;
      case 'api/tiny/webhook/nota-fiscal': $this->webhookTinyNotaFiscal(); break;
      case 'api/tiny/webhook/situacao-pedido': $this->webhookTinySituacaoPedido(); break;
      case 'api/tiny/webhook/pedido': $this->webhookTinyPedidoVsm(); break;
      case 'api/webhook/tiny/evento': $this->webhookTinyEvento(); break;
      default: http_response_code(404); echo json_encode(['erro'=>'endpoint_tiny_nao_encontrado']);
    }
  }
}
