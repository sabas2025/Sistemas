<?php
class IpBlockService {
  public static function enforce(): void {
    if (PHP_SAPI === 'cli') return;
    $ip = class_exists('SecurityEventService') ? SecurityEventService::ip() : (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    try {
      // Schema/DB indisponível não pode escapar do bootstrap como HTTP 500 genérico.
      SecurityEventService::ensureSchema();
      $pdo = Database::getConnection();
      // Auditoria de capacidade 2026-09-14 (achado C-08): esta consulta rodava em TODA requisição,
      // antes de qualquer lógica. Com 100 clientes navegando, é uma ida ao banco por clique só para
      // descobrir que o IP não está bloqueado — que é a resposta em ~100% dos casos.
      //
      // O resultado negativo passa a ser memorizado por 60 segundos no contador atômico em disco.
      // Só o NEGATIVO é memorizado: um bloqueio recém-criado passa a valer na próxima janela, no
      // pior caso 60 segundos depois. Nunca o contrário — um IP bloqueado jamais é liberado por
      // cache, porque o caminho de bloqueio invalida a entrada explicitamente.
      if (self::negativoEmCache($ip)) return;
      $st = $pdo->prepare("SELECT * FROM ips_bloqueados WHERE ip=? AND ativo=1 AND (bloqueado_ate IS NULL OR bloqueado_ate > NOW()) LIMIT 1");
      $st->execute([$ip]);
      if ($st->fetch()) {
        self::limparCache($ip); // bloqueado: nunca deixar resquício de "liberado" em cache
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('Acesso bloqueado por segurança.');
      }
      self::marcarNegativo($ip);
    } catch (Throwable $e) {
      SecurityHealthService::degrade('ip_block', 'Falha ao consultar ips_bloqueados: '.$e->getMessage(), ['ip'=>$ip]);
      if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e, ['ip'=>$ip]);
      else error_log('IpBlock enforce degradado: '.$e->getMessage());
    }
  }

  public static function block(string $ip, string $motivo, int $minutes=60, string $severity='alto'): void {
    try {
      SecurityEventService::ensureSchema();
      $pdo = Database::getConnection();
      $until = $minutes > 0 ? date('Y-m-d H:i:s', time()+($minutes*60)) : null;
      $st = $pdo->prepare("INSERT INTO ips_bloqueados(ip,motivo,severidade,bloqueado_ate,ativo,updated_at) VALUES (?,?,?,?,1,NOW()) ON DUPLICATE KEY UPDATE motivo=VALUES(motivo), severidade=VALUES(severidade), bloqueado_ate=VALUES(bloqueado_ate), ativo=1, updated_at=NOW()");
      $st->execute([$ip, $motivo, $severity, $until]);
      // Achado C-08: invalida o "liberado" memorizado, para o bloqueio valer na requisição seguinte
      // e não só quando a janela de cache expirar.
      self::limparCache($ip);
      SecurityEventService::log('ip.bloqueado', $severity, $motivo, ['ip'=>$ip, 'ate'=>$until]);
    } catch (Throwable $e) {
      if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e, ['ip'=>$ip,'motivo'=>$motivo]);
      else error_log('IpBlock block degradado: '.$e->getMessage());
    }
  }

  // ===================================================================================
  // Cache do resultado NEGATIVO (achado C-08)
  // ===================================================================================
  //
  // Memoriza apenas "este IP não está bloqueado", por 60 segundos, em arquivo local. Guardar o
  // positivo seria arriscado (um desbloqueio demoraria a valer); guardar o negativo tem risco
  // limitado e conhecido: um bloqueio novo passa a valer em até 60 segundos, e block() invalida a
  // entrada na hora, então na prática o atraso só existe se o bloqueio vier de outro processo.
  //
  // Falha de armazenamento NUNCA libera nem bloqueia: apenas devolve false e a consulta ao banco
  // acontece normalmente, como antes desta otimização.

  private const CACHE_SEGUNDOS = 60;

  private static function arquivoCache(string $ip): ?string {
    $dir = dirname(__DIR__, 2).'/storage/cache/security';
    if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) return null;
    return $dir.'/hub_ipok_v1_'.hash('sha256', $ip).'.json';
  }

  private static function negativoEmCache(string $ip): bool {
    $f = self::arquivoCache($ip);
    if ($f === null || !is_file($f)) return false;
    $mtime = @filemtime($f);
    if ($mtime === false) return false;
    if ($mtime < time() - self::CACHE_SEGUNDOS) { @unlink($f); return false; }
    return true;
  }

  private static function marcarNegativo(string $ip): void {
    $f = self::arquivoCache($ip);
    if ($f === null) return;
    @file_put_contents($f, '1');
    @chmod($f, 0600);
    // Coleta oportunista: sem isto o diretório acumularia um arquivo por IP visitante.
    if (random_int(1, 500) === 1) {
      $dir = dirname($f);
      $corte = time() - self::CACHE_SEGUNDOS;
      foreach (glob($dir.'/hub_ipok_v1_*.json') ?: [] as $antigo) {
        if (@filemtime($antigo) < $corte) @unlink($antigo);
      }
    }
  }

  private static function limparCache(string $ip): void {
    $f = self::arquivoCache($ip);
    if ($f !== null && is_file($f)) @unlink($f);
  }
}
