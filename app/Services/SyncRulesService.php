<?php
class SyncRulesService {
  private static array $defaults = [
    'sync_criar_produto_tiny' => 1,
    'sync_atualizar_produto_tiny' => 1,
    'sync_atualizar_estoque_tiny' => 1,
    'sync_atualizar_status_tiny' => 1,
    'sync_atualizar_preco_tiny' => 1,
    'sync_atualizar_descricao_tiny' => 1,
    'sync_atualizar_categoria_tiny' => 1,
    'sync_atualizar_marca_tiny' => 1,
    'sync_criar_produto_se_nao_existir' => 0,
    'sync_bloquear_estoque_negativo' => 1,
  ];

  public static function all(): array {
    $cfg = IntegrationConfig::get();
    $out = self::$defaults;
    foreach ($out as $k => $v) {
      if (array_key_exists($k, $cfg)) $out[$k] = (int)$cfg[$k];
    }
    return $out;
  }

  public static function enabled(string $key): bool {
    $all = self::all();
    return !empty($all[$key]);
  }

  public static function explain(string $key): string {
    $labels = [
      'sync_criar_produto_tiny' => 'Criar produto no Tiny quando criado/recebido pela VSM',
      'sync_atualizar_produto_tiny' => 'Atualizar cadastro do produto no Tiny quando alterado pela VSM',
      'sync_atualizar_estoque_tiny' => 'Atualizar estoque no Tiny quando a VSM enviar saldo',
      'sync_atualizar_status_tiny' => 'Ativar/inativar produto no Tiny quando a VSM enviar status',
      'sync_atualizar_preco_tiny' => 'Atualizar preço no Tiny quando a VSM enviar preço',
      'sync_atualizar_descricao_tiny' => 'Atualizar descrição/nome no Tiny quando a VSM enviar descrição',
      'sync_atualizar_categoria_tiny' => 'Atualizar categoria no Tiny quando a VSM enviar categoria',
      'sync_atualizar_marca_tiny' => 'Atualizar marca no Tiny quando a VSM enviar marca',
      'sync_criar_produto_se_nao_existir' => 'Criar produto automaticamente se o SKU não existir no Tiny',
      'sync_bloquear_estoque_negativo' => 'Bloquear atualização de estoque negativo no Tiny',
    ];
    return $labels[$key] ?? $key;
  }

  public static function skipped(string $key, array $payload = []): array {
    Audit::event('sync.regra_bloqueada', 'alerta', [
      'mensagem' => 'Integração ignorada por regra de sincronização desativada.',
      'contexto' => ['regra'=>$key, 'descricao'=>self::explain($key)],
      'payload' => $payload,
      'acao_recomendada' => 'Abra Integrações → Regras de Sincronização e confirme se este fluxo deve ficar ativo.'
    ]);
    return [
      'sucesso' => true,
      'ignorado' => true,
      'codigo' => 'SYNC_RULE_DISABLED',
      'regra' => $key,
      'mensagem' => 'Processamento ignorado: '.self::explain($key).' está desativada.'
    ];
  }
}
