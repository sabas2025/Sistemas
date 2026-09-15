<?php
// Worker fiscal automático.
// Execute no Windows/XAMPP: php public/worker_fiscal.php 20
require_once __DIR__.'/../app/Core/Autoload.php';
WorkerCliGuardService::enforce(basename(__FILE__));
require_once __DIR__.'/../app/Core/Helpers.php';
App::setupErrors();

$limit = (int)($argv[1] ?? 20);
$db = Database::forTable('fila_fiscal');
$st = $db->prepare("SELECT * FROM fila_fiscal WHERE status='pendente' AND (proxima_tentativa IS NULL OR proxima_tentativa<=NOW()) ORDER BY FIELD(prioridade,'critica','alta','normal','baixa'), id ASC LIMIT ?");
$st->bindValue(1, max(1,min(200,$limit)), PDO::PARAM_INT);
$st->execute();
$itens = $st->fetchAll();

foreach ($itens as $item) {
  $id = (int)$item['id'];
  $notaId = (int)($item['nota_fiscal_id'] ?? 0);
  $integracaoId = (int)($item['nfe_integracao_id'] ?? 0);
  $acao = (string)($item['acao'] ?? 'reprocessar');
  try {
    $db->prepare("UPDATE fila_fiscal SET status='processando', tentativas=tentativas+1, atualizado_em=NOW() WHERE id=?")->execute([$id]);
    $ret = ['ok'=>true,'worker'=>'fiscal_v62','acao'=>$acao,'fila_id'=>$id];

    if ($acao === 'validar_xml') {
      if (!$notaId) throw new RuntimeException('Fila fiscal validar_xml sem nota_fiscal_id.');
      $x = Database::forTable('nfe_xml')->prepare('SELECT * FROM nfe_xml WHERE nota_fiscal_id=? ORDER BY id DESC LIMIT 1');
      $x->execute([$notaId]);
      $xml = $x->fetch();
      if (!$xml || empty($xml['conteudo'])) throw new RuntimeException('XML fiscal não encontrado para validação.');
      $conteudo = (string)$xml['conteudo'];
      $ok = str_contains($conteudo, '<NFe') || str_contains($conteudo, '<procNFe') || str_contains($conteudo, '<nfeProc');
      FiscalEnterpriseService::registrarStatus($notaId, $ok ? 'xml_validado' : 'erro_xml', null, $ok ? 'XML validado pelo worker fiscal.' : 'XML inválido/bloqueado pelo worker fiscal.');
      if (!$ok) throw new RuntimeException('XML não parece ser NF-e/procNFe válido.');
      $ret['xml_validado'] = true;
    } elseif ($acao === 'enviar_tiny') {
      if (!$notaId) {
        if ($integracaoId) {
          $s = Database::forTable('nfe_integracao')->prepare('SELECT nota_fiscal_id FROM nfe_integracao WHERE id=?');
          $s->execute([$integracaoId]);
          $notaId = (int)$s->fetchColumn();
        }
        if (!$notaId) throw new RuntimeException('Fila fiscal enviar_tiny sem nota fiscal vinculada.');
      }
      $n = Database::forTable('notas_fiscais')->prepare('SELECT * FROM notas_fiscais WHERE id=?');
      $n->execute([$notaId]);
      $nota = $n->fetch();
      if (!$nota) throw new RuntimeException('Nota fiscal não encontrada para envio ao Tiny.');
      $x = Database::forTable('nfe_xml')->prepare('SELECT * FROM nfe_xml WHERE nota_fiscal_id=? ORDER BY id DESC LIMIT 1');
      $x->execute([$notaId]);
      $xml = $x->fetch();
      if (!$xml || empty($xml['conteudo'])) throw new RuntimeException('XML não encontrado para enviar ao Tiny.');
      $tiny = TinyFactory::make();
      $pedidoId = (string)($nota['pedido_tiny_id'] ?? $nota['pedido_origem_id'] ?? '');
      if (!$pedidoId) throw new RuntimeException('Nota fiscal sem pedido Tiny vinculado.');
      $payload = ['xml'=>$xml['conteudo'], 'chave_nfe'=>$nota['chave_acesso'] ?? '', 'numero_nfe'=>$nota['numero'] ?? '', 'serie'=>$nota['serie'] ?? '', 'status'=>'faturado'];
      $retTiny = method_exists($tiny,'enviarNfeXmlPedido') ? $tiny->enviarNfeXmlPedido($pedidoId, $payload) : ['erro'=>'Tiny client sem método enviarNfeXmlPedido.'];
      $erro = isset($retTiny['erro']) || isset($retTiny['codigo_erro']);
      FiscalEnterpriseService::registrarStatus($notaId, $erro ? 'erro_envio_tiny' : 'enviado_tiny', null, $erro ? 'Erro ao enviar XML/status para Tiny.' : 'XML/status enviados para Tiny pelo worker fiscal.');
      if ($integracaoId) Database::forTable('nfe_integracao')->prepare("UPDATE nfe_integracao SET status=?, retorno=?, ultimo_erro=?, atualizado_em=NOW() WHERE id=?")->execute([$erro?'erro':'sucesso', json_encode($retTiny,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), $erro?json_encode($retTiny,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null, $integracaoId]);
      if ($erro) throw new RuntimeException('Tiny retornou erro no envio fiscal: '.json_encode($retTiny,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
      $ret['tiny'] = $retTiny;
    } elseif ($acao === 'reprocessar') {
      if ($integracaoId) {
        $s = Database::forTable('nfe_integracao')->prepare('SELECT * FROM nfe_integracao WHERE id=?');
        $s->execute([$integracaoId]);
        $int = $s->fetch();
        if (!$int) throw new RuntimeException('Integração fiscal não encontrada para reprocessamento.');
        $notaId = (int)($int['nota_fiscal_id'] ?? 0);
        FiscalEnterpriseService::registrarStatus($notaId, 'reprocessado_worker', null, 'Integração fiscal reprocessada pelo worker.');
        Database::forTable('nfe_integracao')->prepare("UPDATE nfe_integracao SET status='pendente', ultimo_erro=NULL, proxima_tentativa=NULL, atualizado_em=NOW() WHERE id=?")->execute([$integracaoId]);
      }
      $ret['reprocessado'] = true;
    } elseif ($acao === 'consultar_status') {
      $ret['message'] = 'Consulta de status fiscal preparada para endpoint VSM/Tiny configurável.';
    } else {
      $ret['message'] = 'Ação fiscal registrada como processada sem conector específico: '.$acao;
    }

    $db->prepare("UPDATE fila_fiscal SET status='sucesso', retorno=?, atualizado_em=NOW() WHERE id=?")->execute([json_encode($ret,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$id]);
    Audit::event('fiscal.worker.sucesso','sucesso',['entidade'=>'fila_fiscal','entidade_id'=>$id,'mensagem'=>'Item da fila fiscal processado automaticamente.','contexto'=>$ret]);
  } catch (Throwable $e) {
    $tentativa = (int)$item['tentativas'] + 1;
    $status = $tentativa >= 4 ? 'falha_definitiva' : 'pendente';
    $proxima = $status === 'pendente' ? FiscalEnterpriseService::proximaTentativa($tentativa) : null;
    $db->prepare("UPDATE fila_fiscal SET status=?, ultimo_erro=?, proxima_tentativa=?, atualizado_em=NOW() WHERE id=?")->execute([$status,$e->getMessage(),$proxima,$id]);
    if ($notaId) FiscalEnterpriseService::registrarStatus($notaId, $status === 'falha_definitiva' ? 'erro_worker_fiscal_definitivo' : 'erro_worker_fiscal', null, $e->getMessage());
    Audit::exception($e, 'fiscal.worker.erro', ['entidade'=>'fila_fiscal','entidade_id'=>$id]);
  }
}

echo "Worker fiscal processou ".count($itens)." item(ns).\n";
