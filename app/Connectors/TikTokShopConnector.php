<?php
class TikTokShopConnector extends AbstractConnector {
  public function code(): string { return 'tiktokshop'; }
  public function name(): string { return 'TikTok Shop'; }
  public function category(): string { return 'marketplace'; }
  public function status(): string { return 'planejado'; }
  public function capabilities(): array { return ['pedidos','produtos','estoque','webhooks']; }
  public function requirements(): array { return ['App Key/Secret','seller authorization','webhook secret','mapeamento de SKUs']; }
}
