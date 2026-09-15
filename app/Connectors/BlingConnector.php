<?php
class BlingConnector extends AbstractConnector {
  public function code(): string { return 'bling'; }
  public function name(): string { return 'Bling'; }
  public function category(): string { return 'erp'; }
  public function status(): string { return 'planejado'; }
  public function capabilities(): array { return ['pedidos','produtos','estoque','notas_fiscais','oauth']; }
  public function requirements(): array { return ['Client ID/Secret Bling','OAuth validado','mapeamento SKU','ambiente de homologação']; }
  public function health(): array { return ['status'=>'planejado','mensagem'=>'Conector Bling preparado para plug-in; ativação depende de credenciais e contrato de endpoints.']; }
}
