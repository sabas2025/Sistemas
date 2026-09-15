<?php
class TinyV2HomologationService {
  public static function resumo(array $cfg): array {
    self::ensureTable();
    $historico = self::historico(8);
    $ultimo = $historico[0] ?? null;
    $checks = self::checksBase($cfg, $ultimo);
    $pesoOk = 0; $total = count($checks);
    foreach ($checks as $c) {
      $st = (string)($c['status'] ?? 'pendente');
      if ($st === 'ok') $pesoOk += 1;
      elseif ($st === 'alerta') $pesoOk += 0.5;
    }
    $progresso = $total > 0 ? (int)round(($pesoOk/$total)*100) : 0;
    $diagnostico = class_exists('TinyHomologationDiagnosisService') ? TinyHomologationDiagnosisService::montar($checks, $ultimo, 'Tiny V2') : [];
    return [
      'checks'=>$checks,
      'progresso'=>$progresso,
      'status_geral'=> $progresso >= 100 ? 'aprovada' : ($progresso >= 70 ? 'homologacao_avancada' : ($progresso >= 40 ? 'homologacao_parcial' : 'pendente')),
      'diagnostico'=>$diagnostico,
      'historico'=>$historico,
      'ultimo'=>$ultimo,
    ];
  }

  private static function checksBase(array $cfg, ?array $ultimo): array {
    $tokenOk = trim((string)($cfg['tiny_v2_token'] ?? '')) !== '';
    $urlOk = trim((string)($cfg['tiny_v2_url'] ?? '')) !== '';
    $last = $ultimo ? json_decode((string)($ultimo['resultado_json'] ?? '{}'), true) : [];
    if (!is_array($last)) $last = [];
    $etapas = $last['etapas'] ?? [];
    return [
      ['key'=>'auth','label'=>'Token/API Key','status'=>($tokenOk && $urlOk) ? 'ok' : 'falha', 'detalhe'=>($tokenOk && $urlOk) ? 'Token e URL configurados.' : 'Configure URL e token Tiny V2.', 'acao_recomendada'=>($tokenOk && $urlOk) ? 'Continuar homologação.' : 'Informe URL e token Tiny V2 em Configurações.'],
      ['key'=>'produto','label'=>'Produto','status'=>self::etapaStatus($etapas,'produto'), 'detalhe'=>'Consulta do SKU de homologação no Tiny V2.', 'acao_recomendada'=>'Use um SKU real existente no Tiny V2 e no VSM.'],
      ['key'=>'estoque','label'=>'Estoque','status'=>self::etapaStatus($etapas,'estoque'), 'detalhe'=>'Comparação do saldo Tiny V2 x VSM.', 'acao_recomendada'=>'Se houver divergência, registrar e reconciliar antes da produção.'],
      ['key'=>'pedido','label'=>'Pedido','status'=>self::etapaStatus($etapas,'pedido'), 'detalhe'=>'Consulta de pedido de teste Tiny V2, quando informado.', 'acao_recomendada'=>'Para homologação completa, informe o ID numérico do pedido Tiny V2.'],
      ['key'=>'retorno_tiny','label'=>'Retorno Tiny','status'=>self::etapaStatus($etapas,'retorno_tiny'), 'detalhe'=>'Confirmação de que o Tiny respondeu corretamente às consultas de produto, estoque e pedido.', 'acao_recomendada'=>'Corrigir a primeira etapa falha e executar Continuar de onde parou.'],
    ];
  }

  private static function etapaStatus(array $etapas, string $key): string {
    if (!isset($etapas[$key]) || !is_array($etapas[$key])) return 'pendente';
    if (class_exists('TinyHomologationDiagnosisService')) return TinyHomologationDiagnosisService::statusEtapa($etapas[$key]);
    if (!empty($etapas[$key]['ok'])) return 'ok';
    return (string)($etapas[$key]['status_homologacao'] ?? 'falha');
  }

