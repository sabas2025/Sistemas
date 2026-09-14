<?php
/**
 * DEPRECIADO — melhoria 10 da seção 8 (relatório V104.49.3-R6).
 *
 * Rotas legadas de atualização extraídas do DashboardController. Concentram a maior parte do DDL
 * em runtime do sistema, em dezenas de blocos try/catch que engolem o erro e seguem — o oposto do
 * que as migrations versionadas fazem hoje. Com o schema consolidado e o fluxo de
 * "Migrações Seguras", isto deixou de ter função e passou a ser, sobretudo, superfície de risco.
 *
 * Na auditoria da R6 ficou claro que estas rotas JÁ eram inalcançáveis: FastRouteDispatcherService
 * intercepta todo 'atualizar-v*' antes do DashboardController e envia para
 * MigrationController::legacyBlocked(). Ou seja, o código estava morto mas continuava presente e
 * instanciável — bastava alguém reintroduzir um case para religar todo o DDL em runtime sem
 * perceber.
 *
 * A aposentadoria é feita em duas camadas: as 19 rotas foram removidas do DashboardController e
 * este controller passa a RECUSAR execução, a menos que alguém ligue explicitamente
 * commercial.allow_legacy_db_upgrade — uma chave que não existe no config de exemplo nem é gravada
 * pelo instalador, e que serve apenas para uma recuperação manual assistida em base muito antiga.
 *
 * Não acrescente rotas aqui. Escreva uma migration em database/migrations/.
 */
class LegacyDatabaseUpgradeController {
  private PDO $pdo;
  public function __construct() { $this->pdo = Database::getConnection(); }
  private function db(string $table): PDO { return Database::forTable($table); }

  /** O controller está desligado salvo liberação explícita e consciente. */
  public static function habilitado(): bool {
    if (!class_exists('App')) return false;
    try { return !empty(App::config()['commercial']['allow_legacy_db_upgrade']); }
    catch (Throwable $e) { return false; }
  }

  public function dispatch(string $page): void {
    Auth::requireLogin();
    if (!self::habilitado()) {
      if (class_exists('Audit')) {
        try {
          Audit::event('legacy.db_upgrade.desativado','alerta',[
            'codigo_erro'=>'LEGACY_DB_UPGRADE_DISABLED',
            'mensagem'=>'Rota legada de atualização de schema recusada: o controller está depreciado e desligado.',
            'contexto'=>['page'=>substr($page,0,190)],
            'acao_recomendada'=>'Use Migrações Seguras (database/migrations). Religar exige commercial.allow_legacy_db_upgrade e só se justifica em recuperação manual de base muito antiga.'
          ]);
        } catch (Throwable $e) { if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
      }
      http_response_code(410);
      header('Content-Type: text/plain; charset=UTF-8');
      exit('Atualização legada desativada. Use Migrações Seguras.');
    }
    switch ($page) {
      case 'atualizar-v17-tiny-v3': $this->atualizarV17TinyV3(); break;
      case 'atualizar-v18-tiny-v3-final': $this->atualizarV18TinyV3Final(); break;
      case 'atualizar-v19-tiny-v3-operacional': $this->atualizarV19TinyV3Operacional(); break;
      case 'atualizar-v20-tiny-v3-seguranca': $this->atualizarV20TinyV3Seguranca(); break;
      case 'atualizar-v21-tiny-v3-final': $this->atualizarV21TinyV3Final(); break;
      case 'atualizar-v12-produtos-vsm': $this->atualizarV12ProdutosVsm(); break;
      case 'atualizar-v14-homologacao-real': $this->atualizarV14HomologacaoReal(); break;
      case 'atualizar-v16-correcoes-finais': $this->atualizarV16CorrecoesFinais(); break;
      case 'atualizar-v15-homologacao-final': $this->atualizarV15HomologacaoFinal(); break;
      case 'atualizar-v16-final': $this->atualizarV16Final(); break;
      case 'atualizar-v22-auditoria-seguranca': $this->atualizarV22AuditoriaSeguranca(); break;
      case 'atualizar-v25-final': $this->atualizarV25Final(); break;
      case 'atualizar-v26-ultimate': $this->atualizarV26Ultimate(); break;
      case 'atualizar-v31-regras-sincronizacao': $this->atualizarV31RegrasSincronizacao(); break;
      case 'atualizar-v38-orquestracao': $this->atualizarV38Orquestracao(); break;
      case 'atualizar-v23-seguranca-2fa': $this->atualizarV23Seguranca2FA(); break;
      case 'atualizar-v43-fiscal-dashboard-install': $this->atualizarV43FiscalDashboardInstall(); break;
      case 'atualizar-v45-governanca-produto-vsm': $this->atualizarV45GovernancaProdutoVsm(); break;
      case 'atualizar-v53-enterprise-stabilization': $this->atualizarV53EnterpriseStabilization(); break;
      default: http_response_code(404); throw new RuntimeException('Atualização legada não encontrada.');
    }
  }

  private function safeAddColumn(array &$mensagens, string $table, string $column, string $definition): void {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
      throw new InvalidArgumentException('Identificador SQL inválido em safeAddColumn.');
    }
    try {
      $created = Database::addColumnIfMissing($table, $column, $definition);
      $mensagens[] = $created ? "OK: coluna {$table}.{$column} adicionada." : "IGNORADO: {$table}.{$column} já existe.";
    } catch (PDOException $e) {
      $msg = strtolower($e->getMessage());
      $isDuplicate = (string)$e->getCode() === '42S21' || str_contains($msg, '1060') || str_contains($msg, 'duplicate column');
      if ($isDuplicate || Database::columnExists($table, $column)) {
        $mensagens[] = "IGNORADO: {$table}.{$column} já existia no banco.";
        return;
      }
      throw $e;
    }
  }

