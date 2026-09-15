<?php
/**
 * Correção do achado P0-01 da reauditoria de 2026-08-23, entregue na V104.49.3-R6.
 *
 * Problema original: o "state" do OAuth Tiny V3 era o token CSRF genérico da sessão,
 * gerado em views/tiny_v3_ficha.php, mas o callback (tinyV3Callback) apenas lia
 * $_GET['state'] e NUNCA comparava com nada - ou seja, não havia validação real de
 * state, o que expõe o fluxo a OAuth login-CSRF (um invalidCode/state de um atacante
 * pode ser aceito). Além disso, o cookie de sessão é SameSite=Strict, que o navegador
 * NÃO envia na navegação de retorno vinda de um domínio externo (o servidor OAuth do
 * Tiny), então depender da sessão para validar o retorno é frágil.
 *
 * Esta classe resolve isso com um mecanismo que não depende da sessão principal:
 * - Gera state aleatório de 256 bits e um code_verifier/code_challenge PKCE S256.
 * - Grava os dois em um cookie próprio, assinado (HMAC-SHA256) e cifrado por conteúdo
 *   auto-verificável, com SameSite=Lax (sobrevive ao redirect cross-site) e TTL curto -
 *   nunca na sessão principal (que continua Strict para todo o resto do app).
 * - Usa a tabela integration_replay_guard, que já existe no schema, para garantir
 *   USO ÚNICO do state (a segunda tentativa de consumir o mesmo state é bloqueada).
 * - Não requer nenhuma migração de schema nova.
 */
class OAuthStateService {
  private const COOKIE_PREFIX = 'hub_oauth_';
  private const TTL_SECONDS = 600;

  /**
   * Inicia o fluxo OAuth para um provedor (ex.: 'tiny_v3').
   * Deve ser chamado ANTES de qualquer saída HTML (setcookie exige headers ainda não enviados).
   * @return array{state:string, code_challenge:string}
   */
  public static function start(string $provider): array {
    $state = bin2hex(random_bytes(32));
    $verifier = self::base64UrlEncode(random_bytes(64));
    $challenge = self::base64UrlEncode(hash('sha256', $verifier, true));
    $expires = time() + self::TTL_SECONDS;
    // Reauditoria 2026-09-14 (achado A-02): o retorno do provedor OAuth é uma navegação
    // cross-site, em que o navegador NÃO envia o cookie de sessão SameSite=Strict. Por isso
    // o callback não pode depender da sessão para saber quem é o usuário nem para autorizar.
    // A identidade e o perfil de quem iniciou o fluxo passam a viajar dentro da própria
    // transação assinada, que já é de uso único e expirável.
    $user = class_exists('Auth') ? (Auth::user() ?? []) : [];
    $payload = [
      'provider' => $provider,
      'state' => $state,
      'verifier' => $verifier,
      'expires' => $expires,
      'user_id' => (int)($user['id'] ?? 0),
      'perfil' => (string)($user['perfil'] ?? ''),
    ];
    $token = self::sign($payload);
    self::setStateCookie($provider, $token, $expires);
    return ['state' => $state, 'code_challenge' => $challenge];
  }

  /**
   * Consome o state recebido no callback. Lança RuntimeException se ausente, expirado,
   * de outro provedor, adulterado, reaproveitado (replay) ou se não bater com o state
   * devolvido pelo provedor OAuth.
   *
   * @return array{verifier:string,user_id:int,perfil:string} code_verifier PKCE original
   *         mais a identidade de quem iniciou o fluxo - o callback autoriza por ela, já que
   *         a sessão não acompanha o retorno cross-site (ver nota em start()).
   */
  public static function consume(string $provider, string $stateFromCallback): array {
    $cookieName = self::cookieName($provider);
    $raw = (string)($_COOKIE[$cookieName] ?? '');
    // Cookie de correlação é de uso único: expira imediatamente após a leitura, com sucesso ou não.
    self::setStateCookie($provider, '', time() - 3600);
    if ($raw === '') {
      throw new RuntimeException('OAuth state ausente ou expirado (cookie de correlação não encontrado). Inicie a conexão novamente.');
    }
    $payload = self::verify($raw);
    if ($payload === null) {
      throw new RuntimeException('OAuth state inválido ou adulterado.');
    }
    if (($payload['provider'] ?? '') !== $provider) {
      throw new RuntimeException('OAuth state não pertence a este provedor.');
    }
    if ((int)($payload['expires'] ?? 0) < time()) {
      throw new RuntimeException('OAuth state expirado. Inicie a conexão novamente.');
    }
    $stateFromCallback = trim($stateFromCallback);
    if ($stateFromCallback === '' || !hash_equals((string)$payload['state'], $stateFromCallback)) {
      throw new RuntimeException('OAuth state não confere (possível CSRF/login-CSRF).');
    }
    // Uso único: se o mesmo state já foi consumido antes dentro da janela, bloqueia (replay).
    IntegrationReplayGuardService::guard('oauth_'.$provider, $stateFromCallback, self::TTL_SECONDS, ['rota' => 'oauth_state_'.$provider]);
    $userId = (int)($payload['user_id'] ?? 0);
    $perfil = (string)($payload['perfil'] ?? '');
    if ($userId <= 0 || $perfil === '') {
      throw new RuntimeException('OAuth state sem usuário iniciador vinculado. Inicie a conexão novamente pelo painel.');
    }
    return ['verifier' => (string)$payload['verifier'], 'user_id' => $userId, 'perfil' => $perfil];
  }

  private static function setStateCookie(string $provider, string $value, int $expires): void {
    $cookieName = self::cookieName($provider);
    $isHttps = class_exists('App') ? (App::isProduction() || App::isPublicHost()) : true;
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
      setcookie($cookieName, $value, [
        'expires' => $expires,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        // Lax (não Strict) é essencial aqui: é o único jeito do cookie sobreviver ao
        // redirect de navegação de nível superior vindo de um domínio externo (Tiny/Olist).
        'samesite' => 'Lax',
      ]);
    }
  }

  private static function cookieName(string $provider): string {
    return self::COOKIE_PREFIX . preg_replace('/[^a-z0-9_]/', '', strtolower($provider));
  }

  private static function base64UrlEncode(string $raw): string {
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
  }

  private static function sign(array $payload): string {
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $mac = hash_hmac('sha256', (string)$json, self::key());
    return self::base64UrlEncode((string)$json) . '.' . $mac;
  }

  private static function verify(string $token): ?array {
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2) return null;
    [$b64, $mac] = $parts;
    $json = base64_decode(strtr($b64, '-_', '+/'), true);
    if ($json === false) return null;
    $expectedMac = hash_hmac('sha256', $json, self::key());
    if (!hash_equals($expectedMac, $mac)) return null;
    $payload = json_decode($json, true);
    return is_array($payload) ? $payload : null;
  }

  private static function key(): string {
    $sec = class_exists('App') ? (App::config()['security'] ?? []) : [];
    $key = (string)($sec['integration_replay_hmac_key'] ?? ($sec['encryption_key'] ?? ''));
    if ($key === '') {
      throw new RuntimeException('Chave de assinatura OAuth ausente. Configure security.encryption_key no config/config.php.');
    }
    return $key;
  }
}
