<?php
class ProdutoTinyPreflightService {
  private static function normalizarSku(string $sku): string { return strtoupper(trim($sku)); }

  public static function analisarRetornoPesquisa(array $ret): array {
    $resultado = ['existe'=>false,'id'=>null,'sku'=>null,'nome'=>null,'estoque'=>null,'situacao'=>null,'raw'=>$ret];
    if (isset($ret['erro']) || isset($ret['codigo_erro'])) { $resultado['erro'] = $ret['erro'] ?? $ret['codigo_erro']; return $resultado; }
    $produtos = [];
    $candidatos = [
      $ret['retorno']['produtos'] ?? null,
      $ret['retorno']['produto'] ?? null,
      $ret['produtos'] ?? null,
      $ret['produto'] ?? null,
    ];
    foreach ($candidatos as $c) {
      if (!$c) continue;
      if (isset($c['produto'])) $produtos[] = $c['produto'];
      elseif (is_array($c) && array_is_list($c)) {
        foreach ($c as $linha) $produtos[] = $linha['produto'] ?? $linha;
      } elseif (is_array($c)) $produtos[] = $c;
    }
    $p = $produtos[0] ?? null;
    if (is_array($p)) {
      $resultado['existe'] = true;
      $resultado['id'] = (string)($p['id'] ?? $p['idProduto'] ?? $p['id_produto'] ?? '');
      $resultado['sku'] = (string)($p['codigo'] ?? $p['sku'] ?? '');
      $resultado['nome'] = (string)($p['nome'] ?? $p['descricao'] ?? '');
      $resultado['estoque'] = isset($p['saldo']) ? (float)$p['saldo'] : (isset($p['estoque_atual']) ? (float)$p['estoque_atual'] : (isset($p['estoque']) ? (float)$p['estoque'] : null));
      $resultado['situacao'] = (string)($p['situacao'] ?? $p['status'] ?? '');
    }
    return $resultado;
  }

  public static function consultarOuPendenciar(TinyClientInterface $tiny, string $sku, string $tipoEvento, array $payload, int $filaId, string $trace): array {
    $ret = $tiny->consultarProduto($sku);
    $info = self::analisarRetornoPesquisa($ret);
    Audit::event('tiny.produto.preflight','info',[
      'mensagem'=>'Pré-validação do produto no Tiny antes de atualizar estoque/status.',
      'entidade'=>'fila_integracao','entidade_id'=>$filaId,
      'payload'=>['sku'=>$sku,'tipo_evento'=>$tipoEvento,'payload_vsm'=>$payload],
      'retorno'=>$info
    ]);
    if (!empty($info['erro'])) {
      self::registrarPendencia($sku,$tipoEvento,'erro_consulta_tiny','Falha ao consultar produto no Tiny antes da atualização.',$payload,$ret,$filaId,$trace);
      return ['ok'=>false,'codigo'=>'TINY_PRODUCT_PREFLIGHT_ERROR','mensagem'=>'Falha ao consultar SKU no Tiny antes da atualização.','retorno'=>$ret,'info'=>$info];
    }
    if (!$info['existe']) {
      self::registrarPendencia($sku,$tipoEvento,'produto_nao_encontrado','SKU não encontrado no Tiny. Atualização bloqueada para evitar cadastro inconsistente.',$payload,$ret,$filaId,$trace);
      NotificationService::criar('estoque','SKU não encontrado no Tiny','Produto '.$sku.' enviado pela VSM não existe no Tiny. Evento enviado para pendência.','alerta',['trace_id'=>$trace,'entidade'=>'produto_pendencias','link'=>'index.php?page=produtos-pendencias']);
      return ['ok'=>false,'codigo'=>'TINY_PRODUCT_NOT_FOUND','mensagem'=>'SKU não encontrado no Tiny.','retorno'=>$ret,'info'=>$info];
    }
    $skuRetornado = (string)($info['sku'] ?? '');
    if ($skuRetornado === '' || self::normalizarSku($skuRetornado) !== self::normalizarSku($sku)) {
      self::registrarPendencia($sku,$tipoEvento,'sku_nao_exato','Tiny retornou produto parecido, mas o código não confere exatamente com o SKU enviado pela VSM. Atualização bloqueada para evitar alterar produto errado.',$payload,$ret,$filaId,$trace);
      NotificationService::criar('estoque','SKU Tiny não confere','A VSM enviou '.$sku.', mas o Tiny retornou '.$skuRetornado.'. Evento enviado para pendência.','alerta',['trace_id'=>$trace,'link'=>'index.php?page=produtos-pendencias']);
      return ['ok'=>false,'codigo'=>'TINY_SKU_NOT_EXACT_MATCH','mensagem'=>'SKU retornado pelo Tiny não confere exatamente.','retorno'=>$ret,'info'=>$info];
    }
    return ['ok'=>true,'codigo'=>null,'mensagem'=>'SKU localizado exatamente no Tiny.','retorno'=>$ret,'info'=>$info];
  }

