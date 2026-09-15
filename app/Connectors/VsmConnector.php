<?php
class VsmConnector extends AbstractConnector {
  public function code(): string { return 'vsm_integradora'; }
  public function name(): string { return 'VSM pedidos-integradora'; }
  public function category(): string { return 'erp'; }
  public function status(): string { return 'ativo'; }
  public function capabilities(): array { return ['pedido_tiny_para_vsm','status','estoque','nfe_autorizada','anti_replay','hmac_opcional','trace_id']; }
  public function requirements(): array { return ['Base URL VSM','Credencial/API Key/Token','CNPJ/empresa/filial autorizados','Endpoints oficiais pedidos-integradora','Webhook Secret/HMAC se suportado']; }
  public function operations(): array { return ['pedido_tiny_para_vsm'=>'ativo','status'=>'homologacao','estoque'=>'homologacao','nfe_autorizada'=>'homologacao','webhook'=>'homologacao']; }
  public function risks(): array { return ['Credencial VSM não fornecida','Endpoint pedidos-integradora não homologado','Payload inválido/duplicado','HMAC/allowlist não configurado']; }
}
