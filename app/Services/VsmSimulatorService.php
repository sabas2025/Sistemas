<?php
class VsmSimulatorService {
  public static function exemplos(): array {
    return [
      'pedido_novo'=>['pedidoId'=>'SIM-'.date('YmdHis'),'cliente'=>['nome'=>'Cliente Simulado'],'itens'=>[['sku'=>'HUB-TESTE-SKU','quantidade'=>1,'valor'=>10.90]],'total'=>10.90],
      'produto_novo'=>['sku'=>'HUB-TESTE-PRODUTO','ean'=>'7890000000000','ncm'=>'30049099','nome'=>'Produto simulado VSM','categoriaId'=>'CAT-SIM','ativo'=>true],
      'estoque'=>['sku'=>'HUB-TESTE-SKU','saldo'=>12,'origem'=>'vsm'],
      'nfe'=>['chave'=>'00000000000000000000000000000000000000000000','numero'=>'1','status'=>'autorizada','xml'=>'<nfe>simulada</nfe>'],
      'status_produto'=>['sku'=>'HUB-TESTE-SKU','ativo'=>false,'motivo'=>'Simulação V50'],
      'payload_invalido'=>['sku'=>'','saldo'=>-5,'categoriaId'=>null]
    ];
  }
  public static function simular(string $tipo): array {
    $payload = self::exemplos()[$tipo] ?? self::exemplos()['payload_invalido'];
    $resultado=['tipo'=>$tipo,'payload'=>$payload,'validacoes'=>[],'acao_recomendada'=>''];
    $add=function($nome,$ok,$msg) use (&$resultado){ $resultado['validacoes'][]=['nome'=>$nome,'ok'=>$ok,'mensagem'=>$msg]; };
    if($tipo==='produto_novo'){
      $add('SKU informado', !empty($payload['sku']), 'Produto precisa de SKU.');
      $add('EAN informado', !empty($payload['ean']), 'Produto de farmácia deve ter EAN/GTIN.');
      $add('NCM informado', !empty($payload['ncm']), 'Produto precisa de NCM antes de ir ao Tiny.');
      $add('Categoria informada', !empty($payload['categoriaId']), 'Categoria VSM precisa de mapeamento VSM↔Tiny.');
      $resultado['acao_recomendada']='Enviar para Produtos Pendentes VSM, nunca criar automaticamente no Tiny.';
    } elseif($tipo==='estoque'){
      $add('SKU informado', !empty($payload['sku']), 'Estoque deve identificar SKU mapeado.');
      $add('Saldo não negativo', ($payload['saldo'] ?? -1) >= 0, 'Bloquear estoque negativo quando regra estiver ativa.');
      $resultado['acao_recomendada']='Aplicar somente se SKU já estiver mapeado.';
    } elseif($tipo==='pedido_novo'){
      $add('Itens informados', !empty($payload['itens']), 'Pedido precisa de itens.');
      $add('Total informado', isset($payload['total']), 'Pedido precisa de total.');
      $resultado['acao_recomendada']='Gerar fila pedido_vsm_para_tiny se fluxo estiver ativo.';
    } else {
      $add('Payload estruturado', is_array($payload), 'Payload recebido para simulação.');
      $resultado['acao_recomendada']='Verificar regras de campos obrigatórios antes de integrar.';
    }
    Audit::event('vsm.simulador.executado','info',['mensagem'=>'Simulador VSM executado.','contexto'=>$resultado]);
    return $resultado;
  }
}
