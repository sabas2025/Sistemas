<?php
class TinyV3HomologationService {
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
    $diagnostico = class_exists('TinyHomologationDiagnosisService') ? TinyHomologationDiagnosisService::montar($checks, $ultimo, 'Tiny V3') : [];
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
    $clientOk = trim((string)($cfg['tiny_v3_client_id'] ?? '')) !== '';
    $secretOk = trim((string)($cfg['tiny_v3_client_secret'] ?? '')) !== '';
    $redirectOk = trim((string)($cfg['tiny_v3_redirect_uri'] ?? '')) !== '';
    $urlOk = trim((string)($cfg['tiny_v3_url'] ?? '')) !== '';
    $tokenInfo = class_exists('TinyV3TokenService') ? TinyV3TokenService::statusResumo() : ['tem_token_salvo'=>false,'tem_token_manual'=>false];
    $tokenOk = !empty($tokenInfo['tem_token_salvo']) || !empty($tokenInfo['tem_token_manual']);
    $last = $ultimo ? json_decode((string)($ultimo['resultado_json'] ?? '{}'), true) : [];
    if (!is_array($last)) $last = [];
    $etapas = $last['etapas'] ?? [];
    return [
      ['key'=>'oauth','label'=>'OAuth','status'=>($urlOk && $clientOk && $secretOk && $redirectOk && $tokenOk) ? 'ok' : 'falha', 'detalhe'=>'Client ID, Client Secret, Redirect URI e token OAuth/manual de homologação.', 'acao_recomendada'=>($urlOk && $clientOk && $secretOk && $redirectOk && $tokenOk) ? 'Continuar homologação.' : 'Reconectar OAuth Tiny V3 ou salvar token manual temporário de homologação.'],
      ['key'=>'produto','label'=>'Produto','status'=>self::etapaStatus($etapas,'produto'), 'detalhe'=>'Consulta do SKU de homologação no Tiny V3.', 'acao_recomendada'=>'Confirme se o SKU existe exatamente no Tiny V3.'],
      ['key'=>'estoque','label'=>'Estoque','status'=>self::etapaStatus($etapas,'estoque'), 'detalhe'=>'Consulta/ comparação do saldo Tiny V3 x VSM.', 'acao_recomendada'=>'Se houver divergência, registrar e reconciliar antes de marcar V3 operacional.'],
      ['key'=>'pedido','label'=>'Pedido','status'=>self::etapaStatus($etapas,'pedido'), 'detalhe'=>'Consulta de pedido de teste Tiny V3, quando informado.', 'acao_recomendada'=>'Para homologação completa, informe um pedido do ambiente Tiny V3 selecionado.'],
      ['key'=>'retorno_tiny','label'=>'Retorno Tiny','status'=>self::etapaStatus($etapas,'retorno_tiny'), 'detalhe'=>'Confirmação de que o Tiny respondeu corretamente às consultas de produto, estoque e pedido.', 'acao_recomendada'=>'Corrigir OAuth/SKU/estoque/pedido e executar Continuar de onde parou.'],
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
    $tiny = new TinyV3Service($cfg);
    $vsm = new VsmService($cfg);
    $tokenInfo = class_exists('TinyV3TokenService') ? TinyV3TokenService::statusResumo() : [];
    $accessTokenDisponivel = '';
    if (class_exists('TinyV3TokenService')) {
      try { $accessTokenDisponivel = TinyV3TokenService::accessToken(); } catch (Throwable $e) { $accessTokenDisponivel = ''; }
    }

    $oauthConfigOk = trim((string)($cfg['tiny_v3_url'] ?? '')) !== ''
      && trim((string)($cfg['tiny_v3_client_id'] ?? '')) !== ''
      && trim((string)($cfg['tiny_v3_client_secret'] ?? '')) !== ''
      && trim((string)($cfg['tiny_v3_redirect_uri'] ?? '')) !== '';
    $oauthOk = $oauthConfigOk && $accessTokenDisponivel !== '';

    $etapas['oauth'] = [
      'ok'=> $oauthOk,
      'mensagem'=> $oauthOk
        ? 'OAuth Tiny V3 configurado e access token utilizável.'
        : 'OAuth Tiny V3 incompleto ou token salvo não utilizável. Confira ambiente V3, token OAuth, refresh token, chave de criptografia e validade do token.',
      'ambiente'=> (string)($cfg['tiny_v3_ambiente'] ?? 'homologacao'),
      'base_api'=> (string)($cfg['tiny_v3_url'] ?? ''),
      'tem_client_id'=> trim((string)($cfg['tiny_v3_client_id'] ?? '')) !== '',
      'tem_client_secret'=> trim((string)($cfg['tiny_v3_client_secret'] ?? '')) !== '',
      'tem_redirect_uri'=> trim((string)($cfg['tiny_v3_redirect_uri'] ?? '')) !== '',
      'tem_token_salvo'=> !empty($tokenInfo['tem_token_salvo']),
      'tem_token_manual'=> !empty($tokenInfo['tem_token_manual']),
      'access_token_utilizavel'=> $accessTokenDisponivel !== '',
      'acao_recomendada'=> $accessTokenDisponivel !== '' ? 'Continuar testes.' : 'Reconectar OAuth Tiny V3 no mesmo ambiente selecionado ou salvar novo token manual apenas para homologação.'
    ];

    if (!$oauthOk) {
      $produtoRet = ['erro'=>'Etapa não executada porque o access token Tiny V3 não está utilizável.','codigo_erro'=>'TINY_V3_TOKEN_NOT_USABLE','trace_id'=>$trace];
    } else {
      $produtoRet = $tiny->consultarProduto($sku);
    }
    $etapas['produto'] = [
      'ok'=> self::retornoOk($produtoRet) && !empty($produtoRet['sku_exato']),
      'mensagem'=> !empty($produtoRet['sku_exato']) ? 'Produto localizado por SKU exato no Tiny V3.' : self::resumoRetorno($produtoRet),
      'retorno'=> SensitiveDataService::mask($produtoRet),
      'acao_recomendada'=> !empty($produtoRet['sku_exato']) ? 'Continuar para estoque.' : 'Corrigir token V3 e confirmar se o SKU existe exatamente no Tiny V3 antes do teste de estoque.'
    ];

    $estoqueTinyRet = $oauthOk ? (method_exists($tiny, 'consultarEstoquePorSku') ? $tiny->consultarEstoquePorSku($sku) : $produtoRet) : ['erro'=>'Etapa Tiny V3 não executada porque o token não está utilizável.','codigo_erro'=>'TINY_V3_TOKEN_NOT_USABLE','trace_id'=>$trace];
    $tinySaldo = self::extrairSaldoTiny($estoqueTinyRet, $produtoRet);
    $vsmRet = $vsm->consultarEstoque($sku);
    $vsmSaldo = self::extrairSaldoGenerico($vsmRet);
    $dif = ($tinySaldo !== null && $vsmSaldo !== null) ? ($vsmSaldo - $tinySaldo) : null;
    $difEsperadoTiny = ($estoqueEsperado !== null && $tinySaldo !== null) ? ($tinySaldo - $estoqueEsperado) : null;
    $difEsperadoVsm = ($estoqueEsperado !== null && $vsmSaldo !== null) ? ($vsmSaldo - $estoqueEsperado) : null;
    $estoqueEsperadoOk = $estoqueEsperado !== null && $difEsperadoTiny !== null && $difEsperadoVsm !== null && abs((float)$difEsperadoTiny) < 0.00001 && abs((float)$difEsperadoVsm) < 0.00001;
    $etapas['estoque'] = [
      'ok'=> self::retornoOk($estoqueTinyRet) && self::retornoOk($vsmRet) && ($dif !== null && abs((float)$dif) < 0.00001) && $estoqueEsperadoOk,
      'mensagem'=> $estoqueEsperado === null ? 'Informe o campo Estoque de Homologação para finalizar a etapa de estoque.' : ($dif === null ? 'Consulta realizada, mas saldo não foi identificado em um dos retornos.' : (($estoqueEsperadoOk && abs((float)$dif) < 0.00001) ? 'Tiny V3, VSM e estoque esperado conferem.' : 'Divergência encontrada entre estoque esperado, Tiny V3 ou VSM.')),
      'tiny_saldo'=>$tinySaldo,
      'vsm_saldo'=>$vsmSaldo,
      'diferenca'=>$dif,
      'estoque_esperado'=>$estoqueEsperado,
      'diferenca_esperado_tiny'=>$difEsperadoTiny,
      'diferenca_esperado_vsm'=>$difEsperadoVsm,
      'retorno_tiny_estoque'=> SensitiveDataService::mask($estoqueTinyRet),
      'retorno_vsm'=> SensitiveDataService::mask($vsmRet),
      'acao_recomendada'=> $estoqueEsperado === null ? 'Preencher o campo Estoque de Homologação com o saldo esperado do SKU para finalizar a homologação.' : (($estoqueEsperadoOk && $dif !== null && abs((float)$dif) < 0.00001) ? 'Estoque conferido.' : self::acaoEstoque($estoqueTinyRet, $vsmRet, $dif)),
      'status_homologacao'=> ($estoqueEsperado === null) ? 'falha' : (($dif !== null && abs((float)$dif) >= 0.00001) || !$estoqueEsperadoOk ? 'alerta' : (self::retornoOk($estoqueTinyRet) && self::retornoOk($vsmRet) ? 'ok' : 'falha')),
    ];

    if ($executarPedido) {
      $pedRet = $oauthOk ? $tiny->consultarPedido($pedido) : ['erro'=>'Etapa não executada porque o access token Tiny V3 não está utilizável.','codigo_erro'=>'TINY_V3_TOKEN_NOT_USABLE','trace_id'=>$trace];
      $etapas['pedido'] = ['ok'=>self::retornoOk($pedRet), 'mensagem'=>self::resumoRetorno($pedRet), 'pedido'=>$pedido, 'retorno'=>SensitiveDataService::mask($pedRet), 'acao_recomendada'=>self::retornoOk($pedRet) ? 'Pedido consultado com sucesso no Tiny V3.' : 'Corrigir token V3 ou confirmar se o pedido existe no ambiente Tiny V3 selecionado.'];
    } else {
      $etapas['pedido'] = ['ok'=>false, 'codigo_erro'=>'PEDIDO_OPCIONAL_NAO_INFORMADO', 'status_homologacao'=>$pedidoObrigatorio ? 'falha' : 'pendente', 'mensagem'=>$pedidoObrigatorio ? 'Pedido de teste não informado. Etapa obrigatória para homologação completa.' : 'Pedido de teste não informado. Etapa pendente, mas permitida na homologação parcial.'];
    }

    $retornoTinyOk = $oauthOk && !empty($etapas['produto']['ok']) && self::retornoOk($estoqueTinyRet) && (!$executarPedido || !empty($etapas['pedido']['ok']));
    $etapas['retorno_tiny'] = [
      'ok'=>$retornoTinyOk,
      'mensagem'=>$retornoTinyOk ? 'Tiny V3 respondeu corretamente às consultas do fluxo de homologação.' : 'Retorno Tiny V3 pendente/falho em OAuth, produto, estoque ou pedido.',
      'acao_recomendada'=>$retornoTinyOk ? 'Manter evidência do Trace ID e conferir divergências de estoque antes de marcar V3 operacional.' : 'Corrigir OAuth, SKU, estoque ou pedido de teste antes de aprovar homologação.'
    ];

    $aprovado = true;
    foreach (['oauth','produto','estoque','pedido','retorno_tiny'] as $k) if (empty($etapas[$k]['ok'])) $aprovado = false;

    $res = [
      'trace_id'=>$trace,
      'sku'=>$sku,
      'pedido_teste'=>$pedido,
      'estoque_homologacao'=>$estoqueEsperado,
      'modo_homologacao'=>$modo,
      'aprovado'=>$aprovado,
      'status_final'=>$aprovado ? 'Fluxo VSM → HUB → Tiny V3 aprovado' : ($modo === 'parcial' ? 'Homologação parcial Tiny V3 registrada com pendências/alertas' : 'Fluxo VSM → HUB → Tiny V3 reprovado ou pendente'),
      'etapas'=>$etapas,
      'executado_em'=>date('Y-m-d H:i:s'),
    ];
    self::registrar($res);
    Audit::event('tiny.v3.homologacao.executar', $aprovado ? 'sucesso' : 'alerta', [
      'mensagem'=>$res['status_final'],
      'contexto'=>['sku'=>$sku,'pedido_teste'=>$pedido,'estoque_homologacao'=>$estoqueEsperado,'trace_id'=>$trace],
      'retorno'=>SensitiveDataService::mask($res),
      'acao_recomendada'=>$aprovado ? 'Manter evidência e liberar V3 operacional apenas conforme política de produção.' : 'Corrigir etapas com falha antes de marcar Tiny V3 como operacional.'
    ]);
    return $res;
  }

