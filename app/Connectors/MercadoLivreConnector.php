<?php
class MercadoLivreConnector extends AbstractConnector {
  public function code(): string { return 'mercado_livre'; }
  public function name(): string { return 'Mercado Livre'; }
  public function category(): string { return 'marketplace'; }
  public function status(): string { return 'planejado'; }
  public function capabilities(): array { return ['pedidos','anuncios','estoque','precos','mensageria']; }
  public function requirements(): array { return ['OAuth Mercado Livre','seller_id','app_id/app_secret','política de sincronismo por SKU']; }
}
