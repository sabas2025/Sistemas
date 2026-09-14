<?php
class PedidoValidator {
  private static function digits($v): string { return preg_replace('/\D+/', '', (string)$v); }
  private static function cpfValido(string $cpf): bool {
    $cpf=self::digits($cpf); if(strlen($cpf)!==11 || preg_match('/^(\d)\1{10}$/',$cpf)) return false;
    for($t=9;$t<11;$t++){ $d=0; for($c=0;$c<$t;$c++) $d += (int)$cpf[$c]*(($t+1)-$c); $d=((10*$d)%11)%10; if((int)$cpf[$t]!==$d) return false; }
    return true;
  }
  private static function cnpjValido(string $cnpj): bool {
    $cnpj=self::digits($cnpj); if(strlen($cnpj)!==14 || preg_match('/^(\d)\1{13}$/',$cnpj)) return false;
    $calc=function($base,$pesos){ $s=0; foreach($pesos as $i=>$p) $s+=(int)$base[$i]*$p; $r=$s%11; return $r<2?0:11-$r; };
    return (int)$cnpj[12] === $calc($cnpj,[5,4,3,2,9,8,7,6,5,4,3,2]) && (int)$cnpj[13] === $calc($cnpj,[6,5,4,3,2,9,8,7,6,5,4,3,2]);
  }
  private static function documentoValido(string $doc): bool { $d=self::digits($doc); return strlen($d)===11 ? self::cpfValido($d) : (strlen($d)===14 ? self::cnpjValido($d) : false); }
  private static function emailValido(?string $email): bool { return !$email || filter_var($email, FILTER_VALIDATE_EMAIL) !== false; }

  public static function validarVsm(array $vsm): array {
    $erros = [];
    $pedidoId = $vsm['id'] ?? $vsm['pedido'] ?? $vsm['numero'] ?? $vsm['numeroPedido'] ?? null;
    if (!$pedidoId) $erros[] = 'Pedido sem identificador de origem. Informe id, pedido, numero ou numeroPedido.';

    $cliente = $vsm['cliente'] ?? $vsm['comprador'] ?? [];
    if (!is_array($cliente)) $cliente = [];
    $nome = trim((string)($cliente['nome'] ?? $cliente['razao_social'] ?? ''));
    $doc = self::digits($cliente['documento'] ?? $cliente['cpf_cnpj'] ?? $cliente['cpf'] ?? $cliente['cnpj'] ?? '');
    $email = trim((string)($cliente['email'] ?? ''));
    if ($nome === '') $erros[] = 'Cliente sem nome/razão social.';
    if ($doc !== '' && !self::documentoValido($doc)) $erros[] = 'CPF/CNPJ do cliente inválido.';
    if (!self::emailValido($email)) $erros[] = 'E-mail do cliente inválido.';

    $end = $cliente['endereco'] ?? $vsm['endereco'] ?? $vsm['entrega'] ?? [];
    if (is_array($end) && $end) {
      $cep = self::digits($end['cep'] ?? '');
      $uf = strtoupper((string)($end['uf'] ?? $end['estado'] ?? ''));
      if ($cep !== '' && strlen($cep) !== 8) $erros[] = 'CEP inválido. Informe 8 dígitos.';
      if ($uf !== '' && !preg_match('/^[A-Z]{2}$/', $uf)) $erros[] = 'UF inválida. Informe sigla com 2 letras.';
      foreach(['cidade'=>'cidade','bairro'=>'bairro'] as $k=>$label) if (empty($end[$k])) $erros[] = 'Endereço sem '.$label.'.';
    }

    $itens = $vsm['itens'] ?? $vsm['produtos'] ?? [];
    if (!is_array($itens) || count($itens) === 0) $erros[] = 'Pedido sem itens.';
    $totalItens = 0.0;
    foreach ($itens as $i => $item) {
      if (!is_array($item)) { $erros[] = 'Item #'.($i+1).' inválido.'; continue; }
      $sku = trim((string)($item['sku'] ?? $item['codigo'] ?? $item['codigoProduto'] ?? ''));
      $qtd = (float)str_replace(',','.',(string)($item['quantidade'] ?? $item['qtd'] ?? 0));
      $valor = (float)str_replace(',','.',(string)($item['valor_unitario'] ?? $item['preco'] ?? $item['valor'] ?? 0));
      $totalItens += $qtd * $valor;
      if ($sku === '') $erros[] = 'Item #'.($i+1).' sem SKU/código.';
      if ($qtd <= 0) $erros[] = 'Item #'.($i+1).' com quantidade inválida.';
      if ($valor <= 0) $erros[] = 'Item #'.($i+1).' com valor unitário inválido ou zero.';
      $ean = self::digits($item['gtin'] ?? $item['ean'] ?? $item['codigo_barras'] ?? '');
      if ($ean !== '' && !in_array(strlen($ean), [8,12,13,14], true)) $erros[] = 'Item #'.($i+1).' com GTIN/EAN inválido. Use 8, 12, 13 ou 14 dígitos.';
      $ncm = self::digits($item['ncm'] ?? '');
      if ($ncm !== '' && strlen($ncm) !== 8) $erros[] = 'Item #'.($i+1).' com NCM inválido. Informe 8 dígitos.';
      $cfop = self::digits($item['cfop'] ?? '');
      if ($cfop !== '' && strlen($cfop) !== 4) $erros[] = 'Item #'.($i+1).' com CFOP inválido. Informe 4 dígitos.';
      $un = trim((string)($item['unidade'] ?? ''));
      if ($un !== '' && strlen($un) > 6) $erros[] = 'Item #'.($i+1).' com unidade comercial muito longa.';
    }
    $formaPagamento = trim((string)($vsm['forma_pagamento'] ?? ($vsm['pagamento']['forma'] ?? '')));
    if ($formaPagamento === '') $erros[] = 'Forma de pagamento não informada.';
    $desconto = (float)str_replace(',','.',(string)($vsm['desconto'] ?? $vsm['valor_desconto'] ?? 0));
    if ($desconto < 0) $erros[] = 'Desconto negativo não permitido.';
    if ($desconto > $totalItens && $totalItens > 0) $erros[] = 'Desconto maior que o total dos itens.';
    return $erros;
  }
}
