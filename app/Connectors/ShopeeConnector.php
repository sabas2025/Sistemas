<?php
class ShopeeConnector extends AbstractConnector {
  public function code(): string { return 'shopee'; }
  public function name(): string { return 'Shopee'; }
  public function category(): string { return 'marketplace'; }
  public function status(): string { return 'planejado'; }
  public function capabilities(): array { return ['pedidos','produtos','estoque','precos']; }
  public function requirements(): array { return ['Partner ID/Key','Shop ID','token de loja','homologação de webhooks']; }
}
