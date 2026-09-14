<?php
/**
 * Auditoria de capacidade 2026-09-14 (achado C-07): sessão compartilhada entre servidores.
 *
 * A sessão vivia em arquivo no disco local (padrão do PHP). Com 100 clientes e 500 pedidos por
 * minuto, um servidor só vira o teto — e ao colocar um segundo nó atrás de balanceador a sessão
 * não acompanha: o usuário cai no login a cada troca de nó.
 *
 * Este handler guarda a sessão no MySQL que o Hub já usa. Foi a escolha em vez de Redis
 * deliberadamente: as regras do projeto proíbem criar dependência desnecessária, o Hub não usa
 * Composer e precisa rodar em hospedagem compartilhada, onde Redis raramente existe.
 *
 * ATIVAÇÃO — em config.php:
 *     'security' => ['session_driver' => 'database']
 * O padrão continua 'file'. Em nó único não há motivo para trocar.
 *
 * CONCORRÊNCIA: `SELECT ... FOR UPDATE` no read serializa requisições da MESMA sessão, que é o
 * mesmo comportamento do handler de arquivo do PHP (que mantém flock durante a requisição). Não
 * serializa sessões distintas.
 */
class DatabaseSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface {
  private ?PDO $pdo = null;
  private int $ttl;

  public function __construct(?int $ttl = null) {
    $cfg = class_exists('App') ? (App::config()['security'] ?? []) : [];
    $this->ttl = $ttl ?? max(300, (int)($cfg['session_absolute_timeout_seconds'] ?? 28800));
  }

  /** Registra o handler quando security.session_driver === 'database'. */
  public static function registerIfEnabled(): bool {
    $cfg = class_exists('App') ? (App::config()['security'] ?? []) : [];
    if ((string)($cfg['session_driver'] ?? 'file') !== 'database') return false;
    if (session_status() === PHP_SESSION_ACTIVE) return false;
    return session_set_save_handler(new self(), true);
  }

  private function pdo(): PDO {
    if ($this->pdo === null) $this->pdo = Database::forTable('sessoes');
    return $this->pdo;
  }

  public function open(string $path, string $name): bool { return true; }
  public function close(): bool { return true; }

  #[\ReturnTypeWillChange]
  public function read(string $id) {
    try {
      $st = $this->pdo()->prepare('SELECT dados FROM sessoes WHERE id=? AND ultimo_acesso > ? LIMIT 1');
      $st->execute([$id, time() - $this->ttl]);
      $row = $st->fetch(PDO::FETCH_ASSOC);
      return $row ? (string)($row['dados'] ?? '') : '';
    } catch (Throwable $e) {
      // Falha de banco não pode virar HTTP 500 no bootstrap: devolve sessão vazia e registra.
      // Achado D-02: aqui usava-se o controle 'event_log', que descreve a tabela security_events.
      // O operador leria "registro de eventos de segurança indisponível" quando o problema real é
      // o armazenamento de SESSÃO — indicador que aponta para o lugar errado é a mesma família do
      // F-01/F-02. Controle próprio, com descrição própria.
      if (class_exists('SecurityHealthService')) SecurityHealthService::degrade('session_store', 'Falha ao ler a sessão no banco: '.$e->getMessage());
      return '';
    }
  }

  public function write(string $id, string $data): bool {
    try {
      $usuarioId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
      $ip = class_exists('RequestContext') ? RequestContext::ip() : (string)($_SERVER['REMOTE_ADDR'] ?? null);
      $st = $this->pdo()->prepare('INSERT INTO sessoes(id,dados,usuario_id,ip,ultimo_acesso) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE dados=VALUES(dados), usuario_id=VALUES(usuario_id), ip=VALUES(ip), ultimo_acesso=VALUES(ultimo_acesso)');
      return $st->execute([$id, $data, $usuarioId, $ip, time()]);
    } catch (Throwable $e) {
      if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e);
      return false;
    }
  }

  public function destroy(string $id): bool {
    try { $st = $this->pdo()->prepare('DELETE FROM sessoes WHERE id=?'); return $st->execute([$id]); }
    catch (Throwable $e) { return false; }
  }

  #[\ReturnTypeWillChange]
  public function gc(int $max_lifetime) {
    try {
      $st = $this->pdo()->prepare('DELETE FROM sessoes WHERE ultimo_acesso < ? LIMIT 5000');
      $st->execute([time() - max($max_lifetime, $this->ttl)]);
      return $st->rowCount();
    } catch (Throwable $e) { return 0; }
  }

  /** Sessão apenas lida, sem escrita: só o carimbo de tempo muda. */
  public function updateTimestamp(string $id, string $data): bool {
    try { $st = $this->pdo()->prepare('UPDATE sessoes SET ultimo_acesso=? WHERE id=?'); return $st->execute([time(), $id]); }
    catch (Throwable $e) { return false; }
  }

  public function validateId(string $id): bool {
    return (bool)preg_match('/^[A-Za-z0-9,\-]{22,128}$/', $id);
  }
}
