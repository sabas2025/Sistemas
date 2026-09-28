<?php
/**
 * EH-1 (auditoria de escala horizontal, 2026-09-28) — lock nomeado para jobs de tempo.
 *
 * Garante execução lógica única de um worker agendado mesmo se o cron disparar duas vezes, se o
 * operador rodar manualmente em paralelo ou, no futuro, se houver 2 nós atrás de um balanceador.
 * Reutiliza o padrão já provado em TinyV3TokenService::acquireRefreshLock e VsmTokenService::
 * acquireLock: GET_LOCK do MySQL com fallback para flock de arquivo em hospedagens que bloqueiam
 * GET_LOCK. Não cria mecanismo paralelo — centraliza o que já existia disperso.
 *
 * NÃO altera o comportamento de execução única: rodando sozinho, o worker adquire o lock e segue
 * normalmente. O ganho é que um SEGUNDO processo concorrente PULA limpo (o chamador sai 0) em vez
 * de correr até o lock de serviço e logar falha. Timeout padrão 0 = não espera (comportamento certo
 * para scheduler: se já há execução, pule agora).
 *
 * O nome do lock é escopado por instalação (hash de db|base_url|raiz), para não colidir entre
 * instalações no mesmo servidor MySQL. GET_LOCK aceita até 64 caracteres.
 */
class SchedulerLockService {
  /** @var array<string, array{driver:string, full?:string, pdo?:PDO, handle?:resource}> */
  private static array $held = [];

  private static function lockName(string $name): string {
    $cfg = class_exists('App') ? App::config() : [];
    $instance = trim((string)($cfg['installation_id'] ?? ''));
    if ($instance === '') {
      $db = (string)($cfg['db']['name'] ?? 'hub');
      $base = (string)($cfg['base_url'] ?? '');
      $instance = hash('sha256', $db.'|'.$base.'|'.(string)realpath(__DIR__.'/../..'));
    }
    return 'hub_sched_'.substr(hash('sha256', $instance.'|'.$name), 0, 50);
  }

  /**
   * Tenta adquirir o lock sem esperar (por padrão). Retorna true se adquiriu (ou se já é dono no
   * mesmo processo), false se outro processo o detém ou o mecanismo está indisponível.
   */
  public static function acquire(string $name, int $timeoutSeconds = 0): bool {
    if (isset(self::$held[$name])) return true; // reentrante no mesmo processo
    $full = self::lockName($name);
    $timeout = max(0, min(30, $timeoutSeconds));
    try {
      $pdo = Database::getConnection();
      $st = $pdo->prepare('SELECT GET_LOCK(?, ?)');
      $st->execute([$full, $timeout]);
      $value = $st->fetchColumn();
      if ((int)$value === 1) { self::$held[$name] = ['driver'=>'mysql','full'=>$full,'pdo'=>$pdo]; return true; }
      if ((string)$value === '0' || $value === 0) return false; // ocupado por outro processo
      throw new RuntimeException('GET_LOCK retornou valor indisponível.');
    } catch (Throwable $mysqlError) {
      // Fallback local para hospedagens que bloqueiam GET_LOCK.
      $dir = __DIR__.'/../../storage/locks';
      if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) return false;
      $path = $dir.'/'.preg_replace('/[^a-z0-9_-]/i', '_', $full).'.lock';
      $handle = @fopen($path, 'c+');
      if (!is_resource($handle)) return false;
      if (@flock($handle, LOCK_EX | LOCK_NB)) {
        @ftruncate($handle, 0);
        @fwrite($handle, json_encode(['pid'=>getmypid(),'name'=>$name,'at'=>date('c')], JSON_UNESCAPED_SLASHES));
        self::$held[$name] = ['driver'=>'file','handle'=>$handle];
        return true;
      }
      @fclose($handle);
      return false;
    }
  }

  /** Libera o lock. Chamada explícita é opcional (o fim do script libera GET_LOCK e o flock), mas é limpa. */
  public static function release(string $name): void {
    if (!isset(self::$held[$name])) return;
    $lock = self::$held[$name];
    unset(self::$held[$name]);
    try {
      if (($lock['driver'] ?? '') === 'mysql' && isset($lock['pdo'], $lock['full'])) {
        $st = $lock['pdo']->prepare('SELECT RELEASE_LOCK(?)');
        $st->execute([(string)$lock['full']]);
      } elseif (($lock['driver'] ?? '') === 'file' && isset($lock['handle']) && is_resource($lock['handle'])) {
        @flock($lock['handle'], LOCK_UN);
        @fclose($lock['handle']);
      }
    } catch (Throwable $e) {
      if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e, ['lock'=>$name]);
    }
  }

  /**
   * Envolve um job de tempo: adquire, executa e libera. Se já houver execução, NÃO roda e devolve
   * ['ran'=>false,'skipped'=>true]. Erros do job propagam (o lock é liberado antes).
   * @return array{ran:bool, skipped?:bool, result?:mixed}
   */
  public static function run(string $name, callable $job, int $timeoutSeconds = 0): array {
    if (!self::acquire($name, $timeoutSeconds)) return ['ran'=>false, 'skipped'=>true];
    try {
      $result = $job();
      return ['ran'=>true, 'result'=>$result];
    } finally {
      self::release($name);
    }
  }
}