  public static function executar(array $input): array {
    self::ensureTable();
    $trace = RequestContext::id();
    $cfg = IntegrationConfig::get();
    $sku = trim((string)($input['sku'] ?? '')) ?: 'HUB-TESTE-001';
    $pedido = trim((string)($input['pedido_teste'] ?? ''));
    $estoqueEsperadoRaw = trim((string)($input['estoque_homologacao'] ?? ''));
    $estoqueEsperado = $estoqueEsperadoRaw !== '' && is_numeric(str_replace(',', '.', $estoqueEsperadoRaw)) ? (float)str_replace(',', '.', $estoqueEsperadoRaw) : null;
    $modo = in_array((string)($input['modo_homologacao'] ?? 'completa'), ['completa','parcial','pendentes'], true) ? (string)$input['modo_homologacao'] : 'completa';
    $executarPedido = $pedido !== '';
    $pedidoObrigatorio = $modo === 'completa';

    $etapas = [];
    $tiny = new TinyV2Service($cfg);
    $vsm = new VsmService($cfg);

    $etapas['auth'] = [
      'ok'=> trim((string)($cfg['tiny_v2_token'] ?? '')) !== '' && trim((string)($cfg['tiny_v2_url'] ?? '')) !== '',
      'mensagem'=> trim((string)($cfg['tiny_v2_token'] ?? '')) !== '' ? 'Token Tiny V2 informado.' : 'Token Tiny V2 ausente.',
      'url'=> (string)($cfg['tiny_v2_url'] ?? ''),
    ];

    $produtoRet = $tiny->consultarProduto($sku);
    $etapas['produto'] = [
      'ok'=> self::retornoOk($produtoRet),
      'mensagem'=> self::resumoRetorno($produtoRet),
      'retorno'=> SensitiveDataService::mask($produtoRet),
    ];

    $estoqueTinyRet = method_exists($tiny, 'consultarEstoquePorSku') ? $tiny->consultarEstoquePorSku($sku) : $produtoRet;
    $tinySaldo = self::extrairSaldoTiny($estoqueTinyRet, $produtoRet);
    $vsmRet = $vsm->consultarEstoque($sku);
    $vsmSaldo = self::extrairSaldoGenerico($vsmRet);
    $dif = ($tinySaldo !== null && $vsmSaldo !== null) ? ($vsmSaldo - $tinySaldo) : null;
    $difEsperadoTiny = ($estoqueEsperado !== null && $tinySaldo !== null) ? ($tinySaldo - $estoqueEsperado) : null;
    $difEsperadoVsm = ($estoqueEsperado !== null && $vsmSaldo !== null) ? ($vsmSaldo - $estoqueEsperado) : null;
    $estoqueEsperadoOk = $estoqueEsperado !== null && $difEsperadoTiny !== null && $difEsperadoVsm !== null && abs((float)$difEsperadoTiny) < 0.00001 && abs((float)$difEsperadoVsm) < 0.00001;
    $etapas['estoque'] = [
      'ok'=> self::retornoOk($estoqueTinyRet) && self::retornoOk($vsmRet) && ($dif !== null && abs((float)$dif) < 0.00001) && $estoqueEsperadoOk,
      'mensagem'=> $estoqueEsperado === null ? 'Informe o campo Estoque de Homologação para finalizar a etapa de estoque.' : ($dif === null ? 'Estoque consultado, mas o saldo Tiny ou VSM não foi identificado. Confira SKU, retorno do endpoint e mapeamento de estoque.' : (($estoqueEsperadoOk && abs((float)$dif) < 0.00001) ? 'Tiny V2, VSM e estoque esperado conferem.' : 'Divergência encontrada entre estoque esperado, Tiny V2 ou VSM.')),
      'tiny_saldo'=>$tinySaldo,
      'vsm_saldo'=>$vsmSaldo,
      'diferenca'=>$dif,
      'estoque_esperado'=>$estoqueEsperado,
      'diferenca_esperado_tiny'=>$difEsperadoTiny,
      'diferenca_esperado_vsm'=>$difEsperadoVsm,
      'retorno_tiny_estoque'=> SensitiveDataService::mask($estoqueTinyRet),
      'retorno_vsm'=> SensitiveDataService::mask($vsmRet),
      'status_homologacao'=> ($estoqueEsperado === null) ? 'falha' : (($dif !== null && abs((float)$dif) >= 0.00001) || !$estoqueEsperadoOk ? 'alerta' : (self::retornoOk($estoqueTinyRet) && self::retornoOk($vsmRet) ? 'ok' : 'falha')),
      'acao_recomendada'=> $estoqueEsperado === null ? 'Preencher o campo Estoque de Homologação com o saldo esperado do SKU para finalizar a homologação.' : ($dif === null ? 'Verificar mapeamento de saldo nos retornos Tiny/VSM.' : (($estoqueEsperadoOk && abs((float)$dif) < 0.00001) ? 'Estoque conferido.' : 'Ajustar o saldo esperado ou reconciliar Tiny x VSM antes de produção.')),
    ];

    if ($executarPedido) {
      $pedRet = ctype_digit($pedido) ? $tiny->consultarPedido($pedido) : ['erro'=>'Tiny V2 pedido.obter.php exige ID numérico do pedido. Informe o ID Tiny, não o código interno.', 'codigo_erro'=>'TINY_V2_PEDIDO_ID_INVALIDO', 'trace_id'=>$trace];
      $etapas['pedido'] = ['ok'=>self::retornoOk($pedRet), 'mensagem'=>self::resumoRetorno($pedRet), 'pedido'=>$pedido, 'retorno'=>SensitiveDataService::mask($pedRet)];
    } else {
      $etapas['pedido'] = ['ok'=>false, 'codigo_erro'=>'PEDIDO_OPCIONAL_NAO_INFORMADO', 'status_homologacao'=>$pedidoObrigatorio ? 'falha' : 'pendente', 'mensagem'=>$pedidoObrigatorio ? 'Pedido de teste não informado. Etapa obrigatória para homologação completa.' : 'Pedido de teste não informado. Etapa pendente, mas permitida na homologação parcial.'];
    }

    $retornoTinyOk = !empty($etapas['produto']['ok']) && self::retornoOk($estoqueTinyRet) && (!$executarPedido || !empty($etapas['pedido']['ok']));
    $etapas['retorno_tiny'] = [
      'ok'=>$retornoTinyOk,
      'mensagem'=>$retornoTinyOk ? 'Tiny V2 respondeu corretamente às consultas do fluxo de homologação.' : 'Retorno Tiny V2 pendente/falho em produto, estoque ou pedido.',
      'acao_recomendada'=>$retornoTinyOk ? 'Manter evidência do Trace ID e conferir divergências de estoque antes de liberar produção.' : 'Corrigir token, SKU, estoque ou pedido de teste antes de aprovar homologação.'
    ];

    $aprovado = true;
    foreach (['auth','produto','estoque','pedido','retorno_tiny'] as $k) if (empty($etapas[$k]['ok'])) $aprovado = false;

    $res = [
      'trace_id'=>$trace,
      'sku'=>$sku,
      'pedido_teste'=>$pedido,
      'estoque_homologacao'=>$estoqueEsperado,
      'modo_homologacao'=>$modo,
      'aprovado'=>$aprovado,
      'status_final'=>$aprovado ? 'Fluxo VSM → HUB → Tiny V2 aprovado' : ($modo === 'parcial' ? 'Homologação parcial Tiny V2 registrada com pendências/alertas' : 'Fluxo VSM → HUB → Tiny V2 reprovado ou pendente'),
      'etapas'=>$etapas,
      'executado_em'=>date('Y-m-d H:i:s'),
    ];
    self::registrar($res);
    Audit::event('tiny.v2.homologacao.executar', $aprovado ? 'sucesso' : 'alerta', [
      'mensagem'=>$res['status_final'],
      'contexto'=>['sku'=>$sku,'pedido_teste'=>$pedido,'estoque_homologacao'=>$estoqueEsperado,'trace_id'=>$trace],
      'retorno'=>SensitiveDataService::mask($res),
      'acao_recomendada'=>$aprovado ? 'Manter evidência e liberar apenas conforme política de produção.' : 'Corrigir etapas com falha antes de liberar produção.'
    ]);
    return $res;
  }

