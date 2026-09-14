<?php
/**
 * Reauditoria 2026-09-14: security.admin_ip_allowlist existia em config.example.php e no
 * install.php desde antes, mas nenhum ponto do código lia essa chave - o painel administrativo
 * não era de fato restrito por IP e a opção era apenas decorativa. Este serviço passa a aplicar
 * a allowlist de fato, mantendo o recurso opcional (lista vazia = comportamento antigo, sem bloqueio).
 */
class AdminIpAllowlistService {
  /**
   * Rotas públicas do site comercial, o endpoint de relatório de CSP e o status público
   * não fazem parte do painel administrativo. Note que api/processar-fila, api/notificacoes/*
   * e as demais rotas api/* de uso interno do painel NÃO entram aqui de propósito: todas
   * exigem Auth::requireLogin() e devem continuar protegidas pela allowlist como o resto do painel.
   */
  /**
   * Melhoria 11 da seção 8: as listas de rotas públicas e de webhooks de entrada saíram daqui e
   * passaram a viver em RouteCatalogService, porque o WafService tomava a MESMA decisão com a
   * MESMA heurística de substring (e com o mesmo defeito do B-06). Duas cópias de uma regra de
   * segurança divergem; uma cópia só, não.
   */

  public static function enforceForRequest(string $page): void {
    if (PHP_SAPI === 'cli') return;
    $sec = class_exists('App') ? (App::config()['security'] ?? []) : [];
    $ip = class_exists('RequestContext') ? RequestContext::ip() : (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if (self::isAllowed($page, $ip, $sec)) return;

    if (class_exists('SecurityEventService')) {
      try {
        SecurityEventService::log('painel.ip_nao_permitido', 'alto', 'Acesso ao painel administrativo bloqueado: IP fora da allowlist configurada em security.admin_ip_allowlist.', ['ip' => $ip, 'page' => $page]);
      } catch (Throwable $e) {
        if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e, ['ip' => $ip, 'page' => $page]);
      }
    }

    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Acesso ao painel administrativo bloqueado por política de IP.');
  }

  /**
   * Decisão pura (sem I/O), separada de enforceForRequest() para poder ser testada
   * diretamente sem depender de SAPI, headers ou exit().
   * @param array<string,mixed> $security
   */
  public static function isAllowed(string $page, ?string $ip, array $security): bool {
    if (RouteCatalogService::isPublic($page)) return true;
    // Webhooks de integração (Tiny/VSM) são externos por natureza e já têm sua própria
    // validação por HMAC + allowlist de IP dedicada (tiny_allowed_ips/vsm_allowed_ips).
    // B-06: igualdade exata, nunca substring - a lista exaustiva está em RouteCatalogService.
    if (RouteCatalogService::isInboundWebhook($page)) return true;

    $allowlist = array_values(array_filter(array_map('trim', explode(',', (string)($security['admin_ip_allowlist'] ?? '')))));
    if (!$allowlist) return true; // recurso opcional/desativado por padrão

    return $ip !== null && $ip !== '' && self::matches($ip, $allowlist);
  }

  /** @param list<string> $allowlist */
  private static function matches(string $ip, array $allowlist): bool {
    foreach ($allowlist as $entry) {
      if ($entry === '') continue;
      if (str_contains($entry, '/')) {
        if (self::ipInCidr($ip, $entry)) return true;
        continue;
      }
      if ($entry === $ip) return true;
    }
    return false;
  }

  private static function ipInCidr(string $ip, string $cidr): bool {
    [$subnet, $bits] = array_pad(explode('/', $cidr, 2), 2, null);
    if ($subnet === null || $bits === null || !ctype_digit($bits)) return false;
    $ipBin = @inet_pton($ip);
    $subnetBin = @inet_pton($subnet);
    if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) return false;
    $bits = (int)$bits;
    $maxBits = strlen($ipBin) * 8;
    if ($bits < 0 || $bits > $maxBits) return false;
    $bytes = intdiv($bits, 8);
    $remBits = $bits % 8;
    if ($bytes > 0 && strncmp($ipBin, $subnetBin, $bytes) !== 0) return false;
    if ($remBits === 0) return true;
    $mask = chr((0xFF << (8 - $remBits)) & 0xFF);
    return (($ipBin[$bytes] & $mask) === ($subnetBin[$bytes] & $mask));
  }
}
