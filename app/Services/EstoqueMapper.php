<?php
class EstoqueMapper {
  private static function pick(array $arr, array $keys, $default='') { foreach($keys as $k){ if(isset($arr[$k]) && $arr[$k] !== '') return $arr[$k]; } return $default; }

  public static function extrairItensBaixa(array $payload): array {
    $itens = [];
    $origens = [
      $payload['itens'] ?? null,
      $payload['produtos'] ?? null,
      $payload['pedido']['itens'] ?? null,
      $payload['nota']['itens'] ?? null,
      $payload['nfe']['itens'] ?? null,
    ];
    foreach($origens as $lista){
      if(!is_array($lista)) continue;
      foreach($lista as $item){
        $i = $item['item'] ?? $item;
        if(!is_array($i)) continue;
        $sku = (string)self::pick($i, ['sku','codigo','codigoProduto','codigo_produto','referencia']);
        $qtd = (float)str_replace(',','.',(string)self::pick($i, ['quantidade','qtd','qtde'], 0));
        if($sku !== '' && $qtd > 0){
          $itens[] = [
            'sku' => $sku,
            'quantidade' => $qtd,
            'descricao' => (string)self::pick($i, ['descricao','nome','produto']),
          ];
        }
      }
    }
    return $itens;
  }

  public static function tinyEventoParaVsmBaixa(array $payload): array {
    $referencia = (string)($payload['id'] ?? $payload['numero'] ?? $payload['numeroPedido'] ?? $payload['pedido']['id'] ?? $payload['nota']['id'] ?? '');
    return [
      'origem' => 'tiny',
      'tipo' => 'baixa_estoque',
      'referencia' => $referencia,
      'data' => date('c'),
      'trace_id' => RequestContext::id(),
      'itens' => self::extrairItensBaixa($payload),
      'payload_origem' => $payload,
    ];
  }
}
