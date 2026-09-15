<?php
/** V104.18 - Preparação segura de cobrança real. Não cobra sem gateway configurado. */
class BillingGatewayService {
  public static function status(): array {
    $cfg = App::config()['commercial']['billing_gateway'] ?? [];
    $provider = strtolower((string)($cfg['provider'] ?? 'manual'));
    if($provider === 'manual' || $provider === '') return ['status'=>'alerta','mensagem'=>'Cobrança em modo manual/demonstrativo.'];
    if(!in_array($provider, ['mercado_pago','pix_banco','boleto_banco'], true)) return ['status'=>'erro','mensagem'=>'Gateway de cobrança não suportado: '.$provider];
    $hasToken = !empty($cfg['access_token']) || !empty($cfg['api_key']) || !empty($cfg['client_id']);
    if(!$hasToken) return ['status'=>'erro','mensagem'=>'Gateway '.$provider.' sem credencial configurada.'];
    self::recordProviderEvent($provider,'config_check','ok','Gateway configurado para integração real.'); return ['status'=>'ok','mensagem'=>'Gateway '.$provider.' configurado para integração real.','provider'=>$provider];
  }

  public static function buildInvoicePayload(array $invoice, array $license=[]): array {
    return [
      'external_reference' => 'hub-fatura-'.$invoice['id'],
      'description' => (string)($invoice['descricao'] ?? 'Mensalidade HUB'),
      'amount' => round(((int)($invoice['valor_centavos'] ?? 0))/100,2),
      'payer' => [
        'name' => (string)($license['cliente_nome'] ?? ''),
        'email' => (string)($license['email_responsavel'] ?? ''),
        'document_hash' => hash('sha256',(string)($license['documento'] ?? '')),
      ],
      'metadata' => ['hub_version'=>SystemVersionService::VERSION, 'tenant'=>TenantContextService::context()],
    ];
  }

  public static function recordProviderEvent(string $provider, string $evento, string $status, string $mensagem, array $context=[]): void {
    try {
      SchemaRuntimePolicyService::requireTable('comercial_billing_provider_events', 'eventos do provedor de cobrança');
      $json=json_encode(SensitiveDataService::mask($context), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
      $pdo=Database::forTable('comercial_billing_provider_events');
      $st=$pdo->prepare('INSERT INTO comercial_billing_provider_events(provider,evento,status,referencia,payload_hash,payload_json,criado_em) VALUES(?,?,?,?,?,?,NOW())');
      $st->execute([$provider,$evento,$status,(string)($context['referencia'] ?? ''),hash('sha256',$json ?: ''),$json]);
    } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
  }

  public static function recordEvent(string $tipo, string $status, string $mensagem, array $context=[]): void {
    try {
      SchemaRuntimePolicyService::requireTable('comercial_billing_gateway_events', 'eventos do gateway de cobrança');
      $pdo = Database::forTable('comercial_billing_gateway_events');
      $st=$pdo->prepare('INSERT INTO comercial_billing_gateway_events(tipo,status,mensagem,contexto_json,criado_em) VALUES(?,?,?,?,NOW())');
      $st->execute([$tipo,$status,$mensagem,json_encode(SensitiveDataService::mask($context), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
    } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
  }
}
