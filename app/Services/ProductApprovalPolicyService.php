<?php
class ProductApprovalPolicyService {
  public static function ensureSchema(): void {
    SchemaRuntimePolicyService::requireColumns('configuracoes_integracao', [
      'produto_novo_aprovacao_modo',
      'produto_novo_auto_fallback_manual',
      'produto_novo_auto_exigir_ean',
      'produto_novo_auto_exigir_ncm',
      'produto_novo_auto_exigir_categoria',
      'produto_novo_auto_bloquear_duplicidade',
      'pedido_tiny_vsm_validacao_obrigatoria',
      'pedido_tiny_vsm_aprovacao_manual',
      'pedido_tiny_vsm_auto_enviar_validos',
      'pedido_tiny_vsm_exigir_sku_mapeado',
      'pedido_tiny_vsm_exigir_cliente_documento',
      'pedido_tiny_vsm_exigir_endereco',
      'pedido_tiny_vsm_status_permitidos',
      'vsm_endpoint_pedido'
    ], 'política de aprovação de produtos e pedidos');
  }

  public static function config(): array {
    self::ensureSchema();
    $cfg = IntegrationConfig::get();
    $cfg['produto_novo_aprovacao_modo'] = in_array(($cfg['produto_novo_aprovacao_modo'] ?? 'manual'), ['manual','automatico'], true) ? $cfg['produto_novo_aprovacao_modo'] : 'manual';
    $defaults = [
      'produto_novo_auto_fallback_manual'=>1,'produto_novo_auto_exigir_ean'=>1,'produto_novo_auto_exigir_ncm'=>1,'produto_novo_auto_exigir_categoria'=>1,'produto_novo_auto_bloquear_duplicidade'=>1,
      'pedido_tiny_vsm_validacao_obrigatoria'=>1,'pedido_tiny_vsm_aprovacao_manual'=>1,'pedido_tiny_vsm_auto_enviar_validos'=>0,'pedido_tiny_vsm_exigir_sku_mapeado'=>1,'pedido_tiny_vsm_exigir_cliente_documento'=>1,'pedido_tiny_vsm_exigir_endereco'=>1,
      'pedido_tiny_vsm_status_permitidos'=>'aprovado,pago,faturado,pronto para envio','vsm_endpoint_pedido'=>'/api/pedidos'
    ];
    foreach($defaults as $k=>$v){ if(!isset($cfg[$k]) || $cfg[$k]==='') $cfg[$k]=$v; }
    return $cfg;
  }

