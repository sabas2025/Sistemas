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

  /** Separa DDD (2 primeiros dígitos) e número de um telefone só com dígitos. */
  private static function splitFone(string $digits): array {
    $digits = self::onlyDigits($digits);
    if (strlen($digits) >= 10) return [substr($digits,0,2), substr($digits,2)];
    return ['', $digits];
  }

  /**
   * F6-07 etapa 4 (2026-09-26) — monta o PedidoCadastroDTO da VSM Conecta Venda
   * (contrato pedidos-integradora) a partir do payload intermediário produzido por
   * PedidoTinyVsmValidationService::toVsmPayload (que preserva o payload_original do Tiny).
   *
   * Campos derivados fielmente do pedido: nome, documento, e-mail, telefone/DDD, itens (sku,
   * quantidade, valor unitário, valorFinal=qtd*unitário, nomeCompleto), endereço, frete e total.
   * tipoPessoa é DERIVADO do documento (14 dígitos = jurídica).
   *
   * DECISÕES DE MAPEAMENTO para enums que o pedido Tiny não traz 1:1 (valores neutros/mais comuns,
   * a CONFIRMAR com a VSM/negócio — não são dado inventado do cliente):
   *   - tipo do pedido        = "0" (NORMAL)
   *   - pedidoEntrega.tipo    = "0" (DELIVERY, pois há endereço de entrega)
   *   - pagamento.tipoPagamento = "0" (PAGAMENTO_ANTECIPADO, padrão de e-commerce)
   *   - cliente.sexo          = "2" (INDEFINIDO — o Tiny não informa)
   * Campos obrigatórios sem origem (ex.: telefone/DDD ausentes) ficam vazios de propósito: a VSM
   * responde 400 e o erro é honesto, em vez de fabricar dado.
   */
  public static function tinyParaCadastroIntegradora(array $pv): array {
    $orig = $pv['payload_original'] ?? [];
    $d = $orig['dados'] ?? $orig['pedido'] ?? $orig;
    $cOrig = (is_array($d) ? ($d['cliente'] ?? $d['comprador'] ?? []) : []);
    $cli = $pv['cliente'] ?? [];
    $doc = self::onlyDigits($cli['documento'] ?? self::pick($cOrig, ['cpf_cnpj','documento','cpf','cnpj']));
    [$ddd,$fone] = self::splitFone((string)self::pick($cOrig, ['telefone','fone','celular'], (string)($cli['telefone'] ?? '')));

    $cliente = array_filter([
      'nome'        => (string)($cli['nome'] ?? self::pick($cOrig,['nome','razao_social','razaoSocial'], 'Cliente não informado')),
      'sexo'        => '2',
      'documento'   => $doc,
      'tipoPessoa'  => strlen($doc) === 14 ? '1' : '0',
      'telefone'    => $fone,
      'ddd'         => $ddd,
      'email'       => (string)($cli['email'] ?? self::pick($cOrig,['email'])),
    ], fn($v)=>$v!=='' && $v!==null);

    $itens = [];
    foreach (($pv['itens'] ?? []) as $it) {
      $q  = (float)str_replace(',','.',(string)($it['quantidade'] ?? 0));
      $vu = self::money($it['valor_unitario'] ?? 0);
      $itens[] = array_filter([
        'sku'          => (string)($it['sku'] ?? ''),
        'quantidade'   => $q,
        'valorUnitario'=> $vu,
        'valorFinal'   => round($q * $vu, 2),
        'nomeCompleto' => (string)($it['descricao'] ?? $it['sku'] ?? 'Produto'),
      ], fn($v)=>$v!=='' && $v!==null);
    }

    $frete = self::money($pv['frete'] ?? 0);
    $total = self::money($pv['valor_total'] ?? 0);

    $end = $pv['endereco'] ?? [];
    $cep = self::onlyDigits(self::pick($end, ['cep']));
    $entrega = array_filter([
      'tipo'        => '0',
      'valorEntrega'=> $frete,
      'cep'         => strlen($cep) === 8 ? $cep : '',
      'logradouro'  => (string)self::pick($end, ['logradouro','endereco','rua']),
      'bairro'      => (string)self::pick($end, ['bairro']),
      'numero'      => (string)self::pick($end, ['numero','nro'], 'S/N'),
      'cidade'      => (string)self::pick($end, ['cidade','municipio']),
      'siglaEstado' => strtoupper((string)self::pick($end, ['uf','estado','siglaEstado'])),
    ], fn($v)=>$v!=='' && $v!==null);

    return array_filter([
      'cliente'         => $cliente,
      'pedidoEntrega'   => $entrega,
      'pedidoPagamento' => ['tipoPagamento'=>'0', 'valorPagamento'=>$total > 0 ? $total : $frete],
      'pedidoItem'      => $itens,
      'tipo'            => '0',
      'valorFinal'      => $total,
      'dataAprovacao'   => date('Y-m-d H:i:s'),
    ], fn($v)=>$v!==[] && $v!==null);
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