  // V81: validação XML/NF-e removida deste serviço.
  // Responsabilidade fiscal agora fica no módulo XML/NF-e VSM → HUB → Tiny.

  private static function extrairSaldoTiny(array $ret, array $produtoRet=[]): ?float {
    $cands = [$ret, $produtoRet, $ret['consulta_produto'] ?? [], $ret['retorno'] ?? [], $produtoRet['retorno'] ?? []];
    $produtos = $ret['retorno']['produtos'] ?? $produtoRet['retorno']['produtos'] ?? [];
    if (is_array($produtos)) {
      foreach ($produtos as $p) {
        $prod = $p['produto'] ?? $p;
        if (is_array($prod)) $cands[] = $prod;
      }
    }
    foreach ($cands as $arr) {
      if (!is_array($arr)) continue;
      $flat = new RecursiveIteratorIterator(new RecursiveArrayIterator($arr));
      foreach ($flat as $k=>$v) {
        if (in_array((string)$k, ['saldo','estoque','estoqueAtual','estoque_atual','quantidade','quantidade_estoque','saldoFisico','saldo_fisico'], true) && is_numeric(str_replace(',','.',(string)$v))) {
          return (float)str_replace(',','.',(string)$v);
        }
      }
    }
    return null;
  }

  private static function extrairSaldoGenerico(array $ret): ?float {
    $flat = new RecursiveIteratorIterator(new RecursiveArrayIterator($ret));
    foreach ($flat as $k=>$v) {
      if (in_array((string)$k, ['saldo','estoque','quantidade','quantidade_estoque','estoque_atual'], true) && is_numeric(str_replace(',','.',(string)$v))) return (float)str_replace(',','.',(string)$v);
    }
    return null;
  }

