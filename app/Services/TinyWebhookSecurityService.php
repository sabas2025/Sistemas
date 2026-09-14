<?php
class TinyWebhookSecurityService {
  public static function validar(array $config, string $raw, array $payload): void {
    $pdo = Database::forTable('tiny_webhooks');
    $ip = RequestContext::ip() ?? 'cli';
    $headers = TinyWebhookService::normalizarHeaders();
    $maxBytes = max(1024, (int)($config['tiny_webhook_max_bytes'] ?? 1048576));
    $rateLimit = max(1, (int)($config['tiny_webhook_rate_limit'] ?? 60));
    $secret = (string)($config['tiny_webhook_secret'] ?? '');
    $requireSecret = !empty($config['tiny_webhook_exigir_secret']) || (class_exists('App') && App::isProduction());
    $payloadHash = hash('sha256', $raw !== '' ? $raw : json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    $cfgGlobal = class_exists('App') ? App::config() : [];
    // Reauditoria 2026-09-14 (achado A-09): o rate limit abaixo conta linhas de tiny_webhooks,
    // que só são gravadas DEPOIS do tratamento bem-sucedido - tentativas com secret inválido
    // nunca alimentavam o contador, deixando o segredo estático exposto a força bruta. Este
    // contador é de TENTATIVAS, avaliado antes de qualquer autenticação e sem depender do banco
    // (que, indisponível, também derrubaria o limite global - ver A-06).
    if ($ip !== '' && $ip !== 'cli') {
      // Melhoria 7: política declarada em RateLimitService::POLICIES['tiny_webhook'].
      // Achado B-01: aqui a política é FECHAR. Um canal de webhook sem limite de tentativas é
      // exatamente o risco do A-09; se o contador não funciona, recusar é preferível a abrir
      // brute force contra o segredo estático - e a recusa aparece rápido para o operador.
      $tentativas = RateLimitService::hit('tiny_webhook', $ip, $rateLimit);
      if ($tentativas['degraded']) {
        self::auditBlock('TINY_WEBHOOK_RATE_COUNTER_DEGRADED', 'Contador de tentativas indisponível (storage/cache/security inacessível); webhook recusado por precaução.', $headers, $payloadHash, ['ip'=>$ip]);
        throw new RuntimeException('Webhook Tiny bloqueado: limitador de tentativas indisponível.');
      }
      if ($tentativas['limited']) {
        self::auditBlock('TINY_WEBHOOK_ATTEMPT_RATE_LIMIT', 'Excesso de tentativas de webhook Tiny/Olist por minuto (contadas antes da autenticação).', $headers, $payloadHash, ['ip'=>$ip,'tentativas'=>$tentativas['count'],'limite'=>$rateLimit]);
        throw new RuntimeException('Webhook Tiny bloqueado: excesso de tentativas.');
      }
    }

    $allowedIps = array_filter(array_map('trim', explode(',', (string)($cfgGlobal['security']['tiny_allowed_ips'] ?? $config['tiny_allowed_ips'] ?? ''))));
    if ($allowedIps && $ip !== '' && !in_array($ip, $allowedIps, true)) {
      self::auditBlock('TINY_WEBHOOK_IP_NOT_ALLOWED', 'IP do webhook Tiny/Olist não está autorizado.', $headers, $payloadHash, ['ip'=>$ip,'allowed_ips'=>$allowedIps]);
      throw new RuntimeException('Webhook Tiny bloqueado: IP não permitido.');
    }

    if ($maxBytes > 0 && strlen($raw) > $maxBytes) {
      self::auditBlock('TINY_WEBHOOK_PAYLOAD_TOO_LARGE', 'Payload Tiny/Olist acima do limite configurado.', $headers, $payloadHash);
      throw new RuntimeException('Webhook Tiny bloqueado: payload muito grande.');
    }

    if ($rateLimit > 0 && $ip !== '') {
      $st = $pdo->prepare("SELECT COUNT(*) c FROM tiny_webhooks WHERE headers LIKE ? AND criado_em >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
      try { $st->execute(['%"remote-addr":"'.$ip.'"%']); $count = (int)($st->fetch()['c'] ?? 0); }
      catch (Throwable $e) { $count = 0; }
      if ($count >= $rateLimit) {
        self::auditBlock('TINY_WEBHOOK_RATE_LIMIT', 'Tiny/Olist excedeu o limite de webhooks por minuto.', $headers, $payloadHash);
        throw new RuntimeException('Webhook Tiny bloqueado: rate limit excedido.');
      }
    }

    $cnpjs = array_filter(array_map(fn($v)=>preg_replace('/\D+/', '', trim($v)), explode(',', (string)($config['tiny_webhook_cnpj_autorizados'] ?? ''))));
    if ($cnpjs) {
      $dados = TinyWebhookService::dados($payload);
      $cnpj = preg_replace('/\D+/', '', (string)($payload['cnpj'] ?? $dados['cnpj'] ?? ''));
      if ($cnpj === '' || !in_array($cnpj, $cnpjs, true)) {
        self::auditBlock('TINY_WEBHOOK_CNPJ_NOT_ALLOWED', 'CNPJ do webhook Tiny/Olist não está autorizado.', $headers, $payloadHash, ['cnpj_recebido'=>$cnpj, 'cnpjs_autorizados'=>$cnpjs]);
        throw new RuntimeException('Webhook Tiny bloqueado: CNPJ não autorizado.');
      }
    }

    if ($requireSecret) {
      $received = (string)($headers['x-tiny-hub-secret'] ?? $headers['x-hub-secret'] ?? '');
      if ($secret === '' || $received === '' || !hash_equals($secret, $received)) {
        self::auditBlock('TINY_WEBHOOK_SECRET_INVALID', 'Secret do webhook Tiny/Olist inválido ou ausente.', $headers, $payloadHash);
        throw new RuntimeException('Webhook Tiny bloqueado: secret inválido ou ausente.');
      }
    }
  }

  private static function auditBlock(string $codigo, string $mensagem, array $headers, string $hash, array $extra=[]): void {
    // P0-06: idem - a auditoria não pode reter Authorization/X-TINY-HUB-SECRET em claro.
    $headersSeguro = class_exists('SensitiveHeaderRedactor') ? SensitiveHeaderRedactor::redactForStorage($headers) : [];
    Audit::event('tiny.webhook.seguranca_bloqueio','erro',[
      'codigo_erro'=>$codigo,
      'mensagem'=>$mensagem,
      'causa_provavel'=>'Configuração de segurança dos webhooks Tiny/Olist não foi atendida.',
      'acao_recomendada'=>'Verifique Configurações → Segurança dos Webhooks Tiny/Olist e confira headers, CNPJ autorizado, limite de payload e rate limit.',
      'contexto'=>array_merge(['headers'=>$headersSeguro,'hash_payload'=>$hash], $extra)
    ]);
    NotificationService::erroIntegracao('Webhook Tiny bloqueado', $mensagem, ['trace_id'=>RequestContext::id(),'codigo_erro'=>$codigo]);
  }
}
