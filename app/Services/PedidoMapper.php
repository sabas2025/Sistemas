<?php
class PedidoMapper {
  private static function onlyDigits($v): string { return preg_replace('/\D+/', '', (string)$v); }
  private static function money($v): float { return round((float)str_replace(',','.',(string)$v), 2); }
  private static function pick(array $arr, array $keys, $default='') { foreach($keys as $k){ if(isset($arr[$k]) && $arr[$k] !== '') return $arr[$k]; } return $default; }

  private static function cliente(array $vsm): array {
    $c = $vsm['cliente'] ?? $vsm['comprador'] ?? [];
    $end = $c['endereco'] ?? $vsm['endereco'] ?? $vsm['entrega'] ?? [];
    return [
      'nome' => self::pick($c, ['nome','razao_social','razaoSocial'], 'Cliente não informado'),
      'tipo_pessoa' => strlen(self::onlyDigits(self::pick($c,['documento','cpf_cnpj','cpf','cnpj']))) === 14 ? 'J' : 'F',
      'cpf_cnpj' => self::onlyDigits(self::pick($c, ['documento','cpf_cnpj','cpf','cnpj'])),
      'ie' => self::pick($c, ['ie','inscricao_estadual','inscricaoEstadual']),
      'email' => self::pick($c, ['email']),
      'fone' => self::onlyDigits(self::pick($c, ['telefone','fone','celular'])),
      'endereco' => self::pick($end, ['logradouro','endereco','rua']),
      'numero' => self::pick($end, ['numero','nro'], 'S/N'),
      'complemento' => self::pick($end, ['complemento']),
      'bairro' => self::pick($end, ['bairro']),
      'cep' => self::onlyDigits(self::pick($end, ['cep'])),
      'cidade' => self::pick($end, ['cidade','municipio']),
      'uf' => strtoupper((string)self::pick($end, ['uf','estado'])),
    ];
  }

  public static function vsmParaTiny(array $vsm): array {
    $itensOrigem = $vsm['itens'] ?? $vsm['produtos'] ?? [];
    $itens = [];
    foreach ($itensOrigem as $item) {
      $qtd = (float)str_replace(',','.',(string)($item['quantidade'] ?? $item['qtd'] ?? 1));
      $valor = self::money($item['valor_unitario'] ?? $item['preco'] ?? $item['valor'] ?? 0);
      $itens[] = ['item' => array_filter([
        'codigo' => (string)self::pick($item, ['sku','codigo','codigoProduto']),
        'descricao' => (string)self::pick($item, ['descricao','nome','produto'], 'Produto'),
        'unidade' => (string)self::pick($item, ['unidade'], 'UN'),
        'quantidade' => $qtd,
        'valor_unitario' => $valor,
        'gtin' => self::onlyDigits(self::pick($item, ['gtin','ean','codigo_barras','codigoBarras'])),
        'ncm' => self::onlyDigits(self::pick($item, ['ncm'])),
        'cfop' => self::pick($item, ['cfop']),
        'origem' => self::pick($item, ['origem']),
        'cst' => self::pick($item, ['cst']),
        'csosn' => self::pick($item, ['csosn']),
      ], fn($v)=>$v!=='' && $v!==null)];
    }
    $frete = self::money($vsm['frete'] ?? $vsm['valor_frete'] ?? ($vsm['shipping']['valor'] ?? 0));
    $desconto = self::money($vsm['desconto'] ?? $vsm['valor_desconto'] ?? 0);
    $pedidoOrigem = $vsm['id'] ?? $vsm['pedido'] ?? $vsm['numero'] ?? $vsm['numeroPedido'] ?? '';
    $pag = $vsm['pagamento'] ?? [];
    $envio = $vsm['envio'] ?? [];
    return ['pedido' => array_filter([
      'numero_ecommerce' => (string)$pedidoOrigem,
      'cliente' => self::cliente($vsm),
      'itens' => $itens,
      'valor_frete' => $frete,
      'valor_desconto' => $desconto,
      'forma_pagamento' => $vsm['forma_pagamento'] ?? ($pag['forma'] ?? ''),
      'meio_pagamento' => $pag['meio'] ?? $pag['tipo'] ?? '',
      'parcelas' => $pag['parcelas'] ?? '',
      'forma_envio' => $vsm['forma_envio'] ?? ($envio['forma'] ?? ''),
      'transportadora' => $vsm['transportadora'] ?? ($envio['transportadora'] ?? ''),
      'frete_por_conta' => $vsm['frete_por_conta'] ?? ($envio['frete_por_conta'] ?? ''),
      'vendedor' => $vsm['vendedor'] ?? '',
      'nome_ecommerce' => $vsm['canal'] ?? $vsm['marketplace'] ?? 'VSM',
      'obs' => trim('Pedido importado da VSM. Origem: '.$pedidoOrigem.' '.($vsm['observacao'] ?? $vsm['obs'] ?? '')),
      'obs_interna' => $vsm['observacao_interna'] ?? 'Trace ID: '.RequestContext::id(),
    ], fn($v)=>$v!=='' && $v!==null)];
  }

  public static function valorTotal(array $vsm): float {
    if (isset($vsm['valor_total'])) return self::money($vsm['valor_total']);
    $total = 0;
    foreach (($vsm['itens'] ?? $vsm['produtos'] ?? []) as $item) {
      $total += ((float)str_replace(',','.',(string)($item['quantidade'] ?? $item['qtd'] ?? 1))) * self::money($item['valor_unitario'] ?? $item['preco'] ?? $item['valor'] ?? 0);
    }
    $total += self::money($vsm['frete'] ?? $vsm['valor_frete'] ?? 0);
    $total -= self::money($vsm['desconto'] ?? $vsm['valor_desconto'] ?? 0);
    return round($total,2);
  }

  public static function extrairPedidoTinyId(array $ret): ?string {
    $candidatos = [
      $ret['retorno']['registros'][0]['registro']['id'] ?? null,
      $ret['retorno']['registro']['id'] ?? null,
      $ret['retorno']['pedido']['id'] ?? null,
      $ret['retorno']['id'] ?? null,
      $ret['id'] ?? null,
    ];
    foreach ($candidatos as $v) if ($v !== null && $v !== '') return (string)$v;
    return null;
  }
}
