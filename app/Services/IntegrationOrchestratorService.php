<?php
class IntegrationOrchestratorService {
  /**
   * V39 - Catálogo completo de fluxos operacionais Tiny ⇄ VSM.
   * Cada fluxo pode ser ativado/desativado por checkbox e possui ordem operacional.
   */
  private static array $defaults = [
    // Macros VSM -> Tiny
    'sync_receber_pedidos_vsm' => 0,
    'sync_enviar_pedido_tiny' => 0,
    'sync_vsm_enviar_estoque_tiny' => 1,
    'sync_vsm_status_produto_tiny' => 1,
    'sync_vsm_enviar_nota_tiny' => 1,
    'sync_bloquear_produto_novo_vsm' => 1,
    'sync_permitir_produto_novo_vsm_manual' => 1,
    'sync_aprovacao_manual_produto_novo_vsm' => 1,
    'sync_exigir_categoria_mapeada_vsm' => 1,
    'sync_permitir_atualizar_produto_existente_vsm' => 1,
    'sync_permitir_estoque_vsm_tiny' => 1,
    'sync_permitir_status_vsm_tiny' => 1,

    // Macros Tiny -> VSM
    'sync_tiny_enviar_nota_vsm' => 0,
    'sync_tiny_enviar_pedido_vsm' => 1,
    'sync_tiny_enviar_estoque_vsm' => 0,
    'sync_tiny_status_produto_vsm' => 0,
    'sync_tiny_bloquear_produto_novo_vsm' => 1,

    // Travamentos
    'sync_exigir_mapeamento_sku' => 1,
    'sync_exigir_nfe_autorizada' => 1,
    'sync_ordem_envio' => 'pedido_tiny_enviar_vsm,nfe_vsm_enviar_tiny,estoque_vsm_enviar_tiny,produto_status_vsm_enviar_tiny,produto_novo_vsm_bloquear,produto_novo_tiny_bloquear',
  ];