  // V81: validação XML/NF-e removida deste serviço.
  // Responsabilidade fiscal agora fica no módulo XML/NF-e VSM → HUB → Tiny.

  private static function extrairSaldoTiny(array $estoqueRet, array $produtoRet=[]): ?float {
    $cands = [$estoqueRet, $produtoRet, $estoqueRet['data'] ?? [], $produtoRet['produto_encontrado'] ?? []];
    foreach ($cands as $arr) {
      if (!is_array($arr)) continue;
      $flat = new RecursiveIteratorIterator(new RecursiveArrayIterator($arr));
      foreach ($flat as $k=>$v) {
        if (in_array((string)$k, ['saldo','estoque','estoqueAtual','estoque_atual','quantidade','quantidade_estoque','saldoFisico','saldo_fisico'], true) && is_numeric(str_replace(',','.',(string)$v))) return (float)str_replace(',','.',(string)$v);
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

  private static function acaoEstoque(array $tinyRet, array $vsmRet, $dif): string {
    if (isset($tinyRet['codigo_erro']) && in_array($tinyRet['codigo_erro'], ['TINY_V3_TOKEN_NOT_USABLE','TINY_V3_TOKEN_MISSING'], true)) return 'Corrigir OAuth/access token Tiny V3 antes de consultar estoque.';
    if (isset($tinyRet['codigo_erro']) && $tinyRet['codigo_erro']==='TINY_V3_SKU_NOT_FOUND') return 'Cadastrar/vincular o SKU no Tiny V3 ou usar o botão Buscar SKU.';
    if (isset($vsmRet['http_code']) && (int)$vsmRet['http_code']===403) return 'Corrigir credenciais/permissão VSM. HTTP 403 indica acesso negado no endpoint de estoque.';
    if ($dif !== null && abs((float)$dif) >= 0.00001) return 'Reconciliar estoque Tiny V3 x VSM antes de aprovar homologação.';
    return 'Conferir se os retornos Tiny/VSM trazem campo de saldo numérico.';
  }

  private static function retornoOk(array $ret): bool {
    if (isset($ret['erro']) || isset($ret['codigo_erro'])) return false;
    if (isset($ret['ok'])) return (bool)$ret['ok'];
    if (isset($ret['http_code']) && ((int)$ret['http_code'] < 200 || (int)$ret['http_code'] >= 300)) return false;
    return true;
  }

  private static function resumoRetorno(array $ret): string {
    if (isset($ret['erro'])) return (string)$ret['erro'];
    if (isset($ret['codigo_erro'])) return (string)$ret['codigo_erro'];
    if (isset($ret['http_code'])) return 'HTTP '.$ret['http_code'];
    if (!empty($ret['sku_exato'])) return 'SKU exato localizado.';
    return 'Retorno recebido.';
  }

  private static function registrar(array $res): void {
    $pdo = Database::connection('core');
    self::ensureTable();
    $st = $pdo->prepare('INSERT INTO tiny_v3_homologacao_testes(trace_id, sku, pedido_teste, aprovado, resultado_json, criado_em) VALUES(?,?,?,?,?,NOW())');
    $st->execute([$res['trace_id'],$res['sku'],$res['pedido_teste'],$res['aprovado']?1:0,json_encode(SensitiveDataService::mask($res), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
  }

  public static function historico(int $limit=10): array {
    self::ensureTable();
    $pdo = Database::connection('core');
    $st = $pdo->prepare('SELECT * FROM tiny_v3_homologacao_testes ORDER BY id DESC LIMIT ?');
    $st->bindValue(1, max(1,min(100,$limit)), PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
  }

  public static function ensureTable(): void {
    SchemaRuntimePolicyService::requireTable('tiny_v3_homologacao_testes', 'homologação Tiny V3');
  }
}
