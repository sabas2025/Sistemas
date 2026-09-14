<?php
class TesteRealTinyService {
  public const SKU_PREFIXO_SEGURO = 'HUB-TESTE-';
  public const TIPO_COMPLETO = 'completo';
  public const TIPO_CRIAR = 'criar_produto';
  public const TIPO_ESTOQUE = 'atualizar_estoque';
  public const TIPO_INATIVAR = 'inativar_produto';
  public const TIPO_ATIVAR = 'ativar_produto';

  public static function skuPadrao(): string {
    return self::SKU_PREFIXO_SEGURO.date('Ymd-His');
  }

  public static function cliente(string $modo='auto'): TinyClientInterface {
    $cfg = IntegrationConfig::get();
    if ($modo === 'v2') return new TinyV2Service($cfg);
    if ($modo === 'v3') return new TinyV3Service($cfg);
    return TinyFactory::make();
  }

  public static function versaoEfetiva(string $modo='auto'): string {
    if ($modo === 'v2') return 'v2_forcado';
    if ($modo === 'v3') return 'v3_forcado_homologacao';
    $cfg = IntegrationConfig::get();
    $v = (string)($cfg['tiny_versao'] ?? 'v2');
    if ($v === 'v3' && empty($cfg['tiny_v3_operacional'])) return 'auto_fallback_v2_v3_nao_operacional';
    return 'auto_'.$v;
  }


  public static function skuSeguro(string $sku): bool {
    return str_starts_with(strtoupper(trim($sku)), self::SKU_PREFIXO_SEGURO);
  }

  public static function validarEntradaSegura(array $input): void {
    $sku = trim((string)($input['sku'] ?? ''));
    if ($sku === '') return;
    if (!self::skuSeguro($sku)) {
      throw new InvalidArgumentException('Bloqueio de segurança: teste real Tiny só permite SKU iniciado por '.self::SKU_PREFIXO_SEGURO.' para evitar alteração de produto comercial.');
    }
    $confirmacao = strtoupper(trim((string)($input['confirmacao_sku_teste'] ?? '')));
    if ($confirmacao !== 'SIM') {
      throw new InvalidArgumentException('Confirmação obrigatória: digite SIM no campo de segurança antes de executar chamada real no Tiny.');
    }
  }

  public static function payloadProduto(array $input): array {
    $sku = trim((string)($input['sku'] ?? '')) ?: self::skuPadrao();
    $nome = trim((string)($input['nome'] ?? '')) ?: 'Produto Teste Real Hub de Integração';
    $preco = (float)str_replace(',', '.', (string)($input['preco'] ?? '9.99'));
    $estoque = (float)str_replace(',', '.', (string)($input['estoque_inicial'] ?? '1'));
    $ean = preg_replace('/\D+/', '', (string)($input['ean'] ?? ''));
    $ncm = preg_replace('/\D+/', '', (string)($input['ncm'] ?? ''));
    $base = [
      'acao' => 'produto_novo',
      'sku' => $sku,
      'nome' => $nome,
      'descricao' => $nome,
      'unidade' => trim((string)($input['unidade'] ?? 'UN')) ?: 'UN',
      'preco_venda' => $preco,
      'preco' => $preco,
      'estoque' => $estoque,
      'status' => 'ativo',
      'ativo' => 1,
      'marca' => trim((string)($input['marca'] ?? 'HUB TESTE')),
      'categoria' => trim((string)($input['categoria'] ?? 'Teste Integração')),
      'ncm' => $ncm,
      'gtin' => $ean,
      'observacoes' => 'Produto criado pelo Teste Real do Hub. Trace ID: '.RequestContext::id(),
      'data_referencia' => date('c'),
    ];
    return $base;
  }

