<?php
class AuthRepository {
  public static function pdo(): PDO { return Database::forTable('usuarios'); }

  public static function userByEmail(string $email): ?array {
    $st = self::pdo()->prepare('SELECT * FROM usuarios WHERE email=? AND ativo=1 LIMIT 1');
    $st->execute([$email]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    return is_array($u) ? $u : null;
  }

  public static function userById(int $id): ?array {
    $st = self::pdo()->prepare('SELECT * FROM usuarios WHERE id=? AND ativo=1 LIMIT 1');
    $st->execute([$id]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    return is_array($u) ? $u : null;
  }

  public static function sessionVersion(int $id): int {
    if (!Database::columnExists('usuarios','session_version')) return 0;
    $st = self::pdo()->prepare('SELECT session_version FROM usuarios WHERE id=? LIMIT 1');
    $st->execute([$id]);
    return (int)($st->fetch()['session_version'] ?? 0);
  }

  public static function countFailedByIp(string $ip, string $intervalSql): int {
    $st = self::pdo()->prepare("SELECT COUNT(*) c FROM login_tentativas WHERE ip=? AND sucesso=0 AND criado_em >= DATE_SUB(NOW(), INTERVAL {$intervalSql})");
    $st->execute([$ip]);
    return (int)($st->fetch()['c'] ?? 0);
  }

  public static function countFailedByEmail(string $email, string $intervalSql): int {
    $st = self::pdo()->prepare("SELECT COUNT(*) c FROM login_tentativas WHERE email=? AND sucesso=0 AND criado_em >= DATE_SUB(NOW(), INTERVAL {$intervalSql})");
    $st->execute([$email]);
    return (int)($st->fetch()['c'] ?? 0);
  }

  public static function insertAttempt(string $email, ?string $ip, int $success, string $message): void {
    try {
      self::pdo()->prepare('INSERT INTO login_tentativas(email,ip,sucesso,mensagem) VALUES(?,?,?,?)')->execute([$email,$ip,$success,$message]);
    } catch (Throwable $e) {
      // Registrar a tentativa é best-effort: uma falha parcial do banco não pode
      // transformar credenciais inválidas em erro 500 nem desativar o rate limit.
      if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e, ['email_hash'=>hash('sha256', strtolower(trim($email))), 'ip'=>$ip]);
    } finally {
      if (class_exists('LoginRateLimitFallbackService')) LoginRateLimitFallbackService::record($email,$ip,$success===1);
    }
  }

  public static function save2faSecret(int $id, string $encryptedSecret, bool $enable=true): void {
    if ($enable) {
      self::pdo()->prepare('UPDATE usuarios SET two_factor_enabled=1, two_factor_secret=?, two_factor_created_at=COALESCE(two_factor_created_at,NOW()) WHERE id=?')->execute([$encryptedSecret,$id]);
    } else {
      self::pdo()->prepare('UPDATE usuarios SET two_factor_secret=?, two_factor_created_at=COALESCE(two_factor_created_at,NOW()) WHERE id=?')->execute([$encryptedSecret,$id]);
    }
  }

  public static function mark2faVerified(int $id): void {
    self::pdo()->prepare('UPDATE usuarios SET two_factor_last_verified_at=NOW(), two_factor_enabled=1 WHERE id=?')->execute([$id]);
  }

  public static function markSuccess(int $id): void {
    self::pdo()->prepare('UPDATE usuarios SET ultimo_login=NOW(), tentativas_login=0, bloqueado_ate=NULL WHERE id=?')->execute([$id]);
  }

  public static function markFailure(array $u, int $maxTentativas, int $lockSeconds): void {
    $tentativas = (int)($u['tentativas_login'] ?? 0) + 1;
    $bloqueadoAte = $tentativas >= $maxTentativas ? date('Y-m-d H:i:s', time()+$lockSeconds) : null;
    self::pdo()->prepare('UPDATE usuarios SET tentativas_login=?, bloqueado_ate=? WHERE id=?')->execute([$tentativas,$bloqueadoAte,$u['id']]);
  }
}