  public static function registrarPendencia(string $sku, string $tipoEvento, string $motivo, string $mensagem, array $payload, array $retorno, int $filaId, string $trace): void {
    $pdo = Database::forTable('produto_pendencias');
    TenantScopeService::run('produto_pendencias', 'INSERT INTO produto_pendencias(sku,tipo_evento,motivo,mensagem,payload,retorno_tiny,fila_id,trace_id,status) VALUES(?,?,?,?,?,?,?,?,?)', [$sku,$tipoEvento,$motivo,$mensagem,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),json_encode($retorno,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$filaId,$trace,'aberto']);
    Audit::event('produto.pendencia.criada','alerta',[
      'codigo_erro'=>strtoupper($motivo),
      'mensagem'=>$mensagem,
      'entidade'=>'produto_pendencias','entidade_id'=>$pdo->lastInsertId(),
      'payload'=>$payload,'retorno'=>$retorno,
      'acao_recomendada'=>'Abrir Produtos → Pendências, conferir se o SKU existe no Tiny ou criar/relacionar o produto antes de reprocessar.'
    ]);
  }

  public static function registrarAlertaInativoComEstoque(string $sku, float $estoque, array $payload, int $filaId, string $trace): bool {
    if ($estoque <= 0) return false;
    $cfg = IntegrationConfig::get();
    $bloquear = (int)($cfg['bloquear_inativo_com_estoque'] ?? 1) === 1;
    if (!$bloquear) {
      NotificationService::criar('estoque','Produto inativo com estoque','A VSM enviou o produto '.$sku.' como inativo, mas com estoque '.$estoque.'. Política atual: apenas alertar, sem bloquear.','alerta',['trace_id'=>$trace,'entidade'=>'fila_integracao','entidade_id'=>$filaId,'link'=>'index.php?page=produtos-vsm']);
      Audit::event('produto.inativo_com_estoque.apenas_alerta','alerta',[
        'codigo_erro'=>'PRODUCT_INACTIVE_WITH_STOCK_WARNING',
        'mensagem'=>'Produto inativo com estoque gerou alerta, mas não foi bloqueado pela configuração atual.',
        'entidade'=>'fila_integracao','entidade_id'=>$filaId,
        'payload'=>$payload,
        'contexto'=>['sku'=>$sku,'estoque'=>$estoque],
        'acao_recomendada'=>'Revise a política em Configurações caso deseje bloquear este tipo de evento.'
      ]);
      return false;
    }
    self::registrarPendencia($sku,'produto_status_atualizado','inativo_com_estoque','Produto enviado como inativo pela VSM, porém possui estoque maior que zero. Bloqueado por segurança até conferência manual.',$payload,[], $filaId,$trace);
    NotificationService::criar('estoque','Produto inativo com estoque','A VSM enviou o produto '.$sku.' como inativo, mas com estoque '.$estoque.'. Evento bloqueado e enviado para pendência.','critico',['trace_id'=>$trace,'entidade'=>'fila_integracao','entidade_id'=>$filaId,'link'=>'index.php?page=produtos-pendencias']);
    Audit::event('produto.inativo_com_estoque','critico',[
      'codigo_erro'=>'PRODUCT_INACTIVE_WITH_STOCK_BLOCKED',
      'mensagem'=>'Produto inativo com estoque foi bloqueado por segurança.',
      'entidade'=>'fila_integracao','entidade_id'=>$filaId,
      'payload'=>$payload,
      'contexto'=>['sku'=>$sku,'estoque'=>$estoque],
      'acao_recomendada'=>'Abrir Pendências de Produtos, conferir se deve zerar estoque, manter ativo ou liberar manualmente.'
    ]);
    return true;
  }
}
