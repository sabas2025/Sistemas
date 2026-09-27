<?php
/**
 * V42 - Controller modular planejado: Logs, auditoria e integridade.
 * As rotas legadas continuam em DashboardController para compatibilidade.
 * Este arquivo documenta a separação e serve como ponto de extração progressiva sem quebrar rotas existentes.
 */
class AuditoriaController {
  public static function routes(): array {
    $map = RouteModuleRegistry::architectureControllers();
    return $map['AuditoriaController'] ?? [];
  }

  public function dispatch(string $page): void {
    switch ($page) {
      case 'auditoria': $this->auditoria(); break;
      case 'auditoria-codigo': $this->auditoriaCodigo(); break;
      case 'auditoria-hash-chain': $this->auditoriaHashChain(); break;
      case 'auditoria-assinar-trace': $this->auditoriaAssinarTrace(); break;
      case 'auditoria-exportar-pdf': $this->auditoriaExportarPdf(); break;
      case 'auditoria-exportar-enterprise': $this->auditoriaExportarEnterprise(); break;
      case 'auditoria-detalhe': $this->detalhe(); break;
      default: http_response_code(404); echo 'Rota de auditoria não encontrada';
    }
  }

  /**
   * Detalhe de um evento da trilha, com a linha do tempo do Trace ID inteiro.
   *
   * Achado I-17 (2026-09-15): aceitava APENAS `?id=`, mas `auditoriaAssinarTrace()` redireciona
   * para cá com `?trace_id=` — então assinar um trace, que é ação forense, levava o operador a um
   * **404 "Evento não encontrado"**, sem confirmação de que a assinatura tinha funcionado. A tela
   * de Auditoria linka com `?id=` e sempre funcionou; só o retorno da assinatura quebrava.
   *
   * Mora aqui, e não no `DashboardController`, porque acrescentar as duas formas de busca lá
   * estourava em 339 bytes o teto de 160 KB que `v104_48_1_architecture_test.php` impõe — e a
   * resposta que a guarda pede é mover, não levantar o limite. É o mesmo caminho usado no I-12.
   */
  public function detalhe(): void {
    PermissionService::require('auditoria','visualizar');
    $id = (int)($_GET['id'] ?? 0);
    $trace = trim((string)($_GET['trace_id'] ?? ''));
    $pdo = Database::forTable('auditoria_eventos');
    if ($id > 0) {
      $st = $pdo->prepare('SELECT * FROM auditoria_eventos WHERE id=? LIMIT 1');
      $st->execute([$id]);
    } else {
      // Busca por trace cai no primeiro evento dele; a linha do tempo abaixo mostra o trace inteiro.
      $st = $pdo->prepare('SELECT * FROM auditoria_eventos WHERE trace_id=? ORDER BY id ASC LIMIT 1');
      $st->execute([$trace]);
    }
    $evento = $st->fetch();
    if (!$evento) { http_response_code(404); echo 'Evento não encontrado'; return; }
    $st = $pdo->prepare('SELECT * FROM auditoria_eventos WHERE trace_id=? ORDER BY id ASC');
    $st->execute([$evento['trace_id']]);
    $timeline = $st->fetchAll();
    $pageTitle = 'Detalhe da Auditoria';
    require __DIR__.'/../../views/auditoria_detalhe.php';
  }
  private function auditoria(): void {
    PermissionService::require('auditoria','visualizar');
    $status = $_GET['status'] ?? '';
    $trace = trim($_GET['trace'] ?? '');
    $busca = trim($_GET['busca'] ?? '');
    $sql = "SELECT id,status,trace_id,acao,codigo_erro,mensagem,causa_provavel,criado_em,entidade_id FROM auditoria_eventos WHERE 1=1"; $params=[];
    if($status){ $sql .= " AND status=?"; $params[]=$status; }
    if($trace){ $sql .= " AND trace_id LIKE ?"; $params[]="%$trace%"; }
    if($busca){ $sql .= " AND (acao LIKE ? OR mensagem LIKE ? OR codigo_erro LIKE ? OR entidade_id LIKE ?)"; for($i=0;$i<4;$i++) $params[]="%$busca%"; }
    $sql .= " ORDER BY id DESC LIMIT 300";
    $st=Database::tableConnectionForSql($sql)->prepare($sql); $st->execute($params); $eventos=$st->fetchAll();
    $pageTitle = 'Auditoria';
    require __DIR__.'/../../views/auditoria.php';
  }

  private function auditoriaCodigo(): void {
    PermissionService::require('auditoria','visualizar');
    $codigoAuditoria = CodeAuditService::analisar();
    $pageTitle = 'Auditoria de Código';
    require __DIR__.'/../../views/auditoria_codigo.php';
  }

