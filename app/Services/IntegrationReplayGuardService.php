<?php
class IntegrationReplayGuardService {
  public static function ensureSchema(): void {
    SchemaRuntimePolicyService::requireColumns('integration_replay_guard', ['payload_hash','time_bucket','hmac_validated_at'], 'proteção anti-replay');
  }

  private static function safeAlter(PDO $pdo, string $sql): void {
    try { $pdo->exec($sql); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
  }

  public static function guard(string $origem, string $raw, int $windowSeconds = 600, array $context = []): void {
    self::ensureSchema();
    $windowSeconds = max(30, $windowSeconds);
    $body = $raw !== '' ? $raw : json_encode($_POST ?: [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $rota = (string)($context['rota'] ?? ($_GET['page'] ?? $_SERVER['REQUEST_URI'] ?? ''));
    $payloadHash = hash('sha256', $body);
    $requestHash = hash_hmac('sha256', $origem.'|'.$rota.'|'.$payloadHash, self::hashKey());
    $bucket = intdiv(time(), $windowSeconds);
    $pdo = Database::forTable('integration_replay_guard');
    self::cleanup($pdo, $windowSeconds);

    $st = $pdo->prepare("SELECT id, trace_id, request_time FROM integration_replay_guard WHERE origem=? AND request_hash=? AND status='aceito' AND request_time >= DATE_SUB(NOW(), INTERVAL ? SECOND) ORDER BY id DESC LIMIT 1");
    $st->execute([$origem, $requestHash, $windowSeconds]);
    $existing = $st->fetch();
    if ($existing) {
      SecurityEventService::log('integration.replay_block','alto','Reenvio/replay bloqueado em integração '.$origem, [
        'request_hash'=>$requestHash,
        'payload_hash'=>$payloadHash,
        'window_seconds'=>$windowSeconds,
        'previous_trace_id'=>$existing['trace_id'] ?? null,
        'previous_request_time'=>$existing['request_time'] ?? null,
        'rota'=>$rota,
      ]);
      throw new RuntimeException('Requisição duplicada/replay bloqueada pela proteção anti-reenvio.');
    }

    try {
      $pdo->prepare('INSERT INTO integration_replay_guard(origem,rota,request_hash,payload_hash,time_bucket,request_time,trace_id,ip,status,hmac_validated_at) VALUES(?,?,?,?,?,NOW(),?,?,?,NOW())')
        ->execute([$origem,$rota,$requestHash,$payloadHash,$bucket,RequestContext::id(),RequestContext::ip(),'aceito']);
    } catch (PDOException $e) {
      if ((string)$e->getCode() === '23000') {
        SecurityEventService::log('integration.replay_block','alto','Replay bloqueado por chave única de janela em integração '.$origem, ['request_hash'=>$requestHash,'time_bucket'=>$bucket,'rota'=>$rota]);
        throw new RuntimeException('Requisição duplicada/replay bloqueada pela janela de segurança.');
      }
      throw $e;
    }
  }

  private static function cleanup(PDO $pdo, int $windowSeconds): void {
    try {
      $days = max(1, (int)ceil(($windowSeconds * 6) / 86400));
      $pdo->exec('DELETE FROM integration_replay_guard WHERE request_time < DATE_SUB(NOW(), INTERVAL '.$days.' DAY)');
    } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
  }

  private static function hashKey(): string {
    $sec = App::config()['security'] ?? [];
    $key = (string)($sec['integration_replay_hmac_key'] ?? ($sec['audit_daily_signature_key'] ?? ($sec['encryption_key'] ?? '')));
    if ($key !== '') return $key;
    $producaoOuPublico = (class_exists('App') && (App::isProduction() || App::isPublicHost()));
    if ($producaoOuPublico) {
      SecurityEventService::log('integration.replay_key_missing','critico','Chave HMAC do anti-replay não configurada em ambiente público/produção.', [
        'acao_recomendada'=>'Configurar security.integration_replay_hmac_key com valor forte e exclusivo no config.php.'
      ]);
      throw new RuntimeException('Anti-replay bloqueado: chave HMAC não configurada em produção.');
    }
    return 'hub-integration-replay-local-key';
  }
}
