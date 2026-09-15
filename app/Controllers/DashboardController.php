<?php
class DashboardController {
  private PDO $pdo;
  public function __construct(){ $this->pdo = Database::getConnection(); }
  private function db(string $table): PDO { return Database::forTable($table); }

  private function appBaseUrl(): string {
    $cfgFile = __DIR__.'/../../config/config.php';
    if (is_file($cfgFile)) {
      $cfg = require $cfgFile;
      return rtrim((string)($cfg['base_url'] ?? ''), '/');
    }
    return '';
  }

  /**
   * P0-08 (reauditoria 2026-08-23): host/scheme passam pelo TrustedProxyService
   * (que já respeita trusted_proxies) em vez de ler HTTP_HOST/XFP crus, e usam o
   * primeiro canonical_host configurado quando existir, para não deixar um Host
   * header adulterado ditar a URL de redirect_uri usada no OAuth.
   */
  private function safeHostForUrls(): string {
    $canonical = class_exists('TrustedProxyService') ? TrustedProxyService::canonicalHosts() : [];
    if ($canonical !== []) return $canonical[0];
    return $_SERVER['HTTP_HOST'] ?? 'localhost';
  }

  private function absolutePublicBaseUrl(): string {
    $https = class_exists('TrustedProxyService') ? TrustedProxyService::isHttps() : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'));
    $scheme = $https ? 'https' : 'http';
    $host = $this->safeHostForUrls();
    $script = $_SERVER['SCRIPT_NAME'] ?? '/public/index.php';
    $dir = rtrim(str_replace('\\','/', dirname($script)), '/');
    if ($dir === '' || $dir === '.') $dir = '';
    return $scheme . '://' . $host . $dir;
  }

  private function normalizeTinyV3RedirectUri(string $uri = ''): string {
    $uri = trim($uri);
    if ($uri === '') {
      return $this->absolutePublicBaseUrl() . '/index.php?page=tiny-v3-callback';
    }
    if (preg_match('#^https?://#i', $uri)) {
      return $uri;
    }
    if (str_starts_with($uri, '/')) {
      $https = class_exists('TrustedProxyService') ? TrustedProxyService::isHttps() : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'));
      $scheme = $https ? 'https' : 'http';
      $host = $this->safeHostForUrls();
      return $scheme . '://' . $host . $uri;
    }
    return $this->absolutePublicBaseUrl() . '/' . ltrim($uri, '/');
  }

  /**
   * Reauditoria 2026-09-14 (achado A-02): entrada exclusiva dos callbacks OAuth, que chegam
   * por navegação cross-site sem o cookie de sessão SameSite=Strict e por isso não podem passar
   * pelo Auth::requireLogin() de dispatch(). A autorização é feita dentro do próprio callback,
   * contra o perfil vinculado à transação OAuth assinada. Só rotas de callback entram aqui.
   */
  public function dispatchOAuthCallback(string $page): void {
    if ($page === 'tiny-v3-callback') { $this->tinyV3Callback(); return; }
    http_response_code(404);
    exit('Callback não encontrado.');
  }

  public function dispatch(string $page): void {
    Auth::requireLogin();
    switch($page){
      case 'dashboard': $this->dashboard(); break;
      case 'alertas-operacionais': $this->alertasOperacionais(); break;
      case 'centro-operacoes': $this->centroOperacoes(); break;
      case 'pedidos': (new PedidoController())->dispatch($page); break;
      case 'pedido-detalhe': (new PedidoController())->dispatch($page); break;
      case 'fila': (new FilaController())->dispatch($page); break;
      case 'fila-reprocessar': (new FilaController())->dispatch($page); break;
      case 'fila-criar-teste': (new FilaController())->dispatch($page); break;
      case 'testar-tiny': $this->testarTiny(); break;
      case 'testar-vsm': $this->testarVsm(); break;
      case 'produtos': (new ProdutoController())->dispatch($page); break;
      case 'estoque-dashboard': (new EstoqueController())->dispatch($page); break;
      case 'estoque-config': (new EstoqueController())->dispatch($page); break;
      case 'estoque-config-salvar': (new EstoqueController())->dispatch($page); break;
      case 'estoque-alertas': (new EstoqueController())->dispatch($page); break;
      case 'estoque-sku-historico': (new EstoqueController())->dispatch($page); break;
      case 'estoque-consultas-vsm': (new EstoqueController())->dispatch($page); break;
      case 'estoque-consulta-vsm-resultados': (new EstoqueController())->dispatch($page); break;
      case 'estoque-consulta-vsm-testar-sku': (new EstoqueController())->dispatch($page); break;
      case 'estoque-reconciliar-agora': (new EstoqueController())->dispatch($page); break;
      case 'estoque-consulta-vsm-executar': (new EstoqueController())->dispatch($page); break;
      case 'estoque-consulta-tiny-executar': (new EstoqueController())->dispatch($page); break;
      case 'baixas-estoque': $this->baixasEstoque(); break;
      case 'produtos-vsm': $this->produtosVsm(); break;
      case 'produtos-pendencias': (new ProdutoController())->dispatch($page); break;
      case 'produtos-pendentes-integracao': $this->produtosPendentesIntegracao(); break;
      case 'produto-pendente-integracao-comparar': $this->produtoPendenteIntegracaoComparar(); break;
      case 'produto-pendente-integracao-acao': $this->produtoPendenteIntegracaoAcao(); break;
      case 'categorias-mapeamento': $this->categoriasMapeamento(); break;
      case 'categoria-mapeamento-salvar': $this->categoriaMapeamentoSalvar(); break;
      case 'produto-pendencia-acao': $this->produtoPendenciaAcao(); break;
      case 'divergencia-estoque': $this->divergenciaEstoque(); break;
      case 'divergencia-acao': $this->divergenciaAcao(); break;
      case 'simular-baixa-tiny': $this->simularBaixaTiny(); break;
      case 'simular-produto-vsm': $this->simularProdutoVsm(); break;
      case 'simular-estoque-vsm': $this->simularEstoqueVsm(); break;
      case 'simular-status-vsm': $this->simularStatusVsm(); break;
      case 'salvar-fluxos': $this->salvarFluxos(); break;
      case 'integracoes': $this->integracoes(); break;
      case 'regras-sincronizacao': $this->regrasSincronizacao(); break;
      case 'orquestracao-integracoes': (new OrquestracaoController())->dispatch($page); break;
      case 'bancos-modulos': $this->bancosModulos(); break;
      case 'bancos-modulos-instalar': $this->bancosModulosInstalar(); break;
      case 'central-tecnica': $this->centralTecnica(); break;
      case 'salvar-orquestracao-integracoes': (new OrquestracaoController())->dispatch($page); break;
      case 'testar-orquestracao-fluxo': (new OrquestracaoController())->dispatch($page); break;
      case 'teste-real-tiny': $this->testeRealTiny(); break;
      case 'teste-real-tiny-executar': $this->testeRealTinyExecutar(); break;
      case 'atualizador-seguro': $this->atualizadorSeguro(); break;
      case 'auditoria-codigo': $this->auditoriaCodigo(); break;
      case 'atualizador-seguro-executar': $this->atualizadorSeguroExecutar(); break;
      case 'oauth-v3-checklist': $this->oauthV3Checklist(); break;
      case 'salvar-regras-sincronizacao': $this->salvarRegrasSincronizacao(); break;
      case 'logs': $this->logs(); break;
      case 'notificacoes': $this->notificacoes(); break;
      case 'sobre': $this->sobre(); break;
      case 'notificacao-lida': $this->marcarNotificacaoLida(); break;
      case 'auditoria': $this->auditoria(); break;
      case 'auditoria-detalhe': $this->auditoriaDetalhe(); break;
      case 'diagnostico': $this->diagnostico(); break;
      case 'dashboard-integridade': $this->dashboardIntegridade(); break;
      case 'producao-ready': $this->producaoReady(); break;
      case 'dashboard-integridade-executar': $this->dashboardIntegridadeExecutar(); break;
      case 'health-modulos': $this->healthModulos(); break;
      case 'menu-testes': $this->menuTestes(); break;
      case 'fiscal-reenviar':
      case 'fiscal-dashboard':
      case 'fiscal-xml':
      case 'fiscal-timeline':
      case 'fiscal-reconciliacao':
      case 'fiscal-health':
      case 'fiscal': (new FiscalController())->dispatch($page); break;
      case 'tiny-webhooks': $this->tinyWebhooks(); break;
      case 'tiny-v3-ficha': $this->tinyV3Ficha(); break;
      case 'ficha-tecnica-100': $this->fichaTecnica100(); break;
      case 'production-ready-v24': $this->productionReadyV24(); break;
      case 'production-ready-v25': $this->productionReadyV25(); break;
      case 'production-ready-v26': $this->productionReadyV26(); break;
      case 'vsm-ficha-tecnica': $this->vsmFichaTecnica(); break;
      case 'tiny-v2-ficha-tecnica': $this->tinyV2FichaTecnica(); break;
      case 'fila-analytics-v24': $this->filaAnalyticsV24(); break;
      case 'hosting-infinityfree': $this->hostingInfinityFree(); break;
      case 'auditoria-hash-chain': $this->auditoriaHashChain(); break;
      case 'auditoria-assinar-trace': $this->auditoriaAssinarTrace(); break;
      case 'auditoria-exportar-pdf': $this->auditoriaExportarPdf(); break;
      case 'seguranca-auditoria': $this->segurancaAuditoria(); break;
      case 'seguranca-extrema': $this->segurancaExtrema(); break;
      case 'security-center':
      case 'security-soc':
      case 'security-code-audit':
      case 'security-inventory':
      case 'security-backup-trust':
      case 'security-health':
      case 'security-audit-signatures':
      case 'security-events':
      case 'security-ips':
      case 'security-circuit-breakers':
      case 'security-hardening':
      case 'security-ssl':
      case 'security-user-audit':
      case 'security-pentest': $this->securityCenter(); break;
      case 'security-audit-sign': $this->securityAuditSign(); break;
      case 'security-fim': $this->securityFim(); break;
      case 'security-fim-gerar': $this->securityFimGerar(); break;
      case 'security-score': $this->securityScore(); break;
      case 'relatorio-prontidao-producao': $this->relatorioProntidaoProducao(); break;
      case 'auditoria-exportar-enterprise': $this->auditoriaExportarEnterprise(); break;
      case 'tiny-v3-token-salvar': $this->tinyV3TokenSalvar(); break;
      case 'tiny-v3-token-renovar': $this->tinyV3TokenRenovar(); break;
      case 'tiny-v3-token-revogar': $this->tinyV3TokenRevogar(); break;
      case 'tiny-v3-testar': $this->tinyV3Testar(); break;
      case 'tiny-v3-testar-modulo': $this->tinyV3TestarModulo(); break;
      case 'tiny-v3-endpoints-salvar': $this->tinyV3EndpointsSalvar(); break;
      case 'fila-morta': $this->filaMorta(); break;
      case 'fila-morta-reprocessar': (new FilaController())->dispatch($page); break;
      case 'laboratorio': $this->laboratorio(); break;
      case 'laboratorio-executar': $this->laboratorioExecutar(); break;
      case 'reconciliacao': $this->reconciliacao(); break;
      case 'reconciliacao-executar': $this->reconciliacaoExecutar(); break;
      case 'metricas': $this->metricas(); break;
      case 'selftest': $this->selftest(); break;
      case 'selftest-executar': $this->selftestExecutar(); break;
      case 'homologacao-automatica': $this->homologacaoAutomatica(); break;
      case 'homologacao-automatica-executar': $this->homologacaoAutomaticaExecutar(); break;
      case 'homologacao': $this->homologacao(); break;
      case 'homologacao-acao': $this->homologacaoAcao(); break;
      case 'homologacao-relatorio': $this->homologacaoRelatorio(); break;
      case 'validar-banco': $this->validarBanco(); break;
      case 'relatorio-homologacao': $this->homologacaoRelatorio(); break;
      case 'usuarios': $this->usuarios(); break;
      case 'usuario-salvar': $this->usuarioSalvar(); break;
      case 'usuario-excluir': $this->usuarioExcluir(); break;
      case 'permissoes-salvar': $this->permissoesSalvar(); break;
      case 'trocar-senha': $this->trocarSenha(); break;
      case 'backup': $this->backup(); break;
      case 'backup-download': $this->backupDownload(); break;
      case 'backup-excluir': $this->backupExcluir(); break;
      case 'backup-importar': $this->backupImportar(); break;
      case 'backup-restaurar': $this->backupRestaurar(); break;
      case 'backups': $this->backups(); break;
      case 'logs-exportar': $this->logsExportar(); break;
      case 'configuracoes': $this->configuracoes(); break;
      case 'tiny-ambientes': $this->tinyAmbientes(); break;
      case 'central-homologacao': $this->centralHomologacao(); break;
      case 'tiny-v2-homologacao': $this->tinyV2Homologacao(); break;
      case 'tiny-v2-homologacao-executar': $this->tinyV2HomologacaoExecutar(); break;
      case 'tiny-v3-homologacao': $this->tinyV3Homologacao(); break;
      case 'tiny-v3-homologacao-executar': $this->tinyV3HomologacaoExecutar(); break;
      case 'salvar-configuracoes': $this->salvarConfiguracoes(); break;
      // Reauditoria 2026-09-14 (achado A-13): antes qualquer rota desconhecida renderizava o
      // dashboard com HTTP 200, escondendo links quebrados e dando falso positivo em smoke test.
      // Melhoria 10 da seção 8 (relatório V104.49.3-R6): as 19 rotas 'atualizar-v*' que apontavam
      // para LegacyDatabaseUpgradeController foram removidas daqui porque já eram CÓDIGO MORTO:
      // FastRouteDispatcherService intercepta todo 'atualizar-v*' antes do DashboardController e
      // manda para MigrationController::legacyBlocked(). Elas davam a impressão de existir uma
      // superfície de DDL em runtime que, na prática, o roteamento já não alcançava. O próprio
      // controller passou a recusar execução salvo liberação explícita (ver o arquivo dele).
      default: $this->naoEncontrado($page);
    }
  }

  public function index(){ $this->dashboard(); }