  private function auditoriaHashChain(): void {
    PermissionService::require('auditoria','visualizar');
    $acao = $_POST['acao'] ?? '';
    if($_SERVER['REQUEST_METHOD']==='POST'){
      Csrf::validate();
      if($acao==='assinar'){
        $total = EnterpriseAuditHashChainService::assinarProximos(2000);
        Audit::event('auditoria.hash_chain_assinar','sucesso',['mensagem'=>'Hash chain atualizada.','contexto'=>['eventos'=>$total]]);
        redirect('index.php?page=auditoria-hash-chain&ok=1');
      }
    }
    $resultado = EnterpriseAuditHashChainService::validar();
    $pageTitle = 'Auditoria Hash Chain';
    require __DIR__.'/../../views/auditoria_hash_chain.php';
  }

  private function auditoriaAssinarTrace(): void {
    PermissionService::require('auditoria','exportar');
    Csrf::validate();
    $trace = trim($_POST['trace_id'] ?? '');
    if($trace !== '') {
      $total = AuditIntegrityService::assinarTrace($trace);
      Audit::event('auditoria.assinar_trace','sucesso',['mensagem'=>'Trace assinado com SHA256.','contexto'=>['trace_id'=>$trace,'eventos'=>$total]]);
      redirect('index.php?page=auditoria-detalhe&trace_id='.urlencode($trace).'&assinado=1');
    }
    redirect('index.php?page=auditoria');
  }

  private function auditoriaExportarPdf(): void {
    PermissionService::require('auditoria','exportar');
    $trace = trim($_GET['trace_id'] ?? '');
    $params=[]; $where='1=1';
    if($trace){ $where='trace_id=?'; $params[]=$trace; }
    $st=Database::forTable('auditoria_eventos')->prepare("SELECT * FROM auditoria_eventos WHERE {$where} ORDER BY id ASC LIMIT 1000");
    $st->execute($params); $eventos=$st->fetchAll();
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: inline; filename=auditoria-relatorio.html');
    echo '<!doctype html><html lang="pt-br"><meta charset="utf-8"><title>Relatório de Auditoria</title><style>body{font-family:Arial;margin:30px}table{border-collapse:collapse;width:100%}td,th{border:1px solid #ddd;padding:6px;font-size:12px}th{background:#f2f2f2}.small{color:#666;font-size:12px}</style>';
    echo '<h1>Relatório de Auditoria</h1><p class="small">Use imprimir/salvar como PDF no navegador.</p><p><b>Trace ID:</b> '.e($trace ?: 'Todos').'</p><table><thead><tr><th>Data</th><th>Trace</th><th>Ação</th><th>Status</th><th>Nível</th><th>Mensagem</th></tr></thead><tbody>';
    foreach($eventos as $ev){ echo '<tr><td>'.e($ev['criado_em']).'</td><td>'.e($ev['trace_id']).'</td><td>'.e($ev['acao']).'</td><td>'.e($ev['status']).'</td><td>'.e($ev['nivel']).'</td><td>'.e($ev['mensagem']).'</td></tr>'; }
    echo '</tbody></table></html>'; exit;
  }

  private function auditoriaExportarEnterprise(): void {
    PermissionService::require('auditoria','exportar');
    $trace = trim($_GET['trace_id'] ?? '');
    $formato = strtolower($_GET['formato'] ?? 'json');
    $params=[]; $where='1=1';
    if($trace){ $where='trace_id=?'; $params[]=$trace; }
    $st=Database::forTable('auditoria_eventos')->prepare("SELECT * FROM auditoria_eventos WHERE {$where} ORDER BY id ASC LIMIT 5000"); $st->execute($params); $eventos=$st->fetchAll();
    if($formato==='csv'){
      header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename=auditoria-enterprise.csv');
      $out=fopen('php://output','w'); fputcsv($out,['id','trace_id','acao','status','nivel','codigo_erro','mensagem','ip','criado_em'],';');
      // P2: mesma neutralização de injeção de fórmula CSV aplicada em logsExportar().
      foreach($eventos as $e) fputcsv($out,$this->csvSafeRow([$e['id'],$e['trace_id'],$e['acao'],$e['status'],$e['nivel'],$e['codigo_erro'],$e['mensagem'],$e['ip'],$e['criado_em']]),';');
      exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['trace_id'=>$trace ?: null,'total'=>count($eventos),'eventos'=>$eventos],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit;
  }

  /** Sanitizador de CSV (anti CSV-injection). Duplicado do DashboardController, que ainda o usa em logsExportar. */
  private function csvSafeRow(array $row): array {
    foreach ($row as $k => $v) {
      $s = (string)$v;
      if ($s !== '' && preg_match('/^[=+\-@\t\r]/', $s)) $row[$k] = "'".$s;
    }
    return $row;
  }

}
