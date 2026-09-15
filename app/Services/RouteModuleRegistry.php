<?php
class RouteModuleRegistry {
  public static function groups(): array {
    return [
      'operacao' => ['label'=>'Operação','routes'=>['dashboard','pedidos','produtos','baixas-estoque','fiscal','notificacoes']],
      'integracoes' => ['label'=>'Integrações','routes'=>['integracoes','orquestracao-integracoes','regras-sincronizacao','tiny-webhooks']],
      'reconciliacao' => ['label'=>'Reconciliação','routes'=>['reconciliacao','divergencia-estoque','fila','fila-morta']],
      'relatorios' => ['label'=>'Relatórios','routes'=>['logs','auditoria','metricas']],
      'configuracoes' => ['label'=>'Configurações','routes'=>['usuarios','configuracoes','backups']],
      'tecnico' => ['label'=>'Central Técnica','routes'=>['central-tecnica','homologacao','homologacao-automatica','teste-real-tiny','oauth-v3-checklist','atualizador-seguro','bancos-modulos','health-modulos','menu-testes','dashboard-integridade','auditoria-codigo','selftest','validar-banco','mapa-banco','diagnostico-config-real','diagnostico','production-ready-v24','production-ready-v25','production-ready-v26','hosting-infinityfree','auditoria-hash-chain','tiny-v2-ficha-tecnica','tiny-v3-ficha','vsm-ficha-tecnica','fila-analytics-v24','seguranca-auditoria','security-assisted-test','laboratorio','produtos-vsm','produtos-pendencias','tiny-validacao','vsm-simulador','producao-segura','limpeza-retencao','produto-novo-politica','pedidos-validacao-vsm','pedido-validacao-detalhe','pedido-ciclo-vida','pedido-ciclo-detalhe']],
    ];
  }
  public static function technicalPages(): array { return self::groups()['tecnico']['routes']; }
  public static function moduleForPage(string $page): string { foreach(self::groups() as $key=>$g){ if(in_array($page,$g['routes'],true)) return $key; } return 'operacao'; }
  public static function architectureControllers(): array {
    return [
      'PedidoController'=>['pedidos','pedido-detalhe'],
      'ProdutoController'=>['produtos','produtos-vsm','produtos-pendencias','produto-pendencia-acao'],
      'EstoqueController'=>['baixas-estoque','divergencia-estoque','divergencia-acao','reconciliacao','reconciliacao-executar'],
      'FiscalController'=>['fiscal','fiscal-reenviar','nfe','notas-fiscais'],
      'TinyController'=>['tiny-webhooks','tiny-v3-ficha','tiny-v2-ficha-tecnica','teste-real-tiny','tiny-v3-testar','tiny-validacao'],
      'VsmController'=>['vsm-ficha-tecnica','testar-vsm','vsm-endpoints','vsm-campos','vsm-saude','vsm-logs','vsm-testes','vsm-simulador'],
      'FilaController'=>['fila','fila-reprocessar','fila-morta','fila-morta-reprocessar'],
      'HomologacaoController'=>['homologacao','homologacao-automatica','oauth-v3-checklist','selftest'],
      'ConfiguracaoController'=>['configuracoes','usuarios','bancos-modulos','backups'],
      'AuditoriaController'=>['logs','auditoria','auditoria-hash-chain','auditoria-codigo'],
      'CentralTecnicaController'=>['central-tecnica','diagnostico','atualizador-seguro','validar-banco','mapa-banco','producao-segura','limpeza-retencao'],
    ];
  }
}