  public static function catalogoFluxos(): array {
    return [
      'pedido_vsm_receber' => [
        'grupo'=>'Pedidos','direcao'=>'VSM → Hub','regra'=>'sync_receber_pedidos_vsm','titulo'=>'Receber pedidos da VSM','origem'=>'VSM','destino'=>'Hub','tipo'=>'pedido','padrao'=>0,
        'descricao'=>'Fluxo opcional para cenários em que a VSM também origina pedidos para o Hub.',
        'risco'=>'Deixe desligado quando o pedido nasce no Tiny para evitar duplicidade operacional.'
      ],
      'pedido_vsm_enviar_tiny' => [
        'grupo'=>'Pedidos','direcao'=>'Hub → Tiny','regra'=>'sync_enviar_pedido_tiny','titulo'=>'Enviar pedidos da VSM para o Tiny','origem'=>'Hub','destino'=>'Tiny','tipo'=>'pedido','padrao'=>0,
        'descricao'=>'Fluxo opcional para operações em que a VSM cria pedidos e o Tiny deve receber cópia.',
        'risco'=>'Deixe desligado no modelo Tiny → HUB → VSM para não criar pedido duplicado no Tiny.'
      ],
      'pedido_tiny_enviar_vsm' => [
        'grupo'=>'Pedidos','direcao'=>'Tiny → Hub → VSM','regra'=>'sync_tiny_enviar_pedido_vsm','titulo'=>'Enviar pedido gerado no Tiny para a VSM','origem'=>'Tiny','destino'=>'VSM','tipo'=>'pedido','padrao'=>1,
        'descricao'=>'Fluxo oficial recomendado: o Tiny gera o pedido, o Hub valida e envia para a VSM pela API pedidos-integradora.',
        'risco'=>'Exigir validação de cliente, documento, endereço, itens e SKU mapeado antes de enviar para a VSM.'
      ],
      'nfe_vsm_enviar_tiny' => [
        'grupo'=>'Notas fiscais','direcao'=>'VSM → Hub → Tiny','regra'=>'sync_vsm_enviar_nota_tiny','titulo'=>'Enviar NF-e autorizada do VSM para a Tiny','origem'=>'VSM','destino'=>'Tiny','tipo'=>'nfe','padrao'=>1,
        'descricao'=>'Recebe a NF-e/XML autorizada pela VSM, valida no Hub e envia para a Tiny vinculando ao pedido correto.',
        'risco'=>'Nunca enviar NF-e sem autorização, XML inválido ou sem vínculo de pedido para a Tiny.'
      ],
      'nfe_tiny_enviar_vsm' => [
        'grupo'=>'Notas fiscais','direcao'=>'Tiny → Hub → VSM','regra'=>'sync_tiny_enviar_nota_vsm','titulo'=>'Enviar NF-e autorizada do Tiny para a VSM','origem'=>'Tiny','destino'=>'VSM','tipo'=>'nfe','padrao'=>0,
        'descricao'=>'Envia chave, XML/PDF e status fiscal quando a nota estiver autorizada.',
        'risco'=>'Nunca enviar NF-e rejeitada/cancelada como se estivesse autorizada.'
      ],
      'estoque_tiny_enviar_vsm' => [
        'grupo'=>'Estoque','direcao'=>'Tiny → Hub → VSM','regra'=>'sync_tiny_enviar_estoque_vsm','titulo'=>'Tiny atualiza estoque na VSM','origem'=>'Tiny','destino'=>'VSM','tipo'=>'estoque','padrao'=>0,
        'descricao'=>'Propaga saldo alterado no Tiny para a VSM com fila, retry e reconciliação.',
        'risco'=>'Evitar loop de estoque quando VSM também envia estoque para Tiny.'
      ],
      'estoque_vsm_enviar_tiny' => [
        'grupo'=>'Estoque','direcao'=>'VSM → Hub → Tiny','regra'=>'sync_vsm_enviar_estoque_tiny','titulo'=>'VSM atualiza estoque no Tiny','origem'=>'VSM','destino'=>'Tiny','tipo'=>'estoque','padrao'=>1,
        'descricao'=>'Atualiza saldo no Tiny quando o estoque oficial vier da VSM.',
        'risco'=>'Definir fonte mestre para evitar ida e volta infinita.'
      ],
      'produto_status_tiny_enviar_vsm' => [
        'grupo'=>'Produtos','direcao'=>'Tiny → Hub → VSM','regra'=>'sync_tiny_status_produto_vsm','titulo'=>'Tiny envia status ativo/inativo para VSM','origem'=>'Tiny','destino'=>'VSM','tipo'=>'produto_status','padrao'=>0,
        'descricao'=>'Atualiza na VSM se o produto ficou ativo ou inativo no Tiny.',
        'risco'=>'Não criar produto novo automaticamente se SKU não existir.'
      ],
      'produto_status_vsm_enviar_tiny' => [
        'grupo'=>'Produtos','direcao'=>'VSM → Hub → Tiny','regra'=>'sync_vsm_status_produto_tiny','titulo'=>'VSM envia status ativo/inativo para Tiny','origem'=>'VSM','destino'=>'Tiny','tipo'=>'produto_status','padrao'=>1,
        'descricao'=>'Atualiza no Tiny se o produto ficou ativo ou inativo na VSM.',
        'risco'=>'Inativação deve respeitar pedido pendente e estoque positivo.'
      ],
      'produto_novo_vsm_bloquear' => [
        'grupo'=>'Produtos','direcao'=>'VSM → Hub','regra'=>'sync_bloquear_produto_novo_vsm','titulo'=>'Bloquear produto novo vindo da VSM','origem'=>'VSM','destino'=>'Hub','tipo'=>'produto_novo','padrao'=>1,
        'descricao'=>'Produto sem mapeamento vira pendência manual em vez de ser criado no Tiny.',
        'risco'=>'Protege contra cadastro incompleto, EAN errado, NCM ausente e duplicidade.'
      ],
      'produto_novo_tiny_bloquear' => [
        'grupo'=>'Produtos','direcao'=>'Tiny → Hub','regra'=>'sync_tiny_bloquear_produto_novo_vsm','titulo'=>'Bloquear produto novo vindo do Tiny para VSM','origem'=>'Tiny','destino'=>'Hub','tipo'=>'produto_novo','padrao'=>1,
        'descricao'=>'Produto sem SKU mapeado não é criado automaticamente na VSM.',
        'risco'=>'Protege a VSM contra produto não homologado.'
      ],
    ];
  }

