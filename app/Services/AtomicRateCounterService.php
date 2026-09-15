<?php
/**
 * Reauditoria 2026-09-14 (achados A-06 e A-07): contador de janela deslizante atômico e
 * independente do banco.
 *
 * A-06: RouteRateLimiterService capturava qualquer Throwable e deixava a requisição passar,
 * então uma indisponibilidade de banco/schema removia a limitação de TODAS as rotas, inclusive
 * as públicas (login, webhooks, api/csp-report). Agora existe um limite que continua valendo
 * mesmo sem banco.
 *
 * A-07: public/pwa_telemetry.php fazia ler -> incrementar -> escrever com LOCK_EX apenas na
 * escrita, o que não é atômico: duas requisições simultâneas liam o mesmo valor e o limite era
 * ultrapassado por corrida. Aqui a leitura e a escrita acontecem sob o MESMO lock exclusivo,
 * com abertura 'c+' (cria sem truncar), então o incremento é indivisível.
 *
 * Também aplica retenção: cada chave guarda no máximo os eventos da própria janela, e arquivos
 * de chaves ociosas são removidos oportunisticamente para não crescer sem limite.
 */
class AtomicRateCounterService {
  private const PREFIX = 'hub_rate_v1_';

  /**
   * Registra um evento na chave e devolve o estado da janela.
   *
   * Reauditoria 2026-09-14 (achado B-01, encontrado na revisão desta própria correção): as três
   * saídas de falha de armazenamento devolviam limited=false, reproduzindo o fail-open do A-06
   * dentro do serviço criado justamente para eliminá-lo. Se storage/cache/security ficasse
   * inacessível (permissão, disco cheio), o limite sumia em silêncio. Agora a falha é explícita
   * em 'degraded' e cada chamador decide sua política - não existe resposta segura única:
   * bloquear tudo por causa de um diretório sem permissão seria auto-DoS em rota de painel,
   * enquanto liberar tentativas ilimitadas num webhook é justamente o risco a evitar.
   *
   * @return array{count:int,limited:bool,degraded:bool}
   */
  public static function hit(string $key, int $windowSeconds, int $limit): array {
    $windowSeconds = max(1, $windowSeconds);
    $limit = max(1, $limit);
    $degraded = ['count' => 0, 'limited' => false, 'degraded' => true];
    $file = self::path($key);
    if ($file === null) return $degraded;

    $handle = @fopen($file, 'c+');
    if (!is_resource($handle)) return $degraded;
    try {
      if (!@flock($handle, LOCK_EX)) return $degraded;
      // Leitura e escrita sob o mesmo lock: é isto que torna o incremento atômico.
      $raw = stream_get_contents($handle);
      $events = json_decode((string)$raw, true);
      $cut = time() - $windowSeconds;
      $events = is_array($events)
        ? array_values(array_filter(array_map('intval', $events), static fn(int $t): bool => $t >= $cut))
        : [];
      $events[] = time();
      // Teto de memória por chave: não adianta guardar mais eventos do que o limite avaliado.
      if (count($events) > ($limit + 1)) $events = array_slice($events, -($limit + 1));
      @ftruncate($handle, 0);
      @rewind($handle);
      @fwrite($handle, (string)json_encode($events, JSON_UNESCAPED_SLASHES));
      @fflush($handle);
      @flock($handle, LOCK_UN);
      @chmod($file, 0600);
      self::collectGarbage();
      return ['count' => count($events), 'limited' => count($events) > $limit, 'degraded' => false];
    } finally {
      @fclose($handle);
    }
  }

  /**
   * Lê a janela sem registrar tentativa (melhoria 7: a fachada RateLimitService precisa consultar
   * sem contar, por exemplo para exibir o estado num painel).
   *
   * @return array{count:int,degraded:bool}
   */
  public static function peek(string $key, int $windowSeconds): array {
    $windowSeconds = max(1, $windowSeconds);
    $file = self::path($key);
    if ($file === null) return ['count' => 0, 'degraded' => true];
    if (!is_file($file)) return ['count' => 0, 'degraded' => false];
    $handle = @fopen($file, 'r');
    if (!is_resource($handle)) return ['count' => 0, 'degraded' => true];
    try {
      if (!@flock($handle, LOCK_SH)) return ['count' => 0, 'degraded' => true];
      $raw = stream_get_contents($handle);
      @flock($handle, LOCK_UN);
      $events = json_decode((string)$raw, true);
      $cut = time() - $windowSeconds;
      $events = is_array($events)
        ? array_filter(array_map('intval', $events), static fn(int $t): bool => $t >= $cut)
        : [];
      return ['count' => count($events), 'degraded' => false];
    } finally {
      @fclose($handle);
    }
  }

  /** Zera a janela de uma chave (melhoria 7: login bem-sucedido limpa as tentativas anteriores). */
  public static function reset(string $key): void {
    $file = self::path($key);
    if ($file === null || !is_file($file)) return;
    @unlink($file);
  }

  private static function path(string $key): ?string {
    $dir = __DIR__.'/../../storage/cache/security';
    if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) return null;
    return $dir.'/'.self::PREFIX.hash('sha256', $key).'.json';
  }

  /** Remove contadores ociosos, evitando crescimento ilimitado de arquivos (A-07). */
  private static function collectGarbage(): void {
    if (random_int(1, 200) !== 1) return;
    $dir = __DIR__.'/../../storage/cache/security';
    $cut = time() - 7200;
    foreach (glob($dir.'/'.self::PREFIX.'*.json') ?: [] as $stale) {
      if (@filemtime($stale) < $cut) @unlink($stale);
    }
  }
}
