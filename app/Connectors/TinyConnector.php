<?php
class TinyConnector extends AbstractConnector {
  public function code(): string { return 'tiny'; }
  public function name(): string { return 'Tiny V2/V3'; }
  public function category(): string { return 'erp'; }
  public function status(): string { return 'ativo'; }
  public function capabilities(): array { return ['produtos','estoque','pedidos','webhooks','oauth_v3','auditoria','circuit_breaker']; }
  public function requirements(): array { return ['Tiny V2 token ou Tiny V3 OAuth','Client ID/Secret no V3','Redirect URI válido','SKU e pedido de homologação']; }
  public function operations(): array { return ['pedido'=>'ativo','produto'=>'ativo','estoque'=>'ativo','fiscal'=>'homologacao','webhook'=>'ativo','oauth_v3'=>'ativo']; }
  public function risks(): array { return ['Token/OAuth expirado','SKU sem mapeamento','Webhook sem secret em produção','Limite/rate da API Tiny']; }
}
