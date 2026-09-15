<?php
class OrquestracaoController {
  public function dispatch(string $page): void {
    Auth::requireLogin();
    switch ($page) {
      case 'orquestracao-integracoes': $this->index(); break;
      case 'salvar-orquestracao-integracoes': $this->salvar(); break;
      case 'testar-orquestracao-fluxo': $this->testarFluxo(); break;
      default: redirect('index.php?page=orquestracao-integracoes');
    }
  }

  private function index(): void {
    PermissionService::require('configuracoes','visualizar');
    $this->ensureSchema();
    $config = IntegrationConfig::get();
    $rules = IntegrationOrchestratorService::all();
    $fluxos = IntegrationOrchestratorService::fluxos($rules);
    $historicoOrquestracao = $this->historico(12);
    $pageTitle = 'Orquestração Tiny ⇄ VSM';
    require __DIR__.'/../../views/orquestracao_integracoes.php';
  }

  private function salvar(): void {
    PermissionService::require('configuracoes','editar');
    Csrf::validate();
    $this->ensureSchema(true);
    $keys = IntegrationOrchestratorService::keys();
    $catalogo = IntegrationOrchestratorService::catalogoFluxos();
    $antes = IntegrationOrchestratorService::all();
    $dados = $antes;
    foreach ($keys as $k=>$default) if(!array_key_exists($k, $dados)) $dados[$k] = $default;
    if(isset($_POST['sync_ordem_envio'])) $dados['sync_ordem_envio'] = IntegrationOrchestratorService::normalizarOrdem(trim((string)$_POST['sync_ordem_envio']));
    foreach($catalogo as $id=>$f){ $fk='fluxo_'.$id; if(array_key_exists($fk,$keys)) $dados[$fk]=isset($_POST[$fk])?1:0; }
    $travamentos = [
      'sync_exigir_mapeamento_sku','sync_exigir_nfe_autorizada','sync_bloquear_produto_novo_vsm',
      'sync_tiny_bloquear_produto_novo_vsm','sync_permitir_produto_novo_vsm_manual','sync_aprovacao_manual_produto_novo_vsm',
      'sync_exigir_categoria_mapeada_vsm','sync_permitir_atualizar_produto_existente_vsm','sync_permitir_estoque_vsm_tiny','sync_permitir_status_vsm_tiny'
    ];
    foreach($travamentos as $k) if(array_key_exists($k,$keys)) $dados[$k]=isset($_POST[$k])?1:0;
    foreach($catalogo as $id=>$f){ $regra=$f['regra']??''; if($regra && !empty($dados['fluxo_'.$id]) && array_key_exists($regra,$keys)) $dados[$regra]=1; }
    $macrosAtivas=[]; foreach($catalogo as $id=>$f){ if(!empty($dados['fluxo_'.$id])) $macrosAtivas[$f['regra']]=true; }
    foreach($catalogo as $id=>$f){ $regra=$f['regra']??''; if($regra && array_key_exists($regra,$keys) && !isset($macrosAtivas[$regra]) && !in_array($regra,$travamentos,true)) $dados[$regra]=0; }

    try {
      $pdo = Database::forTable('configuracoes_integracao');
      $pdo->exec('INSERT IGNORE INTO configuracoes_integracao(id) VALUES(1)');
      $msgs=['Schema da orquestração validado; nenhum DDL executado na gravação.'];
      $sql='UPDATE configuracoes_integracao SET '.implode(',', array_map(fn($k)=>'`'.$k.'`=?', array_keys($keys))).' WHERE id=1';
      $st=$pdo->prepare($sql); $st->execute(array_map(fn($k)=>$dados[$k]??null, array_keys($keys)));
      $depois=IntegrationOrchestratorService::all();
      $alteracoes=$this->diff($antes,$depois); $ativos=0; foreach(IntegrationOrchestratorService::fluxos($depois) as $f) if(!empty($f['ativo'])) $ativos++;
      $total=count($catalogo); $trace=RequestContext::id();
      $this->registrar('salvar_fluxos','sucesso','Fluxos ativos salvos e recarregados com persistência confirmada.', ['ativos'=>$ativos,'total'=>$total,'alteracoes'=>$alteracoes,'config'=>$depois,'mensagens_banco'=>$msgs], $trace);
      Audit::event('orquestracao.salvar','sucesso',['mensagem'=>'Orquestração Tiny ⇄ VSM atualizada e persistida.','contexto'=>['ativos'=>$ativos,'total'=>$total,'alteracoes'=>$alteracoes,'config'=>$depois]]);
      NotificationService::criar('sistema','Orquestração salva','Fluxos ativos persistidos no banco. Ativos: '.$ativos.'/'.$total.'.','sucesso',['link'=>'index.php?page=orquestracao-integracoes']);
      redirect('index.php?page=orquestracao-integracoes&salvo=1&ativos='.$ativos.'&trace='.urlencode($trace));
    } catch(Throwable $e) {
      Audit::exception($e,'orquestracao.salvar.erro',['codigo_erro'=>'ORCHESTRATION_SAVE_ERROR']);
      $this->registrar('salvar_fluxos','erro',$e->getMessage(), ['post'=>array_keys($_POST)], RequestContext::id());
      redirect('index.php?page=orquestracao-integracoes&erro=1');
    }
  }

