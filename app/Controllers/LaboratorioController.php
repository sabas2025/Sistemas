<?php
/**
 * Fase 3 (decomposição do controller-deus, 2026-09-26) — etapa Laboratório.
 *
 * Reúne o Laboratório de Integração e os quatro simuladores de fila que viviam no
 * DashboardController (achado A3-01): a tela (laboratorio), o despachante por $tipo
 * (laboratorioExecutar → laboratorio-executar) e as ações simular-baixa-tiny,
 * simular-produto-vsm, simular-estoque-vsm e simular-status-vsm. Ficaram juntos de propósito
 * porque laboratorioExecutar invoca os quatro simuladores por $this->; movidos verbatim, CSRF e
 * PermissionService preservados. Nenhuma URL muda, só quem a atende
 * (FastRouteDispatcherService::$dispatchGroups). O cluster só usa serviços estáticos
 * (Database::forTable, TenantScopeService, Audit, NotificationService, RequestContext, SelfTestService),
 * então não precisa de $pdo próprio.
 */
class LaboratorioController extends BaseModuleController {
  public static function routes(): array {
    return [
      'laboratorio','laboratorio-executar',
      'simular-baixa-tiny','simular-produto-vsm','simular-estoque-vsm','simular-status-vsm',
    ];
  }

  public function dispatch(string $page): void {
    switch ($page) {
      case 'laboratorio-executar': $this->laboratorioExecutar(); break;
      case 'simular-baixa-tiny': $this->simularBaixaTiny(); break;
      case 'simular-produto-vsm': $this->simularProdutoVsm(); break;
      case 'simular-estoque-vsm': $this->simularEstoqueVsm(); break;
      case 'simular-status-vsm': $this->simularStatusVsm(); break;
      default: $this->laboratorio(); break; // laboratorio
    }
  }

  private function laboratorio(): void {
    PermissionService::require('laboratorio','visualizar');
    $pageTitle='Laboratório de Integração';
    require __DIR__.'/../../views/laboratorio.php';
  }

  private function laboratorioExecutar(): void {
    PermissionService::require('laboratorio','executar'); Csrf::validate();
    $tipo=$_POST['tipo'] ?? 'tiny_estoque';
    if($tipo==='tiny_estoque') { $this->simularBaixaTiny(); return; }
    if($tipo==='vsm_produto') { $this->simularProdutoVsm(); return; }
    if($tipo==='vsm_estoque') { $this->simularEstoqueVsm(); return; }
    if($tipo==='vsm_status') { $this->simularStatusVsm(); return; }
    if($tipo==='selftest') { SelfTestService::executar(); redirect('index.php?page=selftest'); }
    redirect('index.php?page=laboratorio');
  }

  private function simularBaixaTiny(): void {
    PermissionService::require('estoque','simular');
    Csrf::validate();
    $trace = RequestContext::id();
    $sku = trim($_POST['sku'] ?? 'TESTE001');
    $qtd = (float)($_POST['quantidade'] ?? 1);
    $ref = trim($_POST['referencia'] ?? ('TINY-TESTE-'.date('Ymd-His')));
    if($sku==='' || $qtd <= 0) redirect('index.php?page=baixas-estoque&erro=campos');
    $payload = [
      'referencia'=>$ref,
      'origem'=>'tiny_simulador',
      'itens'=>[[
        'sku'=>$sku,
        'quantidade'=>$qtd,
        'descricao'=>trim($_POST['descricao'] ?? 'Produto teste baixa Tiny'),
      ]],
      'pedido'=>['numero'=>$ref],
      'observacao'=>'Baixa simulada pelo painel para homologação.'
    ];
    TenantScopeService::run('fila_integracao', 'INSERT INTO fila_integracao(tipo,referencia,payload,status,trace_id) VALUES(?,?,?,?,?)', ['baixa_estoque_vsm',$ref,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'pendente',$trace]);
    Audit::event('simulador.baixa_tiny.criada','sucesso',['entidade'=>'fila_integracao','entidade_id'=>Database::forTable('fila_integracao')->lastInsertId(),'mensagem'=>'Baixa Tiny simulada e enviada para fila Tiny → VSM.','payload'=>$payload]);
    NotificationService::criar('estoque','Baixa teste criada','Baixa '.$ref.' foi enviada para fila Tiny → VSM.','info',['trace_id'=>$trace,'link'=>'index.php?page=fila']);
    redirect('index.php?page=baixas-estoque&simulado=1');
  }

