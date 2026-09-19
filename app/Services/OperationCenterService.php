<?php
/**
 * V92 - Serviço de leitura operacional com origem dos dados.
 * Regra: Dashboard Executivo deve mostrar dados reais do banco/configuração;
 * quando uma tabela não existir ou estiver vazia, a tela sinaliza claramente.
 */
class OperationCenterService {
  public static function tableExists(string $table): bool {
    try {
      /* Achado I-10: `SHOW TABLES LIKE ?` é inválido com prepares nativos. */
      return Database::tableExists($table);
    } catch (Throwable $e) { return false; }
  }

  public static function columnExists(string $table, string $column): bool {
    try {
      $pdo = Database::forTable($table);
      $st = $pdo->prepare('SHOW COLUMNS FROM `'.$table.'` LIKE ?');
      $st->execute([$column]);
      return (bool)$st->fetchColumn();
    } catch (Throwable $e) { return false; }
  }

  public static function safeCount(string $table, string $where='1=1'): int {
    try {
      if (!self::tableExists($table)) return 0;
      // Melhoria 1 da seção 8: o nome da tabela é interpolado, então este ponto servia várias
      // tabelas com escopo de empresa sem filtrar nenhuma. applyToSelect() só age quando a tabela
      // está no catálogo e há empresa ativa; nas demais é passagem direta.
      [$sql, $params] = TenantScopeService::applyToSelect($table, "SELECT COUNT(*) c FROM `{$table}` WHERE {$where}", []);
      $st = Database::forTable($table)->prepare($sql); $st->execute($params);
      return (int)($st->fetch()['c'] ?? 0);
    } catch (Throwable $e) { return 0; }
  }

  public static function safeCountInfo(string $table, string $where='1=1', string $label=''): array {
    try {
      if (!self::tableExists($table)) {
        return ['valor'=>0,'real'=>false,'fonte'=>$table,'status'=>'tabela_ausente','detalhe'=>'Tabela não encontrada: '.$table];
      }
      // Melhoria 1 da seção 8: o nome da tabela é interpolado, então este ponto servia várias
      // tabelas com escopo de empresa sem filtrar nenhuma. applyToSelect() só age quando a tabela
      // está no catálogo e há empresa ativa; nas demais é passagem direta.
      [$sql, $params] = TenantScopeService::applyToSelect($table, "SELECT COUNT(*) c FROM `{$table}` WHERE {$where}", []);
      $st = Database::forTable($table)->prepare($sql); $st->execute($params);
      $valor = (int)($st->fetch()['c'] ?? 0);
      return ['valor'=>$valor,'real'=>true,'fonte'=>$table,'status'=>'ok','detalhe'=>($label ?: 'Consulta real no banco')];
    } catch (Throwable $e) {
      return ['valor'=>0,'real'=>false,'fonte'=>$table,'status'=>'erro','detalhe'=>$e->getMessage()];
    }
  }

  public static function workerCards(): array {
    $workers = [
      ['arquivo'=>'worker_xml_nfe.php','titulo'=>'XML/NF-e'],
      ['arquivo'=>'worker_estoque.php','titulo'=>'Estoque'],
      ['arquivo'=>'worker_consulta_estoque_vsm.php','titulo'=>'Consulta VSM'],
      ['arquivo'=>'worker_fila.php','titulo'=>'Fila'],
      ['arquivo'=>'worker_backup.php','titulo'=>'Backups'],
      ['arquivo'=>'worker_notificacoes.php','titulo'=>'Notificações'],
    ];
    $out=[];
    foreach($workers as $w){
      $file = __DIR__.'/../../public/'.$w['arquivo'];
      $exists = is_file($file);
      $out[] = ['titulo'=>$w['titulo'],'arquivo'=>$w['arquivo'],'status'=>$exists?'online':'erro','detalhe'=>$exists?'Arquivo disponível para cron/agendamento':'Arquivo não encontrado'];
    }
    return $out;
  }

