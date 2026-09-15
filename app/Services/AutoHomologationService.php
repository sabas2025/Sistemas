<?php
class AutoHomologationService {
  private PDO $pdo;
  private array $cfg;
  private string $traceId;

  public function __construct(?PDO $pdo = null) {
    $this->pdo = $pdo ?: Database::getConnection();
    $this->cfg = IntegrationConfig::get();
    $this->traceId = RequestContext::id();
  }

  public function executar(bool $liberarSeAprovado = false): array {
    $inicio = microtime(true);
    $this->garantirEstrutura();
    $testes = [];
    $testes[] = $this->testeBanco();
    $testes[] = $this->testeConfiguracoesTinyV3();
    $testes[] = $this->testeOAuthTinyV3();
    $testes[] = $this->testeProdutoTinyV3();
    $testes[] = $this->testeEstoqueTinyV3();
    $testes[] = $this->testePedidoTinyV3();
    $testes[] = $this->testeLogsTinyV3();
    $testes[] = $this->testeConfiguracoesVsm();
    $testes[] = $this->testeFila();
    $testes[] = $this->testeAuditoria();

    $falhas = array_values(array_filter($testes, fn($t) => ($t['status'] ?? '') === 'falha'));
    $pendencias = array_values(array_filter($testes, fn($t) => ($t['status'] ?? '') === 'pendente'));
    $ok = count($falhas) === 0 && count($pendencias) === 0;

    if ($ok) {
      $this->marcarChecklistBase();
      if ($liberarSeAprovado) {
        $this->pdo->exec("UPDATE configuracoes_integracao SET tiny_v3_operacional=1 WHERE id=1");
        Audit::event('homologacao.automatica.liberou_tiny_v3', 'sucesso', [
          'mensagem' => 'Homologação automática aprovada e Tiny V3 marcado como operacional.',
          'trace_id' => $this->traceId
        ]);
      }
    } else {
      Audit::event('homologacao.automatica.pendencias', 'alerta', [
        'mensagem' => 'Homologação automática encontrou pendências/falhas. Tiny V3 não foi liberado.',
        'contexto' => ['falhas'=>count($falhas),'pendencias'=>count($pendencias)],
        'acao_recomendada' => 'Abrir Assistente de Homologação Automática e corrigir os itens listados.'
      ]);
    }

    $relatorio = [
      'trace_id' => $this->traceId,
      'aprovado' => $ok,
      'liberado_tiny_v3' => $ok && $liberarSeAprovado,
      'ambiente' => $this->cfg['ambiente'] ?? 'homologacao',
      'tiny_v3_ambiente' => $this->cfg['tiny_v3_ambiente'] ?? 'homologacao',
      'executado_em' => date('Y-m-d H:i:s'),
      'tempo_ms' => (int)round((microtime(true)-$inicio)*1000),
      'testes' => $testes,
      'resumo' => [
        'ok' => count(array_filter($testes, fn($t)=>($t['status']??'')==='ok')),
        'pendente' => count($pendencias),
        'falha' => count($falhas),
      ]
    ];
    $this->salvarRelatorio($relatorio);
    return $relatorio;
  }

