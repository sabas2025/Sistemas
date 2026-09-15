<?php
class ProdutoMapper {
  private static function pick(array $arr, array $keys, $default='') {
    foreach($keys as $k){ if(isset($arr[$k]) && $arr[$k] !== '') return $arr[$k]; }
    return $default;
  }
  private static function onlyDigits($v): string { return preg_replace('/\D+/', '', (string)$v); }
  private static function money($v): float { return round((float)str_replace(',','.',(string)$v), 2); }
  public static function sku(array $vsm): string { return (string)self::pick($vsm, ['sku','codigo','codigoProduto','referencia','codigo_produto']); }
  public static function nome(array $vsm): string { return trim((string)self::pick($vsm, ['nome','descricao','produto','descricaoProduto'], '')); }

  public static function normalizarAtivo($valor): int {
    if (is_bool($valor)) return $valor ? 1 : 0;
    $v = strtolower(trim((string)$valor));
    if ($v === '') return 1;
    if (in_array($v, ['1','true','s','sim','a','ativo','active','habilitado','liberado'], true)) return 1;
    if (in_array($v, ['0','false','n','nao','não','i','inativo','inactive','desativado','bloqueado'], true)) return 0;
    return 1;
  }

  public static function situacaoTiny(array $vsm): string {
    $raw = self::pick($vsm, ['situacao','status','ativo','statusProduto','status_produto'], 'A');
    if (self::normalizarAtivo($raw) === 0) return 'I';
    $s = strtoupper(trim((string)$raw));
    return in_array($s, ['I','INATIVO','INACTIVE','0','FALSE','N'], true) ? 'I' : 'A';
  }

  public static function estoque(array $vsm): ?float {
    foreach(['estoque','estoque_atual','estoqueAtual','saldo','quantidade','qtd','available_quantity'] as $k){
      if(isset($vsm[$k]) && $vsm[$k] !== '') return (float)str_replace(',','.',(string)$vsm[$k]);
    }
    if(isset($vsm['dados']) && is_array($vsm['dados'])) return self::estoque($vsm['dados']);
    return null;
  }

  public static function determinarEvento(array $vsm): string {
    $acao = strtolower(trim((string)self::pick($vsm, ['acao','evento','tipo_evento','tipoEvento','operacao','tipo'], '')));
    $acao = str_replace([' ', '-'], '_', $acao);
    if (in_array($acao, ['estoque','atualizacao_estoque','atualizar_estoque','stock','stock_update','saldo','saldo_estoque'], true)) return 'produto_vsm_estoque_para_tiny';
    if (in_array($acao, ['status','situacao','ativo','inativo','ativar','inativar','status_update','atualizacao_status'], true)) return 'produto_vsm_status_para_tiny';
    if (in_array($acao, ['atualizar','alterar','produto_atualizado','update','product_update','cadastro_atualizado'], true)) return 'produto_vsm_atualizar_tiny';
    if (in_array($acao, ['novo','criar','produto_novo','create','product_create','cadastro_novo'], true)) return 'produto_vsm_para_tiny';

    $temEstoque = self::estoque($vsm) !== null;
    $temStatus = self::pick($vsm, ['situacao','status','ativo','statusProduto','status_produto'], null) !== null;
    $temNome = self::pick($vsm, ['nome','descricao','produto','descricaoProduto'], '') !== '';
    $temPreco = self::pick($vsm, ['preco_venda','precoVenda','preco','valor','valor_unitario'], '') !== '';

    if ($temEstoque && !$temNome && !$temPreco && !$temStatus) return 'produto_vsm_estoque_para_tiny';
    if ($temStatus && !$temNome && !$temPreco && !$temEstoque) return 'produto_vsm_status_para_tiny';
    if ($temEstoque || $temStatus) return 'produto_vsm_atualizar_tiny';
    return 'produto_vsm_para_tiny';
  }



  public static function referenciaEvento(array $vsm, string $tipoEvento): string {
    $sku = self::sku($vsm);
    $dataRef = (string)self::pick($vsm, ['data_referencia','dataReferencia','updated_at','atualizado_em','dataAtualizacao','data_atualizacao'], '');
    $estoque = self::estoque($vsm);
    $status = self::pick($vsm, ['situacao','status','ativo','statusProduto','status_produto'], '');
    if ($tipoEvento === 'produto_estoque_atualizado') return implode('|', ['vsm',$sku,$tipoEvento, $estoque === null ? 'null' : number_format((float)$estoque,3,'.',''), $dataRef]);
    if ($tipoEvento === 'produto_status_atualizado') return implode('|', ['vsm',$sku,$tipoEvento, self::situacaoTiny($vsm), $dataRef]);
    return implode('|', ['vsm',$sku,$tipoEvento, $dataRef ?: hash('sha256', json_encode($vsm, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))]);
  }

