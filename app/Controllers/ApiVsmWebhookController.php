<?php
/**
 * V54 - API VSM Webhook Controller
 * Centraliza entradas públicas da VSM, mantendo nomenclatura correta VSM.
 */
class ApiVsmWebhookController extends ApiController {
  public function dispatch(string $page): void {
    switch($page){
      case 'api/webhook/vsm/pedido': $this->webhookVsmPedido(); break;
      case 'api/webhook/vsm/produto': $this->webhookVsmProduto(); break;
      case 'api/webhook/vsm/estoque': $this->webhookVsmEstoque(); break;
      case 'api/webhook/vsm/pedido-retorno': $this->webhookVsmRetornoPedido(); break;
      default: http_response_code(404); echo json_encode(['erro'=>'endpoint_vsm_nao_encontrado']);
    }
  }
}