  /** Resposta 404 controlada para rotas inexistentes (A-13), sem expor detalhes internos. */
  private function naoEncontrado(string $page): void {
    http_response_code(404);
    if (class_exists('Audit')) {
      try {
        Audit::event('rota.nao_encontrada','alerta',[
          'codigo_erro'=>'ROUTE_NOT_FOUND',
          'mensagem'=>'Rota inexistente acessada.',
          'contexto'=>['page'=>substr($page,0,190)],
          'acao_recomendada'=>'Verifique links internos ou favoritos desatualizados.'
        ]);
      } catch (Throwable $e) { if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    }
    $pageTitle = 'Página não encontrada';
    require __DIR__.'/../../views/nao_encontrado.php';
  }

  // Reauditoria 2026-09-14: operação usa apenas 'empresas' como entidade de negócio.
  // 'filiais' saiu das telas/contadores e do seed; a tabela permanece no schema (vazia)
  // para não quebrar instalações existentes, mas não é mais consultada pelo painel.
  private const SAFE_TABLES = ['auditoria_eventos','empresas','estoque_divergencias','estoque_movimentos','fila_estoque','fila_fiscal','fila_integracao','logs_integracao','notificacoes','pedidos_hub','pedidos_integracao','pedidos_nfe_xml','produtos_mapeamento'];

  private const SAFE_COLUMNS = [
    'auditoria_eventos' => ['id','trace_id','usuario_id','acao','entidade','entidade_id','status','nivel','codigo_erro','mensagem','causa_provavel','acao_recomendada','payload','retorno','contexto','ip','criado_em'],
    'empresas' => ['id','nome','cnpj','ativo','criado_em'],
    'estoque_divergencias' => ['id','sku','estoque_vsm','estoque_tiny','diferenca','origem','status','acao_recomendada','trace_id','detalhes','criado_em','atualizado_em'],
    'estoque_movimentos' => ['id','origem','referencia','sku','quantidade','tipo_movimento','status','payload_origem','retorno_vsm','trace_id','criado_em','atualizado_em'],
    'fila_estoque' => ['id','origem','destino','sku','quantidade','status','tentativas','proxima_tentativa','payload','retorno','ultimo_erro','trace_id','criado_em','atualizado_em'],
    'fila_fiscal' => ['id','nota_fiscal_id','nfe_integracao_id','acao','prioridade','status','tentativas','proxima_tentativa','payload','retorno','ultimo_erro','trace_id','criado_em','atualizado_em'],
    'fila_integracao' => ['id','tipo','prioridade','categoria','referencia','payload','retorno','status','codigo_erro','tentativas','proxima_tentativa','processando_desde','processado_em','trace_id','criado_em'],
    'logs_integracao' => ['id','trace_id','tipo','nivel','codigo_erro','mensagem','payload','ip','criado_em'],
    'notificacoes' => ['id','tipo','titulo','mensagem','severidade','entidade','entidade_id','link','trace_id','payload','lida','lida_em','criada_em'],
    'pedidos_hub' => ['id','trace_id','pedido_tiny_id','pedido_vsm_id','numero_pedido','status_hub','status_tiny','status_vsm','cliente_nome','cliente_documento','valor_total','data_recebido_tiny','data_enviado_vsm','data_recebido_vsm','data_enviado_tiny','ultimo_erro','criado_em','atualizado_em'],
    'pedidos_integracao' => ['id','origem','pedido_origem_id','pedido_tiny_id','empresa_id','filial_id','cliente_nome','cliente_documento','valor_total','status','payload_origem','payload_tiny','retorno_tiny','erro','tentativas','trace_id','criado_em','atualizado_em'],
    'pedidos_nfe_xml' => ['id','pedido_hub_id','chave_nfe','numero_nfe','serie','xml_original','xml_hash','status_xml','validado','erro_validacao','retorno_tiny_json','enviado_tiny_em','criado_em','atualizado_em'],
    'produtos_mapeamento' => ['id','sku_tiny','sku_vsm','produto_tiny_id','produto_vsm_id','descricao','ativo','estoque_atual','status_tiny','ultima_sincronizacao','criado_em'],
  ];

  private function safeIdentifier(string $identifier): string {
    if (!in_array($identifier, self::SAFE_TABLES, true)) {
      throw new InvalidArgumentException('Tabela não permitida.');
    }
    return $identifier;
  }

  private function safeColumn(string $table, string $column): string {
    $table = $this->safeIdentifier($table);
    $column = trim($column, " `\t\n\r\0\x0B");
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column)) {
      throw new InvalidArgumentException('Coluna inválida.');
    }
    if (!in_array($column, self::SAFE_COLUMNS[$table] ?? [], true)) {
      throw new InvalidArgumentException('Coluna não permitida para a tabela.');
    }
    return '`'.$column.'`';
  }

  private function safeSqlFragment(string $table, string $fragment): string {
    $table = $this->safeIdentifier($table);
    $fragment = trim($fragment) ?: '1=1';
    if ($fragment === '1=1') return $fragment;
    if (preg_match('/(;|--|#|\/\*|\*\/|\b(UNION|SELECT|INSERT|UPDATE|DELETE|DROP|ALTER|TRUNCATE|CREATE|REPLACE|LOAD_FILE|OUTFILE)\b)/i', $fragment)) {
      throw new InvalidArgumentException('Filtro SQL não permitido.');
    }
    if (!preg_match('/^[A-Za-z0-9_`\s\.\(\)=<>,!\'"%-]+$/', $fragment)) {
      throw new InvalidArgumentException('Filtro SQL inválido.');
    }
    preg_match_all('/`?([A-Za-z_][A-Za-z0-9_]*)`?\s*(?:=|!=|<>|<|>|<=|>=|LIKE|IN\s*\(|IS\s+NOT\s+NULL|IS\s+NULL)/i', $fragment, $matches);
    foreach ($matches[1] ?? [] as $col) {
      $upper = strtoupper($col);
      if (in_array($upper, ['AND','OR','IN','LIKE','IS','NOT','NULL','NOW','DATE_SUB','INTERVAL','MINUTE','HOUR','DAY'], true)) continue;
      if (!in_array($col, self::SAFE_COLUMNS[$table] ?? [], true)) {
        throw new InvalidArgumentException('Filtro usa coluna não permitida: '.$col);
      }
    }
    return $fragment;
  }

  private function safeOrderBy(string $table, string $orderBy): string {
    $table = $this->safeIdentifier($table);
    $parts = array_map('trim', explode(',', $orderBy ?: 'id DESC'));
    $safe = [];
    foreach ($parts as $part) {
      if (!preg_match('/^`?([A-Za-z_][A-Za-z0-9_]*)`?(\s+(ASC|DESC))?$/i', $part, $m)) {
        throw new InvalidArgumentException('Ordenação não permitida.');
      }
      $safe[] = $this->safeColumn($table, $m[1]).(isset($m[3]) ? ' '.strtoupper($m[3]) : '');
    }
    return implode(', ', $safe);
  }

  private function count(string $table, string $where='1=1'): int {
    $table = $this->safeIdentifier($table);
    $where = $this->safeSqlFragment($table, $where);
    // Melhoria 1 da seção 8: nove das SAFE_TABLES são tabelas com escopo de empresa. Como estes
    // dois auxiliares montam o SQL interpolando o nome da tabela, eles escapavam da varredura do
    // tenant-scope-check (que procura "FROM tabela" literal) e continuariam contando linhas de
    // TODAS as empresas nos cartões do dashboard. applyToSelect() resolve, e o verificador foi
    // ensinado a cobrar SQL interpolado também.
    [$sql, $params] = TenantScopeService::applyToSelect($table, "SELECT COUNT(*) c FROM `$table` WHERE $where", []);
    $st = Database::forTable($table)->prepare($sql);
    $st->execute($params);
    return (int)($st->fetch()['c'] ?? 0);
  }


  private function safeCount(string $table, string $where='1=1'): int {
    try { return $this->count($table, $where); } catch (Throwable $e) { return 0; }
  }

  private function statusDot(string $status): string {
    return $status === 'online' ? '🟢' : ($status === 'erro' ? '🔴' : '🟡');
  }

  private function workerCards(): array {
    $workers = [
      ['arquivo'=>'worker_fiscal.php','titulo'=>'XML/NF-e'],
      ['arquivo'=>'worker_estoque.php','titulo'=>'Estoque'],
      ['arquivo'=>'worker_consulta_estoque_vsm.php','titulo'=>'Consulta VSM'],
      ['arquivo'=>'worker_fila.php','titulo'=>'Fila'],
    ];
    $out=[];
    foreach($workers as $w){
      $file = __DIR__.'/../../public/'.$w['arquivo'];
      $exists = is_file($file);
      $out[] = [
        'titulo'=>$w['titulo'],
        'arquivo'=>$w['arquivo'],
        'status'=>$exists ? 'online' : 'erro',
        'detalhe'=>$exists ? 'Arquivo disponível para agendamento' : 'Arquivo não encontrado',
      ];
    }
    return $out;
  }

  private function operationalSummary(array $cfgInt=[]): array {
    $tinyV2Ok = !empty($cfgInt['tiny_v2_token']);
    $tinyV3Operacional = !empty($cfgInt['tiny_v3_operacional']);
    $tinyV3OAuth = !empty($cfgInt['tiny_v3_access_token']) || !empty($cfgInt['tiny_v3_refresh_token']) || !empty($cfgInt['tiny_v3_client_id']);
    $vsmOk = !empty($cfgInt['vsm_url']);
    $vsmStatus = class_exists('VsmEnvironmentService') ? VsmEnvironmentService::dashboardStatus($cfgInt) : ['status'=>$vsmOk?'atencao':'erro','modo'=>$vsmOk?'Homologação':'Não configurado','detalhe'=>$vsmOk?'URL configurada':'URL pendente','acao'=>$vsmOk?'Validar VSM':'Configurar VSM'];
    $filaErro = $this->safeCount('fila_integracao', "status IN ('erro','falha_definitiva')");
    $filaPendente = $this->safeCount('fila_integracao', "status='pendente'");
    $xmlErro = $this->safeCount('pedidos_hub', "status_hub IN ('erro_xml','erro_envio_tiny','erro_retorno_vsm')");
    $estoqueErro = $this->safeCount('fila_estoque', "status IN ('erro','falha_definitiva')");
    $alertas = [];
    if(!$tinyV2Ok) $alertas[] = ['nivel'=>'atencao','titulo'=>'Tiny V2 sem token','mensagem'=>'Configure o token antes de produção.'];
    if(!$tinyV3Operacional) $alertas[] = ['nivel'=>'atencao','titulo'=>'Tiny V3 em homologação','mensagem'=>'Mantenha V2 em produção até OAuth e testes reais passarem.'];
    if(!$vsmOk) $alertas[] = ['nivel'=>'erro','titulo'=>'VSM sem URL','mensagem'=>'Configure a URL/base da VSM.'];
    if($filaErro>0) $alertas[] = ['nivel'=>'erro','titulo'=>'Fila com erro','mensagem'=>$filaErro.' item(ns) com erro definitivo.'];
    if($xmlErro>0) $alertas[] = ['nivel'=>'erro','titulo'=>'XML/NF-e com erro','mensagem'=>$xmlErro.' pedido(s) com problema de XML/NF-e.'];
    if($estoqueErro>0) $alertas[] = ['nivel'=>'erro','titulo'=>'Estoque com erro','mensagem'=>$estoqueErro.' item(ns) com falha na fila de estoque.'];
    $geral = 'online';
    foreach($alertas as $a){ if($a['nivel']==='erro'){ $geral='erro'; break; } if($a['nivel']==='atencao') $geral='atencao'; }
    return [
      'geral'=>$geral,
      'alertas'=>$alertas,
      'integracoes'=>[
        ['nome'=>'Tiny V2','status'=>$tinyV2Ok?'online':'atencao','modo'=>'Produção','detalhe'=>$tinyV2Ok?'Token configurado':'Token pendente'],
        ['nome'=>'Tiny V3','status'=>$tinyV3Operacional?'online':'atencao','modo'=>$tinyV3Operacional?'Produção':'Homologação','detalhe'=>$tinyV3OAuth?'OAuth/configuração parcial':'OAuth pendente'],
        ['nome'=>'VSM','status'=>$vsmStatus['status'],'modo'=>$vsmStatus['modo'],'detalhe'=>$vsmStatus['detalhe']],
      ],
      'filas'=>['pendentes'=>$filaPendente,'erros'=>$filaErro],
    ];
  }

  private function tableRows(string $table, string $orderBy='id DESC', int $limit=10, string $where='1=1'): array {
    $table = $this->safeIdentifier($table);
    $where = $this->safeSqlFragment($table, $where);
    $orderBy = $this->safeOrderBy($table, $orderBy);
    $limit = max(1, min(500, $limit));
    $cols = class_exists('HeavyQueryOptimizerService') ? HeavyQueryOptimizerService::columns($table, '*') : '*';
    // Melhoria 1 da seção 8: ver a nota em count(). O predicado entra antes de ORDER BY/LIMIT.
    [$sql, $params] = TenantScopeService::applyToSelect($table, "SELECT $cols FROM `$table` WHERE $where ORDER BY $orderBy LIMIT $limit", []);
    $st = Database::forTable($table)->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
  }

  private function tableOne(string $table, string $orderBy='id DESC', string $where='1=1'): ?array {
    $rows = $this->tableRows($table, $orderBy, 1, $where);
    return $rows[0] ?? null;
  }

  private function dashboard(): void {
    PermissionService::require('dashboard','visualizar');
    $k = [
      'Pedidos integrados' => $this->count('pedidos_integracao'),
      'Pedidos com erro' => $this->count('pedidos_integracao', "status IN ('erro','falha','falha_definitiva')"),
      'Fila pendente' => $this->count('fila_integracao', "status='pendente'"),
      'Produtos mapeados' => $this->count('produtos_mapeamento'),
      'Empresas' => $this->count('empresas'),
      'Eventos de auditoria' => $this->count('auditoria_eventos'),
      'Notificações não lidas' => $this->count('notificacoes', 'lida=0'),
    ];
    $pedidos = $this->tableRows('pedidos_integracao', 'id DESC', 8);
    $logs = $this->tableRows('logs_integracao', 'id DESC', 8);
    $filaResumo = TenantScopeService::run('fila_integracao', "SELECT status, COUNT(*) total FROM fila_integracao GROUP BY status")->fetchAll();

    // Dashboard de Saúde: indicadores rápidos sem deixar o painel lento.
    // Testes de internet/API mais pesados continuam na página Diagnóstico.
    try {
      $cfgInt = IntegrationConfig::get();
    } catch (Throwable $e) {
      $cfgInt = [];
    }
    $vsmStatus = class_exists('VsmEnvironmentService') ? VsmEnvironmentService::dashboardStatus($cfgInt) : ['status'=>!empty($cfgInt['vsm_url'])?'atencao':'erro','modo'=>!empty($cfgInt['vsm_url'])?'Homologação':'Não configurado','detalhe'=>!empty($cfgInt['vsm_url'])?'URL configurada':'URL pendente','acao'=>!empty($cfgInt['vsm_url'])?'Validar VSM':'Configurar VSM'];
    $syncRules = class_exists('SyncRulesService') ? SyncRulesService::all() : [];
    $syncResumo = [
      ['chave'=>'sync_criar_produto_tiny','titulo'=>'Criar produto Tiny'],
      ['chave'=>'sync_atualizar_estoque_tiny','titulo'=>'Atualizar estoque Tiny'],
      ['chave'=>'sync_atualizar_status_tiny','titulo'=>'Ativo/Inativo Tiny'],
      ['chave'=>'sync_bloquear_estoque_negativo','titulo'=>'Bloquear estoque negativo'],
    ];

    $filaPendente = $this->count('fila_integracao', "status='pendente'");
    $filaErro = $this->count('fila_integracao', "status IN ('erro','falha_definitiva')");
    $filaProcessandoAntiga = $this->count('fila_integracao', "status='processando' AND processando_desde IS NOT NULL AND processando_desde < DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
    $falhasRecentes = $this->count('logs_integracao', "nivel IN ('erro','critico') AND criado_em >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");

    $healthCards = [
      [
        'titulo' => 'Banco MySQL',
        'status' => 'online',
        'icone' => 'bi-database-check',
        'detalhe' => 'Conexão PDO ativa',
        'acao' => 'OK'
      ],
      [
        'titulo' => 'Tiny V2',
        'status' => !empty($cfgInt['tiny_v2_token']) ? 'online' : 'atencao',
        'icone' => 'bi-cloud-check',
        'detalhe' => !empty($cfgInt['tiny_v2_token']) ? 'Token configurado' : 'Token não configurado',
        'acao' => !empty($cfgInt['tiny_v2_token']) ? 'Pronto para teste' : 'Configure o token Tiny'
      ],
      [
        'titulo' => 'VSM',
        'status' => $vsmStatus['status'],
        'icone' => 'bi-diagram-3',
        'detalhe' => $vsmStatus['detalhe'],
        'acao' => $vsmStatus['acao']
      ],
      [
        'titulo' => 'Webhook VSM',
        'status' => !empty($cfgInt['webhook_secret']) ? 'online' : 'atencao',
        'icone' => 'bi-shield-lock',
        'detalhe' => !empty($cfgInt['webhook_secret']) ? 'Secret/HMAC configurado' : 'Secret não configurado',
        'acao' => !empty($cfgInt['webhook_secret']) ? 'Protegido' : 'Configure o segredo do webhook'
      ],
      [
        'titulo' => 'Fila',
        'status' => $filaProcessandoAntiga > 0 || $filaErro > 0 ? 'erro' : ($filaPendente > 0 ? 'atencao' : 'online'),
        'icone' => 'bi-arrow-repeat',
        'detalhe' => $filaPendente.' pendente(s), '.$filaErro.' com erro',
        'acao' => $filaProcessandoAntiga > 0 ? 'Há item travado; rode worker_fila.php' : ($filaPendente > 0 ? 'Processar fila' : 'Fila normal')
      ],
      [
        'titulo' => 'Erros 24h',
        'status' => $falhasRecentes > 0 ? 'erro' : 'online',
        'icone' => 'bi-bug',
        'detalhe' => $falhasRecentes.' erro(s)/crítico(s)',
        'acao' => $falhasRecentes > 0 ? 'Verifique Logs e Auditoria' : 'Sem erros recentes'
      ],
    ];

    $ultimoDiagVsm = class_exists('DiagnosticoApiService') ? DiagnosticoApiService::ultimoPorSistema('vsm') : null;
    $ultimoDiagTiny = class_exists('DiagnosticoApiService') ? DiagnosticoApiService::ultimoPorSistema('tiny') : null;
    $ultimoPedidoIntegrado = $this->tableOne('pedidos_integracao');
    $ultimaFalha = $this->tableOne('logs_integracao', 'id DESC', "nivel IN ('erro','critico')");
    $ultimaBaixaVsm = $this->tableOne('estoque_movimentos');
    $ultimoProdutoTiny = $this->tableOne('produtos_mapeamento');
    $baixasErro = $this->count('estoque_movimentos', "status IN ('erro','falha','falha_definitiva')");
    $produtosErro = $this->count('fila_integracao', "tipo IN ('produto_vsm_para_tiny','produto_vsm_atualizar_tiny','produto_vsm_estoque_para_tiny','produto_vsm_status_para_tiny') AND status IN ('erro','falha_definitiva')");
    $filaTinyVsm = $this->count('fila_integracao', "tipo='baixa_estoque_vsm' AND status='pendente'");
    $filaVsmTiny = $this->count('fila_integracao', "tipo IN ('produto_vsm_para_tiny','produto_vsm_atualizar_tiny','produto_vsm_estoque_para_tiny','produto_vsm_status_para_tiny') AND status='pendente'");

    // V53 - Dashboard operacional do ciclo Tiny → Hub → VSM → Tiny.
    $pedidoCicloResumo = [
      'recebidos_tiny' => 0,
      'enviados_vsm' => 0,
      'aguardando_xml' => 0,
      'enviados_tiny' => 0,
      'concluidos' => 0,
      'erros' => 0,
    ];
    try {
      $pdoCiclo = Database::forTable('pedidos_hub');
      $rowsCiclo = TenantScopeService::run('pedidos_hub', "SELECT status_hub, COUNT(*) total FROM pedidos_hub GROUP BY status_hub")->fetchAll();
      foreach ($rowsCiclo as $rowCiclo) {
        $statusCiclo = (string)($rowCiclo['status_hub'] ?? '');
        $totalCiclo = (int)($rowCiclo['total'] ?? 0);
        if ($statusCiclo === 'recebido_tiny') $pedidoCicloResumo['recebidos_tiny'] += $totalCiclo;
        if ($statusCiclo === 'enviado_vsm') $pedidoCicloResumo['enviados_vsm'] += $totalCiclo;
        if (in_array($statusCiclo, ['recebido_vsm','xml_validado'], true)) $pedidoCicloResumo['aguardando_xml'] += $totalCiclo;
        if ($statusCiclo === 'enviado_tiny') $pedidoCicloResumo['enviados_tiny'] += $totalCiclo;
        if ($statusCiclo === 'concluido') $pedidoCicloResumo['concluidos'] += $totalCiclo;
        if (str_contains($statusCiclo, 'erro')) $pedidoCicloResumo['erros'] += $totalCiclo;
      }
    } catch (Throwable $e) {
      $pedidoCicloResumo['erro_consulta'] = $e->getMessage();
    }
    $tinyV3Aviso = (($cfgInt['tiny_versao'] ?? 'v2') === 'v3' && empty($cfgInt['tiny_v3_operacional']));
    $operacao = $this->operationalSummary($cfgInt);
    $workerCards = $this->workerCards();
    $estoqueResumo = [
      'sincronizados' => $this->safeCount('estoque_movimentos', "status IN ('sucesso','sincronizado','confirmado')"),
      'divergencias' => $this->safeCount('estoque_divergencias'),
      'pendentes' => $this->safeCount('fila_estoque', "status='pendente'"),
      'falhas' => $this->safeCount('fila_estoque', "status IN ('erro','falha_definitiva')"),
    ];
    $fiscalResumo = [
      'recebidas' => $this->safeCount('pedidos_nfe_xml'),
      'xml_processados' => $this->safeCount('pedidos_nfe_xml', "status_xml IN ('validado','enviado_tiny','concluido')"),
      'xml_erros' => $this->safeCount('pedidos_nfe_xml', "status_xml LIKE '%erro%' OR validado=0"),
      'reenvios' => $this->safeCount('fila_fiscal', "status IN ('pendente','processando')"),
    ];
    $pageTitle = 'Dashboard';
    require __DIR__.'/../../views/dashboard.php';
  }


  private function alertasOperacionais(): void {
    PermissionService::require('dashboard','visualizar');
    try { $cfgInt = IntegrationConfig::get(); } catch(Throwable $e){ $cfgInt=[]; }
    $operacao = $this->operationalSummary($cfgInt);
    $pageTitle = 'Alertas Operacionais';
    require __DIR__.'/../../views/alertas_operacionais.php';
  }

  private function centroOperacoes(): void {
    PermissionService::require('dashboard','visualizar');
    try { $cfgInt = IntegrationConfig::get(); } catch(Throwable $e){ $cfgInt=[]; }
    $operacao = $this->operationalSummary($cfgInt);
    $workerCards = $this->workerCards();
    $estoqueResumo = [
      'sincronizados' => $this->safeCount('estoque_movimentos', "status IN ('sucesso','sincronizado','confirmado')"),
      'divergencias' => $this->safeCount('estoque_divergencias'),
      'pendentes' => $this->safeCount('fila_estoque', "status='pendente'"),
      'falhas' => $this->safeCount('fila_estoque', "status IN ('erro','falha_definitiva')"),
    ];
    $fiscalResumo = [
      'recebidas' => $this->safeCount('pedidos_nfe_xml'),
      'xml_processados' => $this->safeCount('pedidos_nfe_xml', "status_xml IN ('validado','enviado_tiny','concluido')"),
      'xml_erros' => $this->safeCount('pedidos_nfe_xml', "status_xml LIKE '%erro%' OR validado=0"),
      'reenvios' => $this->safeCount('fila_fiscal', "status IN ('pendente','processando')"),
    ];
    $pedidoCicloResumo = [
      'recebidos_tiny' => $this->safeCount('pedidos_hub', "status_hub='recebido_tiny'"),
      'enviados_vsm' => $this->safeCount('pedidos_hub', "status_hub='enviado_vsm'"),
      'aguardando_xml' => $this->safeCount('pedidos_hub', "status_hub IN ('recebido_vsm','xml_validado')"),
      'enviados_tiny' => $this->safeCount('pedidos_hub', "status_hub='enviado_tiny'"),
      'concluidos' => $this->safeCount('pedidos_hub', "status_hub='concluido'"),
      'erros' => $this->safeCount('pedidos_hub', "status_hub LIKE '%erro%'"),
    ];
    $pageTitle = 'Centro de Operações';
    require __DIR__.'/../../views/centro_operacoes.php';
  }


  private function dashboardIntegridade(): void {
    PermissionService::require('dashboard','visualizar');
    $checks = DashboardIntegrityService::checks();
    $summary = DashboardIntegrityService::summary($checks);
    $pageTitle = 'Integridade do Dashboard';
    require __DIR__.'/../../views/dashboard_integridade.php';
  }

  private function pedidos(): void {
    PermissionService::require('pedidos','visualizar');
    $status = $_GET['status'] ?? '';
    $busca = trim($_GET['busca'] ?? '');
    $sql = "SELECT * FROM pedidos_integracao WHERE 1=1"; $params=[];
    if($status){ $sql .= " AND status=?"; $params[]=$status; }
    if($busca){ $sql .= " AND (pedido_origem_id LIKE ? OR pedido_tiny_id LIKE ? OR cliente_nome LIKE ?)"; $params[]="%$busca%"; $params[]="%$busca%"; $params[]="%$busca%"; }
    // Melhoria 1 da seção 8: o escopo de empresa entra DEPOIS do WHERE montado pelos filtros e
    // ANTES do ORDER BY/LIMIT — applyToSelect() cuida da posição do parâmetro.
    [$sql, $params] = TenantScopeService::applyToSelect('pedidos_integracao', $sql, $params);
    $sql .= " ORDER BY id DESC LIMIT 100";
    $st=Database::tableConnectionForSql($sql)->prepare($sql); $st->execute($params); $pedidos=$st->fetchAll();
    $pageTitle = 'Pedidos';
    require __DIR__.'/../../views/pedidos.php';
  }


  private function pedidoDetalhe(): void {
    PermissionService::require('pedidos','detalhe');
    $id = (int)($_GET['id'] ?? 0);
    $st = TenantScopeService::run('pedidos_integracao', "SELECT * FROM pedidos_integracao WHERE id=? LIMIT 1", [$id]);
    $pedido=$st->fetch();
    if(!$pedido){ http_response_code(404); echo 'Pedido não encontrado'; return; }
    $trace=$pedido['trace_id'] ?? '';
    $timeline=[]; $fila=[];
    if($trace){
      $st=Database::forTable('auditoria_eventos')->prepare("SELECT * FROM auditoria_eventos WHERE trace_id=? ORDER BY id ASC"); $st->execute([$trace]); $timeline=$st->fetchAll();
      $st = TenantScopeService::run('fila_integracao', "SELECT * FROM fila_integracao WHERE trace_id=? OR referencia=? ORDER BY id DESC", [$trace,$pedido['pedido_origem_id']]); $fila=$st->fetchAll();
    }
    $pageTitle='Detalhe do Pedido';
    require __DIR__.'/../../views/pedido_detalhe.php';
  }

  private function fila(): void {
    PermissionService::require('fila','visualizar');
    $status = $_GET['status'] ?? '';
    $sql = "SELECT id,tipo,referencia,status,tentativas,proxima_tentativa,trace_id,codigo_erro FROM fila_integracao WHERE 1=1"; $params=[];
    if($status){ $sql .= " AND status=?"; $params[]=$status; }
    // Melhoria 1 da seção 8: o escopo de empresa entra DEPOIS do WHERE montado pelos filtros e
    // ANTES do ORDER BY/LIMIT — applyToSelect() cuida da posição do parâmetro.
    [$sql, $params] = TenantScopeService::applyToSelect('fila_integracao', $sql, $params);
    $sql .= " ORDER BY id DESC LIMIT 200";
    $st=Database::tableConnectionForSql($sql)->prepare($sql); $st->execute($params); $itens=$st->fetchAll();
    $pageTitle = 'Fila de Integração';
    require __DIR__.'/../../views/fila.php';
  }

  private function filaReprocessar(): void {
    PermissionService::require('fila','reprocessar');
    Csrf::validate();
    $id=(int)($_POST['id'] ?? 0);
    if($id>0) QueueService::reprocessar($id);
    redirect('index.php?page=fila');
  }

  private function filaCriarTeste(): void {
    PermissionService::require('fila','reprocessar');
    Csrf::validate();
    $trace = RequestContext::id();
    $referencia = 'BAIXA-TESTE-'.date('Ymd-His');
    $payload = [
      'referencia' => $referencia,
      'origem' => 'tiny',
      'evento' => 'baixa_estoque_teste',
      'itens' => [[
        'sku' => 'TESTE001',
        'quantidade' => 1,
        'descricao' => 'Produto Teste Hub de Integração',
        'idProdutoTiny' => 'TESTE-TINY-001'
      ]],
      'observacao' => 'Baixa de estoque teste criada manualmente pelo painel. Este teste segue o fluxo correto: Tiny → Hub → VSM.',
      'criado_em' => date('c')
    ];
    $st = TenantScopeService::run('fila_integracao', 'INSERT INTO fila_integracao(tipo,referencia,payload,status,trace_id) VALUES(?,?,?,?,?)', ['baixa_estoque_vsm',$referencia,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'pendente',$trace]);
    Audit::event('fila.criar_baixa_teste','sucesso',[
      'entidade'=>'fila_integracao',
      'entidade_id'=>Database::forTable('fila_integracao')->lastInsertId(),
      'mensagem'=>'Baixa de estoque teste criada na fila conforme o fluxo correto Tiny → VSM.',
      'payload'=>$payload,
      'acao_recomendada'=>'Agora clique em Processar 1 item para testar o envio da baixa de estoque para a VSM.'
    ]);
    NotificationService::criar('sistema','Baixa Tiny teste criada','Foi criado um item pendente de baixa de estoque Tiny → VSM.','info',['trace_id'=>$trace,'link'=>'index.php?page=fila']);
    redirect('index.php?page=fila&baixa_teste_criada=1');
  }

  private function produtos(): void {
    PermissionService::require('produtos','visualizar');
    $produtos = TenantScopeService::run('produtos_mapeamento', "SELECT id, sku_tiny, sku_vsm, produto_tiny_id, produto_vsm_id, descricao, ativo, estoque_atual, status_tiny, ultima_sincronizacao, criado_em FROM produtos_mapeamento ORDER BY id DESC LIMIT 100")->fetchAll();
    $pageTitle = 'Produtos Mapeados';
    require __DIR__.'/../../views/produtos.php';
  }



  private function auditoriaCodigo(): void {
    PermissionService::require('auditoria','visualizar');
    $codigoAuditoria = CodeAuditService::analisar();
    $pageTitle = 'Auditoria de Código';
    require __DIR__.'/../../views/auditoria_codigo.php';
  }

  private function testeRealTiny(): void {
    PermissionService::require('laboratorio','executar');
    $resultadoTeste = $_SESSION['ultimo_teste_real_tiny'] ?? null;
    unset($_SESSION['ultimo_teste_real_tiny']);
    $historicoTestes = TesteRealTinyService::ultimos(20);
    $pageTitle = 'Teste Real Tiny';
    require __DIR__.'/../../views/teste_real_tiny.php';
  }

  private function testeRealTinyExecutar(): void {
    PermissionService::require('laboratorio','executar');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
      header('Location: index.php?page=teste-real-tiny');
      return;
    }
    Csrf::validate();
    try {
      $resultado = TesteRealTinyService::executar($_POST);
    } catch (Throwable $e) {
      $resultado = [
        'success' => false,
        'trace_id' => RequestContext::id(),
        'sku' => trim((string)($_POST['sku'] ?? '')),
        'acao' => (string)($_POST['acao_teste'] ?? ''),
        'modo_tiny' => (string)($_POST['modo_tiny'] ?? ''),
        'versao_efetiva' => 'bloqueado_pelo_hub',
        'fallback_detectado' => false,
        'resultados' => [[
          'etapa' => 'pre_validacao_segura',
          'ok' => false,
          'mensagem' => $e->getMessage(),
          'codigo_erro' => 'HUB_TESTE_REAL_BLOQUEADO'
        ]],
        'executado_em' => date('Y-m-d H:i:s'),
      ];
      Audit::exception($e, 'teste_real_tiny.bloqueado');
    }
    $_SESSION['ultimo_teste_real_tiny'] = $resultado;
    header('Location: index.php?page=teste-real-tiny');
  }

  private function logs(): void {
    PermissionService::require('logs','visualizar');
    $nivel = $_GET['nivel'] ?? '';
    $sql = "SELECT id,nivel,trace_id,tipo,codigo_erro,mensagem,ip,criado_em FROM logs_integracao"; $params=[];
    if($nivel){ $sql .= " WHERE nivel=?"; $params[]=$nivel; }
    $sql .= " ORDER BY id DESC LIMIT 200";
    // Achado C-05: com 100 clientes, esta tela mostrava os logs de todos misturados.
    [$sql, $params] = TenantScopeService::applyToSelect('logs_integracao', $sql, $params);
    $st=Database::tableConnectionForSql($sql)->prepare($sql); $st->execute($params); $logs=$st->fetchAll();
    $pageTitle = 'Logs';
    require __DIR__.'/../../views/logs.php';
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

  private function auditoriaDetalhe(): void {
    PermissionService::require('auditoria','visualizar');
    $id = (int)($_GET['id'] ?? 0);
    $st=Database::forTable('auditoria_eventos')->prepare("SELECT * FROM auditoria_eventos WHERE id=? LIMIT 1"); $st->execute([$id]); $evento=$st->fetch();
    if(!$evento){ http_response_code(404); echo 'Evento não encontrado'; return; }
    $st=Database::forTable('auditoria_eventos')->prepare("SELECT * FROM auditoria_eventos WHERE trace_id=? ORDER BY id ASC"); $st->execute([$evento['trace_id']]); $timeline=$st->fetchAll();
    $pageTitle = 'Detalhe da Auditoria';
    require __DIR__.'/../../views/auditoria_detalhe.php';
  }


  private function tinyWebhooks(): void {
    PermissionService::require('tiny_webhooks','visualizar');
    $tipo = $_GET['tipo'] ?? '';
    $status = $_GET['status'] ?? '';
    $busca = trim($_GET['busca'] ?? '');
    $sql = "SELECT * FROM tiny_webhooks WHERE 1=1"; $params=[];
    if($tipo){ $sql .= " AND tipo=?"; $params[]=$tipo; }
    if($status){ $sql .= " AND status=?"; $params[]=$status; }
    if($busca){ $sql .= " AND (referencia LIKE ? OR trace_id LIKE ? OR cnpj LIKE ? OR id_ecommerce LIKE ?)"; for($i=0;$i<4;$i++) $params[]="%$busca%"; }
    $sql .= " ORDER BY id DESC LIMIT 300";
    $st=Database::tableConnectionForSql($sql)->prepare($sql); $st->execute($params); $webhooks=$st->fetchAll();
    $pageTitle='Webhooks Tiny/Olist';
    require __DIR__.'/../../views/tiny_webhooks.php';
  }

  private function notificacoes(): void {
    PermissionService::require('notificacoes','visualizar');
    $tipo = $_GET['tipo'] ?? '';
    $lida = $_GET['lida'] ?? '';
    $sql = "SELECT * FROM notificacoes WHERE 1=1"; $params=[];
    if($tipo){ $sql .= " AND tipo=?"; $params[]=$tipo; }
    if($lida !== ''){ $sql .= " AND lida=?"; $params[]=(int)$lida; }
    $sql .= " ORDER BY id DESC LIMIT 300";
    $st=Database::tableConnectionForSql($sql)->prepare($sql); $st->execute($params); $notificacoes=$st->fetchAll();
    $resumo = Database::forTable('notificacoes')->query("SELECT severidade, COUNT(*) total FROM notificacoes WHERE lida=0 GROUP BY severidade")->fetchAll();
    $pageTitle = 'Notificações';
    require __DIR__.'/../../views/notificacoes.php';
  }

  private function marcarNotificacaoLida(): void {
    PermissionService::require('notificacoes','visualizar');
    $id = (int)($_POST['id'] ?? 0);
    $all = (int)($_POST['todas'] ?? 0);
    Csrf::validate();
    if($all){ NotificationService::marcarTodasLidas(); }
    elseif($id > 0){ NotificationService::marcarLida($id); }
    Audit::event('notificacao.marcar_lida','sucesso',['mensagem'=>$all?'Todas as notificações marcadas como lidas':'Notificação marcada como lida','entidade'=>'notificacoes','entidade_id'=>$id ?: 'todas']);
    redirect('index.php?page=notificacoes');
  }


  private function diagnostico(): void {
    PermissionService::require('dashboard','visualizar');
    $checks = HealthCheckService::run();
    $historicoDiagnostico = class_exists('DiagnosticoApiService') ? DiagnosticoApiService::ultimos(50) : [];
    $pageTitle = 'Diagnóstico';
    require __DIR__.'/../../views/diagnostico.php';
  }


  private function garantirEstruturaConfiguracoesVsm(): void {
    $required=['vsm_api_principal','vsm_api_loja','vsm_swagger_integradora','vsm_swagger_loja','vsm_waf_agressivo','vsm_api_observacao','vsm_ambiente','vsm_producao_liberada','vsm_ultimo_teste_ok','vsm_ultimo_teste_em','vsm_host_producao_liberado','vsm_producao_liberada_em','vsm_producao_liberada_por'];
    $missing=[]; foreach($required as $column) if(!Database::columnExists('configuracoes_integracao',$column)) $missing[]=$column;
    if($missing){
      try { Audit::event('configuracoes.vsm_api_profile_schema.pendente','alerta',['mensagem'=>'Estrutura VSM incompleta; nenhuma alteração DDL foi executada durante a requisição.','codigo_erro'=>'VSM_API_PROFILE_MIGRATION_REQUIRED','contexto'=>['colunas_ausentes'=>$missing],'acao_recomendada'=>'Executar Central Técnica > Migrações Seguras (V104.49.3).']); }
      catch(Throwable $e){ if(class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__,$e); }
    }
  }

  private function configuracoes(): void {
    PermissionService::require('configuracoes','visualizar');
    $this->garantirEstruturaConfiguracoesVsm();
    $config = Database::forTable('configuracoes_integracao')->query("SELECT * FROM configuracoes_integracao WHERE id=1 LIMIT 1")->fetch();
    foreach(['tiny_v2_token','tiny_v3_token','tiny_v3_client_secret','vsm_token','webhook_secret','tiny_webhook_secret'] as $k){ if(isset($config[$k])) $config[$k]=CryptoService::decrypt($config[$k]); }
    // Defaults profissionais exibidos no painel, sem depender do install.php.
    $config['tiny_v2_url'] = $config['tiny_v2_url'] ?: 'https://api.tiny.com.br/api2';
    $config['tiny_v3_url'] = $config['tiny_v3_url'] ?: 'https://api.tiny.com.br/public-api/v3';
    $config['tiny_v3_auth_url'] = $config['tiny_v3_auth_url'] ?: 'https://accounts.tiny.com.br/realms/tiny/protocol/openid-connect/auth';
    $config['tiny_v3_token_url'] = $config['tiny_v3_token_url'] ?: 'https://accounts.tiny.com.br/realms/tiny/protocol/openid-connect/token';
    $config['tiny_v3_redirect_uri'] = $config['tiny_v3_redirect_uri'] ?: ($this->appBaseUrl() . '/index.php?page=tiny-v3-callback');
    // Tiny/Olist V3 não aceita escopos livres como 'produtos estoque pedidos notas-fiscais'.
    // As permissões de Produtos/Estoque/Pedidos/NF-e são liberadas no aplicativo do Tiny.
    // Por padrão deixamos vazio e o parâmetro scope só é enviado se o usuário preencher um valor aceito pelo Tiny.
    $config['tiny_v3_scopes'] = trim((string)($config['tiny_v3_scopes'] ?? ''));
    $config['vsm_url'] = $config['vsm_url'] ?: 'https://conectavenda.homolog.vsm.com.br';
    $config['vsm_api_principal'] = $config['vsm_api_principal'] ?: 'pedidos-integradora';
    $config['vsm_api_loja'] = $config['vsm_api_loja'] ?: 'desativado';
    $config['vsm_swagger_integradora'] = $config['vsm_swagger_integradora'] ?: 'https://conectavenda.homolog.vsm.com.br/swagger-ui/index.html?urls.primaryName=pedidos-integradora';
    $config['vsm_swagger_loja'] = $config['vsm_swagger_loja'] ?: 'https://conectavenda.homolog.vsm.com.br/swagger-ui/index.html?urls.primaryName=pedidos-loja';
    $config['vsm_waf_agressivo'] = 0;
    $securityCfg = class_exists('App') ? (App::config()['security'] ?? []) : [];
    $config['queue_processing_timeout_minutes'] = max(10, min(240, (int)($config['queue_processing_timeout_minutes'] ?? ($securityCfg['queue_processing_timeout_minutes'] ?? 30))));
    $config['queue_lease_minutes'] = max(1, min(120, (int)($config['queue_lease_minutes'] ?? ($securityCfg['queue_lease_minutes'] ?? 5))));
    $queueLeaseMap = $securityCfg['queue_lease_minutes_by_type'] ?? [];
    $queueLeaseDb = json_decode((string)($config['queue_lease_by_type_json'] ?? ''), true);
    if (is_array($queueLeaseDb)) $queueLeaseMap = array_merge(is_array($queueLeaseMap)?$queueLeaseMap:[], $queueLeaseDb);
    $config['queue_lease_map'] = $queueLeaseMap;
    $config['vsm_status_operacional'] = class_exists('VsmEnvironmentService') ? VsmEnvironmentService::dashboardStatus($config) : ['modo'=>'Homologação','detalhe'=>'URL configurada','acao'=>'Validar VSM'];
    $pageTitle = 'Configurações';
    require __DIR__.'/../../views/configuracoes.php';
  }

  private function salvarConfiguracoes(): void {
    PermissionService::require('configuracoes','editar');
    if($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php?page=configuracoes');
    Csrf::validate();
    $this->garantirEstruturaConfiguracoesVsm();
    $atual = Database::forTable('configuracoes_integracao')->query("SELECT * FROM configuracoes_integracao WHERE id=1 LIMIT 1")->fetch() ?: [];
    foreach(['tiny_v2_token','tiny_v3_token','tiny_v3_client_secret','vsm_token','webhook_secret','tiny_webhook_secret'] as $k){ if(isset($atual[$k])) $atual[$k]=CryptoService::decrypt($atual[$k]); }
    $dados = [
      'ambiente' => $_POST['ambiente'] ?? 'homologacao',
      'tiny_versao' => $_POST['tiny_versao'] ?? 'v2',
      'tiny_v2_url' => trim($_POST['tiny_v2_url'] ?? '') ?: 'https://api.tiny.com.br/api2',
      'tiny_v2_token' => Secrets::keepIfMasked((string)($_POST['tiny_v2_token'] ?? ''), $atual['tiny_v2_token'] ?? ''),
      'tiny_v3_url' => trim($_POST['tiny_v3_url'] ?? '') ?: 'https://api.tiny.com.br/public-api/v3',
      'tiny_v3_ambiente' => in_array(($_POST['tiny_v3_ambiente'] ?? 'homologacao'), ['homologacao','producao'], true) ? $_POST['tiny_v3_ambiente'] : 'homologacao',
      'tiny_v3_token' => Secrets::keepIfMasked((string)($_POST['tiny_v3_token'] ?? ''), $atual['tiny_v3_token'] ?? ''),
      'tiny_v3_auth_url' => trim((string)($_POST['tiny_v3_auth_url'] ?? ($atual['tiny_v3_auth_url'] ?? ''))) ?: 'https://accounts.tiny.com.br/realms/tiny/protocol/openid-connect/auth',
      'tiny_v3_token_url' => trim((string)($_POST['tiny_v3_token_url'] ?? ($atual['tiny_v3_token_url'] ?? ''))) ?: 'https://accounts.tiny.com.br/realms/tiny/protocol/openid-connect/token',
      'tiny_v3_client_id' => trim((string)($_POST['tiny_v3_client_id'] ?? ($atual['tiny_v3_client_id'] ?? ''))),
      'tiny_v3_client_secret' => Secrets::keepIfMasked((string)($_POST['tiny_v3_client_secret'] ?? ''), $atual['tiny_v3_client_secret'] ?? ''),
      'tiny_v3_redirect_uri' => $this->normalizeTinyV3RedirectUri(trim((string)($_POST['tiny_v3_redirect_uri'] ?? ($atual['tiny_v3_redirect_uri'] ?? '')))),
      'tiny_v3_scopes' => trim((string)($_POST['tiny_v3_scopes'] ?? ($atual['tiny_v3_scopes'] ?? ''))),
      'vsm_url' => trim($_POST['vsm_url'] ?? '') ?: 'https://conectavenda.homolog.vsm.com.br',
      'vsm_token' => Secrets::keepIfMasked((string)($_POST['vsm_token'] ?? ''), $atual['vsm_token'] ?? ''),
      'vsm_api_principal' => in_array(($_POST['vsm_api_principal'] ?? 'pedidos-integradora'), ['pedidos-integradora','pedidos-loja'], true) ? $_POST['vsm_api_principal'] : 'pedidos-integradora',
      'vsm_api_loja' => in_array(($_POST['vsm_api_loja'] ?? 'desativado'), ['desativado','pedidos-loja'], true) ? $_POST['vsm_api_loja'] : 'desativado',
      'vsm_swagger_integradora' => trim((string)($_POST['vsm_swagger_integradora'] ?? ($atual['vsm_swagger_integradora'] ?? ''))) ?: 'https://conectavenda.homolog.vsm.com.br/swagger-ui/index.html?urls.primaryName=pedidos-integradora',
      'vsm_swagger_loja' => trim((string)($_POST['vsm_swagger_loja'] ?? ($atual['vsm_swagger_loja'] ?? ''))) ?: 'https://conectavenda.homolog.vsm.com.br/swagger-ui/index.html?urls.primaryName=pedidos-loja',
      'vsm_waf_agressivo' => 0,
      'vsm_api_observacao' => trim((string)($_POST['vsm_api_observacao'] ?? ($atual['vsm_api_observacao'] ?? ''))),
      'vsm_ambiente' => 'homologacao',
      'vsm_producao_liberada' => 0,
      'vsm_host_producao_liberado' => null,
      'vsm_endpoint_baixa_estoque' => trim($_POST['vsm_endpoint_baixa_estoque'] ?? ($atual['vsm_endpoint_baixa_estoque'] ?? '/api/estoque/baixa')),
      'vsm_endpoint_produto_novo' => trim($_POST['vsm_endpoint_produto_novo'] ?? ($atual['vsm_endpoint_produto_novo'] ?? '/api/produtos')),
      'vsm_endpoint_consulta_estoque' => trim($_POST['vsm_endpoint_consulta_estoque'] ?? ($atual['vsm_endpoint_consulta_estoque'] ?? '/api/estoque/consulta')),
      'fluxo_tiny_vsm_estoque' => isset($_POST['fluxo_tiny_vsm_estoque']) ? 1 : 0,
      'fluxo_vsm_tiny_produto' => isset($_POST['fluxo_vsm_tiny_produto']) ? 1 : 0,
      'fluxo_vsm_tiny_pedido' => isset($_POST['fluxo_vsm_tiny_pedido']) ? 1 : 0,
      'webhook_secret' => Secrets::keepIfMasked((string)($_POST['webhook_secret'] ?? ''), $atual['webhook_secret'] ?? ''),
      'tiny_webhook_secret' => Secrets::keepIfMasked((string)($_POST['tiny_webhook_secret'] ?? ''), $atual['tiny_webhook_secret'] ?? ''),
      'tiny_webhook_cnpj_autorizados' => trim((string)($_POST['tiny_webhook_cnpj_autorizados'] ?? ($atual['tiny_webhook_cnpj_autorizados'] ?? ''))),
      'tiny_webhook_exigir_secret' => isset($_POST['tiny_webhook_exigir_secret']) ? 1 : 0,
      'tiny_webhook_rate_limit' => max(1, (int)($_POST['tiny_webhook_rate_limit'] ?? ($atual['tiny_webhook_rate_limit'] ?? 60))),
      'tiny_webhook_max_bytes' => max(1024, (int)($_POST['tiny_webhook_max_bytes'] ?? ($atual['tiny_webhook_max_bytes'] ?? 1048576))),
      'bloquear_inativo_com_estoque' => isset($_POST['bloquear_inativo_com_estoque']) ? 1 : 0,
      'tiny_v3_operacional' => isset($_POST['tiny_v3_operacional']) ? 1 : 0,
      'queue_processing_timeout_minutes' => max(10, min(240, (int)($_POST['queue_processing_timeout_minutes'] ?? ($atual['queue_processing_timeout_minutes'] ?? 30)))),
      'queue_lease_minutes' => max(1, min(120, (int)($_POST['queue_lease_minutes'] ?? ($atual['queue_lease_minutes'] ?? 5)))),
    ];
    try {
      $dados['tiny_v2_url'] = TinyEndpointSecurityService::validateUrl((string)$dados['tiny_v2_url']);
      $dados['tiny_v3_url'] = TinyEndpointSecurityService::validateUrl((string)$dados['tiny_v3_url']);
      $dados['tiny_v3_auth_url'] = TinyEndpointSecurityService::validateUrl((string)$dados['tiny_v3_auth_url']);
      $dados['tiny_v3_token_url'] = TinyEndpointSecurityService::validateUrl((string)$dados['tiny_v3_token_url']);
    } catch (Throwable $e) {
      Audit::exception($e,'configuracoes.tiny.url_bloqueada',['codigo_erro'=>'TINY_URL_BLOCKED']);
      NotificationService::criar('sistema','URL Tiny bloqueada','A configuração não foi salva porque uma URL Tiny/OAuth não pertence à allowlist segura.','erro',['link'=>'index.php?page=configuracoes']);
      redirect('index.php?page=configuracoes&tiny_url=erro');
    }

    $queueTypes = ['pedido_tiny_para_vsm','baixa_estoque_vsm','produto_vsm_para_tiny','produto_vsm_atualizar_tiny','produto_vsm_estoque_para_tiny','produto_vsm_status_para_tiny'];
    $queueLeaseMap = [];
    foreach ($queueTypes as $queueType) {
      $queueLeaseMap[$queueType] = max(1, min(120, (int)($_POST['queue_lease_'.$queueType] ?? $dados['queue_lease_minutes'])));
    }
    $dados['queue_lease_by_type_json'] = json_encode($queueLeaseMap, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $vsmAmbienteSeguro = class_exists('VsmEnvironmentService') ? VsmEnvironmentService::sanitizePost($_POST, $atual) : ['vsm_ambiente'=>'homologacao','vsm_producao_liberada'=>0,'vsm_host_producao_liberado'=>null];
    foreach($vsmAmbienteSeguro as $k=>$v){ $dados[$k] = $v; }
    if (!empty($dados['vsm_producao_liberada']) && empty($dados['vsm_token'])) {
      $dados['vsm_producao_liberada'] = 0;
      NotificationService::criar('sistema','VSM produção não liberada','A liberação de produção VSM foi bloqueada porque o token VSM não está configurado.','erro',['link'=>'index.php?page=configuracoes']);
    }
    // P0-04 (reauditoria 2026-08-23): mudar a URL ou o token VSM invalidava silenciosamente
    // o teste de conexão anterior sem revogar vsm_ultimo_teste_ok. Isso permitia que um
    // teste bem-sucedido no host/token A continuasse "provando" que o host/token B (novo)
    // está pronto para produção. Agora qualquer alteração de alvo exige novo teste.
    // Preserva o valor atual por padrão; só é sobrescrito abaixo se o alvo mudou.
    // (sem isso, toda vez que $dados não trouxesse essas chaves, o UPDATE dinâmico
    // mais abaixo gravaria NULL e apagaria um teste válido sem motivo.)
    $dados['vsm_ultimo_teste_ok'] = $atual['vsm_ultimo_teste_ok'] ?? 0;
    $dados['vsm_ultimo_teste_em'] = $atual['vsm_ultimo_teste_em'] ?? null;
    $vsmAlvoAlterado = trim((string)$dados['vsm_url']) !== trim((string)($atual['vsm_url'] ?? ''))
      || trim((string)$dados['vsm_token']) !== trim((string)($atual['vsm_token'] ?? ''));
    if ($vsmAlvoAlterado) {
      $dados['vsm_ultimo_teste_ok'] = 0;
      $dados['vsm_ultimo_teste_em'] = null;
      if (!empty($dados['vsm_producao_liberada'])) {
        $dados['vsm_producao_liberada'] = 0;
        NotificationService::criar('sistema','VSM produção não liberada','A URL ou o token VSM mudaram: a liberação de produção foi revogada e exige novo teste de conexão bem-sucedido para o novo alvo.','alerta',['link'=>'index.php?page=configuracoes']);
      }
      Audit::event('vsm.ambiente.alvo_alterado','alerta',['mensagem'=>'URL ou token VSM alterados: teste de conexão anterior invalidado.','codigo_erro'=>'VSM_TARGET_CHANGED_TEST_INVALIDATED']);
    }

    foreach(TinyV3EndpointCatalog::defaults() as $key=>$defaultEndpoint){
      $campo = 'tiny_v3_'.$key;
      $dados[$campo] = trim((string)($_POST[$campo] ?? ($atual[$campo] ?? '')));
    }
    if (!empty($dados['tiny_v3_operacional'])) {
      $ready = $this->tinyV3OperationalReady($dados);
      if (!$ready['ok']) {
        $dados['tiny_v3_operacional'] = 0;
        NotificationService::criar('sistema','Tiny V3 não ativado','A ativação operacional da Tiny V3 foi bloqueada porque o checklist obrigatório ainda possui pendências. Abra a Ficha Tiny V3 e a Homologação para concluir os itens.','erro',['issues'=>$ready['issues'],'link'=>'index.php?page=tiny-v3-ficha']);
        $_SESSION['tiny_v3_blocked_issues'] = $ready['issues'];
        Audit::event('tiny.v3.operacional.bloqueado','erro',[
          'mensagem'=>'Tentativa de ativar Tiny V3 operacional bloqueada pelo checklist obrigatório V29.',
          'codigo_erro'=>'TINY_V3_OPERATIONAL_CHECKLIST_FAILED',
          'contexto'=>$ready,
          'acao_recomendada'=>'Concluir OAuth, testes por módulo, endpoint VSM e checklist de homologação antes de marcar como operacional.'
        ]);
      }
    }
    $dadosSalvar = $dados;
    foreach(['tiny_v2_token','tiny_v3_token','tiny_v3_client_secret','vsm_token','webhook_secret','tiny_webhook_secret'] as $campoSecreto){
      $dadosSalvar[$campoSecreto] = CryptoService::encrypt((string)$dadosSalvar[$campoSecreto]);
    }
    $st = Database::forTable('configuracoes_integracao')->prepare("UPDATE configuracoes_integracao SET ambiente=?, tiny_versao=?, tiny_v2_url=?, tiny_v2_token=?, tiny_v3_url=?, tiny_v3_ambiente=?, tiny_v3_token=?, tiny_v3_auth_url=?, tiny_v3_token_url=?, tiny_v3_client_id=?, tiny_v3_client_secret=?, tiny_v3_redirect_uri=?, tiny_v3_scopes=?, vsm_url=?, vsm_token=?, vsm_endpoint_baixa_estoque=?, vsm_endpoint_produto_novo=?, vsm_endpoint_consulta_estoque=?, fluxo_tiny_vsm_estoque=?, fluxo_vsm_tiny_produto=?, fluxo_vsm_tiny_pedido=?, webhook_secret=?, tiny_webhook_secret=?, tiny_webhook_cnpj_autorizados=?, tiny_webhook_exigir_secret=?, tiny_webhook_rate_limit=?, tiny_webhook_max_bytes=?, bloquear_inativo_com_estoque=?, tiny_v3_operacional=? WHERE id=1");
    $st->execute([
      $dadosSalvar['ambiente'],$dadosSalvar['tiny_versao'],$dadosSalvar['tiny_v2_url'],$dadosSalvar['tiny_v2_token'],$dadosSalvar['tiny_v3_url'],$dadosSalvar['tiny_v3_ambiente'],$dadosSalvar['tiny_v3_token'],
      $dadosSalvar['tiny_v3_auth_url'],$dadosSalvar['tiny_v3_token_url'],$dadosSalvar['tiny_v3_client_id'],$dadosSalvar['tiny_v3_client_secret'],$dadosSalvar['tiny_v3_redirect_uri'],$dadosSalvar['tiny_v3_scopes'],
      $dadosSalvar['vsm_url'],$dadosSalvar['vsm_token'],$dadosSalvar['vsm_endpoint_baixa_estoque'],$dadosSalvar['vsm_endpoint_produto_novo'],$dadosSalvar['vsm_endpoint_consulta_estoque'],
      (int)$dadosSalvar['fluxo_tiny_vsm_estoque'],(int)$dadosSalvar['fluxo_vsm_tiny_produto'],(int)$dadosSalvar['fluxo_vsm_tiny_pedido'],$dadosSalvar['webhook_secret'],
      $dadosSalvar['tiny_webhook_secret'],$dadosSalvar['tiny_webhook_cnpj_autorizados'],(int)$dadosSalvar['tiny_webhook_exigir_secret'],(int)$dadosSalvar['tiny_webhook_rate_limit'],(int)$dadosSalvar['tiny_webhook_max_bytes'],(int)$dadosSalvar['bloquear_inativo_com_estoque'],(int)$dadosSalvar['tiny_v3_operacional']
    ]);
    // V104.4: campos VSM/API profile são salvos de forma compatível com bancos já instalados.
    try {
      $vsmSets=[]; $vsmValues=[];
      // P0-04: vsm_ultimo_teste_ok/vsm_ultimo_teste_em entram aqui para que a invalidação
      // calculada acima ($vsmAlvoAlterado) seja realmente persistida quando URL/token mudam.
      foreach(['vsm_api_principal','vsm_api_loja','vsm_swagger_integradora','vsm_swagger_loja','vsm_waf_agressivo','vsm_api_observacao','vsm_ambiente','vsm_producao_liberada','vsm_host_producao_liberado','vsm_ultimo_teste_ok','vsm_ultimo_teste_em'] as $campo){
        if($this->columnExistsForUpdate('configuracoes_integracao',$campo)){
          $vsmSets[] = $campo.'=?';
          $vsmValues[] = $dadosSalvar[$campo] ?? $dados[$campo] ?? null;
        }
      }
      if($vsmSets){
        Database::forTable('configuracoes_integracao')->prepare('UPDATE configuracoes_integracao SET '.implode(',', $vsmSets).' WHERE id=1')->execute($vsmValues);
      }
    } catch(Throwable $e){ Audit::exception($e,'configuracoes.vsm_api_profile.erro',['codigo_erro'=>'VSM_API_PROFILE_SAVE_ERROR']); }
    if (class_exists('VsmEndpointService')) VsmEndpointService::invalidateBaseConfigCache();
    try {
      $queueSets=[]; $queueValues=[];
      foreach(['queue_processing_timeout_minutes','queue_lease_minutes','queue_lease_by_type_json'] as $campo){
        if($this->columnExistsForUpdate('configuracoes_integracao',$campo)){
          $queueSets[]=$campo.'=?'; $queueValues[]=$dados[$campo];
        }
      }
      if($queueSets) Database::forTable('configuracoes_integracao')->prepare('UPDATE configuracoes_integracao SET '.implode(',', $queueSets).' WHERE id=1')->execute($queueValues);
      else Audit::event('configuracoes.fila.schema_pendente','alerta',['mensagem'=>'Configuração de lease da fila não foi persistida porque a migration V104.49.3 ainda não foi aplicada.','acao_recomendada'=>'Aplicar database/migrations/20260712_001_queue_oauth_concurrency.sql.']);
    } catch(Throwable $e){ Audit::exception($e,'configuracoes.fila_runtime.erro',['codigo_erro'=>'QUEUE_RUNTIME_CONFIG_SAVE_ERROR']); }
    try {
      if (!empty($dados['vsm_producao_liberada']) && $this->columnExistsForUpdate('configuracoes_integracao','vsm_producao_liberada_em')) {
        Database::forTable('configuracoes_integracao')->prepare('UPDATE configuracoes_integracao SET vsm_producao_liberada_em=COALESCE(vsm_producao_liberada_em,NOW()), vsm_producao_liberada_por=COALESCE(vsm_producao_liberada_por,?) WHERE id=1')->execute([Auth::user()['id'] ?? null]);
      }
    } catch(Throwable $e){ Audit::exception($e,'configuracoes.vsm_producao_liberacao.erro',['codigo_erro'=>'VSM_PRODUCTION_RELEASE_SAVE_ERROR']); }
    // Endpoints Tiny V3 editáveis são salvos separadamente para manter compatibilidade com bancos antigos.
    try {
      $endpointSets=[]; $endpointValues=[];
      foreach(TinyV3EndpointCatalog::defaults() as $key=>$defaultEndpoint){
        $campo='tiny_v3_'.$key;
        if($this->columnExistsForUpdate('configuracoes_integracao',$campo)){
          $endpointSets[]="$campo=?";
          $endpointValues[]=$dados[$campo] ?: $defaultEndpoint;
        }
      }
      if($endpointSets){
        Database::forTable('configuracoes_integracao')->prepare('UPDATE configuracoes_integracao SET '.implode(',', $endpointSets).' WHERE id=1')->execute($endpointValues);
      }
    } catch(Throwable $e){ Audit::exception($e,'configuracoes.tiny_v3_endpoints.erro',['codigo_erro'=>'TINY_V3_ENDPOINT_CONFIG_SAVE_ERROR']); }

    foreach($dados as $campo=>$novo){
      $antigo = (string)($atual[$campo] ?? '');
      if($antigo !== (string)$novo){
        $mask = str_contains($campo,'token') || str_contains($campo,'secret');
        $this->db('configuracoes_historico')->prepare('INSERT INTO configuracoes_historico(usuario_id,campo,valor_antigo,valor_novo,trace_id) VALUES(?,?,?,?,?)')->execute([Auth::user()['id']??null,$campo,$mask?Secrets::mask($antigo):$antigo,$mask?Secrets::mask((string)$novo):(string)$novo,RequestContext::id()]);
      }
    }
    Audit::event('configuracoes.atualizar','sucesso',['mensagem'=>'Configurações de integração atualizadas','entidade'=>'configuracoes_integracao','entidade_id'=>'1']);
    redirect('index.php?page=configuracoes&salvo=1'.(!empty($_SESSION['tiny_v3_blocked_issues'])?'&tinyv3_bloqueado=1':''));
  }

  private function testarTiny(): void {
    PermissionService::require('configuracoes','editar');
    Csrf::validate();
    try {
      $tiny = TinyFactory::make();
      $ret = $tiny->consultarProduto($_POST['sku'] ?? 'TESTE');
      $okTiny = !isset($ret['erro']) && !isset($ret['errors']);
      if (class_exists('DiagnosticoApiService')) DiagnosticoApiService::registrar('tiny', 'produto.consultar', $okTiny ? 'online' : 'erro', null, null, $okTiny ? 'Tiny respondeu ao teste.' : 'Tiny retornou erro no teste.', $ret);
      Audit::event('tiny.teste_conexao','sucesso',['mensagem'=>'Teste de conexão Tiny executado.','retorno'=>$ret]);
      NotificationService::criar('sistema','Teste Tiny executado','Veja o retorno completo na Auditoria.','info',['trace_id'=>RequestContext::id()]);
      redirect('index.php?page=configuracoes&teste=ok');
    } catch(Throwable $e){
      Audit::exception($e,'tiny.teste_conexao.erro');
      redirect('index.php?page=configuracoes&teste=erro');
    }
  }

  private function testarVsm(): void {
    PermissionService::require('configuracoes','editar');
    Csrf::validate();
    try {
      $cfg = IntegrationConfig::get();
      $resultado = HealthCheckService::testarVsm($cfg);
      if (class_exists('VsmEnvironmentService')) VsmEnvironmentService::registerTestResult(!empty($resultado['ok']));
      $status = $resultado['ok'] ? 'sucesso' : 'erro';
      Audit::event('vsm.teste_conexao',$status,[
        'codigo_erro'=>$resultado['ok'] ? null : 'VSM_CONNECTION_TEST_FAILED',
        'mensagem'=>'Teste de conexão VSM executado.',
        'causa_provavel'=>$resultado['ok'] ? null : 'URL VSM incorreta, DNS indisponível, HTTPS bloqueado, token inválido ou endpoint de teste inexistente.',
        'acao_recomendada'=>$resultado['ok'] ? 'Conexão VSM validada.' : 'Confira VSM URL, token, internet do servidor, SSL/cURL e endpoint informado no Swagger.',
        'retorno'=>$resultado
      ]);
      NotificationService::criar('sistema',$resultado['ok']?'Teste VSM OK':'Teste VSM falhou',$resultado['mensagem'] ?? 'Veja detalhes na Auditoria.',$resultado['ok']?'sucesso':'erro',['trace_id'=>RequestContext::id()]);
      redirect('index.php?page=configuracoes&teste_vsm='.($resultado['ok']?'ok':'erro'));
    } catch(Throwable $e){
      Audit::exception($e,'vsm.teste_conexao.erro',[
        'codigo_erro'=>'VSM_CONNECTION_TEST_EXCEPTION',
        'causa_provavel'=>'Falha inesperada ao executar diagnóstico VSM.',
        'acao_recomendada'=>'Confira a configuração VSM e veja o erro técnico no evento de auditoria.'
      ]);
      redirect('index.php?page=configuracoes&teste_vsm=erro');
    }
  }


  private function tinyV3Callback(): void {
    // Reauditoria 2026-09-14 (achado A-02): este callback roda ANTES do gate de sessão
    // (ver FastRouteDispatcherService), porque o retorno do provedor OAuth é cross-site e o
    // navegador não envia o cookie SameSite=Strict. A autorização acontece logo abaixo,
    // contra o perfil vinculado à transação OAuth assinada - nunca contra a sessão.
    $cfg = IntegrationConfig::get();
    $code = trim((string)($_GET['code'] ?? ''));
    $state = trim((string)($_GET['state'] ?? ''));
    if ($code === '') {
      Audit::event('tiny.v3.oauth.callback.erro','erro',[
        'mensagem'=>'Callback Tiny V3 recebido sem authorization code.',
        'codigo_erro'=>'TINY_V3_OAUTH_CODE_MISSING',
        'contexto'=>['query'=>$_GET],
        'acao_recomendada'=>'Clique novamente em Conectar Tiny V3 e confira Redirect URI no aplicativo Tiny/Olist.'
      ]);
      redirect('index.php?page=tiny-v3-ficha&oauth=erro');
    }
    // P0-01 (reauditoria 2026-08-23): valida o state real (aleatório, uso único, TTL)
    // ANTES de trocar o code por token. Sem isso o callback aceitava qualquer state.
    try {
      $oauthTransaction = OAuthStateService::consume('tiny_v3', $state);
      $codeVerifier = $oauthTransaction['verifier'];
    } catch (Throwable $e) {
      Audit::event('tiny.v3.oauth.state.invalido','erro',[
        'mensagem'=>'Callback Tiny V3 rejeitado: state OAuth inválido, expirado ou reaproveitado.',
        'codigo_erro'=>'TINY_V3_OAUTH_STATE_INVALID',
        'contexto'=>['detalhe'=>$e->getMessage()],
        'acao_recomendada'=>'Possível tentativa de OAuth CSRF ou link de callback reaproveitado. Inicie a conexão novamente pela Ficha Tiny V3.'
      ]);
      redirect('index.php?page=tiny-v3-ficha&oauth=erro');
    }
    // Reauditoria 2026-09-14 (achado B-01/B-03, revisão da própria correção do A-02): a transação
    // identifica QUEM iniciou, mas o perfil gravado nela tem até 10 minutos de idade. Se nesse
    // intervalo a conta foi desativada ou rebaixada, autorizar pelo instantâneo seria decidir por
    // dado vencido. A identidade vem da transação assinada; a autorização vem do estado atual do
    // banco. userById() já filtra ativo=1, então conta desativada não passa.
    $usuarioAtual = AuthRepository::userById((int)$oauthTransaction['user_id']);
    if (!$usuarioAtual) {
      Audit::event('tiny.v3.oauth.callback.usuario_invalido','erro',[
        'mensagem'=>'Callback Tiny V3 rejeitado: usuário que iniciou o fluxo não existe mais ou foi desativado.',
        'codigo_erro'=>'TINY_V3_OAUTH_USER_INACTIVE',
        'entidade'=>'usuarios','entidade_id'=>$oauthTransaction['user_id'],
        'acao_recomendada'=>'Refaça a conexão com uma conta ativa.'
      ]);
      redirect('index.php?page=tiny-v3-ficha&oauth=erro');
    }
    $oauthTransaction['perfil'] = (string)($usuarioAtual['perfil'] ?? '');
    if (!PermissionService::canForProfile($oauthTransaction['perfil'], 'configuracoes', 'editar')) {
      Audit::event('tiny.v3.oauth.callback.sem_permissao','erro',[
        'mensagem'=>'Callback Tiny V3 rejeitado: o usuário que iniciou o fluxo não tem permissão para editar configurações.',
        'codigo_erro'=>'TINY_V3_OAUTH_FORBIDDEN',
        'entidade'=>'usuarios',
        'entidade_id'=>$oauthTransaction['user_id'],
        'contexto'=>['perfil'=>$oauthTransaction['perfil']],
        'acao_recomendada'=>'Refaça a conexão com um usuário que tenha permissão configuracoes.editar.'
      ]);
      redirect('index.php?page=tiny-v3-ficha&oauth=erro');
    }
    try {
      $tokenUrl = (string)($cfg['tiny_v3_token_url'] ?? '');
      $clientId = (string)($cfg['tiny_v3_client_id'] ?? '');
      $clientSecret = (string)($cfg['tiny_v3_client_secret'] ?? '');
      $redirectUri = $this->normalizeTinyV3RedirectUri((string)($cfg['tiny_v3_redirect_uri'] ?? ''));
      if ($tokenUrl==='' || $clientId==='' || $clientSecret==='' || $redirectUri==='') {
        throw new RuntimeException('Configuração OAuth Tiny V3 incompleta: Token URL, Client ID, Client Secret ou Redirect URI ausente.');
      }
      $post = [
        'grant_type'=>'authorization_code',
        'code'=>$code,
        'redirect_uri'=>$redirectUri,
        'client_id'=>$clientId,
        'client_secret'=>$clientSecret,
        'code_verifier'=>$codeVerifier,
      ];
      $tokenUrl = TinyEndpointSecurityService::validateUrl($tokenUrl);
      $securityOptions = TinyEndpointSecurityService::curlSecurityOptions($tokenUrl);
      $ch = curl_init($tokenUrl);
      curl_setopt_array($ch,$securityOptions+[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
      $body = curl_exec($ch); $err = curl_error($ch); $http = (int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
      if ($err) throw new RuntimeException('Erro cURL OAuth Tiny V3: '.$err);
      $json = json_decode((string)$body,true);
      if ($http < 200 || $http >= 300 || !is_array($json) || empty($json['access_token'])) {
        Audit::event('tiny.v3.oauth.token.erro','erro',[
          'mensagem'=>'Tiny V3 recusou troca do code por token.',
          'codigo_erro'=>'TINY_V3_OAUTH_TOKEN_EXCHANGE_FAILED',
          'http_code'=>$http,
          'retorno'=>SensitiveDataService::mask((string)$body),
          'acao_recomendada'=>'Confira Client ID, Client Secret, Redirect URI e permissões do aplicativo no Tiny/Olist.'
        ]);
        redirect('index.php?page=tiny-v3-ficha&oauth=erro');
      }
      TinyV3TokenService::saveOAuthToken((string)$json['access_token'], (string)($json['refresh_token'] ?? ''), (int)($json['expires_in'] ?? 3600), (string)($json['scope'] ?? ''), $cfg['tiny_v3_ambiente'] ?? 'homologacao');
      Audit::event('tiny.v3.oauth.callback.sucesso','sucesso',[
        'mensagem'=>'Tiny V3 conectado via OAuth e token salvo por ambiente.',
        'entidade'=>'usuarios',
        'entidade_id'=>$oauthTransaction['user_id'],
        'contexto'=>['ambiente'=>$cfg['tiny_v3_ambiente'] ?? 'homologacao','state'=>$state,'iniciado_por_usuario_id'=>$oauthTransaction['user_id'],'perfil'=>$oauthTransaction['perfil']]
      ]);
      redirect('index.php?page=tiny-v3-ficha&oauth=ok');
    } catch(Throwable $e) {
      Audit::exception($e,'tiny.v3.oauth.callback.exception',[ 'codigo_erro'=>'TINY_V3_OAUTH_CALLBACK_EXCEPTION', 'acao_recomendada'=>'Corrija a configuração OAuth no painel e tente conectar novamente.' ]);
      redirect('index.php?page=tiny-v3-ficha&oauth=erro');
    }
  }

  private function tinyV3Ficha(): void {
    PermissionService::require('configuracoes','editar');
    $config = IntegrationConfig::get();
    $config['tiny_v3_auth_url_error'] = null;
    try { $config['tiny_v3_auth_url'] = TinyEndpointSecurityService::validateUrl((string)($config['tiny_v3_auth_url'] ?? '')); }
    catch(Throwable $e) {
      $config['tiny_v3_auth_url_error'] = 'Auth URL Tiny V3 bloqueada pela allowlist de segurança.';
      $config['tiny_v3_auth_url'] = '';
      if(class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e, ['provider'=>'tiny_v3','field'=>'auth_url']);
    }
    $config['tiny_v3_redirect_uri_effective'] = $this->normalizeTinyV3RedirectUri((string)($config['tiny_v3_redirect_uri'] ?? ''));
    $config['tiny_v3_redirect_uri_is_relative'] = !empty($config['tiny_v3_redirect_uri']) && !preg_match('#^https?://#i', (string)$config['tiny_v3_redirect_uri']);
    $tokenStatus = TinyV3TokenService::status();
    $ficha = TinyV3FichaTecnicaService::itens();
    $score = TinyV3FichaTecnicaService::score();
    $endpoints = TinyV3EndpointCatalog::defaults();
    $endpointValues = [];
    foreach($endpoints as $ek=>$ev){ $endpointValues[$ek] = TinyV3EndpointCatalog::get($ek); }
    try { $tokenRows = Database::forTable('tiny_v3_tokens')->query("SELECT id, ambiente, expires_at, scope, origem, criado_em, atualizado_em FROM tiny_v3_tokens ORDER BY ambiente ASC, id DESC LIMIT 20")->fetchAll(); } catch(Throwable $e) { $tokenRows = []; }
    try { $tinyV3Logs = Database::forTable('tiny_v3_endpoint_logs')->query("SELECT endpoint, metodo, http_code, sucesso, tempo_ms, trace_id, erro, criado_em FROM tiny_v3_endpoint_logs ORDER BY id DESC LIMIT 10")->fetchAll(); } catch(Throwable $e) { $tinyV3Logs = []; }
    $tinyV3Diagnostico = [
      'client_id' => !empty($config['tiny_v3_client_id']),
      'client_secret' => !empty($config['tiny_v3_client_secret']),
      'redirect_uri' => !empty($config['tiny_v3_redirect_uri_effective']) && preg_match('#^https?://#i', (string)$config['tiny_v3_redirect_uri_effective']),
      'scope_seguro' => trim((string)($config['tiny_v3_scopes'] ?? '')) === '' || !str_contains((string)$config['tiny_v3_scopes'], 'produtos estoque pedidos notas-fiscais'),
      'token' => !empty($tokenStatus['tem_token_tabela']) || !empty($tokenStatus['tem_token_manual']),
      'operacional' => !empty($config['tiny_v3_operacional']),
    ];
    // P0-01 (reauditoria 2026-08-23): state OAuth aleatório de uso único + PKCE S256,
    // gerado antes de qualquer saída HTML para poder setar o cookie de correlação.
    $config['tiny_v3_oauth_state'] = OAuthStateService::start('tiny_v3');
    $pageTitle='Tiny V3 - OAuth, Token e Diagnóstico';
    require __DIR__.'/../../views/tiny_v3_ficha.php';
  }

  private function tinyV3TokenSalvar(): void {
    PermissionService::require('configuracoes','editar');
    Csrf::validate();
    try {
      TinyV3TokenService::saveManual((string)($_POST['access_token'] ?? ''), (string)($_POST['refresh_token'] ?? ''), (int)($_POST['expires_in'] ?? 3600), (string)($_POST['scope'] ?? ''), (string)($_POST['ambiente_token'] ?? (IntegrationConfig::get()['tiny_v3_ambiente'] ?? 'homologacao')));
      NotificationService::criar('sistema','Tiny V3 token salvo','Token Tiny V3 foi salvo criptografado.','sucesso',['link'=>'index.php?page=tiny-v3-ficha']);
      redirect('index.php?page=tiny-v3-ficha&token=ok');
    } catch(Throwable $e){ Audit::exception($e,'tiny.v3.token.salvar.erro'); redirect('index.php?page=tiny-v3-ficha&token=erro'); }
  }

  private function tinyV3TokenRenovar(): void {
    PermissionService::require('configuracoes','editar');
    Csrf::validate();
    $ret=TinyV3TokenService::refresh(true);
    Audit::event('tiny.v3.token.renovar',empty($ret['erro'])?'sucesso':'erro',['mensagem'=>'Renovação de token Tiny V3 executada.','retorno'=>$ret]);
    redirect('index.php?page=tiny-v3-ficha&refresh='.(empty($ret['erro'])?'ok':'erro'));
  }

  private function tinyV3TokenRevogar(): void {
    PermissionService::require('configuracoes','editar');
    Csrf::validate();
    $ambiente = (string)($_POST['ambiente_token'] ?? (IntegrationConfig::get()['tiny_v3_ambiente'] ?? 'homologacao'));
    if (!in_array($ambiente, ['homologacao','producao'], true)) $ambiente = 'homologacao';
    try {
      $st = $this->db('tiny_v3_tokens')->prepare('DELETE FROM tiny_v3_tokens WHERE ambiente=?');
      $st->execute([$ambiente]);
      Audit::event('tiny.v3.token.revogar','sucesso',[
        'mensagem'=>'Tokens Tiny V3 revogados/removidos para o ambiente selecionado.',
        'contexto'=>['ambiente'=>$ambiente,'removidos'=>$st->rowCount()],
        'acao_recomendada'=>'Reconectar via OAuth antes de usar Tiny V3 neste ambiente.'
      ]);
      NotificationService::criar('sistema','Token Tiny V3 revogado','Tokens removidos para o ambiente '.$ambiente.'.','alerta',['link'=>'index.php?page=tiny-v3-ficha']);
      redirect('index.php?page=tiny-v3-ficha&revogar=ok');
    } catch(Throwable $e) {
      Audit::exception($e,'tiny.v3.token.revogar.erro',['codigo_erro'=>'TINY_V3_TOKEN_REVOKE_ERROR']);
      redirect('index.php?page=tiny-v3-ficha&revogar=erro');
    }
  }

  private function tinyV3Testar(): void {
    PermissionService::require('configuracoes','editar');
    Csrf::validate();
    $sku=trim((string)($_POST['sku'] ?? '')) ?: 'TESTE';
    $tiny=new TinyV3Service(IntegrationConfig::get());
    $ret=$tiny->consultarProduto($sku);
    Audit::event('tiny.v3.teste','info',['mensagem'=>'Teste Tiny V3 executado por SKU.','payload'=>['sku'=>$sku],'retorno'=>$ret]);
    redirect('index.php?page=tiny-v3-ficha&teste=ok');
  }


  private function tinyV3TestarModulo(): void {
    PermissionService::require('configuracoes','editar');
    Csrf::validate();
    $modulo = (string)($_POST['modulo'] ?? 'produto');
    $params = [
      'sku' => trim((string)($_POST['sku'] ?? '')),
      'id_pedido' => trim((string)($_POST['id_pedido'] ?? '')),
      'id_nota' => trim((string)($_POST['id_nota'] ?? '')),
    ];
    $tiny = new TinyV3Service(IntegrationConfig::get());
    $ret = $tiny->testarModulo($modulo, $params);
    Audit::event('tiny.v3.teste_modulo', empty($ret['erro']) ? 'info' : 'erro', [
      'mensagem' => 'Teste Tiny V3 por módulo executado.',
      'payload' => ['modulo'=>$modulo,'params'=>$params],
      'retorno' => $ret,
      'codigo_erro' => $ret['codigo_erro'] ?? null,
      'acao_recomendada' => empty($ret['erro']) ? 'Guardar evidência no checklist de homologação.' : 'Conferir token, endpoint, permissões e parâmetros reais da Tiny V3.'
    ]);
    redirect('index.php?page=tiny-v3-ficha&teste_modulo='.urlencode($modulo));
  }

  private function tinyV3EndpointsSalvar(): void {
    PermissionService::require('configuracoes','editar');
    Csrf::validate();
    $defaults = TinyV3EndpointCatalog::defaults();
    $sets=[]; $values=[]; $historico=[];
    $atual = IntegrationConfig::get();
    foreach($defaults as $key=>$default){
      $col = 'tiny_v3_'.$key;
      $novo = trim((string)($_POST[$col] ?? ''));
      if($novo === '') $novo = $default;
      $sets[] = "$col=?";
      $values[] = $novo;
      if((string)($atual[$col] ?? '') !== $novo) $historico[$col] = ['antes'=>$atual[$col] ?? '', 'depois'=>$novo];
    }
    if($sets){
      $sql = 'UPDATE configuracoes_integracao SET '.implode(',', $sets).' WHERE id=1';
      Database::tableConnectionForSql($sql)->prepare($sql)->execute($values);
    }
    Audit::event('tiny.v3.endpoints.salvar','sucesso',[
      'mensagem'=>'Endpoints Tiny V3 salvos/atualizados pelo painel.',
      'contexto'=>$historico
    ]);
    redirect('index.php?page=tiny-v3-ficha&endpoints=ok');
  }

  private function tinyV3OperationalReady(array $dados = []): array {
    $issues = [];
    $ready = TinyV3TokenService::homologationReady();
    if (!$ready['ok']) $issues = array_merge($issues, $ready['issues']);
    $ambiente = $dados['ambiente'] ?? (IntegrationConfig::get()['ambiente'] ?? 'homologacao');
    $vsmEndpoint = trim((string)($dados['vsm_endpoint_consulta_estoque'] ?? ''));
    if ($vsmEndpoint === '') $issues[] = 'Endpoint VSM de consulta de estoque não configurado para reconciliação/homologação.';
    try {
      SchemaRuntimePolicyService::requireTable('homologacao_checklist', 'prontidão operacional Tiny V3');
      $obrigatorios = ['tiny_v3_token','tiny_v3_produto_sku','tiny_v3_estoque','vsm_conexao','vsm_estoque_consulta'];
      if ($ambiente === 'producao') {
        $obrigatorios[] = 'tiny_v3_refresh';
        $obrigatorios[] = 'tiny_v3_logs';
      }
      foreach ($obrigatorios as $chave) {
        $st = $this->db('homologacao_checklist')->prepare("SELECT status FROM homologacao_checklist WHERE chave=? LIMIT 1");
        $st->execute([$chave]);
        $row = $st->fetch();
        if (!$row || !in_array((string)$row['status'], ['ok','nao_aplicavel'], true)) {
          $issues[] = 'Checklist obrigatório pendente: '.$chave;
        }
      }
    } catch (Throwable $e) {
      $issues[] = 'Não foi possível validar checklist de homologação: '.$e->getMessage();
    }
    return ['ok'=>empty($issues), 'issues'=>array_values(array_unique($issues)), 'token_ready'=>$ready];
  }


  private function usuarios(): void {
    PermissionService::require('usuarios','gerenciar');
    $usuarios = $this->db('usuarios')->query("SELECT id,nome,email,perfil,ativo,ultimo_login,deve_trocar_senha,tentativas_login,bloqueado_ate,two_factor_enabled,two_factor_secret,two_factor_created_at,two_factor_last_verified_at,empresa_id,criado_em FROM usuarios ORDER BY id DESC")->fetchAll();
    $empresas = EmpresaCatalogService::listar();
    $permissoes = $this->db('permissoes_perfil')->query("SELECT * FROM permissoes_perfil ORDER BY perfil,modulo,acao")->fetchAll();
    $senhaTemporaria=$_SESSION['senha_temporaria_ultima']??null;unset($_SESSION['senha_temporaria_ultima']);
    $pageTitle='Usuários e Permissões';
    require __DIR__.'/../../views/usuarios.php';
  }

  private function usuarioSalvar(): void {
    PermissionService::require('usuarios','gerenciar');
    Csrf::validate();
    $id=(int)($_POST['id'] ?? 0);
    $nome=trim($_POST['nome'] ?? '');
    $email=trim($_POST['email'] ?? '');
    $perfil=$_POST['perfil'] ?? 'operador';
    $ativo=(int)($_POST['ativo'] ?? 0);
    $trocar=(int)($_POST['deve_trocar_senha'] ?? 0);
    $senha=(string)($_POST['senha'] ?? '');
    $twoFactor=(int)($_POST['two_factor_enabled'] ?? 0);
    $twoSecretPlain=$twoFactor ? TwoFactorService::generateSecret() : null;
    $twoSecret=$twoSecretPlain ? TwoFactorService::encryptSecret($twoSecretPlain) : null;

    $empresaId = EmpresaCatalogService::idDoFormulario($_POST['empresa_id'] ?? '');
    if ($empresaId === false) { redirect('index.php?page=usuarios&erro=empresa'); }

    if($nome==='' || $email==='' || !filter_var($email,FILTER_VALIDATE_EMAIL)) {
      redirect('index.php?page=usuarios&erro=campos');
    }
    if(!in_array($perfil,['admin','gerente','operador'],true)) $perfil='operador';

    $stDup=$this->db('usuarios')->prepare('SELECT id FROM usuarios WHERE email=? AND id<>? LIMIT 1');
    $stDup->execute([$email,$id]);
    if($stDup->fetch()) { redirect('index.php?page=usuarios&erro=email'); }

    if($senha!==''&&!PasswordPolicyService::isValid($senha,$email)){redirect('index.php?page=usuarios&erro=senha');}

    // Proteção: não permitir remover o último admin ativo.
    if($id>0 && ($perfil!=='admin' || $ativo!==1)) {
      $st=$this->db('usuarios')->prepare("SELECT COUNT(*) c FROM usuarios WHERE perfil='admin' AND ativo=1 AND id<>?");
      $st->execute([$id]);
      if((int)($st->fetch()['c'] ?? 0) < 1) { redirect('index.php?page=usuarios&erro=ultimo_admin'); }
    }

    if($id>0){
      if($senha !== ''){
        $this->db('usuarios')->prepare('UPDATE usuarios SET nome=?,email=?,perfil=?,ativo=?,deve_trocar_senha=?,empresa_id=?,senha=?,session_version=COALESCE(session_version,0)+1,two_factor_enabled=?,two_factor_secret=COALESCE(?,two_factor_secret), two_factor_created_at=CASE WHEN ? IS NOT NULL THEN NOW() ELSE two_factor_created_at END, bloqueado_ate=NULL,tentativas_login=0 WHERE id=?')->execute([$nome,$email,$perfil,$ativo,$trocar,$empresaId,password_hash($senha,PASSWORD_DEFAULT),$twoFactor,$twoSecret,$twoSecret,$id]);
        $msg='Usuário salvo com alteração de senha pelo administrador';
      } else {
        // P1-01 (reauditoria 2026-08-23): este UPDATE (sem troca de senha) não bumpava
        // session_version, então desativar o usuário, rebaixar o perfil ou desligar o
        // 2FA não revogava a sessão já aberta dele - continuava válida até expirar por
        // tempo. Agora qualquer alteração de segurança do usuário revoga a sessão atual.
        $this->db('usuarios')->prepare("UPDATE usuarios SET nome=?,email=?,perfil=?,ativo=?,deve_trocar_senha=?,empresa_id=?,two_factor_enabled=?,two_factor_secret=CASE WHEN ?=1 AND (two_factor_secret IS NULL OR two_factor_secret='') THEN ? ELSE two_factor_secret END, two_factor_created_at=CASE WHEN ?=1 AND (two_factor_secret IS NULL OR two_factor_secret='') THEN NOW() ELSE two_factor_created_at END, session_version=COALESCE(session_version,0)+1 WHERE id=?")->execute([$nome,$email,$perfil,$ativo,$trocar,$empresaId,$twoFactor,$twoFactor,$twoSecret,$twoFactor,$id]);
        $msg='Usuário salvo';
      }
    } else {
      if($senha===''){ $senha=PasswordPolicyService::generateTemporary(); $_SESSION['senha_temporaria_ultima']=['email'=>$email,'senha'=>$senha]; }
      $this->db('usuarios')->prepare('INSERT INTO usuarios(nome,email,perfil,ativo,deve_trocar_senha,empresa_id,senha,two_factor_enabled,two_factor_secret,two_factor_created_at) VALUES(?,?,?,?,?,?,?,?,?,CASE WHEN ?=1 THEN NOW() ELSE NULL END)')->execute([$nome,$email,$perfil,$ativo,1,$empresaId,password_hash($senha,PASSWORD_DEFAULT),$twoFactor,$twoSecret,$twoFactor]);
      $id=(int)$this->pdo->lastInsertId();
      $msg='Usuário criado';
    }
    Audit::event('usuarios.salvar','sucesso',['mensagem'=>$msg,'entidade'=>'usuarios','entidade_id'=>$id,'contexto'=>['perfil'=>$perfil,'ativo'=>$ativo,'empresa_id'=>$empresaId,'senha_alterada'=>$senha!=='' || !empty($_SESSION['senha_temporaria_usuario_'.$email])]]);
    redirect('index.php?page=usuarios&salvo=1');
  }

  private function usuarioExcluir(): void {
    PermissionService::require('usuarios','gerenciar');
    Csrf::validate();
    $id=(int)($_POST['id'] ?? 0);
    $confirmar=trim($_POST['confirmar'] ?? '');
    if($id<=0 || $confirmar!=='EXCLUIR') { redirect('index.php?page=usuarios&erro=confirmacao'); }
    $logado=(int)(Auth::user()['id'] ?? 0);
    if($id===$logado) { redirect('index.php?page=usuarios&erro=self_delete'); }

    $st=$this->db('usuarios')->prepare('SELECT id,nome,email,perfil,ativo FROM usuarios WHERE id=? LIMIT 1');
    $st->execute([$id]);
    $u=$st->fetch();
    if(!$u) { redirect('index.php?page=usuarios&erro=nao_encontrado'); }

    if($u['perfil']==='admin' && (int)$u['ativo']===1) {
      $st=$this->db('usuarios')->prepare("SELECT COUNT(*) c FROM usuarios WHERE perfil='admin' AND ativo=1 AND id<>?");
      $st->execute([$id]);
      if((int)($st->fetch()['c'] ?? 0) < 1) { redirect('index.php?page=usuarios&erro=ultimo_admin'); }
    }

    // Exclusão lógica profissional: preserva auditoria e histórico de integrações.
    $this->db('usuarios')->prepare('UPDATE usuarios SET ativo=0, bloqueado_ate=NULL, tentativas_login=0 WHERE id=?')->execute([$id]);
    Audit::event('usuarios.excluir','sucesso',['mensagem'=>'Usuário inativado/excluído logicamente pelo administrador','entidade'=>'usuarios','entidade_id'=>$id,'contexto'=>['email'=>$u['email'],'perfil'=>$u['perfil']]]);
    redirect('index.php?page=usuarios&excluido=1');
  }

  private function permissoesSalvar(): void {
    PermissionService::require('usuarios','gerenciar');
    Csrf::validate();
    $permissoesPost = $_POST['permissoes'] ?? [];
    if (!is_array($permissoesPost)) $permissoesPost = [];
    $todas = $this->db('permissoes_perfil')->query("SELECT id FROM permissoes_perfil")->fetchAll();
    $permitidos = array_map('intval', array_keys($permissoesPost));
    $this->pdo->beginTransaction();
    try {
      $this->db('permissoes_perfil')->exec("UPDATE permissoes_perfil SET permitido=0");
      if ($permitidos) {
        $in = implode(',', array_fill(0, count($permitidos), '?'));
        $st = $this->db('permissoes_perfil')->prepare("UPDATE permissoes_perfil SET permitido=1 WHERE id IN ($in)");
        $st->execute($permitidos);
      }
      // Admin sempre preserva acesso total para evitar bloqueio administrativo.
      $this->db('permissoes_perfil')->exec("UPDATE permissoes_perfil SET permitido=1 WHERE perfil='admin'");
      $this->pdo->commit();
      Audit::event('permissoes.salvar','sucesso',['mensagem'=>'Matriz de permissões atualizada pelo painel.']);
      redirect('index.php?page=usuarios&permissoes=ok');
    } catch (Throwable $e) {
      if($this->pdo->inTransaction()) $this->pdo->rollBack();
      Audit::exception($e,'permissoes.salvar.erro',['codigo_erro'=>'PERMISSION_SAVE_ERROR']);
      redirect('index.php?page=usuarios&permissoes=erro');
    }
  }

  private function trocarSenha(): void {
    Auth::requireLogin();
    if($_SERVER['REQUEST_METHOD'] !== 'POST') { $pageTitle='Trocar senha'; require __DIR__.'/../../views/trocar_senha.php'; return; }
    Csrf::validate();
    $senha=(string)($_POST['senha'] ?? ''); $confirma=(string)($_POST['confirma'] ?? '');
    $policyError=PasswordPolicyService::message($senha,(string)(Auth::user()['email']??''));
    if($senha!==$confirma||$policyError!==''){ $erro=$senha!==$confirma?'A confirmação da senha não confere.':$policyError; $pageTitle='Trocar senha'; require __DIR__.'/../../views/trocar_senha.php'; return; }
    $id=Auth::user()['id'];
    $this->db('usuarios')->prepare('UPDATE usuarios SET senha=?, deve_trocar_senha=0, tentativas_login=0, bloqueado_ate=NULL, session_version=COALESCE(session_version,0)+1 WHERE id=?')->execute([password_hash($senha,PASSWORD_DEFAULT),$id]);
    $_SESSION['user']['deve_trocar_senha']=0;
    if(Database::columnExists('usuarios','session_version')){ $stVersion=$this->db('usuarios')->prepare('SELECT session_version FROM usuarios WHERE id=?');$stVersion->execute([$id]);$_SESSION['session_version']=(int)($stVersion->fetchColumn()?:0); }
    Audit::event('auth.trocar_senha','sucesso',['mensagem'=>'Senha alterada pelo usuário','entidade'=>'usuarios','entidade_id'=>$id]);
    redirect('index.php?page=dashboard&senha=ok');
  }

  private function backup(): void {
    PermissionService::require('backup','gerar');
    Csrf::validate();
    $file=BackupService::gerarZip();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="'.basename($file).'"');
    readfile($file); exit;
  }

  private function backups(): void {
    PermissionService::require('backup','visualizar');
    BackupSchemaService::ensure();
    $backups=BackupSchemaService::list(100);
    $pageTitle='Backups';
    require __DIR__.'/../../views/backups.php';
  }

  private function backupDownload(): void {
    PermissionService::require('backup','baixar');
    BackupSchemaService::ensure();
    $id=(int)($_GET['id'] ?? 0);
    $pdoBackups=Database::forTable('backups_banco');
    $st=$pdoBackups->prepare('SELECT * FROM backups_banco WHERE id=? LIMIT 1');
    $st->execute([$id]); $b=$st->fetch();
    if(!$b){ http_response_code(404); exit('Backup não encontrado.'); }
    $file=dirname(__DIR__,2).'/storage/backups/'.basename($b['arquivo']);
    if(!is_file($file)){ http_response_code(404); exit('Arquivo físico não encontrado em storage/backups.'); }
    Audit::event('backup.download','sucesso',['mensagem'=>'Backup baixado','entidade'=>'backups_banco','entidade_id'=>$id]);
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="'.basename($file).'"');
    header('Content-Length: '.filesize($file));
    readfile($file); exit;
  }

  private function backupExcluir(): void {
    PermissionService::require('backup','excluir');
    Csrf::validate();
    BackupSchemaService::ensure();
    $id=(int)($_POST['id'] ?? 0);
    $pdoBackups=Database::forTable('backups_banco');
    $st=$pdoBackups->prepare('SELECT * FROM backups_banco WHERE id=? LIMIT 1');
    $st->execute([$id]); $b=$st->fetch();
    if($b){
      $file=dirname(__DIR__,2).'/storage/backups/'.basename($b['arquivo']);
      if(is_file($file)) @unlink($file);
      $pdoBackups->prepare('DELETE FROM backups_banco WHERE id=?')->execute([$id]);
      Audit::event('backup.excluir','sucesso',['mensagem'=>'Backup excluído','entidade'=>'backups_banco','entidade_id'=>$id]);
    }
    redirect('index.php?page=backups');
  }


  private function backupImportar(): void {
    PermissionService::require('backup','importar');
    Csrf::validate();
    try{
      BackupService::importarUpload($_FILES['backup_arquivo'] ?? []);
      $_SESSION['flash_success']='Backup importado com sucesso. Confira o histórico antes de restaurar.';
    } catch(Throwable $e){
      $_SESSION['flash_error']=$e->getMessage();
      Audit::event('backup.importar','erro',['mensagem'=>$e->getMessage()]);
    }
    redirect('index.php?page=backups');
  }

  private function backupRestaurar(): void {
    PermissionService::require('backup','restaurar');
    Csrf::validate();
    $id=(int)($_POST['id'] ?? 0);
    $confirmacao=(string)($_POST['confirmacao'] ?? '');
    try{
      BackupService::restaurarPorId($id, $confirmacao);
      $_SESSION['flash_success']='Backup restaurado com sucesso. Revise o Health do Sistema e valide as integrações.';
    } catch(Throwable $e){
      $_SESSION['flash_error']=$e->getMessage();
      Audit::event('backup.restaurar','erro',['mensagem'=>$e->getMessage(),'entidade'=>'backups_banco','entidade_id'=>$id]);
    }
    redirect('index.php?page=backups');
  }

  /**
   * P2 (reauditoria 2026-08-23): neutraliza injeção de fórmula CSV (CWE-1236). Um campo
   * gravado no banco (ex.: mensagem de log/auditoria vinda de payload externo) que comece
   * com =, +, -, @, tab ou CR poderia virar uma fórmula executável ao abrir o CSV no
   * Excel/Sheets/LibreOffice. Prefixa com aspas simples, que a maioria dos leitores trata
   * como "forçar texto" sem alterar o valor visível.
   */
  private function csvSafeRow(array $row): array {
    foreach ($row as $k => $v) {
      $s = (string)$v;
      if ($s !== '' && preg_match('/^[=+\-@\t\r]/', $s)) $row[$k] = "'".$s;
    }
    return $row;
  }

  private function logsExportar(): void {
    PermissionService::require('logs','exportar');
    // Achado C-05: a exportação entrega um arquivo ao cliente — aqui o escopo é ESTRITO, porque
    // incluir linhas legadas sem empresa num CSV entregue a um cliente seria vazamento.
    $st = TenantScopeService::run('logs_integracao', "SELECT id,trace_id,tipo,nivel,codigo_erro,mensagem,ip,criado_em FROM logs_integracao ORDER BY id DESC LIMIT 5000");
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="logs_integracao_'.date('Ymd_His').'.csv"');
    $out=fopen('php://output','w');
    fputcsv($out,['id','trace_id','tipo','nivel','codigo_erro','mensagem','ip','criado_em'],';');
    while($r=$st->fetch(PDO::FETCH_ASSOC)){ fputcsv($out,$this->csvSafeRow($r),';'); }
    Audit::event('logs.exportar','sucesso',['mensagem'=>'Logs exportados em CSV']);
    exit;
  }


  private function baixasEstoque(): void {
    PermissionService::require('estoque','visualizar');
    $status = $_GET['status'] ?? '';
    $busca = trim($_GET['busca'] ?? '');
    $sql = "SELECT * FROM estoque_movimentos WHERE 1=1"; $params=[];
    if($status !== ''){ $sql .= " AND status=?"; $params[]=$status; }
    if($busca !== ''){ $sql .= " AND (sku LIKE ? OR referencia LIKE ? OR trace_id LIKE ?)"; $params[]="%$busca%"; $params[]="%$busca%"; $params[]="%$busca%"; }
    $sql .= " ORDER BY id DESC LIMIT 300";
    $st=Database::tableConnectionForSql($sql)->prepare($sql); $st->execute($params); $movimentos=$st->fetchAll();
    $resumo=TenantScopeService::run('estoque_movimentos', "SELECT status, COUNT(*) total FROM estoque_movimentos GROUP BY status")->fetchAll();
    $pageTitle='Baixas de Estoque VSM';
    require __DIR__.'/../../views/baixas_estoque.php';
  }

  private function produtosVsm(): void {
    PermissionService::require('produtos_vsm','visualizar');
    $status = $_GET['status'] ?? '';
    $busca = trim($_GET['busca'] ?? '');
    $sql = "SELECT * FROM fila_integracao WHERE tipo IN ('produto_vsm_para_tiny','produto_vsm_atualizar_tiny','produto_vsm_estoque_para_tiny','produto_vsm_status_para_tiny')"; $params=[];
    if($status !== ''){ $sql .= " AND status=?"; $params[]=$status; }
    if($busca !== ''){ $sql .= " AND (referencia LIKE ? OR trace_id LIKE ? OR payload LIKE ?)"; $params[]="%$busca%"; $params[]="%$busca%"; $params[]="%$busca%"; }
    $sql .= " ORDER BY id DESC LIMIT 300";
    $st=Database::tableConnectionForSql($sql)->prepare($sql); $st->execute($params); $produtos=$st->fetchAll();
    $mapeados=TenantScopeService::run('produtos_mapeamento', "SELECT id, sku_tiny, sku_vsm, produto_tiny_id, produto_vsm_id, descricao, ativo, estoque_atual, status_tiny, ultima_sincronizacao, criado_em FROM produtos_mapeamento ORDER BY id DESC LIMIT 100")->fetchAll();
    $eventos=TenantScopeService::run('produtos_vsm_eventos', "SELECT * FROM produtos_vsm_eventos ORDER BY id DESC LIMIT 100")->fetchAll();
    $pageTitle='Produtos recebidos da VSM';
    require __DIR__.'/../../views/produtos_vsm.php';
  }


  private function produtosPendencias(): void {
    PermissionService::require('produtos_vsm','visualizar');
    $status = $_GET['status'] ?? '';
    $busca = trim($_GET['busca'] ?? '');
    $sql = "SELECT * FROM produto_pendencias WHERE 1=1"; $params=[];
    if($status !== ''){ $sql .= " AND status=?"; $params[]=$status; }
    if($busca !== ''){ $sql .= " AND (sku LIKE ? OR motivo LIKE ? OR trace_id LIKE ?)"; $params[]="%$busca%"; $params[]="%$busca%"; $params[]="%$busca%"; }
    // Melhoria 1 da seção 8: o escopo de empresa entra DEPOIS do WHERE montado pelos filtros e
    // ANTES do ORDER BY/LIMIT — applyToSelect() cuida da posição do parâmetro.
    [$sql, $params] = TenantScopeService::applyToSelect('produto_pendencias', $sql, $params);
    $sql .= " ORDER BY id DESC LIMIT 300";
    $st=Database::tableConnectionForSql($sql)->prepare($sql); $st->execute($params); $pendencias=$st->fetchAll();
    $pageTitle='Pendências de Produtos';
    require __DIR__.'/../../views/produtos_pendencias.php';
  }

  private function produtoPendenciaAcao(): void {
    PermissionService::require('produtos_vsm','simular');
    Csrf::validate();
    $id=(int)($_POST['id'] ?? 0);
    $acao=(string)($_POST['acao'] ?? '');
    $st = TenantScopeService::run('produto_pendencias', 'SELECT * FROM produto_pendencias WHERE id=? LIMIT 1', [$id]); $p=$st->fetch();
    if(!$p) redirect('index.php?page=produtos-pendencias&erro=nao_encontrado');
    if($acao==='resolver') {
      TenantScopeService::run('produto_pendencias', "UPDATE produto_pendencias SET status='resolvido', resolvido_por=?, resolvido_em=NOW(), atualizado_em=NOW() WHERE id=?", [Auth::user()['id']??null,$id]);
      Audit::event('produto.pendencia.resolvida','sucesso',['entidade'=>'produto_pendencias','entidade_id'=>$id,'mensagem'=>'Pendência marcada como resolvida manualmente.']);
    } elseif($acao==='ignorar') {
      TenantScopeService::run('produto_pendencias', "UPDATE produto_pendencias SET status='ignorado', atualizado_em=NOW() WHERE id=?", [$id]);
      Audit::event('produto.pendencia.ignorada','alerta',['entidade'=>'produto_pendencias','entidade_id'=>$id,'mensagem'=>'Pendência ignorada manualmente.']);
    } elseif($acao==='reprocessar' && !empty($p['fila_id'])) {
      QueueService::reprocessar((int)$p['fila_id']);
      Audit::event('produto.pendencia.reprocessar','info',['entidade'=>'produto_pendencias','entidade_id'=>$id,'mensagem'=>'Fila da pendência reenviada para processamento.']);
    } elseif($acao==='criar_produto_tiny') {
      $payload = json_decode((string)($p['payload'] ?? '{}'), true) ?: ['sku'=>$p['sku']];
      $payload['hub_aprovado_manual'] = true;
      $payload['hub_aprovado_por'] = Auth::user()['id'] ?? 'admin';
      $payload['hub_aprovado_em'] = date('c');
      TenantScopeService::run('fila_integracao', "INSERT INTO fila_integracao(tipo,referencia,payload,status,trace_id) VALUES('produto_vsm_para_tiny',?,?, 'pendente', ?)", [$p['sku'], json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), RequestContext::id()]);
      TenantScopeService::run('produto_pendencias', "UPDATE produto_pendencias SET status='resolvido', resolucao='Criada fila para criar produto no Tiny a partir da pendência.', resolvido_por=?, resolvido_em=NOW(), atualizado_em=NOW() WHERE id=?", [Auth::user()['id']??null,$id]);
      Audit::event('produto.pendencia.criar_produto_tiny','info',['entidade'=>'produto_pendencias','entidade_id'=>$id,'mensagem'=>'Criada fila para criar produto no Tiny a partir da pendência.','payload'=>$payload]);
    } elseif($acao==='vincular') {
      $skuTiny=trim($_POST['sku_tiny'] ?? '');
      if($skuTiny !== '') {
        TenantScopeService::run('produtos_mapeamento', 'INSERT INTO produtos_mapeamento(sku_vsm, sku_tiny, ativo) VALUES(?,?,1) ON DUPLICATE KEY UPDATE sku_tiny=VALUES(sku_tiny), ativo=1', [$p['sku'],$skuTiny]);
        TenantScopeService::run('produto_pendencias', "UPDATE produto_pendencias SET status='resolvido', atualizado_em=NOW() WHERE id=?", [$id]);
        Audit::event('produto.pendencia.vinculada','sucesso',['entidade'=>'produto_pendencias','entidade_id'=>$id,'mensagem'=>'SKU VSM vinculado manualmente a SKU Tiny.','contexto'=>['sku_vsm'=>$p['sku'],'sku_tiny'=>$skuTiny]]);
      }
    }
    redirect('index.php?page=produtos-pendencias');
  }

  private function divergenciaEstoque(): void {
    PermissionService::require('reconciliacao','visualizar');
    $status = $_GET['status'] ?? '';
    $busca = trim($_GET['busca'] ?? '');
    $sql = "SELECT * FROM estoque_divergencias WHERE 1=1"; $params=[];
    if($status !== ''){ $sql .= " AND status=?"; $params[]=$status; }
    if($busca !== ''){ $sql .= " AND (sku LIKE ? OR trace_id LIKE ?)"; $params[]="%$busca%"; $params[]="%$busca%"; }
    // Melhoria 1 da seção 8: o escopo de empresa entra DEPOIS do WHERE montado pelos filtros e
    // ANTES do ORDER BY/LIMIT — applyToSelect() cuida da posição do parâmetro.
    [$sql, $params] = TenantScopeService::applyToSelect('estoque_divergencias', $sql, $params);
    $sql .= " ORDER BY id DESC LIMIT 300";
    $st=Database::tableConnectionForSql($sql)->prepare($sql); $st->execute($params); $divergencias=$st->fetchAll();
    $pageTitle='Divergência Tiny x VSM';
    require __DIR__.'/../../views/divergencia_estoque.php';
  }

  private function divergenciaAcao(): void {
    PermissionService::require('reconciliacao','executar');
    Csrf::validate();
    $id=(int)($_POST['id'] ?? 0); $acao=(string)($_POST['acao'] ?? '');
    $st = TenantScopeService::run('estoque_divergencias', 'SELECT * FROM estoque_divergencias WHERE id=? LIMIT 1', [$id]); $d=$st->fetch();
    if(!$d) redirect('index.php?page=divergencia-estoque&erro=nao_encontrada');
    if($acao==='ignorar') {
      TenantScopeService::run('estoque_divergencias', "UPDATE estoque_divergencias SET status='ignorado', atualizado_em=NOW() WHERE id=?", [$id]);
      Audit::event('estoque.divergencia.ignorada','alerta',['entidade'=>'estoque_divergencias','entidade_id'=>$id,'mensagem'=>'Divergência ignorada manualmente.']);
    } elseif($acao==='marcar_corrigido') {
      TenantScopeService::run('estoque_divergencias', "UPDATE estoque_divergencias SET status='corrigido', atualizado_em=NOW() WHERE id=?", [$id]);
      Audit::event('estoque.divergencia.corrigida','sucesso',['entidade'=>'estoque_divergencias','entidade_id'=>$id,'mensagem'=>'Divergência marcada como corrigida manualmente.']);
    } elseif($acao==='corrigir_tiny') {
      $payload=['sku'=>$d['sku'],'estoque'=>(float)$d['estoque_vsm'],'origem'=>'divergencia_estoque','divergencia_id'=>$id,'acao'=>'corrigir_tiny'];
      TenantScopeService::run('fila_integracao', "INSERT INTO fila_integracao(tipo,referencia,payload,status,trace_id) VALUES('produto_vsm_estoque_para_tiny',?,?, 'pendente', ?)", [$d['sku'], json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), RequestContext::id()]);
      Audit::event('estoque.divergencia.corrigir_tiny','info',['entidade'=>'estoque_divergencias','entidade_id'=>$id,'mensagem'=>'Gerada fila para corrigir estoque do Tiny com saldo da VSM.','payload'=>$payload]);
    }
    redirect('index.php?page=divergencia-estoque');
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

  private function salvarFluxos(): void {
    PermissionService::require('fluxos','editar');
    Csrf::validate();
    Database::forTable('configuracoes_integracao')->prepare('UPDATE configuracoes_integracao SET fluxo_tiny_vsm_estoque=?, fluxo_vsm_tiny_produto=?, fluxo_vsm_tiny_pedido=? WHERE id=1')
      ->execute([isset($_POST['fluxo_tiny_vsm_estoque'])?1:0, isset($_POST['fluxo_vsm_tiny_produto'])?1:0, isset($_POST['fluxo_vsm_tiny_pedido'])?1:0]);
    Audit::event('fluxos.atualizar','sucesso',['mensagem'=>'Fluxos ativos atualizados no painel.','contexto'=>$_POST]);
    redirect('index.php?page=configuracoes&fluxos=salvo');
  }


  private function filaMorta(): void {
    PermissionService::require('fila_morta','visualizar');
    $status=$_GET['status'] ?? 'aberto';
    $sql='SELECT * FROM fila_morta WHERE 1=1'; $params=[];
    if($status !== ''){ $sql.=' AND status=?'; $params[]=$status; }
    // Melhoria 1 da seção 8: o escopo de empresa entra DEPOIS do WHERE montado pelos filtros e
    // ANTES do ORDER BY/LIMIT — applyToSelect() cuida da posição do parâmetro.
    [$sql, $params] = TenantScopeService::applyToSelect('fila_morta', $sql, $params);
    $sql.=' ORDER BY id DESC LIMIT 300';
    $st=Database::tableConnectionForSql($sql)->prepare($sql); $st->execute($params); $itens=$st->fetchAll();
    $pageTitle='Fila Morta / DLQ';
    require __DIR__.'/../../views/fila_morta.php';
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

  private function reconciliacao(): void {
    PermissionService::require('reconciliacao','visualizar');
    $dados=ReconciliationService::resumo();
    $pageTitle='Reconciliação de Estoque';
    require __DIR__.'/../../views/reconciliacao.php';
  }

  private function reconciliacaoExecutar(): void {
    PermissionService::require('reconciliacao','executar'); Csrf::validate();
    $sku=trim($_POST['sku'] ?? ''); $tinyRaw=trim((string)($_POST['estoque_tiny'] ?? '')); $vsmRaw=trim((string)($_POST['estoque_vsm'] ?? ''));
    if($sku!=='' && $tinyRaw==='' && $vsmRaw==='') { ReconciliationService::reconciliarSku($sku); }
    elseif($sku!=='') { ReconciliationService::registrarManual($sku,(float)$tinyRaw,(float)$vsmRaw,'painel'); }
    redirect('index.php?page=reconciliacao');
  }

  private function metricas(): void {
    PermissionService::require('metricas','visualizar');
    $metricas=$this->db('metricas_api')->query('SELECT id, sistema, endpoint, metodo, http_code, tempo_ms, sucesso, criado_em FROM metricas_api ORDER BY id DESC LIMIT 300')->fetchAll();
    $cb=Database::forTable('circuit_breakers')->query('SELECT id, sistema, status, falhas_consecutivas, aberto_ate, atualizado_em FROM circuit_breakers ORDER BY sistema')->fetchAll();
    $pageTitle='Métricas e Circuit Breaker';
    require __DIR__.'/../../views/metricas.php';
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





  private function centralHomologacao(): void {
    PermissionService::require('homologacao','visualizar');
    $cfg = IntegrationConfig::get();
    $v2 = class_exists('TinyV2HomologationService') ? TinyV2HomologationService::resumo($cfg) : ['checks'=>[], 'progresso'=>0, 'status_geral'=>'pendente', 'historico'=>[], 'ultimo'=>null];
    $v3 = class_exists('TinyV3HomologationService') ? TinyV3HomologationService::resumo($cfg) : ['checks'=>[], 'progresso'=>0, 'status_geral'=>'pendente', 'historico'=>[], 'ultimo'=>null];
    $vsmOk = !empty($cfg['vsm_url']);
    $xmlResumo = ['total'=>0,'pendentes'=>0,'erros'=>0,'xml_sem_envio'=>0];
    try { if (class_exists('FiscalIntegrationService')) $xmlResumo = array_merge($xmlResumo, FiscalIntegrationService::resumo() ?: []); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    try { if (class_exists('FiscalEnterpriseService')) $xmlResumo = array_merge($xmlResumo, FiscalEnterpriseService::reconciliacao() ?: []); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    $pageTitle = 'Central de Homologação';
    require __DIR__.'/../../views/central_homologacao.php';
  }

  private function tinyV2Homologacao(): void {
    PermissionService::require('homologacao','visualizar');
    $cfg = IntegrationConfig::get();
    $skuPadrao = trim((string)($_GET['sku'] ?? 'HUB-TESTE-001'));
    $pedidoPadrao = trim((string)($_GET['pedido_teste'] ?? ''));
    $resumo = class_exists('TinyV2HomologationService') ? TinyV2HomologationService::resumo($cfg) : ['checks'=>[], 'progresso'=>0, 'status_geral'=>'pendente', 'historico'=>[], 'ultimo'=>null];
    $ultimoResultado = $_SESSION['tiny_v2_homologacao_ultimo'] ?? null;
    unset($_SESSION['tiny_v2_homologacao_ultimo']);
    $pageTitle = 'Tiny V2 - Homologação';
    require __DIR__.'/../../views/tiny_v2_homologacao.php';
  }

  private function tinyV2HomologacaoExecutar(): void {
    PermissionService::require('homologacao','executar');
    Csrf::validate();
    try {
      $_SESSION['tiny_v2_homologacao_ultimo'] = TinyV2HomologationService::executar($_POST);
    } catch (Throwable $e) {
      $_SESSION['tiny_v2_homologacao_ultimo'] = ['aprovado'=>false, 'status_final'=>'Erro ao executar homologação Tiny V2', 'trace_id'=>RequestContext::id(), 'erro'=>$e->getMessage(), 'executado_em'=>date('Y-m-d H:i:s')];
      Audit::exception($e, 'tiny.v2.homologacao.erro', ['codigo_erro'=>'TINY_V2_HOMOLOGATION_ERROR']);
    }
    redirect('index.php?page=tiny-v2-homologacao&executado=1');
  }


  private function tinyV3Homologacao(): void {
    PermissionService::require('homologacao','visualizar');
    $cfg = IntegrationConfig::get();
    $skuPadrao = trim((string)($_GET['sku'] ?? 'HUB-TESTE-001'));
    $pedidoPadrao = trim((string)($_GET['pedido_teste'] ?? ''));
    $resumo = class_exists('TinyV3HomologationService') ? TinyV3HomologationService::resumo($cfg) : ['checks'=>[], 'progresso'=>0, 'status_geral'=>'pendente', 'historico'=>[], 'ultimo'=>null];
    $ultimoResultado = $_SESSION['tiny_v3_homologacao_ultimo'] ?? null;
    unset($_SESSION['tiny_v3_homologacao_ultimo']);
    $pageTitle = 'Tiny V3 - Homologação';
    require __DIR__.'/../../views/tiny_v3_homologacao.php';
  }

  private function tinyV3HomologacaoExecutar(): void {
    PermissionService::require('homologacao','executar');
    Csrf::validate();
    try {
      $_SESSION['tiny_v3_homologacao_ultimo'] = TinyV3HomologationService::executar($_POST);
    } catch (Throwable $e) {
      $_SESSION['tiny_v3_homologacao_ultimo'] = ['aprovado'=>false, 'status_final'=>'Erro ao executar homologação Tiny V3', 'trace_id'=>RequestContext::id(), 'erro'=>$e->getMessage(), 'executado_em'=>date('Y-m-d H:i:s')];
      Audit::exception($e, 'tiny.v3.homologacao.erro', ['codigo_erro'=>'TINY_V3_HOMOLOGATION_ERROR']);
    }
    redirect('index.php?page=tiny-v3-homologacao&executado=1');
  }

  private function tinyAmbientes(): void {
    PermissionService::require('configuracoes','visualizar');
    $cfg = IntegrationConfig::get();
    try { $itens = $this->db('homologacao_checklist')->query("SELECT id, chave, titulo, descricao, status, resultado, trace_id, atualizado_em, criado_em FROM homologacao_checklist ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC); }
    catch (Throwable $e) { $itens = []; }
    $analise = TinyEnvironmentReadinessService::analisar($cfg, $itens);
    $pageTitle = 'Tiny V2/V3 - Ambientes';
    require __DIR__.'/../../views/tiny_ambientes.php';
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


  private function validarBanco(): void {
    PermissionService::require('database','validar');
    $reparar = false;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
      Csrf::validate();
      $reparar = ((string)($_POST['acao'] ?? '') === 'repair_schema');
      if ($reparar) {
        Audit::event('database.schema_guard.reparo_manual.solicitado','alerta',[
          'mensagem'=>'Reparo manual de banco solicitado pela tela Validar Banco.',
          'acao_recomendada'=>'Confirmar backup recente antes de executar reparos de schema em produção.'
        ]);
      }
    }
    try {
      $resultado = (new DatabaseValidationService($this->pdo, ['validation_max_seconds'=>(int)cfg('security.database_validation_max_seconds',20),'repair_max_seconds'=>(int)cfg('security.database_repair_max_seconds',90),'max_checks'=>(int)cfg('security.database_validation_max_checks',320)]))->executar($reparar);
    } catch(Throwable $e) {
      try { Audit::exception($e, 'database.validacao.legado.erro'); } catch(Throwable $ignored){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $ignored); }
      $resultado = ['status'=>'erro','total'=>1,'ok'=>0,'atencao'=>0,'erro'=>1,'checks'=>[['status'=>'erro','titulo'=>'Falha ao validar banco','mensagem'=>$e->getMessage(),'acao'=>'Consultar Auditoria pelo Trace ID e revisar config.php/permissões MySQL.']],'trace_id'=>RequestContext::id(),'executado_em'=>date('Y-m-d H:i:s'),'modo'=>$reparar?'reparo_manual':'validacao_leitura_segura','parcial'=>false,'duracao_ms'=>0,'acao_recomendada'=>'Corrigir erro informado e consultar auditoria pelo Trace ID.'];
    }
    $pageTitle = $reparar ? 'Reparar Banco de Dados' : 'Validar Banco de Dados';
    require __DIR__.'/../../views/validar_banco.php';
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

  private function columnExistsForUpdate(string $table, string $column): bool {
    try { return Database::columnExists($table, $column); }
    catch (Throwable $e) { return false; }
  }

  private function fichaTecnica100(): void {
    PermissionService::require('ficha_tecnica','visualizar');
    $prechecks = InstallationPrecheckService::run();
    $preScore = InstallationPrecheckService::score();
    $queue = QueueAnalyticsService::resumo();
    $tinyV2Errors = TinyV2ErrorCatalogService::all();
    $cfg = IntegrationConfig::get();
    $pageTitle = 'Ficha Técnica 100%';
    require __DIR__.'/../../views/ficha_tecnica_100.php';
  }

  private function segurancaAuditoria(): void {
    PermissionService::require('seguranca','visualizar');
    $eventos = SecurityAuditService::recentes(120);
    $timeline = $this->db('auditoria_timeline')->query("SELECT * FROM auditoria_timeline ORDER BY id DESC LIMIT 120")->fetchAll();
    $correlacoes = $this->db('evento_correlacao')->query("SELECT * FROM evento_correlacao ORDER BY id DESC LIMIT 80")->fetchAll();
    $pageTitle = 'Segurança e Auditoria Forense';
    require __DIR__.'/../../views/seguranca_auditoria.php';
  }

  private function segurancaExtrema(): void {
    PermissionService::require('seguranca','visualizar');
    $status = SecurityHardeningService::status();
    $pageTitle = 'Segurança Extrema';
    require __DIR__.'/../../views/seguranca_extrema.php';
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




  private function productionReadyV25(): void {
    PermissionService::require('ficha_tecnica','visualizar');
    $scores = ProductionReadinessV24Service::scores();
    $checklist = ProductionReadinessV24Service::checklist();
    $postTests = PostInstallTestService::run();
    $postScore = PostInstallTestService::score($postTests);
    $trace = trim($_GET['trace_id'] ?? RequestContext::id());
    $integridade = class_exists('AuditIntegrityService') ? AuditIntegrityService::verificarTrace($trace) : ['integro'=>false,'mensagem'=>'Serviço indisponível'];
    $pageTitle = 'Production Ready V25';
    require __DIR__.'/../../views/production_ready_v25.php';
  }

  private function productionReadyV24(): void {
    PermissionService::require('ficha_tecnica','visualizar');
    $scores = ProductionReadinessV24Service::scores();
    $checklist = ProductionReadinessV24Service::checklist();
    $prechecks = class_exists('InstallationPrecheckService') ? InstallationPrecheckService::run() : [];
    $pageTitle = 'Production Ready V24';
    require __DIR__.'/../../views/production_ready_v24.php';
  }

  private function vsmFichaTecnica(): void {
    PermissionService::require('ficha_tecnica','visualizar');
    $endpoints = VsmFichaTecnicaService::endpoints();
    $metricas = VsmFichaTecnicaService::metricas();
    $payloads = VsmFichaTecnicaService::payloads();
    $cfg = IntegrationConfig::get();
    $pageTitle = 'Ficha Técnica VSM';
    require __DIR__.'/../../views/vsm_ficha_tecnica.php';
  }

  private function tinyV2FichaTecnica(): void {
    PermissionService::require('ficha_tecnica','visualizar');
    $metricas = TinyV2ObservabilityService::metricas();
    $erros = TinyV2ObservabilityService::erros();
    $cfg = IntegrationConfig::get();
    $pageTitle = 'Ficha Técnica Tiny V2';
    require __DIR__.'/../../views/tiny_v2_ficha_tecnica.php';
  }

  private function filaAnalyticsV24(): void {
    PermissionService::require('fila_morta','visualizar');
    $analytics = QueueV24AnalyticsService::resumoAvancado();
    $pageTitle = 'Fila / DLQ Analytics V24';
    require __DIR__.'/../../views/fila_analytics_v24.php';
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


  private function productionReadyV26(): void {
    PermissionService::require('production_ready','visualizar');
    $resumo = EnterpriseV26ReadinessService::resumo();
    $pageTitle = 'Production Ready V26 / InfinityFree';
    require __DIR__.'/../../views/production_ready_v26.php';
  }

  private function hostingInfinityFree(): void {
    PermissionService::require('hosting','visualizar');
    $compat = HostingCompatibilityService::ambiente();
    $recomendacoes = HostingCompatibilityService::recomendações();
    $pageTitle = 'Hospedagem InfinityFree';
    require __DIR__.'/../../views/hosting_infinityfree.php';
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



  private function integracoes(): void {
    PermissionService::require('configuracoes','visualizar');
    $rules = IntegrationOrchestratorService::all();
    $fluxos = IntegrationOrchestratorService::fluxos($rules);
    $ativos = count(array_filter($fluxos, fn($f)=>!empty($f['ativo'])));
    $ordem = IntegrationOrchestratorService::ordemLista();
    $arquitetura = class_exists('RouteModuleRegistry') ? RouteModuleRegistry::architectureControllers() : [];
    $pageTitle = 'Central de Integrações';
    require __DIR__.'/../../views/integracoes.php';
  }

  private function regrasSincronizacao(): void {
    PermissionService::require('configuracoes','visualizar');
    $config = IntegrationConfig::get();
    $rules = SyncRulesService::all();
    $pageTitle = 'Regras de Sincronização Tiny ⇄ VSM';
    require __DIR__.'/../../views/regras_sincronizacao.php';
  }

  private function salvarRegrasSincronizacao(): void {
    PermissionService::require('configuracoes','editar');
    Csrf::validate();
    $keys = [
      'sync_criar_produto_tiny',
      'sync_atualizar_produto_tiny',
      'sync_atualizar_estoque_tiny',
      'sync_atualizar_status_tiny',
      'sync_atualizar_preco_tiny',
      'sync_atualizar_descricao_tiny',
      'sync_atualizar_categoria_tiny',
      'sync_atualizar_marca_tiny',
      'sync_criar_produto_se_nao_existir',
      'sync_bloquear_estoque_negativo',
    ];
    $dados = [];
    foreach($keys as $k){ $dados[$k] = isset($_POST[$k]) ? 1 : 0; }
    try {
      SchemaRuntimePolicyService::requireColumns('configuracoes_integracao', $keys, 'regras de sincronização Tiny ⇄ VSM');
      Database::forTable('configuracoes_integracao')->exec("INSERT IGNORE INTO configuracoes_integracao(id) VALUES(1)");
      $sql = "UPDATE configuracoes_integracao SET ".implode(',', array_map(fn($k)=>$k.'=?', $keys))." WHERE id=1";
      $st = Database::tableConnectionForSql($sql)->prepare($sql);
      $st->execute(array_map(fn($k)=>(int)$dados[$k], $keys));
      Audit::event('sync.regras.salvas','sucesso',[ 'mensagem'=>'Regras de sincronização Tiny ⇄ VSM atualizadas.', 'contexto'=>$dados ]);
      NotificationService::criar('sistema','Regras de sincronização salvas','As regras de criação de produto, atualização de estoque e status foram atualizadas.','sucesso',['link'=>'index.php?page=regras-sincronizacao']);
      redirect('index.php?page=regras-sincronizacao&salvo=1');
    } catch(Throwable $e) {
      Audit::exception($e, 'sync.regras.salvar.erro');
      redirect('index.php?page=regras-sincronizacao&erro=1');
    }
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

  private function atualizadorSeguro(): void {
    PermissionService::require('database','validar');
    $arquivos = glob(dirname(__DIR__,2).'/database/update_v*.sql') ?: [];
    sort($arquivos, SORT_NATURAL);
    $pageTitle='Atualizador Seguro Universal';
    require __DIR__.'/../../views/atualizador_seguro.php';
  }

  private function atualizadorSeguroExecutar(): void {
    PermissionService::require('database','validar');
    Csrf::validate();
    $relatorio = (new UniversalUpgradeService(dirname(__DIR__,2)))->run();
    $_SESSION['upgrade_report_v32'] = $relatorio;
    redirect('index.php?page=atualizador-seguro&executado=1');
  }

  private function oauthV3Checklist(): void {
    PermissionService::require('configuracoes','visualizar');
    $passos = OAuthV3ChecklistService::passos();
    $score = OAuthV3ChecklistService::score();
    $pageTitle='Checklist OAuth Tiny V3';
    require __DIR__.'/../../views/oauth_v3_checklist.php';
  }


  private function relatorioProntidaoProducao(): void {
    PermissionService::require('configuracoes','visualizar');
    $checks = class_exists('ProductionReadinessV24Service') ? ProductionReadinessV24Service::checks() : [];
    $host = class_exists('HostingCompatibilityService') ? HostingCompatibilityService::ambiente() : [];
    $pageTitle = 'Relatório de Prontidão para Produção';
    require __DIR__.'/../../views/relatorio_prontidao_producao.php';
  }

  private function fiscal(): void {
    PermissionService::require('configuracoes','visualizar');
    $resumoFiscal = class_exists('FiscalIntegrationService') ? FiscalIntegrationService::resumo() : [];
    $notas = [];
    try { $notas = FiscalIntegrationService::listar($_GET['status'] ?? '', 100); } catch(Throwable $e) { $erroFiscal = $e->getMessage(); }
    $nfeIntegracoes=[]; try { $nfeIntegracoes = TenantScopeService::run('nfe_integracao', 'SELECT i.*, n.numero, n.serie, n.chave_acesso FROM nfe_integracao i LEFT JOIN notas_fiscais n ON n.id=i.nota_fiscal_id ORDER BY i.id DESC LIMIT 50', [], 'i')->fetchAll(); } catch(Throwable $e) { $erroFiscal = ($erroFiscal ?? '').' '.$e->getMessage(); }
    $pageTitle = 'XML / NF-e';
    require __DIR__.'/../../views/fiscal.php';
  }

  private function dashboardIntegridadeExecutar(): void {
    PermissionService::require('dashboard','visualizar');
    Csrf::validate();
    $checks = DashboardIntegrityService::checks(true);
    $_SESSION['dashboard_integridade_v43'] = ['checks'=>$checks, 'summary'=>DashboardIntegrityService::summary($checks), 'executado_em'=>date('Y-m-d H:i:s')];
    Audit::event('dashboard.integridade.executar','sucesso',['mensagem'=>'Checklist real do dashboard V43 executado.','contexto'=>DashboardIntegrityService::summary($checks)]);
    redirect('index.php?page=dashboard-integridade&executado=1');
  }


  private function healthModulos(): void {
    PermissionService::require('configuracoes','visualizar');
    $checks = class_exists('ModuleHealthService') ? ModuleHealthService::checks() : [];
    $pageTitle = 'Health Check por Módulo';
    require __DIR__.'/../../views/health_modulos.php';
  }

  private function menuTestes(): void {
    PermissionService::require('dashboard','visualizar');
    $checks = class_exists('MenuActionTestService') ? MenuActionTestService::run() : [];
    $pageTitle = 'Teste de Menu e Botões';
    require __DIR__.'/../../views/menu_testes.php';
  }

  private function fiscalReenviar(): void {
    PermissionService::require('configuracoes','visualizar');
    Csrf::validate();
    $id=(int)($_POST['id'] ?? 0);
    if ($id>0 && class_exists('FiscalIntegrationService')) FiscalIntegrationService::marcarReenvio($id);
    Audit::event('fiscal.reenviar','sucesso',['mensagem'=>'NF-e marcada para reenvio à VSM.','entidade'=>'nfe_integracao','entidade_id'=>$id]);
    redirect('index.php?page=fiscal&reenviar=1');
  }

  private function centralTecnica(): void {
    PermissionService::require('configuracoes','visualizar');
    $pageTitle = 'Central Técnica';
    require __DIR__.'/../../views/central_tecnica.php';
  }

  private function bancosModulos(): void {
    PermissionService::require('database','validar');
    $relatorio = ModuleDatabaseService::relatorio();
    $pageTitle = 'Bancos por Módulo';
    require __DIR__.'/../../views/bancos_modulos.php';
  }


  private function bancosModulosInstalar(): void {
    PermissionService::require('database','validar');
    Csrf::validate();
    $modulo = trim((string)($_POST['modulo'] ?? 'todos'));
    if ($modulo === 'todos') $resultado = MultiDbMigrationService::instalarTodos();
    else $resultado = [$modulo => MultiDbMigrationService::instalarModulo($modulo)];
    $_SESSION['multidb_v42_resultado'] = $resultado;
    redirect('index.php?page=bancos-modulos&instalado=1');
  }


  private function produtosPendentesIntegracao(): void {
    PermissionService::require('produtos_vsm','visualizar');
    $status = $_GET['status'] ?? 'pendente';
    $busca = trim((string)($_GET['busca'] ?? ''));
    $pendentes = [];
    $categorias = [];
    try {
      $pdo = Database::forTable('produtos_pendentes_integracao');
      $sql = "SELECT * FROM produtos_pendentes_integracao WHERE 1=1"; $params=[];
      if($status !== ''){ $sql .= " AND status=?"; $params[]=$status; }
      if($busca !== ''){ $sql .= " AND (sku LIKE ? OR ean LIKE ? OR nome LIKE ? OR trace_id LIKE ? OR categoria_vsm_nome LIKE ?)"; for($i=0;$i<5;$i++) $params[]='%'.$busca.'%'; }
      $sql .= " ORDER BY id DESC LIMIT 300";
      $st=$pdo->prepare($sql); $st->execute($params); $pendentes=$st->fetchAll();
    } catch(Throwable $e){ $erro = $e->getMessage(); }
    try { $categorias = TenantScopeService::run('categorias_mapeamento', 'SELECT * FROM categorias_mapeamento WHERE ativo=1 ORDER BY prioridade DESC, nome_categoria_vsm ASC LIMIT 500')->fetchAll(); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    $pageTitle = 'Produtos Pendentes VSM';
    require __DIR__.'/../../views/produtos_pendentes_integracao.php';
  }

  private function produtoPendenteIntegracaoComparar(): void {
    PermissionService::require('produtos_vsm','visualizar');
    $id=(int)($_GET['id'] ?? 0);
    $pdo = Database::forTable('produtos_pendentes_integracao');
    $st = TenantScopeService::run('produtos_pendentes_integracao', 'SELECT * FROM produtos_pendentes_integracao WHERE id=? LIMIT 1', [$id]); $p=$st->fetch(PDO::FETCH_ASSOC);
    if(!$p) redirect('index.php?page=produtos-pendentes-integracao&erro=nao_encontrado');
    $check = ProdutoVsmApprovalGuardService::checklist($p);
    $historico=[];
    try { $h = TenantScopeService::run('produtos_aprovacao_historico', 'SELECT * FROM produtos_aprovacao_historico WHERE produto_pendente_id=? ORDER BY id DESC LIMIT 50', [$id]); $historico=$h->fetchAll(); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    $pageTitle = 'Comparar Produto VSM x Tiny';
    require __DIR__.'/../../views/produto_pendente_comparar.php';
  }

  private function produtoPendenteIntegracaoAcao(): void {
    PermissionService::require('produtos_vsm','simular');
    Csrf::validate();
    $id=(int)($_POST['id'] ?? 0);
    $acao=(string)($_POST['acao'] ?? '');
    $pdo = Database::forTable('produtos_pendentes_integracao');
    $st = TenantScopeService::run('produtos_pendentes_integracao', 'SELECT * FROM produtos_pendentes_integracao WHERE id=? LIMIT 1', [$id]); $p=$st->fetch(PDO::FETCH_ASSOC);
    if(!$p) redirect('index.php?page=produtos-pendentes-integracao&erro=nao_encontrado');
    $userId = Auth::user()['id'] ?? null;
    $check = ProdutoVsmApprovalGuardService::checklist($p);

    if($acao === 'rejeitar'){
      $motivo=trim((string)($_POST['motivo_rejeicao'] ?? 'Rejeitado manualmente'));
      TenantScopeService::run('produtos_pendentes_integracao', "UPDATE produtos_pendentes_integracao SET status='rejeitado', rejeitado_por=?, rejeitado_em=NOW(), atualizado_em=NOW() WHERE id=?", [$userId,$id]);
      ProdutoVsmApprovalGuardService::registrarHistorico($id,'rejeitar','sucesso','Produto rejeitado manualmente.',['motivo'=>$motivo]);
      Audit::event('produto_vsm.pendente.rejeitado','alerta',['entidade'=>'produtos_pendentes_integracao','entidade_id'=>$id,'mensagem'=>'Produto novo VSM rejeitado manualmente.','contexto'=>['motivo'=>$motivo]]);

    } elseif($acao === 'vincular'){
      $skuTiny=trim((string)($_POST['sku_tiny'] ?? ''));
      $produtoTinyId=trim((string)($_POST['produto_tiny_id'] ?? ''));
      if($skuTiny === '') redirect('index.php?page=produto-pendente-integracao-comparar&id='.$id.'&erro=sku_tiny_obrigatorio');
      TenantScopeService::run('produtos_mapeamento', 'INSERT INTO produtos_mapeamento(sku_vsm, sku_tiny, produto_tiny_id, descricao, ativo, ultima_sincronizacao) VALUES(?,?,?,?,1,NOW()) ON DUPLICATE KEY UPDATE sku_tiny=VALUES(sku_tiny), produto_tiny_id=COALESCE(VALUES(produto_tiny_id),produto_tiny_id), descricao=COALESCE(VALUES(descricao),descricao), ativo=1, ultima_sincronizacao=NOW()', [$p['sku'],$skuTiny,$produtoTinyId ?: null,$p['nome'] ?: null]);
      TenantScopeService::run('produtos_pendentes_integracao', "UPDATE produtos_pendentes_integracao SET status='vinculado', aprovado_por=?, aprovado_em=NOW(), atualizado_em=NOW() WHERE id=?", [$userId,$id]);
      ProdutoVsmApprovalGuardService::registrarHistorico($id,'vincular','sucesso','Produto vinculado a item Tiny existente.',['sku_vsm'=>$p['sku'],'sku_tiny'=>$skuTiny,'produto_tiny_id'=>$produtoTinyId]);
      Audit::event('produto_vsm.pendente.vinculado','sucesso',['entidade'=>'produtos_pendentes_integracao','entidade_id'=>$id,'mensagem'=>'Produto VSM vinculado a produto Tiny existente.','contexto'=>['sku_vsm'=>$p['sku'],'sku_tiny'=>$skuTiny,'produto_tiny_id'=>$produtoTinyId]]);

    } elseif($acao === 'aprovar_criar_tiny'){
      if(!$check['pode_aprovar']){
        ProdutoVsmApprovalGuardService::registrarHistorico($id,'aprovar_criar_tiny','bloqueado','Aprovação bloqueada pelo checklist V46.',['bloqueios'=>$check['bloqueios']]);
        redirect('index.php?page=produto-pendente-integracao-comparar&id='.$id.'&erro=checklist_bloqueou');
      }
      $payload=ProdutoVsmApprovalGuardService::payloadFromPending($p) ?: ['sku'=>$p['sku']];
      $categoriaTiny=trim((string)($_POST['categoria_tiny_id'] ?? ($p['categoria_tiny_id_sugerida'] ?? '')));
      if($categoriaTiny !== '') $payload['categoria_tiny_id'] = $categoriaTiny;
      $payload['hub_aprovado_manual'] = true;
      $payload['hub_aprovado_por'] = Auth::user()['id'] ?? 'admin';
      $payload['hub_aprovado_em'] = date('c');
      $payload['hub_aprovacao_checklist'] = $check['items'];
      $payload['hub_ncm_validado'] = $check['ncm'];
      $payload['hub_ean_validado'] = $check['ean'];
      $trace=RequestContext::id();
      TenantScopeService::run('fila_integracao', "INSERT INTO fila_integracao(tipo,referencia,payload,status,trace_id) VALUES('produto_vsm_para_tiny',?,?, 'pendente', ?)", [$p['sku'], json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), $trace]);
      $filaId=(int)Database::forTable('fila_integracao')->lastInsertId();
      TenantScopeService::run('produtos_pendentes_integracao', "UPDATE produtos_pendentes_integracao SET status='aprovado', fila_id=?, aprovado_por=?, aprovado_em=NOW(), atualizado_em=NOW() WHERE id=?", [$filaId,$userId,$id]);
      ProdutoVsmApprovalGuardService::registrarHistorico($id,'aprovar_criar_tiny','sucesso','Produto aprovado com checklist V46 e enviado para fila Tiny.',['fila_id'=>$filaId,'sku'=>$p['sku'],'checklist'=>$check['items']]);
      Audit::event('produto_vsm.pendente.aprovado_criar_tiny','sucesso',['entidade'=>'produtos_pendentes_integracao','entidade_id'=>$id,'mensagem'=>'Produto novo aprovado manualmente com camadas extras V46 e enviado para fila Tiny.','contexto'=>['fila_id'=>$filaId,'sku'=>$p['sku']]]);
    }
    redirect('index.php?page=produtos-pendentes-integracao');
  }

  private function categoriasMapeamento(): void {
    PermissionService::require('configuracoes','visualizar');
    $busca=trim((string)($_GET['busca'] ?? ''));
    $categorias=[];
    try{
      $pdo=Database::forTable('categorias_mapeamento');
      $sql='SELECT * FROM categorias_mapeamento WHERE 1=1'; $params=[];
      if($busca !== ''){ $sql.=' AND (id_categoria_vsm LIKE ? OR nome_categoria_vsm LIKE ? OR id_categoria_tiny LIKE ? OR nome_categoria_tiny LIKE ?)'; for($i=0;$i<4;$i++) $params[]='%'.$busca.'%'; }
      // Melhoria 1 da seção 8: o escopo de empresa entra DEPOIS do WHERE montado pelos filtros e
      // ANTES do ORDER BY/LIMIT — applyToSelect() cuida da posição do parâmetro.
      [$sql, $params] = TenantScopeService::applyToSelect('categorias_mapeamento', $sql, $params);
      $sql.=' ORDER BY ativo DESC, prioridade DESC, nome_categoria_vsm ASC LIMIT 500';
      $st=$pdo->prepare($sql); $st->execute($params); $categorias=$st->fetchAll();
    }catch(Throwable $e){ $erro=$e->getMessage(); }
    $pageTitle='Mapeamento de Categorias VSM ↔ Tiny';
    require __DIR__.'/../../views/categorias_mapeamento.php';
  }

  private function categoriaMapeamentoSalvar(): void {
    PermissionService::require('configuracoes','editar');
    Csrf::validate();
    $id=(int)($_POST['id'] ?? 0);
    $dados=[
      trim((string)($_POST['id_categoria_vsm'] ?? '')) ?: null,
      trim((string)($_POST['nome_categoria_vsm'] ?? '')),
      trim((string)($_POST['id_categoria_tiny'] ?? '')),
      trim((string)($_POST['nome_categoria_tiny'] ?? '')),
      isset($_POST['ativo']) ? 1 : 0,
      (int)($_POST['prioridade'] ?? 0),
      trim((string)($_POST['observacao'] ?? '')) ?: null,
      Auth::user()['id'] ?? null
    ];
    if($dados[1] === '' || $dados[2] === '' || $dados[3] === '') redirect('index.php?page=categorias-mapeamento&erro=campos');
    $pdo=Database::forTable('categorias_mapeamento');
    if($id>0){
      TenantScopeService::run('categorias_mapeamento', 'UPDATE categorias_mapeamento SET id_categoria_vsm=?, nome_categoria_vsm=?, id_categoria_tiny=?, nome_categoria_tiny=?, ativo=?, prioridade=?, observacao=?, atualizado_por=?, atualizado_em=NOW() WHERE id=?', [...$dados,$id]);
      Audit::event('categoria_mapeamento.atualizada','sucesso',['entidade'=>'categorias_mapeamento','entidade_id'=>$id,'mensagem'=>'Mapeamento de categoria atualizado.']);
    } else {
      TenantScopeService::run('categorias_mapeamento', 'INSERT INTO categorias_mapeamento(id_categoria_vsm,nome_categoria_vsm,id_categoria_tiny,nome_categoria_tiny,ativo,prioridade,observacao,criado_por) VALUES(?,?,?,?,?,?,?,?)', $dados);
      Audit::event('categoria_mapeamento.criada','sucesso',['entidade'=>'categorias_mapeamento','entidade_id'=>$pdo->lastInsertId(),'mensagem'=>'Mapeamento de categoria criado.']);
    }
    redirect('index.php?page=categorias-mapeamento&salvo=1');
  }

  private function sobre(): void {
    Auth::requireLogin();
    $pageTitle = 'Sobre';
    $branding = 'Hub de Integração Enterprise';
    $assinatura = 'Desenvolvido por Sabas';
    require __DIR__.'/../../views/sobre.php';
  }

  private function producaoReady(): void {
    PermissionService::require('configuracoes','visualizar');
    $pageTitle = 'Produção Segura';
    $resultado = ProductionReadyService::checks();
    require __DIR__.'/../../views/producao_ready.php';
  }


  private function securityCenter(): void {
    PermissionService::require('seguranca','visualizar');
    SecurityEventService::ensureSchema();
    $pageTitle = 'Central Técnica · Segurança';
    $activeSecurityPage = $_GET['page'] ?? 'security-center';
    $eventos = [];
    $ips = [];
    $circuitBreakers = [];
    $loginTentativas = [];
    try { $eventos = $this->db('security_events')->query("SELECT id, tipo, severidade, ip, user_agent, mensagem, trace_id, created_at FROM security_events ORDER BY id DESC LIMIT 100")->fetchAll(); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    try { $ips = $this->db('ips_bloqueados')->query("SELECT id, ip, motivo, ativo, criado_em, expira_em FROM ips_bloqueados WHERE ativo=1 ORDER BY id DESC LIMIT 100")->fetchAll(); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    try { $circuitBreakers = Database::forTable('circuit_breakers')->query("SELECT id, sistema, status, falhas_consecutivas, aberto_ate, atualizado_em FROM circuit_breakers ORDER BY FIELD(status,'aberto','meio_aberto','fechado'), sistema ASC")->fetchAll(); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    try { $loginTentativas = Database::forTable('login_tentativas')->query("SELECT id, email, ip, sucesso, motivo, criado_em FROM login_tentativas ORDER BY id DESC LIMIT 80")->fetchAll(); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    $fim = class_exists('FileIntegrityService') ? FileIntegrityService::check() : ['status'=>'indisponivel','total'=>0,'alterados'=>[],'faltantes'=>[],'novos'=>[]];
    $score = class_exists('SecurityScoreService') ? SecurityScoreService::evaluate() : ['score'=>0,'classificacao'=>'indisponível','checks'=>[]];
    $hardening = class_exists('SecurityHardeningService') ? SecurityHardeningService::status() : ['score'=>0,'checks'=>[]];
    $codeAudit = class_exists('CodeExecutionAuditService') ? CodeExecutionAuditService::scan() : ['status'=>'indisponivel','counts'=>[],'hits'=>[]];
    $legacyInventory = class_exists('LegacyInventoryService') ? LegacyInventoryService::controllersServices() : ['items'=>[],'resumo'=>[]];
    $databaseInventory = class_exists('DatabaseInventoryService') ? DatabaseInventoryService::classify() : ['items'=>[],'resumo'=>[],'total'=>0];
    $backupTrust = class_exists('BackupTrustService') ? BackupTrustService::score() : ['score'=>0,'items'=>[]];
    $realtimeHealth = class_exists('RealtimeHealthService') ? RealtimeHealthService::snapshot() : ['score'=>0,'checks'=>[]];
    $auditDailySignatures = class_exists('AuditDailySignatureService') ? AuditDailySignatureService::latest(12) : [];
    $tokenVaultHealth = class_exists('TokenVaultService') ? TokenVaultService::health() : ['rows'=>[]];
    $ssl = [
      'https' => class_exists('TrustedProxyService') ? TrustedProxyService::isHttps() : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
      'hsts' => App::isProduction(),
      'host' => $_SERVER['HTTP_HOST'] ?? 'localhost',
      'acao' => 'Validar certificado no navegador/Cloudflare/hospedagem e manter renovação automática ativa.'
    ];
    $pentestChecklist = [
      ['item'=>'Login: brute force por IP e usuário', 'ok'=>!empty(App::config()['security']['login_user_rate_limit_per_hour'])],
      ['item'=>'Painel: WAF restrito às telas administrativas', 'ok'=>!empty(App::config()['security']['waf_panel_only'])],
      ['item'=>'Tiny/VSM: rotas sem filtro agressivo por palavras-chave', 'ok'=>true],
      ['item'=>'Tiny: webhook secret obrigatório', 'ok'=>!empty(App::config()['security']['tiny_webhook_exigir_secret'])],
      ['item'=>'VSM: HMAC disponível e allowlist opcional', 'ok'=>array_key_exists('vsm_hmac_enabled', App::config()['security'] ?? [])],
      ['item'=>'Backups: HMAC antes de restore', 'ok'=>strlen((string)((App::config()['security']['backup_signature_key'] ?? ''))) >= 32],
      ['item'=>'Anti-replay VSM com request_hash/request_time', 'ok'=>Database::tableExists('integration_replay_guard')],
      ['item'=>'Assinatura diária da auditoria', 'ok'=>Database::tableExists('audit_daily_signatures')],
      ['item'=>'Token Vault interno para rotação', 'ok'=>Database::tableExists('token_vault')],
      ['item'=>'Código sem chamada real ao sistema operacional', 'ok'=>(int)(($codeAudit['counts']['os_exec_real'] ?? 0)) === 0],
      ['item'=>'FIM: manifesto de integridade disponível', 'ok'=>is_file(FileIntegrityService::manifestPath())],
      ['item'=>'Sessão: versionamento para logout global', 'ok'=>Database::columnExists('usuarios','session_version')],
    ];
    require __DIR__.'/../../views/security_center.php';
  }

  private function securityFim(): void {
    PermissionService::require('seguranca','visualizar');
    $resultado = FileIntegrityService::check();
    require __DIR__.'/../../views/security_fim.php';
  }

  private function securityFimGerar(): void {
    PermissionService::require('seguranca','gerenciar');
    Csrf::validate();
    $ok = FileIntegrityService::saveManifest();
    SecurityEventService::log('fim.manifesto_gerado', $ok ? 'baixo' : 'alto', $ok ? 'Manifesto de integridade gerado' : 'Falha ao gerar manifesto');
    redirect('index.php?page=security-fim&gerado='.($ok?'1':'0'));
  }

  private function securityScore(): void {
    PermissionService::require('seguranca','visualizar');
    $score = SecurityScoreService::evaluate();
    require __DIR__.'/../../views/security_score.php';
  }


  private function securityAuditSign(): void {
    PermissionService::require('seguranca','gerenciar');
    Csrf::validate();
    $date = preg_replace('/[^0-9\-]/', '', (string)($_POST['audit_date'] ?? date('Y-m-d')));
    try { AuditDailySignatureService::sign($date ?: date('Y-m-d')); }
    catch(Throwable $e){ SecurityEventService::log('auditoria.assinatura_diaria.erro','alto','Falha ao assinar auditoria diária.', ['erro'=>$e->getMessage(),'date'=>$date]); }
    redirect('index.php?page=security-audit-signatures&signed=1');
  }

}
