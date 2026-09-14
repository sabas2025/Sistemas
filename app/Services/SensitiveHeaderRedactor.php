<?php
/**
 * Correção do achado P0-06 da reauditoria de 2026-08-23, entregue na V104.49.3-R6.
 *
 * Problema original: WebhookSecurityService, TinyWebhookService e
 * TinyWebhookSecurityService gravavam TODOS os headers HTTP recebidos - inclusive
 * Cookie, Authorization, X-HUB-SECRET e a própria assinatura - em banco (colunas
 * webhook_requisicoes.headers e tiny_webhooks.headers) e em Audit::event(). Isso
 * duplica credenciais reutilizáveis em banco, backup e trilha de auditoria.
 *
 * Esta classe centraliza o que pode ser persistido: allowlist de metadados inócuos;
 * nonce/assinatura viram apenas hash (prova de uso sem expor o valor); qualquer coisa
 * fora da allowlist (cookie, authorization, x-hub-secret, x-tiny-hub-secret etc.) é
 * descartada silenciosamente antes de qualquer INSERT ou Audit::event.
 */
class SensitiveHeaderRedactor {
  private const ALLOWLIST = [
    'user-agent','content-type','content-length','accept','accept-encoding',
    'x-forwarded-for','x-real-ip','host','origin','referer',
    'x-hub-timestamp','x-tiny-hub-timestamp','x-request-id','remote-addr',
  ];

  /** Headers cujo VALOR nunca é gravado em claro - só um hash, para provar reuso/replay sem expor. */
  private const HASH_ONLY = [
    'x-hub-nonce','x-hub-signature','x-tiny-hub-nonce','x-tiny-hub-signature',
  ];

  /**
   * @param array<string,mixed> $headers headers já normalizados em minúsculas
   * @return array<string,mixed> versão segura para persistir em banco/auditoria
   */
  public static function redactForStorage(array $headers): array {
    $out = [];
    foreach ($headers as $k => $v) {
      $key = strtolower((string)$k);
      if (in_array($key, self::HASH_ONLY, true)) {
        $out[$key] = 'sha256:'.hash('sha256', (string)$v);
        continue;
      }
      if (in_array($key, self::ALLOWLIST, true)) {
        $out[$key] = is_scalar($v) ? (string)$v : null;
      }
      // Qualquer outro header (cookie, authorization, x-hub-secret, x-tiny-hub-secret,
      // ou qualquer header customizado não previsto) é descartado por padrão: nunca
      // presumimos que um header desconhecido é seguro para gravar.
    }
    return $out;
  }
}