  public static function ultimoHomologacao(string $table): array {
    try {
      if (!self::tableExists($table)) return ['existe'=>false,'aprovado'=>false,'trace_id'=>null,'criado_em'=>null,'percentual'=>0,'status'=>'sem_tabela'];
      // Melhoria 1 da seção 8: o nome da tabela é interpolado, então este ponto servia várias
      // tabelas com escopo de empresa sem filtrar nenhuma. applyToSelect() só age quando a tabela
      // está no catálogo e há empresa ativa; nas demais é passagem direta.
      [$sql, $params] = TenantScopeService::applyToSelect($table, "SELECT * FROM `{$table}` ORDER BY id DESC LIMIT 1", []);
      $st = Database::forTable($table)->prepare($sql); $st->execute($params);
      $row = $st->fetch(PDO::FETCH_ASSOC);
      if (!$row) return ['existe'=>true,'aprovado'=>false,'trace_id'=>null,'criado_em'=>null,'percentual'=>0,'status'=>'sem_teste'];
      $json = [];
      if (!empty($row['resultado_json'])) $json = json_decode((string)$row['resultado_json'], true) ?: [];
      $percentual = 0;
      if (!empty($row['aprovado'])) $percentual = 100;
      elseif (isset($json['diagnostico']['percentual'])) $percentual = (int)$json['diagnostico']['percentual'];
      elseif (isset($json['percentual'])) $percentual = (int)$json['percentual'];
      else {
        $etapas = $json['etapas'] ?? [];
        if (is_array($etapas) && $etapas) {
          $ok=0; $total=0; foreach($etapas as $e){ $total++; if(!empty($e['ok'])) $ok++; }
          $percentual = $total ? (int)round(($ok/$total)*100) : 0;
        }
      }
      return ['existe'=>true,'aprovado'=>!empty($row['aprovado']),'trace_id'=>$row['trace_id']??null,'criado_em'=>$row['criado_em']??null,'percentual'=>$percentual,'status'=>!empty($row['aprovado'])?'aprovado':'parcial','raw'=>$row];
    } catch(Throwable $e) { return ['existe'=>false,'aprovado'=>false,'trace_id'=>null,'criado_em'=>null,'percentual'=>0,'status'=>'erro','erro'=>$e->getMessage()]; }
  }

  public static function summary(?array $cfgInt=null): array {
    try { $cfgInt = $cfgInt ?? IntegrationConfig::get(); } catch(Throwable $e){ $cfgInt=[]; }
    $tinyV2Ok = !empty($cfgInt['tiny_v2_token']);
    $tinyV3Operacional = !empty($cfgInt['tiny_v3_operacional']);
    $tinyV3OAuth = !empty($cfgInt['tiny_v3_access_token']) || !empty($cfgInt['tiny_v3_refresh_token']) || !empty($cfgInt['tiny_v3_client_id']) || !empty($cfgInt['tiny_v3_manual_access_token']);
    $vsmOk = !empty($cfgInt['vsm_url']) && !empty($cfgInt['vsm_token']);
    $vsmUrlOk = !empty($cfgInt['vsm_url']);

    $filaErro = self::safeCount('fila_integracao', "status IN ('erro','falha_definitiva')");
    $filaPendente = self::safeCount('fila_integracao', "status='pendente'");
    $xmlErro = self::safeCount('pedidos_hub', "status_hub IN ('erro_xml','erro_envio_tiny','erro_retorno_vsm')");
    $estoqueErro = self::safeCount('fila_estoque', "status IN ('erro','falha_definitiva')");
    $divergencias = self::safeCount('estoque_divergencias', "status='aberto'");
    $alertas = [];
    if(!$tinyV2Ok) $alertas[] = ['nivel'=>'atencao','titulo'=>'Tiny V2 sem token','mensagem'=>'Configure o token antes de operar em produção.','acao'=>'index.php?page=tiny-v2-homologacao'];
    if(!$tinyV3Operacional) $alertas[] = ['nivel'=>'atencao','titulo'=>'Tiny V3 em homologação','mensagem'=>'Mantenha V2 em produção até OAuth e testes reais passarem.','acao'=>'index.php?page=tiny-v3-homologacao'];
    if(!$vsmUrlOk) $alertas[] = ['nivel'=>'erro','titulo'=>'VSM sem URL','mensagem'=>'Configure a URL/base da VSM.','acao'=>'index.php?page=configuracoes'];
    elseif(empty($cfgInt['vsm_token'])) $alertas[] = ['nivel'=>'atencao','titulo'=>'VSM sem token','mensagem'=>'URL configurada, mas token da VSM não foi informado.','acao'=>'index.php?page=configuracoes'];
    if($filaErro>0) $alertas[] = ['nivel'=>'erro','titulo'=>'Fila com erro','mensagem'=>$filaErro.' item(ns) com erro definitivo.','acao'=>'index.php?page=fila-morta'];
    if($xmlErro>0) $alertas[] = ['nivel'=>'erro','titulo'=>'XML/NF-e com erro','mensagem'=>$xmlErro.' pedido(s) com problema de XML/NF-e.','acao'=>'index.php?page=fiscal'];
    if($estoqueErro>0) $alertas[] = ['nivel'=>'erro','titulo'=>'Estoque com erro','mensagem'=>$estoqueErro.' item(ns) com falha na fila de estoque.','acao'=>'index.php?page=estoque-dashboard'];
    if($divergencias>0) $alertas[] = ['nivel'=>'atencao','titulo'=>'Divergência Tiny x VSM','mensagem'=>$divergencias.' divergência(s) de estoque aberta(s).','acao'=>'index.php?page=monitor-divergencias'];
    $geral = 'online';
    foreach($alertas as $a){ if(($a['nivel']??'')==='erro'){ $geral='erro'; break; } if(($a['nivel']??'')==='atencao') $geral='atencao'; }
    return [
      'geral'=>$geral,
      'alertas'=>$alertas,
      'integracoes'=>[
        ['nome'=>'Tiny V2','status'=>$tinyV2Ok?'online':'atencao','modo'=>'Produção','detalhe'=>$tinyV2Ok?'Token configurado':'Token pendente'],
        ['nome'=>'Tiny V3','status'=>$tinyV3Operacional?'online':'atencao','modo'=>$tinyV3Operacional?'Produção':'Homologação','detalhe'=>$tinyV3OAuth?'OAuth configurado/parcial':'OAuth pendente'],
        ['nome'=>'VSM','status'=>$vsmOk?'online':($vsmUrlOk?'atencao':'erro'),'modo'=>'Origem operacional','detalhe'=>$vsmOk?'URL e token configurados':($vsmUrlOk?'URL configurada, token pendente':'URL pendente')],
        ['nome'=>'XML/NF-e','status'=>$xmlErro>0?'erro':'online','modo'=>'VSM → HUB → Tiny','detalhe'=>$xmlErro>0?'Há erros no fluxo XML/NF-e':'Sem erros críticos detectados'],
      ],
      'filas'=>['pendentes'=>$filaPendente,'erros'=>$filaErro],
      'estoque'=>self::estoqueResumo(),
      'xml_nfe'=>self::xmlResumo(),
      'pedidos'=>self::pedidosResumo(),
      'workers'=>self::workerCards(),
      'homologacao'=>['tiny_v2'=>self::ultimoHomologacao('tiny_v2_homologacao_testes'), 'tiny_v3'=>self::ultimoHomologacao('tiny_v3_homologacao_testes')],
      'fontes'=>self::fontesDashboard(),
    ];
  }