  public static function salvar(array $post): void {
    self::ensureSchema();
    $modo = ($post['produto_novo_aprovacao_modo'] ?? 'manual') === 'automatico' ? 'automatico' : 'manual';
    $endpoint = trim((string)($post['vsm_endpoint_pedido'] ?? '/api/pedidos')) ?: '/api/pedidos';
    if(!str_starts_with($endpoint,'/')) $endpoint='/'.$endpoint;
    if(preg_match('~https?://|localhost|127\.0\.0\.1~i',$endpoint)) throw new RuntimeException('Endpoint de pedido VSM deve ser caminho relativo, exemplo /api/pedidos.');
    $campos = [
      'produto_novo_aprovacao_modo'=>$modo,
      'produto_novo_auto_fallback_manual'=>!empty($post['produto_novo_auto_fallback_manual'])?1:0,
      'produto_novo_auto_exigir_ean'=>!empty($post['produto_novo_auto_exigir_ean'])?1:0,
      'produto_novo_auto_exigir_ncm'=>!empty($post['produto_novo_auto_exigir_ncm'])?1:0,
      'produto_novo_auto_exigir_categoria'=>!empty($post['produto_novo_auto_exigir_categoria'])?1:0,
      'produto_novo_auto_bloquear_duplicidade'=>!empty($post['produto_novo_auto_bloquear_duplicidade'])?1:0,
      'pedido_tiny_vsm_validacao_obrigatoria'=>!empty($post['pedido_tiny_vsm_validacao_obrigatoria'])?1:0,
      'pedido_tiny_vsm_aprovacao_manual'=>!empty($post['pedido_tiny_vsm_aprovacao_manual'])?1:0,
      'pedido_tiny_vsm_auto_enviar_validos'=>!empty($post['pedido_tiny_vsm_auto_enviar_validos'])?1:0,
      'pedido_tiny_vsm_exigir_sku_mapeado'=>!empty($post['pedido_tiny_vsm_exigir_sku_mapeado'])?1:0,
      'pedido_tiny_vsm_exigir_cliente_documento'=>!empty($post['pedido_tiny_vsm_exigir_cliente_documento'])?1:0,
      'pedido_tiny_vsm_exigir_endereco'=>!empty($post['pedido_tiny_vsm_exigir_endereco'])?1:0,
      'pedido_tiny_vsm_status_permitidos'=>trim((string)($post['pedido_tiny_vsm_status_permitidos'] ?? 'aprovado,pago,faturado,pronto para envio')),
      'vsm_endpoint_pedido'=>$endpoint,
      // Compatibilidade com as chaves antigas de governança: o modo V51 manda nas travas antigas.
      'sync_bloquear_produto_novo_vsm'=>$modo === 'automatico' ? 0 : 1,
      'sync_aprovacao_manual_produto_novo_vsm'=>$modo === 'automatico' ? 0 : 1,
      'sync_criar_produto_tiny'=>$modo === 'automatico' ? 1 : 0,
      'sync_criar_produto_se_nao_existir'=>$modo === 'automatico' ? 1 : 0
    ];
    $sets=[]; $vals=[]; foreach($campos as $k=>$v){ $sets[]="$k=?"; $vals[]=$v; } $vals[] = 1;
    Database::forTable('configuracoes_integracao')->prepare('UPDATE configuracoes_integracao SET '.implode(',',$sets).' WHERE id=?')->execute($vals);
    Audit::event('v51.politica.salva','sucesso',['mensagem'=>'Política V51 de produto novo e pedido Tiny→VSM salva.','contexto'=>$campos]);
  }

  public static function autoProductAllowed(array $payload, string $sku, string $tipoFila, array $baseConfig): array {
    $cfg = array_merge($baseConfig, self::config());
    if(($cfg['produto_novo_aprovacao_modo'] ?? 'manual') !== 'automatico') return ['ok'=>false,'fallback'=>true,'reason'=>'Modo manual ativo. Produto novo deve ir para aprovação.'];
    $category = ProdutoVsmGovernanceService::categoryMappingForPayload($payload);
    $pendingLike = array_merge($payload,[
      'sku'=>$sku,
      'ean'=>$payload['ean'] ?? $payload['gtin'] ?? $payload['codigo_gtin'] ?? '',
      'nome'=>$payload['nome'] ?? $payload['descricao'] ?? '',
      'categoria_tiny_id_sugerida'=>$category['id_categoria_tiny'] ?? null,
      'categoria_tiny_nome_sugerida'=>$category['nome_categoria_tiny'] ?? null,
      'payload_json'=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
    ]);
    $check = ProdutoVsmApprovalGuardService::checklist($pendingLike);
    $bloqueios=[];
    if(!empty($cfg['produto_novo_auto_exigir_ean']) && empty($check['ean'])) $bloqueios[]='EAN/GTIN obrigatório para automático.';
    if(!empty($cfg['produto_novo_auto_exigir_ncm']) && empty($check['ncm'])) $bloqueios[]='NCM obrigatório para automático.';
    if(!empty($cfg['produto_novo_auto_exigir_categoria']) && empty($check['categoria_ok'])) $bloqueios[]='Categoria VSM↔Tiny não mapeada.';
    if(!empty($cfg['produto_novo_auto_bloquear_duplicidade']) && !empty($check['duplicidades'])) $bloqueios[]='Possível duplicidade detectada por SKU/EAN/nome.';
    if($bloqueios) return ['ok'=>false,'fallback'=>!empty($cfg['produto_novo_auto_fallback_manual']),'reason'=>implode(' | ',$bloqueios),'check'=>$check];
    return ['ok'=>true,'fallback'=>false,'reason'=>'Automático liberado pelo checklist V51.','check'=>$check];
  }
}