  private function garantirEstrutura(): void {
    SchemaRuntimePolicyService::requireTables(['homologacao_automatica_relatorios','homologacao_checklist'], 'homologação automática');
    $this->pdo->exec("INSERT IGNORE INTO homologacao_checklist(chave,titulo,descricao,status) VALUES
      ('auto_banco','Homologação automática: banco','Validação automática de conexão e estrutura mínima do banco.','pendente'),
      ('auto_tiny_v3_oauth','Homologação automática: OAuth Tiny V3','Validação automática de Client ID, Redirect URI, token e refresh.','pendente'),
      ('auto_tiny_v3_modulos','Homologação automática: módulos Tiny V3','Testes automáticos de produto, estoque, pedido e logs Tiny V3.','pendente'),
      ('auto_vsm','Homologação automática: VSM','Validação automática da configuração mínima da VSM.','pendente'),
      ('auto_fila_auditoria','Homologação automática: fila/auditoria','Validação automática de fila, DLQ e auditoria por Trace ID.','pendente')");
  }

  private function salvarRelatorio(array $relatorio): void {
    $json = json_encode($relatorio, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    $resumo = json_encode($relatorio['resumo'] ?? [], JSON_UNESCAPED_UNICODE);
    $st = $this->pdo->prepare('INSERT INTO homologacao_automatica_relatorios(trace_id,aprovado,liberado_tiny_v3,ambiente,tiny_v3_ambiente,resumo_json,relatorio_json) VALUES(?,?,?,?,?,?,?)');
    $st->execute([$relatorio['trace_id'], $relatorio['aprovado']?1:0, $relatorio['liberado_tiny_v3']?1:0, $relatorio['ambiente'], $relatorio['tiny_v3_ambiente'], $resumo, $json]);
    $dir = dirname(__DIR__,2).'/storage/reports';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    @file_put_contents($dir.'/homologacao_automatica_'.$relatorio['trace_id'].'.json', $json);
    @file_put_contents($dir.'/homologacao_automatica_'.$relatorio['trace_id'].'.html', $this->htmlRelatorio($relatorio));
  }

  private function htmlRelatorio(array $r): string {
    $rows = '';
    foreach($r['testes'] as $t){
      $rows .= '<tr><td>'.e($t['nome']).'</td><td>'.e(strtoupper($t['status'])).'</td><td>'.e($t['mensagem']).'</td><td>'.e($t['acao_recomendada'] ?? '').'</td></tr>';
    }
    return '<!doctype html><html><head><meta charset="utf-8"><title>Relatório Homologação Automática</title><style>body{font-family:Arial;margin:24px}table{border-collapse:collapse;width:100%}td,th{border:1px solid #ddd;padding:8px}th{background:#f2f2f2}</style></head><body><h1>Relatório Homologação Automática</h1><p><b>Trace ID:</b> '.e($r['trace_id']).'</p><p><b>Status:</b> '.($r['aprovado']?'APROVADO':'PENDENTE/FALHA').'</p><table><thead><tr><th>Teste</th><th>Status</th><th>Mensagem</th><th>Ação recomendada</th></tr></thead><tbody>'.$rows.'</tbody></table></body></html>';
  }

  private function item(string $nome, string $status, string $mensagem, string $acao = '', array $contexto = []): array {
    return ['nome'=>$nome,'status'=>$status,'mensagem'=>$mensagem,'acao_recomendada'=>$acao,'contexto'=>$contexto];
  }

  private function testeBanco(): array {
    try {
      $this->pdo->query('SELECT 1')->fetchColumn();
      $tables = ['configuracoes_integracao','fila_integracao','auditoria_eventos','tiny_v3_tokens','tiny_v3_endpoint_logs'];
      $faltando=[];
      foreach($tables as $t){ if(!$this->tabelaExiste($t)) $faltando[]=$t; }
      if($faltando) return $this->item('Banco e estrutura', 'falha', 'Tabelas obrigatórias ausentes: '.implode(', ', $faltando), 'Execute Validação do Banco ou Atualizador Seguro Universal.');
      return $this->item('Banco e estrutura', 'ok', 'Conexão e tabelas obrigatórias OK.');
    } catch(Throwable $e){ return $this->item('Banco e estrutura','falha',$e->getMessage(),'Corrigir credenciais MySQL e rodar install/update.'); }
  }

  private function testeConfiguracoesTinyV3(): array {
    $faltas=[];
    foreach(['tiny_v3_url','tiny_v3_auth_url','tiny_v3_token_url','tiny_v3_client_id','tiny_v3_redirect_uri'] as $k){ if(empty($this->cfg[$k])) $faltas[]=$k; }
    if($faltas) return $this->item('Configurações Tiny V3','pendente','Campos pendentes: '.implode(', ', $faltas),'Preencha a aba OAuth da Ficha Tiny V3.');
    if(!preg_match('#^https?://#i', (string)$this->cfg['tiny_v3_redirect_uri'])) return $this->item('Configurações Tiny V3','falha','Redirect URI não é absoluta.','Use URL completa: https://dominio/public/index.php?page=tiny-v3-callback');
    if(str_contains((string)($this->cfg['tiny_v3_scopes'] ?? ''), 'produtos estoque pedidos notas-fiscais')) return $this->item('Configurações Tiny V3','falha','Escopos inválidos antigos detectados.','Deixe Escopos OAuth vazio ou use apenas escopos oficiais do aplicativo Tiny.');
    return $this->item('Configurações Tiny V3','ok','URLs, Client ID e Redirect URI estão preenchidos.');
  }

  private function testeOAuthTinyV3(): array {
    try {
      $status = TinyV3TokenService::status();
      if(empty($status['tem_token_tabela']) && empty($status['tem_token_manual'])) return $this->item('OAuth/Token Tiny V3','pendente','Nenhum token Tiny V3 encontrado.','Clique em Conectar Tiny V3 via OAuth ou use token manual apenas para homologação.',$status);
      if(!empty($status['expirado'])) return $this->item('OAuth/Token Tiny V3','pendente','Token Tiny V3 expirado ou perto de expirar.','Use Renovar Token ou refaça OAuth.',$status);
      return $this->item('OAuth/Token Tiny V3','ok','Token Tiny V3 encontrado e válido para sequência de testes.',$status);
    } catch(Throwable $e){ return $this->item('OAuth/Token Tiny V3','falha',$e->getMessage(),'Conferir Client ID, Client Secret, Redirect URI e tabela tiny_v3_tokens.'); }
  }

  private function testeProdutoTinyV3(): array {
    $sku = trim((string)($this->cfg['tiny_v3_sku_homologacao'] ?? '')) ?: trim((string)($_POST['sku_homologacao'] ?? ''));
    if($sku === '') return $this->item('Tiny V3 produto por SKU','pendente','SKU de homologação não informado.','Informe um SKU real na tela de Homologação Automática.');
    try {
      $tiny = new TinyV3Service($this->cfg);
      $ret = $tiny->consultarProduto($sku);
      if(!empty($ret['erro'])) return $this->item('Tiny V3 produto por SKU','falha','Tiny retornou erro ao consultar SKU.','Conferir token, endpoint produtos e SKU real.',['retorno'=>$ret]);
      return $this->item('Tiny V3 produto por SKU','ok','Consulta de produto executada para SKU '.$sku.'.','Conferir logs técnicos para confirmar retorno esperado.',['retorno'=>$ret]);
    } catch(Throwable $e){ return $this->item('Tiny V3 produto por SKU','falha',$e->getMessage(),'Conferir OAuth e endpoint de produtos.'); }
  }

  private function testeEstoqueTinyV3(): array {
    $sku = trim((string)($this->cfg['tiny_v3_sku_homologacao'] ?? '')) ?: trim((string)($_POST['sku_homologacao'] ?? ''));
    if($sku === '') return $this->item('Tiny V3 estoque','pendente','SKU de homologação não informado.','Informe um SKU real para testar estoque.');
    try {
      $tiny = new TinyV3Service($this->cfg);
      $ret = method_exists($tiny,'consultarEstoque') ? $tiny->consultarEstoque($sku) : $tiny->testarModulo('estoque',['sku'=>$sku]);
      if(!empty($ret['erro'])) return $this->item('Tiny V3 estoque','falha','Tiny retornou erro no teste de estoque.','Conferir endpoint de estoque e permissões do aplicativo.',['retorno'=>$ret]);
      return $this->item('Tiny V3 estoque','ok','Teste de estoque executado para SKU '.$sku.'.','Conferir saldo retornado no log.',['retorno'=>$ret]);
    } catch(Throwable $e){ return $this->item('Tiny V3 estoque','falha',$e->getMessage(),'Conferir endpoint de estoque.'); }
  }

  private function testePedidoTinyV3(): array {
    $id = trim((string)($this->cfg['tiny_v3_pedido_homologacao'] ?? '')) ?: trim((string)($_POST['id_pedido_homologacao'] ?? ''));
    if($id === '') return $this->item('Tiny V3 pedido','pendente','ID de pedido de homologação não informado.','Informe um ID de pedido Tiny para teste ou marque manualmente no checklist.');
    try {
      $tiny = new TinyV3Service($this->cfg);
      $ret = method_exists($tiny,'consultarPedido') ? $tiny->consultarPedido($id) : $tiny->testarModulo('pedido',['id_pedido'=>$id]);
      if(!empty($ret['erro'])) return $this->item('Tiny V3 pedido','falha','Tiny retornou erro ao consultar pedido.','Conferir endpoint /pedidos e ID real de pedido.',['retorno'=>$ret]);
      return $this->item('Tiny V3 pedido','ok','Teste de pedido executado.','Conferir retorno e logs.',['retorno'=>$ret]);
    } catch(Throwable $e){ return $this->item('Tiny V3 pedido','falha',$e->getMessage(),'Conferir endpoint de pedidos.'); }
  }

  private function testeLogsTinyV3(): array {
    try {
      $q = $this->pdo->query("SELECT COUNT(*) FROM tiny_v3_endpoint_logs WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 1 DAY)");
      $n = (int)$q->fetchColumn();
      return $n > 0 ? $this->item('Logs Tiny V3','ok',$n.' log(s) Tiny V3 nas últimas 24h.') : $this->item('Logs Tiny V3','pendente','Nenhum log Tiny V3 recente encontrado.','Execute testes de produto/estoque/pedido.');
    } catch(Throwable $e){ return $this->item('Logs Tiny V3','falha',$e->getMessage(),'Verificar tabela tiny_v3_endpoint_logs.'); }
  }

  private function testeConfiguracoesVsm(): array {
    if(empty($this->cfg['vsm_url'])) return $this->item('VSM configuração','pendente','URL da VSM não configurada.','Preencha Configurações → VSM.');
    if(!preg_match('#^https?://#i',(string)$this->cfg['vsm_url'])) return $this->item('VSM configuração','falha','URL da VSM inválida.','Use URL completa https://...');
    $endpoints = ['vsm_endpoint_baixa_estoque','vsm_endpoint_produto_novo','vsm_endpoint_consulta_estoque'];
    $faltando=[]; foreach($endpoints as $e){ if(empty($this->cfg[$e])) $faltando[]=$e; }
    if($faltando) return $this->item('VSM configuração','pendente','Endpoints VSM pendentes: '.implode(', ', $faltando),'Preencher endpoints reais do Swagger VSM.');
    return $this->item('VSM configuração','ok','URL e endpoints VSM preenchidos.','Executar Teste VSM para validar acesso real.');
  }

  private function testeFila(): array {
    try {
      TenantScopeService::run('fila_integracao', 'SELECT COUNT(*) FROM fila_integracao')->fetchColumn();
      return $this->item('Fila/DLQ','ok','Tabela de fila acessível.','Para homologação final, processar um item real ou simulado.');
    } catch(Throwable $e){ return $this->item('Fila/DLQ','falha',$e->getMessage(),'Executar atualização do banco.'); }
  }

  private function testeAuditoria(): array {
    try {
      Audit::event('homologacao.automatica.audit_test','info',['mensagem'=>'Teste de auditoria da homologação automática.']);
      $q=Database::forTable('auditoria_eventos')->prepare('SELECT COUNT(*) FROM auditoria_eventos WHERE trace_id=?');
      $q->execute([$this->traceId]);
      return ((int)$q->fetchColumn()>0) ? $this->item('Auditoria/Trace ID','ok','Auditoria registrou evento com Trace ID atual.') : $this->item('Auditoria/Trace ID','pendente','Evento de auditoria não encontrado pelo Trace ID.','Conferir Audit::event e tabela auditoria_eventos.');
    } catch(Throwable $e){ return $this->item('Auditoria/Trace ID','falha',$e->getMessage(),'Verificar tabela auditoria_eventos.'); }
  }

  private function tabelaExiste(string $t): bool {
    try { $st=$this->pdo->prepare('SHOW TABLES LIKE ?'); $st->execute([$t]); return (bool)$st->fetchColumn(); }
    catch(Throwable $e){ return false; }
  }

  private function marcarChecklistBase(): void {
    $keys=['auto_banco','auto_tiny_v3_oauth','auto_tiny_v3_modulos','auto_vsm','auto_fila_auditoria','tiny_v3_token','tiny_v3_logs'];
    $st=$this->pdo->prepare('UPDATE homologacao_checklist SET status="ok", resultado=?, trace_id=?, atualizado_em=NOW() WHERE chave=?');
    foreach($keys as $k){ $st->execute(['Aprovado automaticamente pelo assistente de homologação.', $this->traceId, $k]); }
  }

  public static function diagnosticarErroOAuth(array $query): array {
    $error = (string)($query['error'] ?? '');
    $desc = (string)($query['error_description'] ?? '');
    if($error === 'invalid_scope') return ['causa'=>'Escopos OAuth inválidos para este aplicativo Tiny.','acao'=>'Deixe o campo Escopos OAuth vazio ou use apenas escopos oficiais liberados no aplicativo Tiny.','criticidade'=>'alta'];
    if($error === 'invalid_request') return ['causa'=>'Parâmetro obrigatório ausente ou Redirect URI inconsistente.','acao'=>'Conferir Redirect URI absoluta e idêntica no Tiny e no Hub.','criticidade'=>'alta'];
    if($error === 'unauthorized_client') return ['causa'=>'Client ID não autorizado para esta Redirect URI ou fluxo.','acao'=>'Conferir Client ID, aplicativo Tiny e URL de redirecionamento.','criticidade'=>'alta'];
    if($error) return ['causa'=>'Erro OAuth retornado pelo Tiny: '.$error,'acao'=>'Abrir Auditoria e conferir query completa do callback.','criticidade'=>'media'];
    return ['causa'=>'Sem erro OAuth informado.','acao'=>'Continuar testes de token/produto/estoque.','criticidade'=>'baixa'];
  }
}
