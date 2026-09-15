<?php
class DeadLetterQueueService {
  public static function enviar(array $item, array $retorno=[], ?string $codigoErro=null, ?string $motivo=null): void {
    try {
      $pdo = Database::forTable('fila_morta');
      $classificacao = class_exists('ErrorClassificationService') ? ErrorClassificationService::classify($codigoErro, $retorno, $motivo) : ['categoria'=>'operacional','severidade'=>'media','retryable'=>1,'owner_area'=>'operacao','acao'=>'Analisar manualmente.'];
      $hasEnterprise = Database::columnExists('fila_morta','categoria_erro') && Database::columnExists('fila_morta','acao_recomendada_dlq');
      // Achado I-13: a linha da fila morta nascia sem empresa, então o dono se perdia já na
      // falha — e o reprocessamento não tinha de onde recuperá-lo. O item vem de um SELECT *
      // sobre fila_integracao, logo traz empresa_id quando a coluna existe.
      $empresaDoItem = (Database::columnExists('fila_morta','empresa_id') && array_key_exists('empresa_id', $item)) ? $item['empresa_id'] : false;
      if ($hasEnterprise) {
        $pdo->prepare('INSERT INTO fila_morta(fila_id,tipo,referencia,payload_original,ultimo_retorno,codigo_erro,motivo,tentativas,trace_id,status,categoria_erro,severidade,retryable,owner_area,acao_recomendada_dlq,classificado_em) VALUES(?,?,?,?,?,?,?,?,?,"aberto",?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE ultimo_retorno=VALUES(ultimo_retorno), codigo_erro=VALUES(codigo_erro), motivo=VALUES(motivo), tentativas=VALUES(tentativas), status="aberto", categoria_erro=VALUES(categoria_erro), severidade=VALUES(severidade), retryable=VALUES(retryable), owner_area=VALUES(owner_area), acao_recomendada_dlq=VALUES(acao_recomendada_dlq), classificado_em=NOW(), atualizado_em=NOW()')
          ->execute([
            $item['id'] ?? null,
            $item['tipo'] ?? 'desconhecido',
            $item['referencia'] ?? null,
            $item['payload'] ?? null,
            json_encode($retorno, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            $codigoErro,
            $motivo ?: 'Item atingiu o limite de tentativas e foi movido para análise manual.',
            (int)($item['tentativas'] ?? 0),
            $item['trace_id'] ?? RequestContext::id(),
            $classificacao['categoria'],
            $classificacao['severidade'],
            (int)$classificacao['retryable'],
            $classificacao['owner_area'],
            $classificacao['acao']
          ]);
      } else {
        $pdo->prepare('INSERT INTO fila_morta(fila_id,tipo,referencia,payload_original,ultimo_retorno,codigo_erro,motivo,tentativas,trace_id,status) VALUES(?,?,?,?,?,?,?,?,?,"aberto") ON DUPLICATE KEY UPDATE ultimo_retorno=VALUES(ultimo_retorno), codigo_erro=VALUES(codigo_erro), motivo=VALUES(motivo), tentativas=VALUES(tentativas), status="aberto", atualizado_em=NOW()')
          ->execute([
            $item['id'] ?? null,
            $item['tipo'] ?? 'desconhecido',
            $item['referencia'] ?? null,
            $item['payload'] ?? null,
            json_encode($retorno, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            $codigoErro,
            $motivo ?: 'Item atingiu o limite de tentativas e foi movido para análise manual.',
            (int)($item['tentativas'] ?? 0),
            $item['trace_id'] ?? RequestContext::id()
          ]);
      }
      if ($empresaDoItem !== false && $empresaDoItem !== null) {
        $pdo->prepare('UPDATE fila_morta SET empresa_id=? WHERE fila_id=? AND empresa_id IS NULL')
          ->execute([$empresaDoItem, $item['id'] ?? null]);
      }
      NotificationService::criar('erro_integracao','Item enviado para fila morta','A integração '.$item['referencia'].' atingiu o limite de tentativas.','erro',['trace_id'=>$item['trace_id'] ?? RequestContext::id(),'link'=>'index.php?page=fila-morta','categoria_erro'=>$classificacao['categoria']]);
      Audit::event('fila_morta.criada','erro',['entidade'=>'fila_integracao','entidade_id'=>$item['id'] ?? null,'codigo_erro'=>$codigoErro,'mensagem'=>'Item movido para fila morta.','retorno'=>$retorno,'contexto'=>['classificacao'=>$classificacao],'acao_recomendada'=>$classificacao['acao']]);
    } catch(Throwable $e) { Audit::exception($e,'fila_morta.erro_criacao'); }
  }

  public static function reprocessar(int $dlqId): void {
    $pdo = Database::forTable('fila_morta');
    $st = $pdo->prepare('SELECT * FROM fila_morta WHERE id=? LIMIT 1');
    $st->execute([$dlqId]);
    $dlq = $st->fetch();
    if (!$dlq) throw new RuntimeException('Registro da fila morta não encontrado.');
    // Achado I-13: a linha reprocessada nascia com empresa_id NULL — visível a todas as empresas.
    // O dono não precisa ser adivinhado: a própria linha da fila morta sabe de quem ela é. A
    // condição de coluna segue o desenho de enviar() acima, para banco anterior à migration 010.
    $params = [$dlq['tipo'],$dlq['referencia'],$dlq['payload_original'],$dlq['trace_id'] ?: RequestContext::id()];
    $herdaEmpresa = Database::columnExists('fila_integracao','empresa_id') && array_key_exists('empresa_id', (array)$dlq);
    if ($herdaEmpresa) $params[] = $dlq['empresa_id'];
    $colunas = 'tipo,referencia,payload,status,tentativas,trace_id'.($herdaEmpresa ? ',empresa_id' : '');
    $valores = '?,?,?,"pendente",0,?'.($herdaEmpresa ? ',?' : '');
    Database::forTable('fila_integracao')->prepare('INSERT INTO fila_integracao('.$colunas.') VALUES('.$valores.')')
      ->execute($params);
    $novoId = (int)Database::forTable('fila_integracao')->lastInsertId();
    $pdo->prepare('UPDATE fila_morta SET status="reprocessado", atualizado_em=NOW() WHERE id=?')->execute([$dlqId]);
    Audit::event('fila_morta.reprocessar','sucesso',['entidade'=>'fila_integracao','entidade_id'=>$novoId,'mensagem'=>'Item da fila morta reenviado para fila principal.']);
  }
}
