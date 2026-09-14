<?php
class AmazonConnector extends AbstractConnector {
  public function code(): string { return 'amazon'; }
  public function name(): string { return 'Amazon'; }
  public function category(): string { return 'marketplace'; }
  public function status(): string { return 'planejado'; }
  public function capabilities(): array { return ['pedidos','catalogo','estoque','precos','fulfillment']; }
  public function requirements(): array { return ['SP-API','seller account','IAM/LWA','mapeamento SKU/FNSKU']; }
}