  public static function validarVsmProduto(array $vsm): array {
    $erros=[];
    $sku = self::sku($vsm);
    $nome = self::nome($vsm);
    $tipoFila = self::determinarEvento($vsm);
    if($sku==='') $erros[]='Produto sem SKU/código.';
    if(in_array($tipoFila, ['produto_vsm_para_tiny','produto_vsm_atualizar_tiny'], true) && $nome==='') $erros[]='Produto sem nome/descrição.';
    if($tipoFila === 'produto_vsm_estoque_para_tiny' && self::estoque($vsm) === null) $erros[]='Atualização de estoque sem saldo/estoque/quantidade.';
    if($tipoFila === 'produto_vsm_status_para_tiny' && self::pick($vsm, ['situacao','status','ativo','statusProduto','status_produto'], null) === null) $erros[]='Atualização de status sem campo ativo/status/situacao.';
    $precoVenda = self::money(self::pick($vsm, ['preco_venda','precoVenda','preco','valor','valor_unitario'], 0));
    $ncm = self::onlyDigits(self::pick($vsm, ['ncm']));
    $ean = self::onlyDigits(self::pick($vsm, ['gtin','ean','codigo_barras','codigoBarras']));
    $unidade = strtoupper((string)self::pick($vsm, ['unidade','unidadeMedida'], ''));
    if($precoVenda < 0) $erros[]='Preço do produto não pode ser negativo.';
    if($precoVenda == 0 && in_array($tipoFila, ['produto_vsm_para_tiny','produto_vsm_atualizar_tiny'], true)) $erros[]='Preço de venda zerado. Confirmar se isso é permitido antes de enviar ao Tiny.';
    if(isset($vsm['preco_custo']) && self::money($vsm['preco_custo']) < 0) $erros[]='Preço de custo não pode ser negativo.';
    if(self::estoque($vsm) !== null && self::estoque($vsm) < 0) $erros[]='Estoque não pode ser negativo.';
    if($ncm !== '' && strlen($ncm) !== 8) $erros[]='NCM deve conter 8 dígitos.';
    if($ean !== '' && !in_array(strlen($ean), [8,12,13,14], true)) $erros[]='EAN/GTIN deve ter 8, 12, 13 ou 14 dígitos.';
    if($unidade !== '' && strlen($unidade) > 6) $erros[]='Unidade comercial muito longa. Use UN, CX, KG, LT etc.';
    return $erros;
  }

  public static function vsmParaTiny(array $vsm): array {
    $sku = self::sku($vsm);
    $nome = self::nome($vsm);
    $produto = [
      'sequencia' => '1',
      'codigo' => $sku,
      'nome' => $nome !== '' ? $nome : 'Produto sem descrição',
      'unidade' => (string)self::pick($vsm, ['unidade','unidadeMedida'], 'UN'),
      'preco' => self::money(self::pick($vsm, ['preco_venda','precoVenda','preco','valor','valor_unitario'], 0)),
      'preco_promocional' => self::money(self::pick($vsm, ['preco_promocional','precoPromocional'], 0)),
      'ncm' => self::onlyDigits(self::pick($vsm, ['ncm'])),
      'gtin' => self::onlyDigits(self::pick($vsm, ['gtin','ean','codigo_barras','codigoBarras'])),
      'codigo_barras' => self::onlyDigits(self::pick($vsm, ['gtin','ean','codigo_barras','codigoBarras'])),
      'origem' => (string)self::pick($vsm, ['origem'], ''),
      'situacao' => self::situacaoTiny($vsm),
      'tipo' => (string)self::pick($vsm, ['tipo_produto','tipoProduto'], 'P'),
      'marca' => (string)self::pick($vsm, ['marca'], ''),
      'categoria' => (string)self::pick($vsm, ['categoria'], ''),
      'estoque_minimo' => (float)self::pick($vsm, ['estoque_minimo','estoqueMinimo'], 0),
      'estoque_atual' => (float)(self::estoque($vsm) ?? 0),
      'observacoes' => 'Produto recebido/atualizado pela VSM. Trace ID: '.RequestContext::id(),
    ];
    return ['produtos' => [['produto' => array_filter($produto, fn($v)=>$v!=='' && $v!==null)]]];
  }

  public static function vsmStatusParaTiny(array $vsm): array {
    return ['produtos' => [[ 'produto' => [
      'codigo' => self::sku($vsm),
      'situacao' => self::situacaoTiny($vsm),
      'observacoes' => 'Status atualizado pela VSM. Trace ID: '.RequestContext::id(),
    ]]]];
  }

  public static function extrairProdutoTinyId(array $ret): ?string {
    $candidatos = [
      $ret['retorno']['registros'][0]['registro']['id'] ?? null,
      $ret['retorno']['registro']['id'] ?? null,
      $ret['retorno']['produto']['id'] ?? null,
      $ret['retorno']['id'] ?? null,
      $ret['id'] ?? null,
    ];
    foreach($candidatos as $v) if($v !== null && $v !== '') return (string)$v;
    return null;
  }
}
