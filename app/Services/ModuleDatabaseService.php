<?php
class ModuleDatabaseService {
  public static function modulos(): array {
    $cfg = require __DIR__ . '/../../config/config.php';
    $base = $cfg['db'] ?? [];
    $modules = $cfg['db_modules'] ?? ['core'=>$base];
    $labels = [
      'core'=>'Núcleo / Login / Configurações',
      'pedidos'=>'Pedidos / Webhooks de pedido',
      'produtos'=>'Produtos / Catálogo / Pendências',
      'estoque'=>'Estoque / Reconciliação / Divergências',
      'fiscal'=>'XML / NF-e / eventos fiscais',
      'fila'=>'Fila / DLQ / Circuit breaker',
      'observabilidade'=>'Logs / Auditoria / Métricas / Diagnóstico',
      'backups'=>'Backups / pacotes gerados'
    ];
    $out=[];
    foreach($modules as $key=>$db){
      $merged = array_merge($base, $db);
      $out[$key]=[
        'modulo'=>$key,
        'titulo'=>$labels[$key] ?? ucfirst($key),
        'host'=>$merged['host'] ?? '127.0.0.1',
        'banco'=>$merged['name'] ?? '',
        'user'=>$merged['user'] ?? '',
        'charset'=>$merged['charset'] ?? 'utf8mb4',
        'tabelas'=>self::tabelasDoModulo($key),
        'status'=>self::status($key),
      ];
    }
    return $out;
  }

  public static function status(string $module): array {
    try {
      $pdo = Database::connection($module);
      $q = $pdo->query('SELECT DATABASE() db, VERSION() version');
      $row = $q->fetch() ?: [];
      return ['ok'=>true,'mensagem'=>'Conectado','detalhe'=>($row['db'] ?? '').' / MySQL '.($row['version'] ?? '')];
    } catch(Throwable $e){
      return ['ok'=>false,'mensagem'=>'Erro de conexão','detalhe'=>$e->getMessage()];
    }
  }

  public static function tabelasDoModulo(string $module): array {
    $map = [
      'core'=>['usuarios','empresas','filiais','configuracoes_integracao','configuracoes_historico','login_tentativas','permissoes_perfil','schema_migrations','tiny_v3_tokens','tiny_error_catalogo','tiny_v2_retry_policies','mapper_versions','instalacao_prechecks','upgrade_snapshots','post_install_tests','homologacao_checklist','homologacao_automatica_relatorios','selftest_relatorios'],
      'pedidos'=>['pedidos_integracao','tiny_webhooks','webhook_requisicoes','eventos_processados','integracao_execucoes','evento_correlacao'],
      'produtos'=>['produtos_mapeamento','produtos_vsm','produtos_vsm_eventos','produto_pendencias','produtos_pendentes_integracao','categorias_mapeamento','produtos_aprovacao_historico','produtos_tiny','vsm_endpoint_catalogo','vsm_payload_catalogo','vsm_endpoint_metricas'],
      'estoque'=>['estoque_movimentos','estoque_divergencias','estoque_reconciliacao','reconciliacao_execucoes','reconciliacao_itens'],
      'fiscal'=>['notas_fiscais','nfe_integracao','notas_fiscais_eventos'],
      'fila'=>['fila_integracao','fila_morta','circuit_breakers','payload_snapshots','fila_analytics_snapshots'],
      'observabilidade'=>['logs_integracao','auditoria_eventos','auditoria_timeline','auditoria_detalhes','metricas_api','diagnostico_api','security_audit','auditoria_hash_chain','auditoria_assinaturas','audit_exports','tiny_v3_endpoint_logs','tiny_v2_endpoint_logs','hosting_checks','notificacoes','notificacoes_config'],
      'backups'=>['backups','backups_banco']
    ];
    return $map[$module] ?? [];
  }

  public static function relatorio(): array {
    $mods = self::modulos();
    $ok = count(array_filter($mods, fn($m)=>$m['status']['ok']));
    return ['total'=>count($mods),'ok'=>$ok,'falha'=>count($mods)-$ok,'modulos'=>$mods];
  }
}