  private function atualizarV17TinyV3(): void {
    PermissionService::require('database','validar');
    Csrf::validate();
    $mensagens=[]; $erros=[];
    $cols = [
      'tiny_v3_auth_url'=>'VARCHAR(255) NULL',
      'tiny_v3_token_url'=>'VARCHAR(255) NULL',
      'tiny_v3_client_id'=>'VARCHAR(255) NULL',
      'tiny_v3_client_secret'=>'TEXT NULL',
      'tiny_v3_redirect_uri'=>'VARCHAR(255) NULL',
      'tiny_v3_scopes'=>'TEXT NULL',
      'tiny_v3_manual_access_token'=>'TEXT NULL',
      'tiny_v3_manual_refresh_token'=>'TEXT NULL',
    ];
    foreach($cols as $col=>$def){ try { $this->safeAddColumn($mensagens, 'configuracoes_integracao', $col, $def); } catch(Throwable $e){ $erros[]=$col.': '.$e->getMessage(); } }
    try { Database::forTable('tiny_v3_tokens')->exec("CREATE TABLE IF NOT EXISTS tiny_v3_tokens (id INT AUTO_INCREMENT PRIMARY KEY, access_token TEXT NOT NULL, refresh_token TEXT NULL, expires_at DATETIME NULL, scope TEXT NULL, origem VARCHAR(30) DEFAULT 'manual', criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP, atualizado_em DATETIME NULL, INDEX idx_tiny_v3_tokens_expira(expires_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); $mensagens[]='OK: tiny_v3_tokens verificada.'; } catch(Throwable $e){ $erros[]='tiny_v3_tokens: '.$e->getMessage(); }
    try { Database::forTable('tiny_v3_endpoint_logs')->exec("CREATE TABLE IF NOT EXISTS tiny_v3_endpoint_logs (id INT AUTO_INCREMENT PRIMARY KEY, endpoint VARCHAR(180) NULL, metodo VARCHAR(10) NULL, http_code INT NULL, sucesso TINYINT DEFAULT 0, tempo_ms INT NULL, trace_id VARCHAR(80) NULL, request_body LONGTEXT NULL, response_body LONGTEXT NULL, erro TEXT NULL, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_tiny_v3_endpoint_logs_data(criado_em), INDEX idx_tiny_v3_endpoint_logs_trace(trace_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); $mensagens[]='OK: tiny_v3_endpoint_logs verificada.'; } catch(Throwable $e){ $erros[]='tiny_v3_endpoint_logs: '.$e->getMessage(); }
    Audit::event('database.v17_tiny_v3','info',['mensagem'=>'Atualização V17 Tiny V3 executada de forma idempotente.','retorno'=>['mensagens'=>$mensagens,'erros'=>$erros]]);
    redirect('index.php?page=tiny-v3-ficha&db=ok');
  }

  private function atualizarV18TinyV3Final(): void {
    PermissionService::require('database','validar');
    Csrf::validate();
    $mensagens=[];
    try {
      foreach(TinyV3EndpointCatalog::defaults() as $key=>$endpoint){
        $this->safeAddColumn($mensagens,'configuracoes_integracao','tiny_v3_'.$key,'VARCHAR(255) NULL');
      }
      try { Database::forTable('tiny_v3_endpoint_logs')->exec("CREATE TABLE IF NOT EXISTS tiny_v3_endpoint_logs (id INT AUTO_INCREMENT PRIMARY KEY, endpoint VARCHAR(180) NULL, metodo VARCHAR(10) NULL, http_code INT NULL, sucesso TINYINT DEFAULT 0, tempo_ms INT NULL, trace_id VARCHAR(80) NULL, request_body LONGTEXT NULL, response_body LONGTEXT NULL, erro TEXT NULL, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_tiny_v3_endpoint_logs_data(criado_em), INDEX idx_tiny_v3_endpoint_logs_trace(trace_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); $mensagens[]='OK: tiny_v3_endpoint_logs verificada.'; } catch(Throwable $e){ $mensagens[]='ERRO: tiny_v3_endpoint_logs: '.$e->getMessage(); }
      try { Database::forTable('tiny_v3_tokens')->exec("CREATE TABLE IF NOT EXISTS tiny_v3_tokens (id INT AUTO_INCREMENT PRIMARY KEY, access_token TEXT NOT NULL, refresh_token TEXT NULL, expires_at DATETIME NULL, scope TEXT NULL, origem VARCHAR(30) DEFAULT 'manual', criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP, atualizado_em DATETIME NULL, INDEX idx_tiny_v3_tokens_expira(expires_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); $mensagens[]='OK: tiny_v3_tokens verificada.'; } catch(Throwable $e){ $mensagens[]='ERRO: tiny_v3_tokens: '.$e->getMessage(); }
      $resultado=(new DatabaseValidationService($this->pdo))->executar();
      Audit::event('database.update_v18.tiny_v3_final','sucesso',['mensagem'=>'Atualização V18 Tiny V3 final executada.','contexto'=>['mensagens'=>$mensagens,'validacao'=>$resultado]]);
    } catch(Throwable $e){
      Audit::exception($e,'database.update_v18.tiny_v3_final.erro',['codigo_erro'=>'DB_UPDATE_V18_ERROR','contexto'=>$mensagens]);
      throw $e;
    }
    $pageTitle='Atualização V18 Tiny V3 Final';
    require __DIR__.'/../../views/layout_top.php';
    echo '<div class="card-soft"><h4>Atualização V18 Tiny V3 Final executada</h4><p>Endpoints editáveis, logs dedicados e validação Tiny V3 reforçada.</p><pre class="json-box">'.e(implode("\n", $mensagens)).'</pre><a class="btn btn-primary" href="index.php?page=tiny-v3-ficha">Ficha Tiny V3</a> <a class="btn btn-outline-primary" href="index.php?page=validar-banco">Validar Banco</a></div>';
    require __DIR__.'/../../views/layout_bottom.php';
  }

  private function atualizarV19TinyV3Operacional(): void {
    PermissionService::require('database','validar');
    Csrf::validate();
    $mensagens=[];
    try {
      $this->safeAddColumn($mensagens,'configuracoes_integracao','tiny_v3_ambiente',"ENUM('homologacao','producao') DEFAULT 'homologacao'");
      $this->safeAddColumn($mensagens,'tiny_v3_endpoint_logs','request_body','LONGTEXT NULL');
      $this->safeAddColumn($mensagens,'tiny_v3_endpoint_logs','response_body','LONGTEXT NULL');
      $this->safeAddColumn($mensagens,'tiny_v3_endpoint_logs','erro','TEXT NULL');
      try { Database::forTable('tiny_v3_endpoint_logs')->exec('CREATE INDEX idx_tiny_v3_endpoint_logs_trace ON tiny_v3_endpoint_logs(trace_id)'); $mensagens[]='OK: índice tiny_v3_endpoint_logs.trace_id criado.'; } catch(Throwable $e){ $mensagens[]='IGNORADO/ATENÇÃO: índice trace V3: '.$e->getMessage(); }
      try { Database::forTable('configuracoes_integracao')->exec("UPDATE configuracoes_integracao SET tiny_v3_operacional=0 WHERE tiny_versao='v3' AND tiny_v3_operacional IS NULL"); $mensagens[]='OK: proteção operacional Tiny V3 revisada.'; } catch(Throwable $e){ $mensagens[]='ATENÇÃO: proteção V3: '.$e->getMessage(); }
      $resultado=(new DatabaseValidationService($this->pdo))->executar();
      Audit::event('database.update_v19.tiny_v3_operacional','sucesso',[ 'mensagem'=>'Atualização V19 Tiny V3 operacional/logs completos executada.', 'contexto'=>['mensagens'=>$mensagens,'validacao'=>$resultado] ]);
    } catch(Throwable $e){
      Audit::exception($e,'database.update_v19.tiny_v3_operacional.erro',['codigo_erro'=>'DB_UPDATE_V19_ERROR','contexto'=>$mensagens]);
      throw $e;
    }
    $pageTitle='Atualização V19 Tiny V3 Operacional';
    require __DIR__.'/../../views/layout_top.php';
    echo '<div class="card-soft"><h4>Atualização V19 executada</h4><p>Tiny V3 protegido por homologação operacional, OAuth obrigatório em produção e logs técnicos com request/response.</p><pre class="json-box">'.e(implode("\n", $mensagens)).'</pre><a class="btn btn-primary" href="index.php?page=tiny-v3-ficha">Ficha Tiny V3</a> <a class="btn btn-outline-primary" href="index.php?page=validar-banco">Validar Banco</a></div>';
    require __DIR__.'/../../views/layout_bottom.php';
  }

  private function atualizarV20TinyV3Seguranca(): void {
    PermissionService::require('database','validar');
    Csrf::validate();
    $mensagens=[];
    try {
      $this->safeAddColumn($mensagens,'tiny_v3_tokens','ambiente',"ENUM('homologacao','producao') DEFAULT 'homologacao' AFTER id");
      $this->safeAddColumn($mensagens,'tiny_v3_endpoint_logs','request_body','LONGTEXT NULL');
      $this->safeAddColumn($mensagens,'tiny_v3_endpoint_logs','response_body','LONGTEXT NULL');
      $this->safeAddColumn($mensagens,'tiny_v3_endpoint_logs','erro','TEXT NULL');
      try { Database::addIndexIfMissing('tiny_v3_tokens','idx_tiny_v3_tokens_ambiente','INDEX idx_tiny_v3_tokens_ambiente (ambiente)'); $mensagens[]='OK: índice tiny_v3_tokens.ambiente criado.'; } catch(Throwable $e){ $mensagens[]='IGNORADO/ATENÇÃO: índice ambiente V3: '.$e->getMessage(); }
      try { Database::forTable('tiny_v3_tokens')->exec("UPDATE tiny_v3_tokens SET ambiente='homologacao' WHERE ambiente IS NULL OR ambiente='' "); $mensagens[]='OK: tokens antigos associados ao ambiente homologação.'; } catch(Throwable $e){ $mensagens[]='ATENÇÃO: normalização de ambiente nos tokens: '.$e->getMessage(); }
      $resultado=(new DatabaseValidationService($this->pdo))->executar();
      Audit::event('database.update_v20.tiny_v3_seguranca','sucesso',[ 'mensagem'=>'Atualização V20 Tiny V3 segurança/ambiente executada.', 'contexto'=>['mensagens'=>$mensagens,'validacao'=>$resultado] ]);
    } catch(Throwable $e){
      Audit::exception($e,'database.update_v20.tiny_v3_seguranca.erro',['codigo_erro'=>'DB_UPDATE_V20_ERROR','contexto'=>$mensagens]);
      throw $e;
    }
    $pageTitle='Atualização V20 Tiny V3 Segurança';
    require __DIR__.'/../../views/layout_top.php';
    echo '<div class="card-soft"><h4>Atualização V20 executada</h4><p>Tokens Tiny V3 por ambiente, checklist obrigatório, OAuth seguro e logs com dados sensíveis mascarados.</p><pre class="json-box">'.e(implode("\n", $mensagens)).'</pre><a class="btn btn-primary" href="index.php?page=tiny-v3-ficha">Ficha Tiny V3</a> <a class="btn btn-outline-primary" href="index.php?page=validar-banco">Validar Banco</a></div>';
    require __DIR__.'/../../views/layout_bottom.php';
  }

  private function atualizarV21TinyV3Final(): void {
    PermissionService::require('database','validar');
    Csrf::validate();
    $mensagens=[];
    try {
      $this->safeAddColumn($mensagens,'tiny_v3_tokens','ambiente',"ENUM('homologacao','producao') DEFAULT 'homologacao' AFTER id");
      $this->safeAddColumn($mensagens,'tiny_v3_endpoint_logs','request_body','LONGTEXT NULL');
      $this->safeAddColumn($mensagens,'tiny_v3_endpoint_logs','response_body','LONGTEXT NULL');
      $this->safeAddColumn($mensagens,'tiny_v3_endpoint_logs','erro','TEXT NULL');
      SchemaRuntimePolicyService::requireTable('homologacao_checklist', 'prontidão operacional Tiny V3');
      $checks = [
        ['tiny_v3_token','Tiny V3 OAuth válido','Salvar token OAuth por ambiente e validar access/refresh token.'],
        ['tiny_v3_refresh','Tiny V3 refresh token','Executar renovação automática e confirmar sucesso.'],
        ['tiny_v3_produto_sku','Tiny V3 produto por SKU','Consultar SKU real e confirmar comparação exata.'],
        ['tiny_v3_estoque','Tiny V3 estoque por SKU','Consultar/atualizar estoque em SKU real de homologação.'],
        ['tiny_v3_logs','Tiny V3 logs técnicos','Confirmar tiny_v3_endpoint_logs com request/response mascarados.'],
        ['vsm_estoque_consulta','VSM consulta estoque por SKU','Validar endpoint real de consulta de estoque da VSM para reconciliação.'],
      ];
      $st = $this->db('homologacao_checklist')->prepare("INSERT IGNORE INTO homologacao_checklist(chave,titulo,descricao,status) VALUES(?,?,?,'pendente')");
      foreach($checks as $c){ $st->execute($c); }
      try { Database::addIndexIfMissing('tiny_v3_tokens','idx_tiny_v3_tokens_ambiente','INDEX idx_tiny_v3_tokens_ambiente (ambiente)'); $mensagens[]='OK: índice tiny_v3_tokens.ambiente verificado/criado.'; } catch(Throwable $e){ $mensagens[]='IGNORADO/ATENÇÃO: índice ambiente V3: '.$e->getMessage(); }
      $resultado=(new DatabaseValidationService($this->pdo))->executar();
      Audit::event('database.update_v21.tiny_v3_final','sucesso',[ 'mensagem'=>'Atualização V21 Tiny V3 final executada.', 'contexto'=>['mensagens'=>$mensagens,'validacao'=>$resultado] ]);
    } catch(Throwable $e){
      Audit::exception($e,'database.update_v21.tiny_v3_final.erro',['codigo_erro'=>'DB_UPDATE_V21_ERROR','contexto'=>$mensagens]);
      throw $e;
    }
    $pageTitle='Atualização V21 Tiny V3 Final';
    require __DIR__.'/../../views/layout_top.php';
    echo '<div class="card-soft"><h4>Atualização V21 executada</h4><p>Checklist obrigatório Tiny V3, tokens por ambiente, logs técnicos e reconciliação VSM reforçados.</p><pre class="json-box">'.e(implode("
", $mensagens)).'</pre><a class="btn btn-primary" href="index.php?page=tiny-v3-ficha">Ficha Tiny V3</a> <a class="btn btn-outline-primary" href="index.php?page=homologacao">Checklist Homologação</a></div>';
    require __DIR__.'/../../views/layout_bottom.php';
  }

  private function atualizarV12ProdutosVsm(): void {
    PermissionService::require('configuracoes','editar');
    $sqlPath = __DIR__.'/../../database/update_v12_produtos_vsm.sql';
    $sql = is_file($sqlPath) ? file_get_contents($sqlPath) : '';
    if ($sql === '') { $mensagens=['Update legado V12 removido; use database/install_final_v104_12.sql e Validar Banco.']; }
    $linhas = array_filter(explode("
", (string)$sql), fn($l) => !str_starts_with(trim($l), '--'));
    $sqlLimpo = implode("
", $linhas);
    $mensagens = [];
    foreach (array_filter(array_map('trim', explode(';', $sqlLimpo))) as $stmt) {
      if ($stmt === '') continue;
      try {
        $this->pdo->exec($stmt);
        $mensagens[] = 'OK: '.substr(preg_replace('/\s+/', ' ', $stmt), 0, 120);
      } catch (Throwable $e) {
        if (str_contains($e->getMessage(), 'Duplicate column name')) {
          $mensagens[] = 'IGNORADO: coluna já existe.';
        } else {
          Audit::exception($e, 'database.update_v12.erro', ['codigo_erro'=>'DB_UPDATE_V12_ERROR']);
          throw $e;
        }
      }
    }
    Audit::event('database.update_v12.produtos_vsm','sucesso',['mensagem'=>'Atualização V12 de produtos VSM executada.','contexto'=>$mensagens]);
    $pageTitle = 'Atualização V12 executada';
    require __DIR__.'/../../views/layout_top.php';
    echo '<div class="card-soft"><h4>Atualização V12 executada</h4><p>Banco ajustado para estoque/status VSM → Tiny.</p><pre class="json-box">'.e(implode("\n", $mensagens)).'</pre><a class="btn btn-primary" href="index.php?page=produtos-vsm">Ir para Produtos VSM</a></div>';
    require __DIR__.'/../../views/layout_bottom.php';
  }

  private function atualizarV14HomologacaoReal(): void {
    PermissionService::require('configuracoes','editar');
    $sqlPath = __DIR__.'/../../database/update_v14_homologacao_real.sql';
    $sql = is_file($sqlPath) ? file_get_contents($sqlPath) : '';
    if ($sql === '') { $mensagens=['Update legado V14 removido; use database/install_final_v104_12.sql e Validar Banco.']; }
    $linhas = array_filter(explode("
", (string)$sql), fn($l) => !str_starts_with(trim($l), '--'));
    $sqlLimpo = implode("
", $linhas);
    $mensagens = [];
    foreach (array_filter(array_map('trim', explode(';', $sqlLimpo))) as $stmt) {
      if ($stmt === '') continue;
      try { $this->pdo->exec($stmt); $mensagens[] = 'OK: '.substr(preg_replace('/\s+/', ' ', $stmt), 0, 160); }
      catch (Throwable $e) {
        $msg=$e->getMessage();
        if (str_contains($msg,'Duplicate column') || str_contains($msg,'already exists')) $mensagens[]='IGNORADO: item já existia.';
        else { Audit::exception($e, 'database.update_v14.erro', ['codigo_erro'=>'DB_UPDATE_V14_ERROR']); throw $e; }
      }
    }
    Audit::event('database.update_v14.homologacao_real','sucesso',['mensagem'=>'Atualização V14 de homologação real executada.','contexto'=>$mensagens]);
    $pageTitle = 'Atualização V14 executada';
    require __DIR__.'/../../views/layout_top.php';
    echo '<div class="card-soft"><h4>Atualização V14 executada</h4><p>Banco ajustado para pendências, antes/depois de estoque/status e ações de homologação real.</p><pre class="json-box">'.e(implode("\n", $mensagens)).'</pre><a class="btn btn-primary" href="index.php?page=produtos-pendencias">Ir para Pendências</a></div>';
    require __DIR__.'/../../views/layout_bottom.php';
  }

  private function atualizarV16CorrecoesFinais(): void {
    PermissionService::require('configuracoes','editar');
    Csrf::validate();
    $mensagens=[];
    $runSchemaStep=function(callable $fn, string $ok) use (&$mensagens){
      try { $created = $fn(); $mensagens[] = ($created === false ? 'IGNORADO: ' : 'OK: ').$ok.($created === false ? ' já existia.' : ''); }
      catch(Throwable $e){
        $m=$e->getMessage();
        if(str_contains($m,'Duplicate column') || str_contains($m,'already exists') || str_contains($m,'Duplicate entry') || str_contains($m,'Multiple primary key')) $mensagens[]='IGNORADO: '.$ok.' já existia.';
        else { $mensagens[]='ERRO: '.$ok.' => '.$m; Audit::exception($e,'database.update_v16.erro',['codigo_erro'=>'DB_UPDATE_V16_ERROR','contexto'=>['item'=>$ok]]); }
      }
    };
    $runSchemaStep(fn()=>Database::addColumnIfMissing('configuracoes_integracao','vsm_endpoint_consulta_estoque',"VARCHAR(255) DEFAULT '/api/estoque/consulta'"), 'Endpoint de consulta de estoque VSM');
    $runSchemaStep(fn()=>Database::addColumnIfMissing('produto_pendencias','resolvido_por','INT NULL'), 'produto_pendencias.resolvido_por');
    $runSchemaStep(fn()=>Database::addColumnIfMissing('produto_pendencias','resolvido_em','DATETIME NULL'), 'produto_pendencias.resolvido_em');
    $runSchemaStep(fn()=>Database::execIdempotent(Database::forTable('notificacoes'), "ALTER TABLE notificacoes MODIFY COLUMN severidade ENUM('info','sucesso','alerta','erro','critico') DEFAULT 'info'"), 'notificacoes.severidade com critico');
    Database::forTable('homologacao_checklist')->exec("CREATE TABLE IF NOT EXISTS homologacao_checklist (id INT AUTO_INCREMENT PRIMARY KEY, chave VARCHAR(80) NOT NULL UNIQUE, titulo VARCHAR(180) NOT NULL, descricao TEXT NULL, status ENUM('pendente','ok','falha','nao_aplicavel') DEFAULT 'pendente', resultado LONGTEXT NULL, trace_id VARCHAR(80) NULL, atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_homologacao_status(status), INDEX idx_homologacao_chave(chave)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    Database::forTable('homologacao_checklist')->exec("INSERT IGNORE INTO homologacao_checklist(chave,titulo,descricao,status) VALUES ('tiny_token','Testar token Tiny','Executar teste de conexão Tiny V2 com SKU real ou SKU de homologação.','pendente'),('vsm_conexao','Testar conexão VSM','Validar DNS, HTTPS, token e endpoint configurado da VSM.','pendente'),('vsm_produto_tiny','Produto VSM para Tiny','Simular ou receber produto real da VSM e criar/atualizar no Tiny.','pendente'),('vsm_estoque_tiny','Estoque VSM para Tiny','Atualizar saldo de SKU existente no Tiny a partir da VSM.','pendente'),('vsm_status_tiny','Status ativo/inativo VSM para Tiny','Inativar/ativar produto pela VSM respeitando bloqueio de estoque.','pendente'),('tiny_baixa_vsm','Baixa Tiny para VSM','Receber evento do Tiny e enviar baixa de estoque para a VSM.','pendente'),('auditoria_trace','Auditoria e Trace ID','Conferir payload original, transformado, enviado e retorno.','pendente'),('fila_dlq','Fila morta / DLQ','Forçar erro controlado e validar reprocessamento/fila morta.','pendente'),('reconciliacao','Reconciliação de estoque','Comparar saldo Tiny x VSM e registrar divergência.','pendente')");
    Database::forTable('permissoes_perfil')->exec("INSERT IGNORE INTO permissoes_perfil(perfil,modulo,acao,permitido) VALUES ('admin','homologacao','visualizar',1),('admin','homologacao','executar',1),('gerente','homologacao','visualizar',1),('admin','banco','validar',1),('gerente','banco','validar',1)");
    Audit::event('database.update_v16.correcoes_finais','sucesso',['mensagem'=>'Atualização V16 executada.','contexto'=>$mensagens]);
    $pageTitle='Atualização V16 executada';
    require __DIR__.'/../../views/layout_top.php';
    echo '<div class="card-soft"><h4>Atualização V16 executada</h4><p>Correções finais aplicadas: banco, pendências, notificações críticas e checklist.</p><pre class="json-box">'.e(implode("\n", $mensagens)).'</pre><a class="btn btn-primary" href="index.php?page=validar-banco">Validar Banco</a></div>';
    require __DIR__.'/../../views/layout_bottom.php';
  }

  private function atualizarV15HomologacaoFinal(): void {
    PermissionService::require('configuracoes','editar');
    Csrf::validate();
    $sqlPath = __DIR__.'/../../database/update_v15_homologacao_final.sql';
    $sql = is_file($sqlPath) ? file_get_contents($sqlPath) : '';
    if ($sql === '') { $mensagens=['Update legado V15 removido; use database/install_final_v104_12.sql e Validar Banco.']; }
    $linhas = array_filter(explode("\n", (string)$sql), fn($l) => !str_starts_with(trim($l), '--'));
    $sqlLimpo = implode("\n", $linhas);
    $mensagens=[];
    foreach (array_filter(array_map('trim', explode(';', $sqlLimpo))) as $stmt) {
      if($stmt==='') continue;
      try { $this->pdo->exec($stmt); $mensagens[]='OK: '.substr(preg_replace('/\s+/', ' ', $stmt),0,180); }
      catch(Throwable $e){
        $msg=$e->getMessage();
        if(str_contains($msg,'Duplicate column') || str_contains($msg,'already exists') || str_contains($msg,'Duplicate entry')) $mensagens[]='IGNORADO: item já existia.';
        else { Audit::exception($e,'database.update_v15.erro',['codigo_erro'=>'DB_UPDATE_V15_ERROR']); throw $e; }
      }
    }
    Audit::event('database.update_v15.homologacao_final','sucesso',['mensagem'=>'Atualização V15 de homologação final executada.','contexto'=>$mensagens]);
    $pageTitle='Atualização V15 executada';
    require __DIR__.'/../../views/layout_top.php';
    echo '<div class="card-soft"><h4>Atualização V15 executada</h4><p>Banco ajustado para checklist final, controle Tiny V3 e políticas de inativo com estoque.</p><pre class="json-box">'.e(implode("\n", $mensagens)).'</pre><a class="btn btn-primary" href="index.php?page=homologacao">Ir para Checklist de Homologação</a></div>';
    require __DIR__.'/../../views/layout_bottom.php';
  }

  private function atualizarV16Final(): void {
    PermissionService::require('configuracoes','editar');
    Csrf::validate();
    $mensagens=[];
    try {
      $this->safeAddColumn($mensagens,'produto_pendencias','resolvido_por','INT NULL AFTER resolucao');
      $this->safeAddColumn($mensagens,'produto_pendencias','resolvido_em','DATETIME NULL AFTER resolvido_por');
      $this->safeAddColumn($mensagens,'configuracoes_integracao','tiny_v3_operacional','TINYINT DEFAULT 0');
      $this->safeAddColumn($mensagens,'configuracoes_integracao','bloquear_inativo_com_estoque','TINYINT DEFAULT 1');
      $this->safeAddColumn($mensagens,'estoque_divergencias','acao_recomendada','TEXT NULL');
      try { Database::forTable('logs_integracao')->exec("ALTER TABLE logs_integracao MODIFY nivel ENUM('info','alerta','erro','critico') DEFAULT 'info'"); $mensagens[]='OK: logs_integracao.nivel aceita crítico.'; } catch(Throwable $e){ $mensagens[]='ATENÇÃO: logs_integracao.nivel não alterado: '.$e->getMessage(); }
      try { $this->db('permissoes_perfil')->exec("INSERT IGNORE INTO permissoes_perfil(perfil,modulo,acao,permitido) VALUES ('admin','database','validar',1),('gerente','database','validar',1),('admin','homologacao','relatorio',1),('gerente','homologacao','relatorio',1)"); $mensagens[]='OK: permissões V16 inseridas.'; } catch(Throwable $e){ $mensagens[]='ATENÇÃO: permissões V16: '.$e->getMessage(); }
      try { $this->db('homologacao_checklist')->exec("INSERT IGNORE INTO homologacao_checklist(chave,titulo,descricao,status) VALUES ('database_schema','Validar banco de dados','Executar Validação do Banco e confirmar ausência de erros críticos.','pendente'),('relatorio_final','Gerar relatório final','Gerar relatório HTML/PDF com checklist, Trace IDs e evidências.','pendente')"); $mensagens[]='OK: novos itens de homologação inseridos.'; } catch(Throwable $e){ $mensagens[]='ATENÇÃO: checklist V16: '.$e->getMessage(); }
      $resultado=(new DatabaseValidationService($this->pdo))->executar();
      Audit::event('database.update_v16.final','sucesso',['mensagem'=>'Atualização V16 final executada.','contexto'=>['mensagens'=>$mensagens,'validacao'=>$resultado]]);
    } catch(Throwable $e) {
      Audit::exception($e,'database.update_v16.erro',['codigo_erro'=>'DB_UPDATE_V16_ERROR','contexto'=>$mensagens]);
      throw $e;
    }
    $pageTitle='Atualização V16 executada';
    require __DIR__.'/../../views/layout_top.php';
    echo '<div class="card-soft"><h4>Atualização V16 executada</h4><p>Banco ajustado para correções finais, validação estrutural e relatório de homologação.</p><pre class="json-box">'.e(implode("\n", $mensagens)).'</pre><a class="btn btn-primary" href="index.php?page=validar-banco">Validar Banco</a> <a class="btn btn-outline-primary" href="index.php?page=homologacao">Checklist</a></div>';
    require __DIR__.'/../../views/layout_bottom.php';
  }

  private function atualizarV22AuditoriaSeguranca(): void {
    PermissionService::require('database','validar');
    Csrf::validate();
    $sqlPath = __DIR__.'/../../database/update_v22_auditoria_seguranca_100.sql';
    $sql = is_file($sqlPath) ? file_get_contents($sqlPath) : '';
    if ($sql === '') { $mensagens=['Update legado V22 removido; use database/install_final_v104_12.sql e Validar Banco.']; }
    $parts = array_filter(array_map('trim', explode(';', $sql)));
    $ok=0; $erros=[];
    foreach($parts as $q){
      if($q==='') continue;
      try { $this->pdo->exec($q); $ok++; }
      catch(Throwable $e){ if(stripos($e->getMessage(),'Duplicate')===false && stripos($e->getMessage(),'already exists')===false) $erros[]=$e->getMessage(); }
    }
    $mensagens=[];
    try { $this->safeAddColumn($mensagens,'usuarios','two_factor_enabled','TINYINT DEFAULT 0 AFTER deve_trocar_senha'); } catch(Throwable $e){ $mensagens[]='ATENÇÃO usuarios.two_factor_enabled: '.$e->getMessage(); }
    try { $this->safeAddColumn($mensagens,'usuarios','two_factor_secret','TEXT NULL AFTER two_factor_enabled'); } catch(Throwable $e){ $mensagens[]='ATENÇÃO usuarios.two_factor_secret: '.$e->getMessage(); }
    try { $this->safeAddColumn($mensagens,'usuarios','two_factor_created_at','DATETIME NULL AFTER two_factor_secret'); } catch(Throwable $e){ $mensagens[]='ATENÇÃO usuarios.two_factor_created_at: '.$e->getMessage(); }
    try { $this->safeAddColumn($mensagens,'usuarios','two_factor_last_verified_at','DATETIME NULL AFTER two_factor_created_at'); } catch(Throwable $e){ $mensagens[]='ATENÇÃO usuarios.two_factor_last_verified_at: '.$e->getMessage(); }
    try { $this->safeAddColumn($mensagens,'fila_integracao','prioridade',"ENUM('critica','alta','normal','baixa') DEFAULT 'normal' AFTER tipo"); } catch(Throwable $e){ $mensagens[]='ATENÇÃO fila_integracao.prioridade: '.$e->getMessage(); }
    try { $this->safeAddColumn($mensagens,'fila_integracao','categoria','VARCHAR(60) NULL AFTER prioridade'); } catch(Throwable $e){ $mensagens[]='ATENÇÃO fila_integracao.categoria: '.$e->getMessage(); }
    try { $this->db('permissoes_perfil')->exec("INSERT IGNORE INTO permissoes_perfil(perfil,modulo,acao,permitido) VALUES ('admin','ficha_tecnica','visualizar',1),('admin','seguranca','visualizar',1),('admin','seguranca','gerenciar',1),('admin','auditoria','exportar',1),('gerente','ficha_tecnica','visualizar',1),('gerente','seguranca','visualizar',1),('gerente','auditoria','exportar',1),('operador','ficha_tecnica','visualizar',0),('operador','seguranca','visualizar',0),('operador','auditoria','exportar',0)"); } catch(Throwable $e){ $erros[]=$e->getMessage(); }
    Audit::event('database.update_v22','sucesso',['mensagem'=>'Estrutura V22 aplicada/validada.','contexto'=>['ok'=>$ok,'erros'=>$erros,'mensagens'=>$mensagens]]);
    redirect('index.php?page=validar-banco&v22=1');
  }

  private function atualizarV25Final(): void {
    PermissionService::require('database','validar');
    Csrf::validate();
    $mensagens=[];
    try {
      ProductionReadinessV24Service::gerarSnapshotUpgrade('V25');
      if(file_exists(__DIR__.'/../../database/update_v25_final_recomendacoes.sql')){
        $mensagens = array_merge($mensagens, SafeSqlUpgradeService::runStatements($this->pdo, file_get_contents(__DIR__.'/../../database/update_v25_final_recomendacoes.sql')));
      }
      $mensagens[] = SafeSqlUpgradeService::addColumnIfMissing($this->pdo,'auditoria_assinaturas','hash_anterior','VARCHAR(128) NULL');
      $mensagens[] = SafeSqlUpgradeService::addColumnIfMissing($this->pdo,'auditoria_assinaturas','hash_canonico','LONGTEXT NULL');
      $mensagens[] = SafeSqlUpgradeService::addColumnIfMissing($this->pdo,'auditoria_assinaturas','cadeia_valida','TINYINT DEFAULT 1');
      $tests = PostInstallTestService::run();
      $score = PostInstallTestService::score($tests);
      if(SafeSqlUpgradeService::tableExists($this->pdo,'post_install_tests')){
        $st=$this->db('post_install_tests')->prepare('INSERT INTO post_install_tests(score,resultado_json,trace_id) VALUES(?,?,?)');
        $st->execute([$score,json_encode($tests,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),RequestContext::id()]);
      }
      Audit::event('database.update_v25.final','sucesso',['mensagem'=>'V25 final aplicada/validada.','contexto'=>['mensagens'=>$mensagens,'post_install_score'=>$score]]);
    } catch(Throwable $e){ Audit::exception($e,'database.update_v25.erro',['codigo_erro'=>'DB_UPDATE_V25_ERROR','contexto'=>$mensagens]); throw $e; }
    redirect('index.php?page=central-tecnica');
  }

  private function atualizarV24ProductionReady(): void {
    PermissionService::require('database','validar');
    Csrf::validate();
    ProductionReadinessV24Service::gerarSnapshotUpgrade('V24');
    $sqlPath = __DIR__.'/../../database/update_v24_production_ready.sql';
    $ok=0; $erros=[];
    if(file_exists($sqlPath)){
      $sql=file_get_contents($sqlPath);
      $parts=array_filter(array_map('trim', explode(';',$sql)));
      foreach($parts as $q){
        if($q==='') continue;
        try { $this->pdo->exec($q); $ok++; }
        catch(Throwable $e){ if(stripos($e->getMessage(),'Duplicate')===false && stripos($e->getMessage(),'already exists')===false) $erros[]=$e->getMessage(); }
      }
    }
    $mensagens=[];
    $this->safeAddColumn($mensagens,'fila_integracao','prioridade',"ENUM('critica','alta','normal','baixa') DEFAULT 'normal' AFTER tipo");
    $this->safeAddColumn($mensagens,'fila_integracao','categoria','VARCHAR(60) NULL AFTER prioridade');
    try { QueueV24AnalyticsService::snapshot(); } catch(Throwable $e){ $erros[]='Snapshot fila: '.$e->getMessage(); }
    Audit::event('database.update_v24','sucesso',['mensagem'=>'Estrutura V24 Production Ready aplicada/validada.','contexto'=>['ok'=>$ok,'erros'=>$erros,'mensagens'=>$mensagens]]);
    redirect('index.php?page=central-tecnica');
  }

  private function atualizarV26Ultimate(): void {
    PermissionService::require('database','validar');
    Csrf::validate();
    $erros=[]; $ok=0;
    $sqlPath = __DIR__.'/../../database/update_v26_ultimate_infinityfree.sql';
    if(file_exists($sqlPath)){
      $parts = array_filter(array_map('trim', explode(';', file_get_contents($sqlPath))));
      foreach($parts as $q){
        if($q==='') continue;
        try { $this->pdo->exec($q); $ok++; }
        catch(Throwable $e){ if(stripos($e->getMessage(),'Duplicate')===false && stripos($e->getMessage(),'already exists')===false) $erros[]=$e->getMessage(); }
      }
    }
    try { EnterpriseAuditHashChainService::assinarProximos(1000); } catch(Throwable $e){ $erros[]='Hash chain: '.$e->getMessage(); }
    try {
      $host = HostingCompatibilityService::ambiente();
      $st = $this->db('hosting_checks')->prepare("INSERT INTO hosting_checks(provedor,score,resultado_json) VALUES('infinityfree',?,?)");
      $st->execute([(int)$host['score'], json_encode($host, JSON_UNESCAPED_UNICODE)]);
    } catch(Throwable $e){ $erros[]='Hosting check: '.$e->getMessage(); }
    Audit::event('database.update_v26','sucesso',['mensagem'=>'Estrutura V26 Ultimate/InfinityFree aplicada.','contexto'=>['ok'=>$ok,'erros'=>$erros]]);
    redirect('index.php?page=central-tecnica');
  }

  private function atualizarV31RegrasSincronizacao(): void {
    PermissionService::require('database','validar');
    Csrf::validate();
    $mensagens=[];
    $cols = [
      'sync_criar_produto_tiny' => 'TINYINT DEFAULT 1',
      'sync_atualizar_produto_tiny' => 'TINYINT DEFAULT 1',
      'sync_atualizar_estoque_tiny' => 'TINYINT DEFAULT 1',
      'sync_atualizar_status_tiny' => 'TINYINT DEFAULT 1',
      'sync_atualizar_preco_tiny' => 'TINYINT DEFAULT 1',
      'sync_atualizar_descricao_tiny' => 'TINYINT DEFAULT 1',
      'sync_atualizar_categoria_tiny' => 'TINYINT DEFAULT 1',
      'sync_atualizar_marca_tiny' => 'TINYINT DEFAULT 1',
      'sync_criar_produto_se_nao_existir' => 'TINYINT DEFAULT 0',
      'sync_bloquear_estoque_negativo' => 'TINYINT DEFAULT 1',
    ];
    foreach($cols as $c=>$def) $this->safeAddColumn($mensagens,'configuracoes_integracao',$c,$def);
    try {
      Database::forTable('configuracoes_integracao')->exec("UPDATE configuracoes_integracao SET sync_criar_produto_tiny=COALESCE(sync_criar_produto_tiny,1), sync_atualizar_produto_tiny=COALESCE(sync_atualizar_produto_tiny,1), sync_atualizar_estoque_tiny=COALESCE(sync_atualizar_estoque_tiny,1), sync_atualizar_status_tiny=COALESCE(sync_atualizar_status_tiny,1), sync_atualizar_preco_tiny=COALESCE(sync_atualizar_preco_tiny,1), sync_atualizar_descricao_tiny=COALESCE(sync_atualizar_descricao_tiny,1), sync_atualizar_categoria_tiny=COALESCE(sync_atualizar_categoria_tiny,1), sync_atualizar_marca_tiny=COALESCE(sync_atualizar_marca_tiny,1), sync_criar_produto_se_nao_existir=COALESCE(sync_criar_produto_se_nao_existir,0), sync_bloquear_estoque_negativo=COALESCE(sync_bloquear_estoque_negativo,1) WHERE id=1");
      $mensagens[]='Padrões das regras de sincronização aplicados.';
    } catch(Throwable $e){ $mensagens[]='Aviso ao aplicar padrões: '.$e->getMessage(); }
    Audit::event('database.update_v31','sucesso',['mensagem'=>'Estrutura V31 Regras de Sincronização aplicada.','contexto'=>['mensagens'=>$mensagens]]);
    echo '<div class="card-soft"><h4>Atualização V31 executada</h4><p>Campos de regra para criar produto, atualizar estoque e ativo/inativo no Tiny adicionados.</p><pre class="json-box">'.e(implode("\n", $mensagens)).'</pre><a class="btn btn-primary" href="index.php?page=regras-sincronizacao">Abrir Regras de Sincronização</a> <a class="btn btn-outline-primary" href="index.php?page=validar-banco">Validar Banco</a></div>';
  }

  private function atualizarV38Orquestracao(): void {
    PermissionService::require('database','validar');
    Csrf::validate();
    $mensagens=[];
    foreach(IntegrationOrchestratorService::keys() as $k=>$default){
      $def = ($k === 'sync_ordem_envio') ? "TEXT NULL" : "TINYINT DEFAULT ".((int)$default ? '1':'0');
      $this->safeAddColumn($mensagens,'configuracoes_integracao',$k,$def);
    }
    try {
      $ordem = IntegrationOrchestratorService::normalizarOrdem('pedido_vsm_receber,pedido_vsm_enviar_tiny,nfe_vsm_enviar_tiny,nfe_tiny_enviar_vsm,estoque_tiny_enviar_vsm,estoque_vsm_enviar_tiny,produto_status_tiny_enviar_vsm,produto_status_vsm_enviar_tiny');
      Database::forTable('configuracoes_integracao')->prepare("UPDATE configuracoes_integracao SET sync_ordem_envio=COALESCE(sync_ordem_envio, ?) WHERE id=1")->execute([$ordem]);
      $mensagens[]='Ordem padrão segura aplicada.';
    } catch(Throwable $e){ $mensagens[]='Aviso ao aplicar ordem padrão: '.$e->getMessage(); }
    Audit::event('database.update_v39','sucesso',['mensagem'=>'Estrutura V39 Orquestração granular Tiny ⇄ VSM aplicada.','contexto'=>['mensagens'=>$mensagens]]);
    echo '<div class="card-soft"><h4>Atualização V39 executada</h4><p>Campos de orquestração granular, checkboxes por fluxo e ordem de envio Tiny ⇄ VSM adicionados/atualizados.</p><pre class="json-box">'.e(implode("\n", $mensagens)).'</pre><a class="btn btn-primary" href="index.php?page=orquestracao-integracoes">Abrir Orquestração</a> <a class="btn btn-outline-primary" href="index.php?page=regras-sincronizacao">Regras</a></div>';
  }

  private function atualizarV23Seguranca2FA(): void {
    PermissionService::require('database','validar');
    Csrf::validate();
    $sqlPath = __DIR__.'/../../database/update_v23_seguranca_2fa.sql';
    $mensagens = [];
    if (is_file($sqlPath)) {
      foreach (array_filter(array_map('trim', explode(';', file_get_contents($sqlPath)))) as $q) {
        try { if ($q !== '') { $this->pdo->exec($q); $mensagens[] = 'OK: '.substr($q,0,80); } }
        catch(Throwable $e) { $mensagens[] = 'Aviso: '.$e->getMessage(); }
      }
    } else { $mensagens[] = 'Arquivo update_v23_seguranca_2fa.sql não encontrado.'; }
    Audit::event('database.update_v23_2fa','sucesso',['mensagem'=>'Atualização V23 2FA executada via rota corrigida V43.','contexto'=>['mensagens'=>$mensagens]]);
    redirect('index.php?page=seguranca-auditoria&v23=1');
  }

  private function atualizarV43FiscalDashboardInstall(): void {
    PermissionService::require('database','validar');
    Csrf::validate();
    $path = __DIR__.'/../../database/update_v43_fiscal_dashboard_install_futurista.sql';
    $mensagens = [];
    if (is_file($path)) {
      foreach (array_filter(array_map('trim', explode(';', file_get_contents($path)))) as $q) {
        try { if ($q !== '') { Database::connection('fiscal')->exec($q); $mensagens[]='Fiscal OK: '.substr($q,0,90); } }
        catch(Throwable $e) { $mensagens[]='Aviso fiscal: '.$e->getMessage(); }
      }
    }
    try { Database::recordMigration('v43_correcoes','v43','Correções V43 aplicadas'); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    Audit::event('database.update_v43','sucesso',['mensagem'=>'V43 Fiscal/Dashboard/Install aplicada.','contexto'=>['mensagens'=>$mensagens]]);
    redirect('index.php?page=central-tecnica&v43=1');
  }

  private function atualizarV45GovernancaProdutoVsm(): void {
    PermissionService::require('database','validar');
    Csrf::validate();
    $mensagens=[];
    $coreCols = [
      'sync_aprovacao_manual_produto_novo_vsm' => 'TINYINT DEFAULT 1',
      'sync_exigir_categoria_mapeada_vsm' => 'TINYINT DEFAULT 1',
      'sync_permitir_atualizar_produto_existente_vsm' => 'TINYINT DEFAULT 1',
      'sync_permitir_estoque_vsm_tiny' => 'TINYINT DEFAULT 1',
      'sync_permitir_status_vsm_tiny' => 'TINYINT DEFAULT 1',
    ];
    foreach($coreCols as $column => $definition){ try { $created = Database::addColumnIfMissing('configuracoes_integracao', $column, $definition); $mensagens[]='Core '.($created?'criado':'ok').': configuracoes_integracao.'.$column; } catch(Throwable $e){ $mensagens[]='Core aviso: '.$e->getMessage(); } }
    try { Database::forTable('configuracoes_integracao')->exec("UPDATE configuracoes_integracao SET sync_bloquear_produto_novo_vsm=1, sync_permitir_produto_novo_vsm_manual=1, sync_aprovacao_manual_produto_novo_vsm=1, sync_exigir_categoria_mapeada_vsm=1, sync_criar_produto_tiny=0, sync_criar_produto_se_nao_existir=0, sync_exigir_mapeamento_sku=1 WHERE id=1"); $mensagens[]='Core OK: política de governança atualizada.'; } catch(Throwable $e){ $mensagens[]='Core aviso: '.$e->getMessage(); }
    $prodPath = __DIR__.'/../../database/modules/produtos.sql';
    if(is_file($prodPath)){
      foreach(array_filter(array_map('trim', explode(';', file_get_contents($prodPath)))) as $q){
        try { Database::connection('produtos')->exec($q); $mensagens[]='Produtos OK: '.substr($q,0,100); }
        catch(Throwable $e){ $mensagens[]='Produtos aviso: '.$e->getMessage(); }
      }
    }
    try { Database::recordMigration('v45_governanca_produto_vsm','v45','Governança produto novo VSM aplicada'); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    Audit::event('database.update_v45','sucesso',['mensagem'=>'V45 Governança Produto VSM aplicada.','contexto'=>['mensagens'=>$mensagens]]);
    $_SESSION['update_v45_resultado']=$mensagens;
    redirect('index.php?page=central-tecnica&v45=1');
  }

  private function atualizarV48VsmConfiguravel(): void {
    PermissionService::require('database','validar');
    Csrf::validate();
    try { $mensagens=VsmEndpointService::ensureSchema(); Database::recordMigration('v48_vsm_configuravel','v48','VSM configurável: endpoints, campos, saúde, logs e testes'); $_SESSION['update_v48_resultado']=$mensagens; Audit::event('database.update_v48.vsm_configuravel','sucesso',['mensagem'=>'Estrutura V48 VSM configurável aplicada.','contexto'=>['mensagens'=>$mensagens]]); }
    catch(Throwable $e){ $_SESSION['update_v48_resultado']=['Erro: '.$e->getMessage()]; Audit::exception($e,'database.update_v48.erro'); }
    redirect('index.php?page=central-tecnica&v48=1');
  }

  private function atualizarV53EnterpriseStabilization(): void {
    PermissionService::require('database','validar');
    Csrf::validate();
    $msgs=[];
    $sql=__DIR__.'/../../database/update_v53_enterprise_stabilization.sql';
    if(is_file($sql)){
      foreach(array_filter(array_map('trim', explode(';', file_get_contents($sql)))) as $q){
        try{ Database::connection('core')->exec($q); $msgs[]='SQL OK: '.substr($q,0,110); }
        catch(Throwable $e){ $msgs[]='Aviso SQL: '.$e->getMessage(); }
      }
    }
    Audit::event('database.update_v53','sucesso',['mensagem'=>'V53 Enterprise Stabilization aplicada.','contexto'=>['mensagens'=>$msgs]]);
    $_SESSION['update_v53_resultado']=$msgs;
    redirect('index.php?page=central-tecnica&v53=1');
  }
}
