<?php
class DataRetentionService {
  private static bool $ran = false;

  public static function maybeRun(): void {
    if (self::$ran || PHP_SAPI === 'cli') return;
    self::$ran = true;
    try {
      if (random_int(1, 100) !== 1) return;
      self::cleanup();
    } catch (Throwable $e) { error_log('DataRetentionService: '.$e->getMessage()); }
  }

  /** Dias de retenção de item de fila já concluído. Configurável; 30 dias por padrão. */
  private static function filaRetentionDays(): int {
    $cfg = class_exists('App') ? (App::config()['security'] ?? []) : [];
    $dias = (int)($cfg['queue_done_retention_days'] ?? 30);
    return max(1, min(365, $dias));
  }

  /**
   * Expurga itens de fila em estado TERMINAL. Achado C-06.
   * @return list<array<string,mixed>>
   */
  private static function cleanupFilaConcluida(): array {
    try {
      if (!Database::tableExists('fila_integracao') || !Database::columnExists('fila_integracao','status')) return [];
      $coluna = Database::columnExists('fila_integracao','processado_em') ? 'processado_em' : 'criado_em';
      $dias = self::filaRetentionDays();
      $pdo = Database::forTable('fila_integracao');
      // Estados terminais explícitos: nada de "tudo que não é pendente".
      $st = $pdo->prepare('DELETE FROM fila_integracao WHERE status IN (?,?,?) AND `'.$coluna.'` IS NOT NULL AND `'.$coluna.'` < DATE_SUB(NOW(), INTERVAL ? DAY) LIMIT 5000');
      $st->execute(['concluido','processado','sucesso',$dias]);
      return [['table'=>'fila_integracao','deleted'=>(int)$st->rowCount(),'rule'=>$dias.' DAY (apenas estado terminal)']];
    } catch (Throwable $e) {
      if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e);
      return [];
    }
  }

  /**
   * Expurga sessões expiradas da tabela `sessoes`. Achado D-01.
   *
   * DatabaseSessionHandler::gc() existe, mas quem o chama é o PHP, de forma PROBABILÍSTICA — e
   * `session.gc_probability = 0` é a configuração de fábrica de várias distribuições (Debian e
   * Ubuntu entre elas), que desligam o coletor do PHP porque limpam sessões de ARQUIVO por cron.
   * Esse cron não sabe nada de tabela. Foi verificado no PHP deste ambiente: gc_probability = 0.
   *
   * Consequência sem este método: ao ligar security.session_driver='database', a tabela cresceria
   * para sempre — e a correção de capacidade C-07 teria criado um vazamento de armazenamento no
   * lugar do problema que resolveu.
   *
   * A coluna `ultimo_acesso` é INT (epoch), não DATETIME, então não cabe na regra genérica de
   * idade usada pelas demais tabelas.
   *
   * @return list<array<string,mixed>>
   */
  private static function cleanupSessoes(): array {
    try {
      if (!Database::tableExists('sessoes') || !Database::columnExists('sessoes','ultimo_acesso')) return [];
      $cfg = class_exists('App') ? (App::config()['security'] ?? []) : [];
      $ttl = max(300, (int)($cfg['session_absolute_timeout_seconds'] ?? 28800));
      $pdo = Database::forTable('sessoes');
      $st = $pdo->prepare('DELETE FROM sessoes WHERE ultimo_acesso < ? LIMIT 5000');
      $st->execute([time() - $ttl]);
      return [['table'=>'sessoes','deleted'=>(int)$st->rowCount(),'rule'=>$ttl.' SECOND (ultimo_acesso)']];
    } catch (Throwable $e) {
      if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e);
      return [];
    }
  }

  public static function cleanup(): array {
    $items = [];
    $rules = [
      ['rate_limit_hits','janela_inicio',2,'HOUR'],
      ['integration_replay_guard','request_time',7,'DAY'],
      ['security_events','created_at',180,'DAY'],
      ['logs_integracao','criado_em',180,'DAY'],
      ['auditoria_eventos','criado_em',365,'DAY'],
      ['module_health_snapshots','created_at',90,'DAY'],
    ];
    // Auditoria de capacidade 2026-09-14 (achado C-06): fila_integracao era a tabela de MAIOR
    // escrita do sistema e a única sem regra de retenção — crescia sem teto, e itens concluídos há
    // meses continuavam sendo lidos pelos índices em cada reserva de trabalho.
    //
    // A regra é diferente das demais: expurga por idade MAS SOMENTE em estado terminal. Item
    // pendente, em processamento, em retry ou em falha definitiva NUNCA é apagado por idade — seria
    // perder trabalho. fila_morta, fila_reprocessamento_historico e payload_snapshots ficam
    // intactos: são a evidência de que algo deu errado e existem justamente para sobreviver ao item.
    $items = array_merge($items, self::cleanupFilaConcluida());
    $items = array_merge($items, self::cleanupSessoes());

    foreach ($rules as [$table,$col,$n,$unit]) {
      try {
        if (!Database::tableExists($table) || !Database::columnExists($table,$col)) continue;
        $pdo = Database::forTable($table);
        $sql = 'DELETE FROM `'.$table.'` WHERE `'.$col.'` < DATE_SUB(NOW(), INTERVAL '.(int)$n.' '.$unit.') LIMIT 5000';
        $count = $pdo->exec($sql);
        $items[] = ['table'=>$table,'deleted'=>(int)$count,'rule'=>$n.' '.$unit];
      } catch (Throwable $e) { $items[] = ['table'=>$table,'error'=>$e->getMessage()]; }
    }
    return $items;
  }
}
