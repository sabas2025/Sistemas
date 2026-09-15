<?php
class NotificationService {
  public static function criar(string $tipo, string $titulo, string $mensagem, string $severidade='info', array $opts=[]): int {
    try {
      $pdo = Database::forTable('notificacoes');
      if(!in_array($severidade, ['info','sucesso','alerta','erro','critico'], true)) $severidade = 'info';
      $trace = $opts['trace_id'] ?? RequestContext::id();
      $stmt = $pdo->prepare("INSERT INTO notificacoes(tipo,titulo,mensagem,severidade,entidade,entidade_id,link,trace_id,payload) VALUES(?,?,?,?,?,?,?,?,?)");
      $stmt->execute([
        $tipo,
        $titulo,
        $mensagem,
        $severidade,
        $opts['entidade'] ?? null,
        $opts['entidade_id'] ?? null,
        $opts['link'] ?? null,
        $trace,
        isset($opts['payload']) ? json_encode($opts['payload'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null
      ]);
      return (int)$pdo->lastInsertId();
    } catch (Throwable $e) {
      // Não deixa uma falha de notificação derrubar a integração.
      try { Logger::log('notificacao','Falha ao criar notificação',['erro'=>$e->getMessage()],'erro'); } catch(Throwable $ignored){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $ignored); }
      return 0;
    }
  }

  public static function novoPedido(string $pedido, array $payload=[]): int {
    return self::criar(
      'pedido_novo',
      'Novo pedido recebido',
      'Pedido '.$pedido.' recebido da VSM e enviado para a fila de integração.',
      'sucesso',
      ['entidade'=>'pedido','entidade_id'=>$pedido,'link'=>'index.php?page=pedidos&busca='.urlencode($pedido),'payload'=>$payload]
    );
  }

  public static function erroIntegracao(string $titulo, string $mensagem, array $opts=[]): int {
    $opts['link'] = $opts['link'] ?? 'index.php?page=auditoria&trace='.urlencode($opts['trace_id'] ?? RequestContext::id());
    return self::criar('erro_integracao', $titulo, $mensagem, 'erro', $opts);
  }

  public static function fila(string $titulo, string $mensagem, string $severidade='alerta', array $opts=[]): int {
    return self::criar('fila', $titulo, $mensagem, $severidade, $opts);
  }

  public static function listar(int $limit=100, bool $somenteNaoLidas=false): array {
    $pdo = Database::forTable('notificacoes');
    $sql = "SELECT * FROM notificacoes" . ($somenteNaoLidas ? " WHERE lida=0" : "") . " ORDER BY id DESC LIMIT ".(int)$limit;
    return $pdo->query($sql)->fetchAll();
  }

  /**
   * P2 (reauditoria 2026-08-23): esta função só é consumida pelo widget de notificações
   * (dropdown do topo) e usava SELECT * - devolvendo entidade/entidade_id/trace_id e o
   * payload JSON integral (que em algumas chamadas de criar() carrega dados operacionais
   * completos) para qualquer usuário autenticado, mesmo sem essa informação ser exibida.
   * Agora só traz os campos que o widget de fato usa.
   */
  public static function recentes(int $ultimoId=0, int $limit=15): array {
    $pdo = Database::forTable('notificacoes');
    $stmt = $pdo->prepare("SELECT id,tipo,titulo,mensagem,severidade,link,lida,criada_em FROM notificacoes WHERE id > ? ORDER BY id DESC LIMIT ".(int)$limit);
    $stmt->execute([$ultimoId]);
    return $stmt->fetchAll();
  }

  public static function totalNaoLidas(): int {
    $pdo = Database::forTable('notificacoes');
    return (int)($pdo->query("SELECT COUNT(*) c FROM notificacoes WHERE lida=0")->fetch()['c'] ?? 0);
  }

  public static function marcarLida(int $id): void {
    $pdo = Database::forTable('notificacoes');
    $stmt = $pdo->prepare("UPDATE notificacoes SET lida=1, lida_em=NOW() WHERE id=?");
    $stmt->execute([$id]);
  }

  public static function marcarTodasLidas(): void {
    $pdo = Database::forTable('notificacoes');
    $pdo->exec("UPDATE notificacoes SET lida=1, lida_em=NOW() WHERE lida=0");
  }
}