  public static function estoqueResumo(): array {
    return [
      'sincronizados'=>self::safeCount('estoque_movimentos', "status IN ('sucesso','sincronizado','confirmado')"),
      'divergencias'=>self::safeCount('estoque_divergencias'),
      'divergencias_abertas'=>self::safeCount('estoque_divergencias', "status='aberto'"),
      'pendentes'=>self::safeCount('fila_estoque', "status='pendente'"),
      'falhas'=>self::safeCount('fila_estoque', "status IN ('erro','falha_definitiva')"),
    ];
  }

  public static function xmlResumo(): array {
    return [
      'recebidas'=>self::safeCount('pedidos_nfe_xml'),
      'xml_processados'=>self::safeCount('pedidos_nfe_xml', "status_xml IN ('validado','enviado_tiny','concluido')"),
      'xml_erros'=>self::safeCount('pedidos_nfe_xml', "status_xml LIKE '%erro%' OR validado=0"),
      'reenvios'=>self::safeCount('fila_fiscal', "status IN ('pendente','processando')"),
    ];
  }

  public static function pedidosResumo(): array {
    return [
      'recebidos_tiny'=>self::safeCount('pedidos_hub', "status_hub='recebido_tiny'"),
      'enviados_vsm'=>self::safeCount('pedidos_hub', "status_hub='enviado_vsm'"),
      'aguardando_xml'=>self::safeCount('pedidos_hub', "status_hub IN ('recebido_vsm','xml_validado')"),
      'enviados_tiny'=>self::safeCount('pedidos_hub', "status_hub='enviado_tiny'"),
      'concluidos'=>self::safeCount('pedidos_hub', "status_hub='concluido'"),
      'erros'=>self::safeCount('pedidos_hub', "status_hub LIKE '%erro%'"),
    ];
  }