  private function testarFluxo(): void {
    PermissionService::require('configuracoes','visualizar');
    Csrf::validate(); $this->ensureSchema();
    $fluxo = preg_replace('/[^a-z0-9_\-]/i','', (string)($_POST['fluxo'] ?? ''));
    $catalogo = IntegrationOrchestratorService::catalogoFluxos();
    if(!isset($catalogo[$fluxo])) redirect('index.php?page=orquestracao-integracoes&teste=fluxo_invalido');
    $regra = $catalogo[$fluxo]['regra'];
    $resultado = IntegrationOrchestratorService::podeExecutar($regra, ['fluxo_id'=>$fluxo,'sku_mapeado'=>true,'nfe_autorizada'=>true]);
    $status = !empty($resultado['ok']) ? 'sucesso' : 'alerta';
    $this->registrar('testar_fluxo', $status, (string)$resultado['mensagem'], ['fluxo'=>$fluxo,'regra'=>$regra,'resultado'=>$resultado], RequestContext::id());
    redirect('index.php?page=orquestracao-integracoes&teste='.($resultado['ok']?'ok':'bloqueado').'&fluxo='.urlencode($fluxo));
  }

  private function ensureSchema(bool $strict=false): bool {
    $missing=[];
    foreach(['configuracoes_integracao','orquestracao_fluxos_historico'] as $table) if(!Database::tableExists($table)) $missing[]='tabela:'.$table;
    foreach(IntegrationOrchestratorService::keys() as $column=>$default) if(!Database::columnExists('configuracoes_integracao',$column)) $missing[]='coluna:configuracoes_integracao.'.$column;
    if($missing){
      $message='Estrutura da orquestração incompleta. Execute Central Técnica > Migrações Seguras V104.49.3. Ausências: '.implode(', ',array_slice($missing,0,8)).(count($missing)>8?' e mais '.(count($missing)-8):'');
      try { Audit::event('orquestracao.schema.pendente','alerta',['mensagem'=>$message,'codigo_erro'=>'ORCHESTRATION_MIGRATION_REQUIRED','contexto'=>['itens_ausentes'=>$missing]]); } catch(Throwable $e){ if(class_exists('BestEffortLogService'))BestEffortLogService::warning(__METHOD__,$e); }
      if($strict) throw new RuntimeException($message);
      return false;
    }
    return true;
  }

  private function columnExists(PDO $pdo, string $table, string $column): bool {
    return Database::columnExistsOn($pdo, $table, $column);
  }

  private function assertIdentifier(string $identifier): void {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $identifier)) {
      throw new InvalidArgumentException('Identificador SQL inválido: '.$identifier);
    }
  }

  private function registrar(string $acao, string $status, string $mensagem, array $contexto=[], ?string $trace=null): void {
    try { if(!$this->ensureSchema()) return; Database::forTable('orquestracao_fluxos_historico')->prepare('INSERT INTO orquestracao_fluxos_historico(acao,status,mensagem,contexto_json,usuario_id,trace_id) VALUES(?,?,?,?,?,?)')->execute([$acao,$status,$mensagem,json_encode($contexto,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),Auth::user()['id']??null,$trace?:RequestContext::id()]); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
  }

  private function historico(int $limit=10): array {
    try { if(!$this->ensureSchema()) return []; $st=Database::forTable('orquestracao_fluxos_historico')->prepare('SELECT * FROM orquestracao_fluxos_historico ORDER BY id DESC LIMIT '.max(1,min(50,$limit))); $st->execute(); return $st->fetchAll(PDO::FETCH_ASSOC) ?: []; } catch(Throwable $e) { if(class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); return []; }
  }

  private function diff(array $antes, array $depois): array {
    $diff=[]; foreach(IntegrationOrchestratorService::keys() as $k=>$default){ $a=(string)($antes[$k]??''); $d=(string)($depois[$k]??''); if($a!==$d) $diff[$k]=['antes'=>$a,'depois'=>$d,'label'=>IntegrationOrchestratorService::label($k)]; }
    return $diff;
  }
}
