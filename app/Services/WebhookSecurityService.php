<?php
class WebhookSecurityService {
  private static function headers(): array {
    $raw = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
    $headers = [];
    foreach ($raw as $k => $v) {
      $headers[strtolower(str_replace('_','-', (string)$k))] = $v;
    }
    foreach ($_SERVER as $k => $v) {
      if (str_starts_with($k, 'HTTP_')) {
        $name = strtolower(str_replace('_','-', substr($k, 5)));
        $headers[$name] = $v;
      }
    }
    return $headers;
  }

  /**
   * Caminho canônico assinado na v2 (A-08): usa a rota lógica (?page=...) quando presente,
   * porque o mesmo script de entrada atende todos os webhooks. Sem query string - qualquer
   * identificador que precise ser autenticado deve viajar no corpo assinado.
   */
  private static function canonicalPath(): string {
    $page = (string)($_GET['page'] ?? '');
    if ($page !== '') return '/'.ltrim($page, '/');
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    $path = (string)(parse_url($uri, PHP_URL_PATH) ?: '/');
    return $path;
  }

  public static function validar(array $config, string $raw): void {
    $cfg = App::config();
    $pdo = Database::forTable('webhook_requisicoes');
    $ip = RequestContext::ip() ?? '';
    $headers = self::headers();
    $ambiente = $config['ambiente'] ?? 'homologacao';
    $secret = (string)($config['webhook_secret'] ?? '');
    if ($secret === '') $secret = (string)($cfg['security']['webhook_secret'] ?? '');
    $secret = CryptoService::decrypt($secret);
    $signature = (string)($headers['x-hub-signature'] ?? '');
    $timestamp = (string)($headers['x-hub-timestamp'] ?? '');
    $nonce = (string)($headers['x-hub-nonce'] ?? '');
    $simpleSecret = (string)($headers['x-hub-secret'] ?? '');
    $payloadHash = hash('sha256', $raw);
    $maxBytes = (int)($cfg['security']['webhook_max_bytes'] ?? 1048576);
    $rateLimit = (int)($cfg['security']['webhook_rate_limit_per_minute'] ?? 60);
    $replayWindow = (int)($cfg['security']['integration_anti_replay_window_seconds'] ?? 600);

    $registrar = function(string $status, string $msg) use ($pdo,$ip,$signature,$timestamp,$nonce,$payloadHash,$headers) {
      $nonceDb = trim((string)$nonce) === '' ? null : trim((string)$nonce);
      // P0-06: nunca persistir headers crus (podem conter Cookie/Authorization/X-HUB-SECRET).
      $headersSeguro = class_exists('SensitiveHeaderRedactor') ? SensitiveHeaderRedactor::redactForStorage($headers) : [];
      $pdo->prepare('INSERT IGNORE INTO webhook_requisicoes(trace_id,origem_ip,assinatura,timestamp_cliente,nonce,payload_hash,status,mensagem,headers) VALUES(?,?,?,?,?,?,?,?,?)')
        ->execute([RequestContext::id(),$ip,$signature,$timestamp,$nonceDb,$payloadHash,$status,$msg,json_encode($headersSeguro,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
    };

    if ($rateLimit > 0 && $ip !== '') {
      $stRate = $pdo->prepare("SELECT COUNT(*) c FROM webhook_requisicoes WHERE origem_ip=? AND criado_em >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
      $stRate->execute([$ip]);
      if ((int)($stRate->fetch()['c'] ?? 0) >= $rateLimit) {
        $registrar('bloqueado','Limite de requisições por minuto excedido');
        throw new RuntimeException('Webhook bloqueado: muitas requisições no último minuto.');
      }
    }

    if ($maxBytes > 0 && strlen($raw) > $maxBytes) {
      $registrar('bloqueado','Payload acima do limite permitido');
      throw new RuntimeException('Webhook bloqueado: payload muito grande.');
    }

    $allowedIps = array_filter(array_map('trim', explode(',', (string)($cfg['security']['vsm_allowed_ips'] ?? ''))));
    if ($allowedIps && !in_array($ip, $allowedIps, true)) {
      $registrar('bloqueado','IP não permitido');
      throw new RuntimeException('Webhook bloqueado: IP não permitido.');
    }

    $window = (int)($cfg['security']['webhook_hmac_window_seconds'] ?? 300);
    $hostPublico = class_exists('App') ? App::isPublicHost() : false;
    $appProducao = class_exists('App') ? App::isProduction() : false;
    $requireHmac = (bool)($cfg['security']['require_hmac_in_production'] ?? true) && ($ambiente === 'producao' || $hostPublico || $appProducao);
    $okHmac = false;

    if (trim($nonce) !== '') {
      $stNonce = $pdo->prepare('SELECT COUNT(*) c FROM webhook_requisicoes WHERE nonce=?');
      $stNonce->execute([$nonce]);
      if ((int)($stNonce->fetch()['c'] ?? 0) > 0) {
        $registrar('bloqueado','Nonce já utilizado: possível replay attack');
        throw new RuntimeException('Webhook bloqueado: nonce já utilizado.');
      }
    }

    // Reauditoria 2026-09-14 (achado A-08): a assinatura v1 cobria apenas timestamp, nonce e
    // corpo - nunca o método HTTP nem a rota. Uma assinatura válida capturada para uma rota
    // podia ser reapresentada em outra, e identificadores em query string ficavam fora do que
    // é autenticado. A v2 canoniza versão, método e caminho junto do timestamp, nonce e hash
    // do corpo. A v1 continua aceita por compatibilidade com a VSM atual; ative
    // security.webhook_signature_require_v2 assim que o outro lado passar a assinar v2.
    $requireV2 = (bool)($cfg['security']['webhook_signature_require_v2'] ?? false);
    $metodo = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? ''));
    $rota = self::canonicalPath();
    $versaoAceita = '';

    if ($secret !== '' && $signature !== '' && ctype_digit($timestamp) && $nonce !== '') {
      if (abs(time() - (int)$timestamp) <= $window) {
        $baseV2 = 'v2:'.$metodo.':'.$rota.':'.$timestamp.':'.$nonce.':'.$payloadHash;
        if (hash_equals('sha256='.hash_hmac('sha256', $baseV2, $secret), $signature)) {
          $okHmac = true; $versaoAceita = 'v2';
        } elseif (!$requireV2 && hash_equals('sha256='.hash_hmac('sha256', $timestamp.'.'.$nonce.'.'.$raw, $secret), $signature)) {
          $okHmac = true; $versaoAceita = 'v1';
        }
      }
    }

    if ($okHmac) {
      if ($replayWindow > 0) IntegrationReplayGuardService::guard('vsm', $metodo.':'.$rota.':'.$raw, $replayWindow, ['auth'=>'hmac','assinatura'=>$versaoAceita]);
      $registrar('aceito','HMAC '.$versaoAceita.' aceito'.($versaoAceita === 'v1' ? ' (formato legado: não cobre método/rota; migre a VSM para v2)' : ''));
      // Melhoria 2 da seção 8: aceitar v1 é uma concessão temporária de compatibilidade, e antes
      // ela era silenciosa - só aparecia no texto de um registro que ninguém lê rotineiramente.
      // Agora vira controle degradado visível no painel e em api/status, para que a migração da
      // VSM tenha dono e prazo em vez de virar um "false" permanente no config.
      if ($versaoAceita === 'v1') {
        if (class_exists('SecurityHealthService')) {
          SecurityHealthService::degrade('webhook_signature_v1',
            'Webhook VSM aceito com assinatura v1 (não cobre método nem rota). Migre a VSM para v2 e ligue security.webhook_signature_require_v2.',
            ['rota'=>$rota,'metodo'=>$metodo]);
        }
        if (class_exists('SecurityEventService')) {
          try {
            SecurityEventService::log('webhook.assinatura_v1','medio','Webhook aceito com assinatura legada v1.',['rota'=>$rota,'metodo'=>$metodo]);
          } catch (Throwable $e) { if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
        }
      }
      return;
    }

    if (!$requireHmac && !$hostPublico && !$appProducao && $secret !== '' && hash_equals($secret, $simpleSecret)) {
      if ($replayWindow > 0) IntegrationReplayGuardService::guard('vsm', $raw, $replayWindow, ['auth'=>'simple_secret']);
      $registrar('aceito','Secret simples aceito somente em ambiente local/homologação interna');
      return;
    }

    $registrar('bloqueado',$requireHmac ? 'HMAC obrigatório inválido ou ausente' : 'Secret/HMAC inválido');
    throw new RuntimeException('Webhook não autorizado. Use HMAC com X-HUB-TIMESTAMP, X-HUB-NONCE e X-HUB-SIGNATURE.');
  }
}
