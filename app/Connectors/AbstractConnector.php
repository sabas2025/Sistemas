<?php
abstract class AbstractConnector implements ConnectorInterface {
  public function status(): string { return 'planejado'; }
  public function health(): array { return ['status'=>$this->status(), 'mensagem'=>'Conector catalogado. Implementação operacional depende de credenciais/homologação.']; }
  public function operations(): array { return ['pedido'=>'planejado','produto'=>'planejado','estoque'=>'planejado','fiscal'=>'planejado']; }
  public function risks(): array { return ['Credenciais não configuradas','Contrato de endpoints não homologado','Mapeamento SKU/categoria pendente']; }
}

