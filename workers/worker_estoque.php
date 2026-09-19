<?php
// Worker de estoque enterprise com VSM como fonte real.
// Execute no Windows/XAMPP: php public/worker_estoque.php 20
require_once __DIR__.'/../app/Core/Autoload.php';
WorkerCliGuardService::enforce(basename(__FILE__));
require_once __DIR__.'/../app/Core/Helpers.php';
App::setupErrors();

$limit = (int)($argv[1] ?? 20);
$db = Database::forTable('fila_estoque');
$itens = $db->prepare("SELECT * FROM fila_estoque WHERE empresa_id=".(int)IntegrationTenantService::boundEmpresaId()." AND status='pendente' AND (proxima_tentativa IS NULL OR proxima_tentativa<=NOW()) ORDER BY id ASC LIMIT ?");
$itens->bindValue(1, max(1,min(200,$limit)), PDO::PARAM_INT);
$itens->execute();
$rows = $itens->fetchAll();

foreach ($rows as $r) {
  if (!TenantScopeService::assertRow('fila_estoque',$r,'worker_estoque')) continue;
  $id = (int)$r['id'];
  try {
    $db->prepare("UPDATE fila_estoque SET status='processando', tentativas=tentativas+1, atualizado_em=NOW() WHERE id=?")->execute([$id]);
    $payload = json_decode((string)$r['payload'], true) ?: [];
    $origem = (string)$r['origem'];
    $destino = (string)$r['destino'];
    $sku = (string)$r['sku'];
    $qtd = (float)$r['quantidade'];
    $ret = ['ok'=>true,'worker'=>'estoque_v62','origem'=>$origem,'destino'=>$destino,'sku'=>$sku,'quantidade'=>$qtd];

    if ($sku === '__RECONCILIACAO__') {
      $execId = (int)($payload['execucao_id'] ?? 0);
      if ($execId) {
        Database::forTable('reconciliacao_execucoes')->prepare("UPDATE reconciliacao_execucoes SET status='executando', iniciado_em=COALESCE(iniciado_em,NOW()) WHERE id=?")->execute([$execId]);
        Database::forTable('reconciliacao_execucoes')->prepare("UPDATE reconciliacao_execucoes SET status='concluido', finalizado_em=NOW(), mensagem='Reconciliação processada pelo worker de estoque.' WHERE id=?")->execute([$execId]);
      }
    } elseif ($destino === 'tiny') {
      // VSM é o estoque real: saldo recebido da VSM atualiza Tiny como saldo autoritativo.
      $tiny = TinyFactory::make();
      $retTiny = $tiny->atualizarEstoque($sku, $qtd);
      $erro = isset($retTiny['erro']) || isset($retTiny['codigo_erro']) || (isset($retTiny['http_code']) && ((int)$retTiny['http_code'] < 200 || (int)$retTiny['http_code'] >= 300));
      $ret['tiny'] = $retTiny;
      // P1-08 (reauditoria 2026-08-23): retorno_tiny/retorno_vsm/contexto de auditoria e
      // a mensagem de erro guardavam a resposta integral do parceiro sem sanitização nem
      // limite de tamanho. Agora tudo passa por SensitiveDataService::sanitizeForStorage().
      Database::forTable('estoque_movimentos')->prepare("UPDATE estoque_movimentos SET status=?, retorno_tiny=? WHERE trace_id=? AND sku=? ORDER BY id DESC LIMIT 1")
        ->execute([$erro?'erro':'sucesso', SensitiveDataService::sanitizeForStorage($retTiny), (string)$r['trace_id'], $sku]);
      EstoqueEnterpriseService::auditarSku($sku,$erro?'estoque.envio_tiny.erro':'estoque.envio_tiny.sucesso','processando',$erro?'erro':'sucesso',null,$qtd,'worker_estoque',$erro?'Erro ao atualizar Tiny com saldo VSM.':'Tiny atualizado com saldo autoritativo da VSM.',['retorno'=>SensitiveDataService::mask($retTiny),'fila_id'=>$id]);
      if ($erro) throw new RuntimeException('Erro ao atualizar estoque Tiny: '.SensitiveDataService::sanitizeForStorage($retTiny, 2000));
    } elseif ($destino === 'vsm') {
      // Tiny teve venda/alteração: VSM recebe a baixa/atualização e continua sendo estoque real.
      $vsm = new VsmService();
      $baixa = $payload['payload_original'] ?? $payload;
      if (empty($baixa['itens'])) $baixa = ['referencia'=>$payload['referencia'] ?? $r['id'], 'itens'=>[['sku'=>$sku,'quantidade'=>$qtd]], 'origem'=>'tiny', 'politica'=>'vsm_fonte_real'];
      $retVsm = $vsm->enviarBaixaEstoque($baixa, 'fila_estoque:'.$id);
      $erro = isset($retVsm['erro']) || isset($retVsm['codigo_erro']) || (isset($retVsm['http_code']) && ((int)$retVsm['http_code'] < 200 || (int)$retVsm['http_code'] >= 300));
      $ret['vsm'] = $retVsm;
      Database::forTable('estoque_movimentos')->prepare("UPDATE estoque_movimentos SET status=?, retorno_vsm=? WHERE trace_id=? AND sku=? ORDER BY id DESC LIMIT 1")
        ->execute([$erro?'erro':'sucesso', SensitiveDataService::sanitizeForStorage($retVsm), (string)$r['trace_id'], $sku]);
      EstoqueEnterpriseService::auditarSku($sku,$erro?'estoque.envio_vsm.erro':'estoque.envio_vsm.sucesso','processando',$erro?'erro':'sucesso',null,$qtd,'worker_estoque',$erro?'Erro ao enviar baixa/atualização Tiny para VSM.':'VSM recebeu baixa/atualização do Tiny e permanece estoque real.',['retorno'=>SensitiveDataService::mask($retVsm),'fila_id'=>$id]);
      if ($erro) throw new RuntimeException('Erro ao enviar estoque para VSM: '.SensitiveDataService::sanitizeForStorage($retVsm, 2000));
    }

    $db->prepare("UPDATE fila_estoque SET status='sucesso', retorno=?, atualizado_em=NOW() WHERE id=?")->execute([SensitiveDataService::sanitizeForStorage($ret),$id]);
    Audit::event('estoque.worker.sucesso','sucesso',['entidade'=>'fila_estoque','entidade_id'=>$id,'mensagem'=>'Item da fila de estoque processado pela política VSM estoque real.','contexto'=>SensitiveDataService::mask($ret)]);
  } catch (Throwable $e) {
    $tentativa = (int)$r['tentativas'] + 1;
    $status = $tentativa >= 4 ? 'falha_definitiva' : 'pendente';
    $proxima = $status === 'pendente' ? EstoqueEnterpriseService::proximaTentativa($tentativa) : null;
    $db->prepare("UPDATE fila_estoque SET status=?, ultimo_erro=?, proxima_tentativa=?, atualizado_em=NOW() WHERE id=?")->execute([$status,$e->getMessage(),$proxima,$id]);
    EstoqueEnterpriseService::registrarAlerta((string)$r['sku'], 'worker_erro', $e->getMessage(), $status === 'falha_definitiva' ? 'critico' : 'erro', ['fila_id'=>$id,'origem'=>$r['origem'],'destino'=>$r['destino']]);
    Audit::exception($e, 'estoque.worker.erro', ['entidade'=>'fila_estoque','entidade_id'=>$id]);
  }
}

echo "Worker estoque processou ".count($rows)." item(ns).\n";
