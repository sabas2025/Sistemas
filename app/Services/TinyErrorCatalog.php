<?php
class TinyErrorCatalog {
  public static function detectar(array $retorno): array {
    $texto = mb_strtolower(json_encode($retorno, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    $map = [
      'TINY_TOKEN_INVALID' => ['token', 'chave', 'autentica', 'não autorizado', 'nao autorizado'],
      'TINY_RATE_LIMIT' => ['limite', 'rate limit', 'muitas requisições', 'too many requests'],
      'TINY_PRODUTO_NAO_ENCONTRADO' => ['produto não encontrado', 'produto nao encontrado', 'sku não encontrado', 'codigo não encontrado'],
      'TINY_PEDIDO_DUPLICADO' => ['duplicado', 'já existe', 'ja existe', 'pedido existente'],
      'TINY_CLIENTE_INVALIDO' => ['cpf', 'cnpj', 'cliente', 'documento inválido', 'documento invalido'],
      'TINY_ENDERECO_INCOMPLETO' => ['endereço', 'endereco', 'cep', 'bairro', 'cidade', 'uf'],
      'TINY_ERRO_FISCAL' => ['ncm', 'cfop', 'cst', 'csosn', 'fiscal', 'tribut'],
      'TINY_TIMEOUT' => ['timeout', 'tempo esgotado', 'timed out'],
      'TINY_JSON_INVALIDO' => ['json', 'formato inválido', 'formato invalido'],
    ];
    foreach ($map as $code => $terms) {
      foreach ($terms as $term) {
        if (str_contains($texto, $term)) return self::info($code);
      }
    }
    return self::info('TINY_ORDER_CREATE_ERROR');
  }

  public static function info(string $code): array {
    $base = [
      'TINY_TOKEN_INVALID' => ['Token Tiny inválido ou sem permissão.', 'Gerar novo token no Tiny/Olist e atualizar em Configurações.'],
      'TINY_RATE_LIMIT' => ['Limite da API Tiny atingido.', 'Aguardar e reprocessar. Reduzir frequência do worker.'],
      'TINY_PRODUTO_NAO_ENCONTRADO' => ['Produto/SKU não localizado no Tiny.', 'Conferir mapeamento SKU VSM ⇄ SKU Tiny.'],
      'TINY_PEDIDO_DUPLICADO' => ['Pedido possivelmente já enviado ao Tiny.', 'Consultar pedido no Tiny antes de reprocessar.'],
      'TINY_CLIENTE_INVALIDO' => ['Dados do cliente inválidos.', 'Conferir CPF/CNPJ, nome, telefone e e-mail.'],
      'TINY_ENDERECO_INCOMPLETO' => ['Endereço do cliente incompleto.', 'Conferir CEP, rua, número, bairro, cidade e UF.'],
      'TINY_ERRO_FISCAL' => ['Dados fiscais ausentes/inválidos.', 'Conferir NCM, CFOP, CST/CSOSN, unidade, origem e observação fiscal.'],
      'TINY_TIMEOUT' => ['Tempo de resposta da API Tiny esgotado.', 'Verificar internet/Tiny e reprocessar a fila.'],
      'TINY_JSON_INVALIDO' => ['Retorno ou payload em formato inválido.', 'Verificar mapper e retorno bruto da API.'],
      'TINY_ORDER_CREATE_ERROR' => ['Erro genérico ao criar pedido no Tiny.', 'Abrir detalhe do pedido e auditoria pelo Trace ID.'],
    ];
    [$causa,$acao] = $base[$code] ?? $base['TINY_ORDER_CREATE_ERROR'];
    return ['codigo'=>$code,'causa_provavel'=>$causa,'acao_recomendada'=>$acao];
  }
}
