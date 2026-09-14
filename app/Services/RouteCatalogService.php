<?php
/**
 * Melhoria 11 da seção 8 (relatório V104.49.3-R6): catálogo único de classificação de rotas.
 *
 * O achado B-06 foi causado por uma decisão de segurança tomada com casamento de substring:
 * AdminIpAllowlistService isentava da allowlist de IP tudo que contivesse "webhook" no nome, o que
 * isentava também 'tiny-webhooks' - a PÁGINA ADMINISTRATIVA que edita segredo e CNPJs autorizados
 * dos webhooks. Ao corrigir aquele ponto, a varredura encontrou exatamente o mesmo padrão em
 * WafService::shouldInspect(), que também desliga a inspeção para qualquer rota cujo nome contenha
 * "webhook" - ou seja, o mesmo defeito, no mesmo dia, em outro arquivo.
 *
 * A causa raiz não é nenhum dos dois pontos: é não haver um lugar só que diga o que cada rota é.
 * Este catálogo é esse lugar. Classificação por IGUALDADE, listas exaustivas, e uma rota nova que
 * ninguém classificar falha para o lado seguro (tratada como rota de painel: sujeita à allowlist
 * de IP e inspecionada pelo WAF) em vez de ganhar isenção por parecer-se com outra.
 */
class RouteCatalogService {
  /**
   * Webhooks de ENTRADA: chamados por Tiny/Olist e VSM, autenticados por HMAC + allowlist de IP
   * dedicada (tiny_allowed_ips/vsm_allowed_ips). Não são rotas de painel.
   *
   * ATENÇÃO: acrescentar uma rota aqui a isenta da allowlist de IP administrativa e da inspeção do
   * WAF. Só entram endpoints que realmente recebem chamadas de sistemas externos.
   *
   * @var list<string>
   */
  private const INBOUND_WEBHOOKS = [
    'api/tiny/webhook/estoque',
    'api/tiny/webhook/produto',
    'api/tiny/webhook/nota-fiscal',
    'api/tiny/webhook/situacao-pedido',
    'api/tiny/webhook/pedido',
    'api/webhook/tiny/evento',
    'api/webhook/vsm/pedido',
    'api/webhook/vsm/produto',
    'api/webhook/vsm/estoque',
    'api/webhook/vsm/pedido-retorno',
  ];

  /** Rotas públicas do site comercial e endpoints públicos sem dados operacionais. */
  private const PUBLIC_ROUTES = [
    'produto-institucional',
    'demo-online',
    'api/csp-report',
    'api/status',
  ];

  /**
   * Páginas administrativas cujo NOME se parece com o de um webhook, mas que são painel.
   * Existem explicitamente para documentar o B-06 e para o teste de regressão.
   * @var list<string>
   */
  private const WEBHOOK_LOOKALIKE_PANEL_ROUTES = [
    'tiny-webhooks',
  ];

  /** @return list<string> */
  public static function inboundWebhooks(): array { return self::INBOUND_WEBHOOKS; }
  /** @return list<string> */
  public static function publicRoutes(): array { return self::PUBLIC_ROUTES; }
  /** @return list<string> */
  public static function webhookLookalikePanelRoutes(): array { return self::WEBHOOK_LOOKALIKE_PANEL_ROUTES; }

  /** Webhook de entrada, por igualdade exata - nunca por substring. */
  public static function isInboundWebhook(string $page): bool {
    return in_array($page, self::INBOUND_WEBHOOKS, true);
  }

  public static function isPublic(string $page): bool {
    return in_array($page, self::PUBLIC_ROUTES, true);
  }

  /** Tudo que não é webhook de entrada nem rota pública é painel administrativo. */
  public static function isAdminPanel(string $page): bool {
    if ($page === '') return false;
    return !self::isInboundWebhook($page) && !self::isPublic($page);
  }
}