  public static function keys(): array {
    $keys = self::$defaults;
    foreach(self::catalogoFluxos() as $id=>$f){ $keys['fluxo_'.$id] = (int)$f['padrao']; }
    return $keys;
  }

  public static function all(): array {
    $cfg = IntegrationConfig::get();
    $out = self::keys();
    foreach($out as $k=>$v){ if(array_key_exists($k,$cfg)) $out[$k] = $cfg[$k]; }
    $out['sync_ordem_envio'] = self::normalizarOrdem((string)($out['sync_ordem_envio'] ?? self::$defaults['sync_ordem_envio']));
    // Mantém compatibilidade: se uma macro estiver desligada, os fluxos ligados a ela ficam efetivamente bloqueados.
    return $out;
  }

  public static function normalizarOrdem(string $ordem): string {
    $permitidos = array_keys(self::catalogoOrdem());
    $itens = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $ordem)));
    $limpos = [];
    foreach($itens as $i){ if(in_array($i,$permitidos,true) && !in_array($i,$limpos,true)) $limpos[]=$i; }
    foreach($permitidos as $p){ if(!in_array($p,$limpos,true)) $limpos[]=$p; }
    return implode(',', $limpos);
  }

  public static function ordemLista(): array { return explode(',', self::all()['sync_ordem_envio']); }

  public static function catalogoOrdem(): array {
    $out=[];
    foreach(self::catalogoFluxos() as $id=>$f){ $out[$id] = $f['titulo']; }
    return $out;
  }

  public static function label(string $key): string {
    $labels = [
      'sync_receber_pedidos_vsm'=>'Receber pedidos da VSM no Hub',
      'sync_enviar_pedido_tiny'=>'Enviar/criar pedido no Tiny',
      'sync_vsm_enviar_estoque_tiny'=>'VSM envia atualização de estoque para Tiny',
      'sync_vsm_status_produto_tiny'=>'VSM envia status ativo/inativo para Tiny',
      'sync_vsm_enviar_nota_tiny'=>'VSM envia NF-e autorizada para Tiny',
      'sync_bloquear_produto_novo_vsm'=>'Bloquear produto novo vindo da VSM',
      'sync_permitir_produto_novo_vsm_manual'=>'Permitir receber produto novo da VSM como pendência manual',
      'sync_aprovacao_manual_produto_novo_vsm'=>'Exigir aprovação manual para produto novo VSM',
      'sync_exigir_categoria_mapeada_vsm'=>'Exigir categoria VSM ↔ Tiny mapeada',
      'sync_permitir_atualizar_produto_existente_vsm'=>'Permitir atualizar cadastro de produto já mapeado',
      'sync_permitir_estoque_vsm_tiny'=>'Permitir estoque VSM → Tiny para SKU mapeado',
      'sync_permitir_status_vsm_tiny'=>'Permitir status ativo/inativo VSM → Tiny para SKU mapeado',
      'sync_tiny_enviar_nota_vsm'=>'Tiny envia NF-e gerada/autorizada para VSM',
      'sync_tiny_enviar_pedido_vsm'=>'Tiny envia pedido para VSM',
      'sync_tiny_enviar_estoque_vsm'=>'Tiny envia atualização de estoque para VSM',
      'sync_tiny_status_produto_vsm'=>'Tiny envia status ativo/inativo para VSM',
      'sync_tiny_bloquear_produto_novo_vsm'=>'Bloquear produto novo vindo do Tiny para VSM',
      'sync_exigir_mapeamento_sku'=>'Exigir SKU mapeado antes de qualquer envio',
      'sync_exigir_nfe_autorizada'=>'Enviar NF-e à VSM somente quando autorizada',
      'sync_ordem_envio'=>'Ordem de envio configurável',
    ];
    if(str_starts_with($key,'fluxo_')){
      $id = substr($key, 6);
      $cat = self::catalogoFluxos();
      return $cat[$id]['titulo'] ?? $key;
    }
    return $labels[$key] ?? $key;
  }

  public static function gruposFluxos(array $cfg=[]): array {
    $cfg = $cfg ?: self::all();
    $grupos = [];
    foreach(self::catalogoFluxos() as $id=>$f){
      $f['id']=$id;
      $f['ativo']=!empty($cfg['fluxo_'.$id]) && !empty($cfg[$f['regra']]);
      $f['macro_ativa']=!empty($cfg[$f['regra']]);
      $grupos[$f['grupo']][]=$f;
    }
    return $grupos;
  }

  public static function fluxos(array $rules = []): array {
    $cfg = $rules ?: self::all();
    $out=[];
    foreach(self::catalogoFluxos() as $id=>$f){
      $out[] = ['id'=>$id,'grupo'=>$f['direcao'],'regra'=>$f['regra'],'destino'=>$f['destino'],'tipo'=>$f['tipo'],'risco'=>$f['risco'],'titulo'=>$f['titulo'],'ativo'=>!empty($cfg['fluxo_'.$id]) && !empty($cfg[$f['regra']])];
    }
    return $out;
  }

  public static function podeExecutar(string $regra, array $payload=[]): array {
    $cfg = self::all();
    $fluxoId = (string)($payload['fluxo_id'] ?? '');
    if($fluxoId !== '' && array_key_exists('fluxo_'.$fluxoId, $cfg) && empty($cfg['fluxo_'.$fluxoId])){
      Audit::event('orquestracao.fluxo_bloqueado','alerta',[ 'mensagem'=>'Fluxo específico bloqueado por checkbox.', 'contexto'=>['fluxo'=>$fluxoId,'regra'=>$regra], 'payload'=>$payload ]);
      return ['ok'=>false,'codigo'=>'FLOW_DISABLED','mensagem'=>'Fluxo específico '.$fluxoId.' está desativado.'];
    }
    if(empty($cfg[$regra])){
      Audit::event('orquestracao.fluxo_bloqueado','alerta',[ 'mensagem'=>'Fluxo bloqueado por macro de orquestração desativada.', 'contexto'=>['regra'=>$regra,'descricao'=>self::label($regra)], 'payload'=>$payload ]);
      return ['ok'=>false,'codigo'=>'ORCHESTRATION_DISABLED','mensagem'=>self::label($regra).' está desativado.'];
    }
    if(!empty($cfg['sync_exigir_mapeamento_sku']) && array_key_exists('sku_mapeado',$payload) && !$payload['sku_mapeado']){
      return ['ok'=>false,'codigo'=>'SKU_NOT_MAPPED','mensagem'=>'SKU não mapeado; envio bloqueado para evitar produto novo/errado.'];
    }
    if(in_array($regra, ['sync_tiny_enviar_nota_vsm','sync_vsm_enviar_nota_tiny'], true) && !empty($cfg['sync_exigir_nfe_autorizada']) && array_key_exists('nfe_autorizada',$payload) && !$payload['nfe_autorizada']){
      return ['ok'=>false,'codigo'=>'NFE_NOT_AUTHORIZED','mensagem'=>'NF-e ainda não autorizada; envio para VSM bloqueado.'];
    }
    return ['ok'=>true,'codigo'=>'OK','mensagem'=>'Fluxo autorizado.'];
  }
}
