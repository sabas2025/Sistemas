<?php
class OmieConnector extends AbstractConnector {
  public function code(): string { return 'omie'; }
  public function name(): string { return 'Omie'; }
  public function category(): string { return 'erp'; }
  public function status(): string { return 'planejado'; }
  public function capabilities(): array { return ['pedidos','produtos','estoque','financeiro','notas_fiscais']; }
  public function requirements(): array { return ['App Key','App Secret','empresa/filial','mapeamento de categorias e SKUs']; }
}
