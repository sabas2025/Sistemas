<?php
/**
 * Contrato explícito de autoridade por domínio. Não altera fluxos automaticamente;
 * centraliza a regra já praticada pelo HUB para validação, reconciliação e auditoria.
 */
class IntegrationSourceOfTruthService {
  private const DOMAINS = [
    'pedidos' => ['source'=>'tiny','direction'=>'tiny_hub_vsm','conflict_policy'=>'manual_review'],
    'estoque' => ['source'=>'vsm','direction'=>'vsm_hub_tiny','conflict_policy'=>'vsm_wins_after_validation'],
    'fiscal' => ['source'=>'vsm','direction'=>'vsm_hub_tiny','conflict_policy'=>'authorized_document_wins'],
    'produtos_novos' => ['source'=>'vsm','direction'=>'vsm_hub_tiny','conflict_policy'=>'manual_approval'],
    'status_integracao' => ['source'=>'hub','direction'=>'internal','conflict_policy'=>'append_only_audit'],
  ];

  public static function all(): array { return self::DOMAINS; }
  public static function forDomain(string $domain): array {
    $domain=strtolower(trim($domain));
    if(!isset(self::DOMAINS[$domain])) throw new InvalidArgumentException('Domínio de integração sem contrato de autoridade: '.$domain);
    return self::DOMAINS[$domain];
  }
  public static function isAuthoritative(string $domain,string $system): bool {
    return hash_equals((string)self::forDomain($domain)['source'],strtolower(trim($system)));
  }
  public static function describe(): array {
    $labels=['pedidos'=>'Pedidos','estoque'=>'Estoque real','fiscal'=>'NF-e/XML','produtos_novos'=>'Produtos novos','status_integracao'=>'Status da integração'];
    $out=[];
    foreach(self::DOMAINS as $domain=>$rule)$out[]=['domain'=>$domain,'label'=>$labels[$domain]??$domain]+$rule;
    return $out;
  }
}
