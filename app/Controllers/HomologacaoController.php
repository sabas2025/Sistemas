<?php
/**
 * Fase 3 (decomposição do controller-deus, 2026-09-26) — etapa Homologação.
 *
 * Reúne homologação (manual, automática, relatório, ação), self-test e o checklist OAuth V3 que
 * viviam no DashboardController (achado A3-01). Rotas despachadas pelo
 * FastRouteDispatcherService::$dispatchGroups; nenhuma URL muda, só quem a atende. Handlers movidos
 * verbatim (CSRF e PermissionService preservados). csvSafeRow é duplicado aqui (sanitizador de CSV
 * puro de 5 linhas) porque o DashboardController ainda o usa na exportação de logs.
 */
class HomologacaoController extends BaseModuleController {
  private PDO $pdo;
  public function __construct(){ $this->pdo = Database::getConnection(); }

  public static function routes(): array {
    return [
      'selftest','selftest-executar','homologacao','homologacao-acao','homologacao-relatorio',
      'relatorio-homologacao','homologacao-automatica','homologacao-automatica-executar','oauth-v3-checklist',
    ];
  }

  public function dispatch(string $page): void {
    switch ($page) {
      case 'selftest': $this->selftest(); break;
      case 'selftest-executar': $this->selftestExecutar(); break;
      case 'homologacao-acao': $this->homologacaoAcao(); break;
      case 'homologacao-relatorio': $this->homologacaoRelatorio(); break;
      case 'relatorio-homologacao': $this->homologacaoRelatorio(); break;
      case 'homologacao-automatica': $this->homologacaoAutomatica(); break;
      case 'homologacao-automatica-executar': $this->homologacaoAutomaticaExecutar(); break;
      case 'oauth-v3-checklist': $this->oauthV3Checklist(); break;
      default: $this->homologacao(); break; // homologacao
    }
  }

  private function db(string $table): PDO { return Database::forTable($table); }

  /** Sanitizador de CSV (anti CSV-injection). Duplicado do DashboardController, que ainda o usa. */
  private function csvSafeRow(array $row): array {
    foreach ($row as $k => $v) {
      $s = (string)$v;
      if ($s !== '' && preg_match('/^[=+\-@\t\r]/', $s)) $row[$k] = "'".$s;
    }
    return $row;
  }

  private function selftest(): void {
    PermissionService::require('selftest','visualizar');
    $relatorios=$this->db('selftest_relatorios')->query('SELECT id, status, resumo, detalhes, trace_id, criado_em FROM selftest_relatorios ORDER BY id DESC LIMIT 50')->fetchAll();
    $pageTitle='Self-Test do Sistema';
    require __DIR__.'/../../views/selftest.php';
  }

  private function selftestExecutar(): void {
    PermissionService::require('selftest','executar'); Csrf::validate();
    SelfTestService::executar();
    redirect('index.php?page=selftest&executado=1');
  }

  private function homologacao(): void {
    PermissionService::require('homologacao','visualizar');
    try { $itens = $this->db('homologacao_checklist')->query("SELECT id, chave, titulo, descricao, status, resultado, trace_id, atualizado_em, criado_em FROM homologacao_checklist ORDER BY id ASC")->fetchAll(); }
    catch (Throwable $e) { $itens = []; }
    $cfg = IntegrationConfig::get();
    $pageTitle = 'Checklist de Homologação Final';
    require __DIR__.'/../../views/homologacao.php';
  }

  private function homologacaoAcao(): void {
    PermissionService::require('homologacao','executar');
    Csrf::validate();
    $id=(int)($_POST['id'] ?? 0);
    $status=$_POST['status'] ?? 'ok';
    if(!in_array($status,['pendente','ok','falha','nao_aplicavel'],true)) $status='pendente';
    $resultado=trim((string)($_POST['resultado'] ?? ''));
    $this->db('homologacao_checklist')->prepare('UPDATE homologacao_checklist SET status=?, resultado=?, trace_id=?, atualizado_em=NOW() WHERE id=?')->execute([$status,$resultado,RequestContext::id(),$id]);
    Audit::event('homologacao.checklist.atualizar','sucesso',['entidade'=>'homologacao_checklist','entidade_id'=>$id,'mensagem'=>'Checklist de homologação atualizado.','contexto'=>['status'=>$status,'resultado'=>$resultado]]);
    redirect('index.php?page=homologacao');
  }

  private function homologacaoRelatorio(): void {
    PermissionService::require('homologacao','relatorio');
    $html = HomologationReportService::gerarHtml($this->pdo);
    Audit::event('homologacao.relatorio.gerado','sucesso',[
      'mensagem'=>'Relatório final de homologação gerado em HTML.',
      'acao_recomendada'=>'Salvar o HTML/PDF junto com evidências dos testes reais Tiny e VSM.'
    ]);
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: inline; filename="relatorio-homologacao-hub-vsm-tiny.html"');
    echo $html;
  }

  private function homologacaoAutomatica(): void {
    PermissionService::require('homologacao','visualizar');
    try { (new AutoHomologationService($this->pdo))->executar(false); } catch(Throwable $e) { if(class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    try { $relatorios = $this->db('homologacao_automatica_relatorios')->query('SELECT * FROM homologacao_automatica_relatorios ORDER BY id DESC LIMIT 30')->fetchAll(); }
    catch(Throwable $e) { if(class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); $relatorios = []; }
    $ultimoRelatorio = $relatorios[0] ?? null;
    $pageTitle = 'Homologação Automática';
    require __DIR__.'/../../views/homologacao_automatica.php';
  }

  private function homologacaoAutomaticaExecutar(): void {
    PermissionService::require('homologacao','executar');
    Csrf::validate();
    $liberar = isset($_POST['liberar_se_aprovado']);
    try {
      $service = new AutoHomologationService($this->pdo);
      $rel = $service->executar($liberar);
      $_SESSION['homologacao_automatica_ultimo'] = $rel;
      NotificationService::criar('sistema', $rel['aprovado'] ? 'Homologação automática aprovada' : 'Homologação automática com pendências', $rel['aprovado'] ? 'Todos os testes obrigatórios passaram.' : 'Existem pendências/falhas no assistente de homologação.', $rel['aprovado'] ? 'sucesso' : 'alerta', ['link'=>'index.php?page=homologacao-automatica','trace_id'=>$rel['trace_id']]);
    } catch(Throwable $e) {
      Audit::exception($e,'homologacao.automatica.erro',['codigo_erro'=>'AUTO_HOMOLOGATION_ERROR','acao_recomendada'=>'Verificar estrutura do banco, tokens Tiny V3 e permissões do usuário.']);
      NotificationService::criar('sistema','Erro na homologação automática',$e->getMessage(),'erro',['link'=>'index.php?page=homologacao-automatica']);
    }
    redirect('index.php?page=homologacao-automatica&executado=1');
  }

  private function oauthV3Checklist(): void {
    PermissionService::require('configuracoes','visualizar');
    $passos = OAuthV3ChecklistService::passos();
    $score = OAuthV3ChecklistService::score();
    $pageTitle='Checklist OAuth Tiny V3';
    require __DIR__.'/../../views/oauth_v3_checklist.php';
  }
}
