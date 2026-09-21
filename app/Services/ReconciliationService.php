<?php
class ReconciliationService {
  public static function registrarManual(string $sku, float $tiny, float $vsm, string $origem='manual', array $detalhesExtra=[]): array {
    $diff = $tiny - $vsm;
    $status = abs($diff) < 0.0001 ? 'ok' : 'divergente';
    $pdo = Database::forTable('estoque_movimentos');
    TenantScopeService::run('estoque_reconciliacao', 'INSERT INTO estoque_reconciliacao(sku,estoque_tiny,estoque_vsm,diferenca,status,origem,trace_id,detalhes) VALUES(?,?,?,?,?,?,?,?)', [$sku,$tiny,$vsm,$diff,$status,$origem,RequestContext::id(),json_encode(array_merge(['acao'=>'comparacao_manual'], $detalhesExtra),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
    if($status==='divergente') NotificationService::criar('estoque','Divergência de estoque','SKU '.$sku.' está diferente entre Tiny e VSM.','alerta',['trace_id'=>RequestContext::id(),'link'=>'index.php?page=reconciliacao']);
    return ['sku'=>$sku,'tiny'=>$tiny,'vsm'=>$vsm,'diferenca'=>$diff,'status'=>$status];
  }
  public static function reconciliarSku(string $sku): array {
    // Achado L-01 (2026-09-21): a consulta real depende de Tiny e VSM. VsmService::consultarEstoque()
    // LANÇA quando o host está indisponível (o lado Tiny devolve array de erro), e o controller
    // chamava sem try/catch — o operador via a tela de Recuperação genérica, sem registro nem
    // mensagem. Um provedor externo fora não pode virar erro fatal: capturamos, auditamos e
    // sinalizamos 'indisponivel'. NÃO registramos comparação — números inválidos gerariam um falso
    // 'divergente'. O caminho de sucesso é idêntico ao anterior.
    try {
      $tiny = TinyFactory::make();
      $vsm = new VsmService();
      $tinyRet = $tiny->consultarProduto($sku);
      $info = class_exists('ProdutoTinyPreflightService') ? ProdutoTinyPreflightService::analisarRetornoPesquisa($tinyRet, $sku) : ['estoque'=>null];
      $estoqueTiny = isset($info['estoque']) ? (float)$info['estoque'] : 0.0;
      $vsmRet = $vsm->consultarEstoque($sku);
      $estoqueVsm = 0.0;
      if (isset($vsmRet['estoque'])) $estoqueVsm = (float)$vsmRet['estoque'];
      elseif (isset($vsmRet['saldo'])) $estoqueVsm = (float)$vsmRet['saldo'];
      elseif (isset($vsmRet['dados']['estoque'])) $estoqueVsm = (float)$vsmRet['dados']['estoque'];
    } catch (Throwable $e) {
      Audit::event('reconciliacao.sku.indisponivel','alerta',[
        'codigo_erro'=>'RECONCILE_PROVIDER_UNAVAILABLE',
        'mensagem'=>'Reconciliação real não pôde consultar Tiny/VSM.',
        'causa_provavel'=>'Provedor externo indisponível (rede, credencial ou endpoint de consulta).',
        'acao_recomendada'=>'Confira o token Tiny e o endpoint de consulta de estoque da VSM, ou use a comparação manual.',
        'contexto'=>['sku'=>$sku,'erro'=>$e->getMessage()],
      ]);
      return ['sku'=>$sku,'status'=>'indisponivel','erro'=>$e->getMessage()];
    }
    $res = self::registrarManual($sku, $estoqueTiny, $estoqueVsm, 'api_real', ['retorno_tiny'=>$tinyRet,'retorno_vsm'=>$vsmRet]);
    Audit::event('reconciliacao.sku.real','sucesso',[ 'mensagem'=>'Reconciliação real executada para SKU.', 'contexto'=>['sku'=>$sku], 'retorno'=>['tiny'=>$tinyRet,'vsm'=>$vsmRet,'resultado'=>$res] ]);
    return $res + ['retorno_tiny'=>$tinyRet,'retorno_vsm'=>$vsmRet];
  }

  public static function resumo(): array {
    $pdo = Database::forTable('estoque_movimentos');
    return [
      'total'=>(int)TenantScopeService::run('estoque_reconciliacao', 'SELECT COUNT(*) c FROM estoque_reconciliacao')->fetch()['c'],
      'divergentes'=>(int)TenantScopeService::run('estoque_reconciliacao', "SELECT COUNT(*) c FROM estoque_reconciliacao WHERE status='divergente'")->fetch()['c'],
      'ultimos'=>TenantScopeService::run('estoque_reconciliacao', 'SELECT * FROM estoque_reconciliacao ORDER BY id DESC LIMIT 50')->fetchAll()
    ];
  }
}
