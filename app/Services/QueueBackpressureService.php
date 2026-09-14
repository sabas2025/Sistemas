<?php
/**
 * Auditoria de vazão 2026-09-14 (achado E-05): alarme de contrapressão da fila.
 *
 * A fila do Hub protege bem a INGESTÃO — o webhook enfileira e responde, nada trava na entrada.
 * O risco está do outro lado: se o consumidor drenar mais devagar do que entra, a fila cresce em
 * silêncio. O painel mostra o número de pendentes, mas número exibido só ajuda quem está olhando,
 * e o acúmulo costuma começar de madrugada.
 *
 * Este serviço compara AMOSTRAS SUCESSIVAS do tamanho da fila e alarma quando ela cresce de forma
 * sustentada. O critério é crescimento por N ciclos seguidos, não um limite absoluto: limite
 * absoluto gera alarme falso em pico normal (que a fila existe justamente para absorver) e não
 * pega o vazamento lento, que é o perigoso.
 *
 * O estado fica em arquivo, sob o mesmo lock do AtomicRateCounterService, e NUNCA lança: um alarme
 * que derruba o worker seria pior do que a ausência do alarme.
 */
class QueueBackpressureService {
  /** Ciclos consecutivos de crescimento até alarmar. */
  public const CICLOS_PARA_ALARME = 3;
  /** Amostras guardadas (as mais recentes). */
  private const MAX_AMOSTRAS = 10;

  private static function arquivo(): ?string {
    $dir = dirname(__DIR__, 2).'/storage/cache/security';
    if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) return null;
    return $dir.'/queue-backpressure.json';
  }

  /** @return array{pendentes:int,processando:int,mais_antigo_min:?int} */
  public static function medir(): array {
    $out = ['pendentes' => 0, 'processando' => 0, 'mais_antigo_min' => null];
    try {
      if (!Database::tableExists('fila_integracao')) return $out;
      $pdo = Database::forTable('fila_integracao');
      $st = $pdo->query("SELECT status, COUNT(*) total FROM fila_integracao WHERE status IN ('pendente','processando') GROUP BY status");
      foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $linha) {
        if (($linha['status'] ?? '') === 'pendente')    $out['pendentes']   = (int)$linha['total'];
        if (($linha['status'] ?? '') === 'processando') $out['processando'] = (int)$linha['total'];
      }
      // Idade do item pendente mais antigo: é o sintoma que o operador sente (pedido atrasado).
      $st = $pdo->query("SELECT TIMESTAMPDIFF(MINUTE, MIN(criado_em), NOW()) idade FROM fila_integracao WHERE status='pendente'");
      $idade = $st->fetchColumn();
      $out['mais_antigo_min'] = ($idade === null || $idade === false) ? null : (int)$idade;
    } catch (Throwable $e) {
      if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e);
    }
    return $out;
  }

  /**
   * Registra uma amostra e devolve o veredito.
   *
   * @return array{status:string,pendentes:int,processando:int,mais_antigo_min:?int,ciclos_crescendo:int,mensagem:string}
   */
  public static function avaliar(): array {
    $atual = self::medir();
    $amostras = self::carregar();
    $anterior = $amostras ? $amostras[count($amostras) - 1] : null;

    $ciclos = 0;
    if ($anterior !== null) {
      $cresceu = (int)$atual['pendentes'] > (int)($anterior['pendentes'] ?? 0);
      $ciclos = $cresceu ? (int)($anterior['ciclos'] ?? 0) + 1 : 0;
    }

    $amostras[] = ['t' => time(), 'pendentes' => (int)$atual['pendentes'], 'ciclos' => $ciclos];
    self::salvar(array_slice($amostras, -self::MAX_AMOSTRAS));

    $status = 'ok';
    $msg = 'Fila drenando normalmente.';
    if ($ciclos >= self::CICLOS_PARA_ALARME) {
      $status = 'alerta';
      $msg = 'Fila cresceu em '.$ciclos.' ciclos seguidos ('.$atual['pendentes'].' pendentes). '
           . 'O consumidor não está acompanhando a entrada: aumente o número de processos '
           . 'worker_enterprise em paralelo ou reduza o intervalo do cron.';
    } elseif ($atual['mais_antigo_min'] !== null && $atual['mais_antigo_min'] >= 30) {
      $status = 'alerta';
      $msg = 'Há item pendente parado há '.$atual['mais_antigo_min'].' minutos. '
           . 'Verifique se algum worker está rodando.';
    }

    if ($status === 'alerta') {
      if (class_exists('SecurityHealthService')) {
        SecurityHealthService::degrade('queue_backpressure', $msg, ['pendentes'=>$atual['pendentes'],'ciclos'=>$ciclos]);
      }
      if (class_exists('Audit')) {
        try {
          Audit::event('fila.contrapressao','alerta',[
            'codigo_erro'=>'QUEUE_BACKPRESSURE',
            'mensagem'=>$msg,
            'contexto'=>$atual + ['ciclos_crescendo'=>$ciclos],
            'acao_recomendada'=>'Rode mais processos worker_enterprise em paralelo. Ver workers/README-WORKERS.md.'
          ]);
        } catch (Throwable $e) { if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
      }
    } elseif (class_exists('SecurityHealthService')) {
      SecurityHealthService::healthy('queue_backpressure');
    }

    return $atual + ['status'=>$status, 'ciclos_crescendo'=>$ciclos, 'mensagem'=>$msg];
  }

  /** @return list<array<string,int>> */
  private static function carregar(): array {
    $f = self::arquivo();
    if ($f === null || !is_file($f)) return [];
    $d = json_decode((string)@file_get_contents($f), true);
    return is_array($d) ? $d : [];
  }

  /** @param list<array<string,int>> $amostras */
  private static function salvar(array $amostras): void {
    $f = self::arquivo();
    if ($f === null) return;
    $h = @fopen($f, 'c+');
    if (!is_resource($h)) return;
    try {
      if (!@flock($h, LOCK_EX)) return;
      @ftruncate($h, 0); @rewind($h);
      @fwrite($h, (string)json_encode($amostras, JSON_UNESCAPED_SLASHES));
      @fflush($h); @flock($h, LOCK_UN);
    } finally { @fclose($h); @chmod($f, 0600); }
  }
}