  public static function fontesDashboard(): array {
    return [
      self::safeCountInfo('pedidos_hub','1=1','Pedidos operacionais'),
      self::safeCountInfo('pedidos_nfe_xml','1=1','XML/NF-e recebidos'),
      self::safeCountInfo('estoque_movimentos','1=1','Movimentos de estoque'),
      self::safeCountInfo('estoque_divergencias','1=1','Divergências de estoque'),
      self::safeCountInfo('fila_integracao','1=1','Fila de integração'),
      self::safeCountInfo('fila_estoque','1=1','Fila de estoque'),
      self::safeCountInfo('tiny_v2_homologacao_testes','1=1','Histórico homologação Tiny V2'),
      self::safeCountInfo('tiny_v3_homologacao_testes','1=1','Histórico homologação Tiny V3'),
    ];
  }

  public static function scoreDetalhado(): array {
    $s = self::summary();
    try { $cfg = IntegrationConfig::get(); } catch(Throwable $e) { $cfg=[]; }
    $calc = function(int $erros, int $pendentes=0): int { return max(0, min(100, 100 - ($erros*18) - ($pendentes*3))); };
    $v2 = $s['homologacao']['tiny_v2'] ?? [];
    $v3 = $s['homologacao']['tiny_v3'] ?? [];
    $tinyV2Score = !empty($v2['existe']) && ($v2['status']??'')!=='sem_teste' ? (int)($v2['percentual'] ?? 0) : (!empty($cfg['tiny_v2_token']) ? 60 : 0);
    $tinyV3Score = !empty($v3['existe']) && ($v3['status']??'')!=='sem_teste' ? (int)($v3['percentual'] ?? 0) : ((!empty($cfg['tiny_v3_access_token']) || !empty($cfg['tiny_v3_manual_access_token']) || !empty($cfg['tiny_v3_client_id'])) ? 50 : 0);
    $vsmScore = (!empty($cfg['vsm_url']) && !empty($cfg['vsm_token'])) ? 100 : (!empty($cfg['vsm_url']) ? 50 : 0);
    $items = [
      'Tiny V2'=>['score'=>$tinyV2Score,'real'=>!empty($v2['existe']) && ($v2['status']??'')!=='sem_teste','fonte'=>!empty($v2['trace_id'])?'tiny_v2_homologacao_testes / '.$v2['trace_id']:'configuracoes_integracao','detalhe'=>!empty($v2['trace_id'])?'Último teste real de homologação':'Sem teste salvo; score baseado somente em configuração'],
      'Tiny V3'=>['score'=>$tinyV3Score,'real'=>!empty($v3['existe']) && ($v3['status']??'')!=='sem_teste','fonte'=>!empty($v3['trace_id'])?'tiny_v3_homologacao_testes / '.$v3['trace_id']:'configuracoes_integracao','detalhe'=>!empty($v3['trace_id'])?'Último teste real de homologação':'Sem teste salvo; score baseado somente em OAuth/configuração'],
      'VSM'=>['score'=>$vsmScore,'real'=>false,'fonte'=>'configuracoes_integracao','detalhe'=>'Validação local de URL/token; teste HTTP real aparece na homologação/alertas'],
      'Estoque'=>['score'=>$calc((int)($s['estoque']['falhas']??0), (int)($s['estoque']['pendentes']??0) + (int)($s['estoque']['divergencias_abertas']??0)),'real'=>self::tableExists('fila_estoque') || self::tableExists('estoque_divergencias'),'fonte'=>'fila_estoque + estoque_divergencias','detalhe'=>'Calculado por falhas, pendências e divergências abertas'],
      'XML/NF-e'=>['score'=>$calc((int)($s['xml_nfe']['xml_erros']??0), (int)($s['xml_nfe']['reenvios']??0)),'real'=>self::tableExists('pedidos_nfe_xml') || self::tableExists('fila_fiscal'),'fonte'=>'pedidos_nfe_xml + fila_fiscal','detalhe'=>'Calculado por XML com erro e reenvios pendentes'],
      'Fila'=>['score'=>$calc((int)($s['filas']['erros']??0), (int)($s['filas']['pendentes']??0)),'real'=>self::tableExists('fila_integracao'),'fonte'=>'fila_integracao','detalhe'=>'Calculado por pendentes e erros definitivos'],
    ];
    return $items;
  }

  public static function score(): array {
    $out=[]; foreach(self::scoreDetalhado() as $k=>$v){ $out[$k]=(int)($v['score']??0); } return $out;
  }
}
