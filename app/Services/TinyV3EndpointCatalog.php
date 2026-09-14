<?php
class TinyV3EndpointCatalog {
  public static function defaults(): array {
    return [
      'produtos_listar' => '/produtos',
      'produtos_obter' => '/produtos/{id}',
      'produtos_criar' => '/produtos',
      'produtos_alterar' => '/produtos/{id}',
      'produtos_preco' => '/produtos/{id}/preco',
      'estoque_consultar' => '/estoque/produtos/{id}',
      'estoque_atualizar' => '/estoque/produtos/{id}',
      'pedidos_obter' => '/pedidos/{id}',
      'pedidos_lancar_estoque' => '/pedidos/{idPedido}/lancar-estoque',
      'notas_obter' => '/notas-fiscais/{id}',
    ];
  }
  public static function get(string $key): string {
    $cfg = IntegrationConfig::get();
    $custom = trim((string)($cfg['tiny_v3_'.$key] ?? ''));
    return $custom !== '' ? $custom : (self::defaults()[$key] ?? '');
  }
  public static function fill(string $template, array $vars): string {
    if (isset($vars['id']) && !isset($vars['idPedido'])) $vars['idPedido'] = $vars['id'];
    if (isset($vars['idPedido']) && !isset($vars['id'])) $vars['id'] = $vars['idPedido'];
    foreach($vars as $k=>$v) $template = str_replace('{'.$k.'}', rawurlencode((string)$v), $template);
    return $template;
  }
}