  public static function executar(array $input): array {
    $pdo = Database::connection('core');
    $trace = RequestContext::id();
    $acao = (string)($input['acao_teste'] ?? self::TIPO_COMPLETO);
    $modo = (string)($input['modo_tiny'] ?? 'auto');
    $sku = trim((string)($input['sku'] ?? '')) ?: self::skuPadrao();
    $input['sku'] = $sku;
    self::validarEntradaSegura($input);
    $estoqueNovo = (float)str_replace(',', '.', (string)($input['estoque_novo'] ?? '5'));
    $cliente = self::cliente($modo);
    $versao = self::versaoEfetiva($modo);
    $produtoVsm = self::payloadProduto($input);
    $produtoTiny = ProdutoMapper::vsmParaTiny($produtoVsm);
    $resultados = [];
    $okGeral = true;

    $registrar = function(string $etapa, array $payload, array $retorno) use (&$resultados, &$okGeral) {
      $ok = self::retornoOk($retorno);
      if (!$ok) $okGeral = false;
      $resultados[] = [
        'etapa' => $etapa,
        'ok' => $ok,
        'payload' => $payload,
        'retorno' => $retorno,
        'codigo_erro' => $retorno['codigo_erro'] ?? ($ok ? null : 'TINY_TEST_FAILED'),
        'mensagem' => self::resumoRetorno($retorno),
      ];
      PayloadSnapshotService::registrar(0, 'teste_real_'.$etapa, ['payload'=>$payload,'retorno'=>$retorno], ['trace_id'=>RequestContext::id(),'origem'=>'hub','destino'=>'tiny']);
    };

    Audit::event('teste_real_tiny.inicio','info',[
      'mensagem'=>'Iniciando teste real de produto/estoque/status no Tiny.',
      'payload'=>SensitiveDataService::mask($produtoVsm),
      'contexto'=>['acao'=>$acao,'modo'=>$modo,'versao_efetiva'=>$versao,'sku'=>$sku],
      'acao_recomendada'=>'Use obrigatoriamente SKU iniciado por '.self::SKU_PREFIXO_SEGURO.' e confirme o teste antes de executar.'
    ]);

    if (in_array($acao, [self::TIPO_COMPLETO, self::TIPO_CRIAR], true)) {
      $ret = $cliente->criarProduto($produtoTiny);
      $registrar('criar_produto', $produtoTiny, $ret);
    }

    if (in_array($acao, [self::TIPO_COMPLETO, self::TIPO_ESTOQUE], true)) {
      $payloadEstoque = ['sku'=>$sku, 'estoque'=>$estoqueNovo, 'quantidade'=>$estoqueNovo];
      $ret = $cliente->atualizarEstoque($sku, $estoqueNovo);
      $registrar('atualizar_estoque', $payloadEstoque, $ret);
    }

    if (in_array($acao, [self::TIPO_COMPLETO, self::TIPO_INATIVAR], true)) {
      $payloadStatus = ['sku'=>$sku, 'situacao'=>'I', 'status'=>'inativo'];
      $ret = $cliente->atualizarStatusProduto($sku, 'I');
      $registrar('inativar_produto', $payloadStatus, $ret);
    }

    if ($acao === self::TIPO_ATIVAR) {
      $payloadStatus = ['sku'=>$sku, 'situacao'=>'A', 'status'=>'ativo'];
      $ret = $cliente->atualizarStatusProduto($sku, 'A');
      $registrar('ativar_produto', $payloadStatus, $ret);
    }

    $resumo = [
      'success' => $okGeral,
      'trace_id' => $trace,
      'sku' => $sku,
      'acao' => $acao,
      'modo_tiny' => $modo,
      'versao_efetiva' => $versao,
      'fallback_detectado' => str_contains($versao, 'fallback'),
      'sku_prefixo_obrigatorio' => self::SKU_PREFIXO_SEGURO,
      'resultados' => $resultados,
      'executado_em' => date('Y-m-d H:i:s'),
    ];

    self::registrarHistorico($resumo, $produtoVsm, $produtoTiny);
    Audit::event('teste_real_tiny.fim', $okGeral ? 'sucesso' : 'erro', [
      'mensagem' => $okGeral ? 'Teste real Tiny concluído com sucesso.' : 'Teste real Tiny concluído com falhas. Verifique retornos e logs Tiny.',
      'payload' => SensitiveDataService::mask($resumo),
      'codigo_erro' => $okGeral ? null : 'TINY_REAL_TEST_FAILED',
      'acao_recomendada' => $okGeral ? 'Valide o produto no painel do Tiny e mantenha o SKU de teste para auditoria.' : 'Abra Logs Tiny V2/V3, Auditoria e confira credenciais, endpoint, payload e permissões.'
    ]);
    return $resumo;
  }

  private static function registrarHistorico(array $resumo, array $produtoVsm, array $produtoTiny): void {
    try {
      $pdo = Database::connection('core');
      self::ensureTable($pdo);
      $st = $pdo->prepare('INSERT INTO tiny_testes_reais(trace_id, sku, acao, modo_tiny, versao_efetiva, sucesso, payload_vsm, payload_tiny, retorno_json, criado_em) VALUES(?,?,?,?,?,?,?,?,?,NOW())');
      $st->execute([
        $resumo['trace_id'], $resumo['sku'], $resumo['acao'], $resumo['modo_tiny'], $resumo['versao_efetiva'], $resumo['success'] ? 1 : 0,
        json_encode(SensitiveDataService::mask($produtoVsm), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        json_encode(SensitiveDataService::mask($produtoTiny), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        json_encode(SensitiveDataService::mask($resumo), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
      ]);
    } catch (Throwable $e) {
      Audit::exception($e, 'teste_real_tiny.historico.erro');
    }
  }

  public static function ultimos(int $limit=20): array {
    $pdo = Database::connection('core');
    self::ensureTable($pdo);
    $st = $pdo->prepare('SELECT * FROM tiny_testes_reais ORDER BY id DESC LIMIT ?');
    $st->bindValue(1, max(1, min(100, $limit)), PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
  }

  public static function ensureTable(PDO $pdo): void {
    SchemaRuntimePolicyService::requireTable('tiny_testes_reais', 'testes reais Tiny');
  }

  public static function retornoOk(array $ret): bool {
    if (isset($ret['erro']) || isset($ret['codigo_erro'])) return false;
    if (array_key_exists('ok', $ret) && $ret['ok'] === false) return false;
    if (isset($ret['http_code']) && ((int)$ret['http_code'] < 200 || (int)$ret['http_code'] >= 300)) return false;
    if (isset($ret['retorno']['status_processamento']) && (int)$ret['retorno']['status_processamento'] !== 3 && !empty($ret['retorno']['erros'])) return false;
    return true;
  }

  public static function resumoRetorno(array $ret): string {
    if (isset($ret['erro'])) return (string)$ret['erro'];
    if (isset($ret['codigo_erro'])) return (string)$ret['codigo_erro'];
    if (isset($ret['retorno']['status'])) return (string)$ret['retorno']['status'];
    if (isset($ret['http_code'])) return 'HTTP '.$ret['http_code'];
    return 'Retorno recebido. Consulte JSON completo.';
  }
}