  private function simularProdutoVsm(): void {
    PermissionService::require('produtos_vsm','simular');
    Csrf::validate();
    $trace = RequestContext::id();
    $sku = trim($_POST['sku'] ?? ('VSMTESTE'.date('His')));
    $nome = trim($_POST['nome'] ?? 'Produto Teste VSM');
    if($sku==='' || $nome==='') redirect('index.php?page=produtos-vsm&erro=campos');
    $payload = [
      'id'=>'SIM-'.$sku,
      'sku'=>$sku,
      'codigo'=>$sku,
      'nome'=>$nome,
      'descricao'=>$nome,
      'ean'=>preg_replace('/\D/','',$_POST['ean'] ?? ''),
      'gtin'=>preg_replace('/\D/','',$_POST['ean'] ?? ''),
      'ncm'=>preg_replace('/\D/','',$_POST['ncm'] ?? '00000000'),
      'unidade'=>trim($_POST['unidade'] ?? 'UN'),
      'categoria'=>trim($_POST['categoria'] ?? 'Geral'),
      'marca'=>trim($_POST['marca'] ?? ''),
      'preco_venda'=>(float)($_POST['preco_venda'] ?? 10),
      'preco_custo'=>(float)($_POST['preco_custo'] ?? 0),
      'estoque'=>(float)($_POST['estoque'] ?? 0),
      'estoque_minimo'=>(float)($_POST['estoque_minimo'] ?? 0),
      'ativo'=>1,
      'origem'=>'vsm_simulador'
    ];
    TenantScopeService::run('fila_integracao', 'INSERT INTO fila_integracao(tipo,referencia,payload,status,trace_id) VALUES(?,?,?,?,?)', ['produto_vsm_para_tiny',$sku,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'pendente',$trace]);
    Audit::event('simulador.produto_vsm.criado','sucesso',['entidade'=>'fila_integracao','entidade_id'=>Database::forTable('fila_integracao')->lastInsertId(),'mensagem'=>'Produto VSM simulado e enviado para fila VSM → Tiny.','payload'=>$payload]);
    NotificationService::criar('sistema','Produto teste VSM criado','Produto '.$sku.' foi enviado para fila VSM → Tiny.','info',['trace_id'=>$trace,'link'=>'index.php?page=produtos-vsm']);
    redirect('index.php?page=produtos-vsm&simulado=1');
  }

  private function simularEstoqueVsm(): void {
    PermissionService::require('produtos_vsm','simular');
    Csrf::validate();
    $trace = RequestContext::id();
    $sku = trim($_POST['sku'] ?? ('VSMEST'.date('His')));
    $estoque = (float)($_POST['estoque'] ?? 10);
    if($sku==='' || $estoque < 0) redirect('index.php?page=produtos-vsm&erro=estoque');
    $payload = [
      'acao'=>'atualizar_estoque',
      'id'=>'EST-'.$sku,
      'sku'=>$sku,
      'codigo'=>$sku,
      'estoque'=>$estoque,
      'saldo'=>$estoque,
      'origem'=>'vsm_simulador',
      'observacao'=>'Simulação de atualização de estoque VSM → Tiny.'
    ];
    TenantScopeService::run('fila_integracao', 'INSERT INTO fila_integracao(tipo,referencia,payload,status,trace_id) VALUES(?,?,?,?,?)', ['produto_vsm_estoque_para_tiny',$sku,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'pendente',$trace]);
    Audit::event('simulador.estoque_vsm.criado','sucesso',['entidade'=>'fila_integracao','entidade_id'=>Database::forTable('fila_integracao')->lastInsertId(),'mensagem'=>'Atualização de estoque VSM simulada e enviada para fila VSM → Tiny.','payload'=>$payload]);
    NotificationService::criar('estoque','Estoque VSM teste criado','Estoque do produto '.$sku.' foi enviado para fila VSM → Tiny.','info',['trace_id'=>$trace,'link'=>'index.php?page=produtos-vsm']);
    redirect('index.php?page=produtos-vsm&estoque_simulado=1');
  }

  private function simularStatusVsm(): void {
    PermissionService::require('produtos_vsm','simular');
    Csrf::validate();
    $trace = RequestContext::id();
    $sku = trim($_POST['sku'] ?? ('VSMSTS'.date('His')));
    $ativo = ($_POST['ativo'] ?? '1') === '1' ? 1 : 0;
    if($sku==='') redirect('index.php?page=produtos-vsm&erro=status');
    $payload = [
      'acao'=>'atualizacao_status',
      'id'=>'STS-'.$sku,
      'sku'=>$sku,
      'codigo'=>$sku,
      'ativo'=>$ativo,
      'status'=>$ativo ? 'ativo' : 'inativo',
      'situacao'=>$ativo ? 'A' : 'I',
      'origem'=>'vsm_simulador',
      'observacao'=>'Simulação de atualização de status VSM → Tiny.'
    ];
    TenantScopeService::run('fila_integracao', 'INSERT INTO fila_integracao(tipo,referencia,payload,status,trace_id) VALUES(?,?,?,?,?)', ['produto_vsm_status_para_tiny',$sku,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'pendente',$trace]);
    Audit::event('simulador.status_vsm.criado','sucesso',['entidade'=>'fila_integracao','entidade_id'=>Database::forTable('fila_integracao')->lastInsertId(),'mensagem'=>'Atualização de status VSM simulada e enviada para fila VSM → Tiny.','payload'=>$payload]);
    NotificationService::criar('sistema','Status VSM teste criado','Status do produto '.$sku.' foi enviado para fila VSM → Tiny.','info',['trace_id'=>$trace,'link'=>'index.php?page=produtos-vsm']);
    redirect('index.php?page=produtos-vsm&status_simulado=1');
  }
}