  private static function retornoOk(array $ret): bool {
    if (isset($ret['erro']) || isset($ret['codigo_erro'])) return false;
    if (isset($ret['http_code']) && ((int)$ret['http_code'] < 200 || (int)$ret['http_code'] >= 300)) return false;
    if (isset($ret['retorno']['status_processamento']) && (int)$ret['retorno']['status_processamento'] !== 3 && !empty($ret['retorno']['erros'])) return false;
    return true;
  }

  private static function resumoRetorno(array $ret): string {
    if (isset($ret['erro'])) return (string)$ret['erro'];
    if (isset($ret['codigo_erro'])) return (string)$ret['codigo_erro'];
    if (isset($ret['retorno']['status'])) return (string)$ret['retorno']['status'];
    if (isset($ret['http_code'])) return 'HTTP '.$ret['http_code'];
    return 'Retorno recebido.';
  }

  private static function registrar(array $res): void {
    $pdo = Database::connection('core');
    self::ensureTable();
    $st = $pdo->prepare('INSERT INTO tiny_v2_homologacao_testes(trace_id, sku, pedido_teste, aprovado, resultado_json, criado_em) VALUES(?,?,?,?,?,NOW())');
    $st->execute([$res['trace_id'],$res['sku'],$res['pedido_teste'],$res['aprovado']?1:0,json_encode(SensitiveDataService::mask($res), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
  }

  public static function historico(int $limit=10): array {
    self::ensureTable();
    $pdo = Database::connection('core');
    $st = $pdo->prepare('SELECT * FROM tiny_v2_homologacao_testes ORDER BY id DESC LIMIT ?');
    $st->bindValue(1, max(1,min(100,$limit)), PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
  }

  public static function ensureTable(): void {
    SchemaRuntimePolicyService::requireTable('tiny_v2_homologacao_testes', 'homologação Tiny V2');
  }
}
