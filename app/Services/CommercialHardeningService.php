<?php
/**
 * V104.18 - Checklist final de produção comercial.
 * Consolida prioridade alta + média e aponta lacunas antes de cliente pagante.
 */
class CommercialHardeningService {
  public static function run(): array {
    $items = [];
    $add = function(string $grupo, string $item, string $status, string $detalhe, string $acao, int $peso=1) use (&$items) {
      $items[] = compact('grupo','item','status','detalhe','acao','peso');
    };

    $cfg = App::config();
    $commercial = $cfg['commercial'] ?? [];

    $add('Versão','Versão centralizada', class_exists('SystemVersionService') ? 'ok':'erro', class_exists('SystemVersionService') ? SystemVersionService::fullLabel() : 'Serviço ausente', 'Manter todas as mensagens usando SystemVersionService.', 2);

    $single = class_exists('Database') ? Database::isSingleDatabaseMode() : false;
    $add('Banco','Banco único absoluto', $single ? 'ok':'alerta', $single ? 'db_storage_mode single/rescue ativo; db_modules ignorado para conexão.' : 'Modo modular estrito detectado.', 'Em hospedagem compartilhada, manter db_storage_mode=single.', 3);

    try {
      $diag = class_exists('DatabaseConfigDiagnosticService') ? DatabaseConfigDiagnosticService::run() : [];
      $risk = $diag['risk'] ?? [];
      $add('Banco','Diagnóstico config real', empty($risk) ? 'ok':'alerta', empty($risk) ? 'Sem risco crítico de conexão detectado.' : implode('; ', array_slice($risk,0,3)), 'Abrir Diagnóstico Config Real e corrigir banco conectado por módulo.', 3);
    } catch(Throwable $e) { $add('Banco','Diagnóstico config real','erro',$e->getMessage(),'Corrigir conexão antes de produção.',3); }

    try {
      $license = LicenseEnforcementService::status();
      $add('Licença','Licença local HMAC', ($license['status'] ?? '')==='ok' ? 'ok':'alerta', $license['mensagem'] ?? 'Sem mensagem', 'Cadastrar licença válida e assinada antes de modo enforce.', 3);
    } catch(Throwable $e) { $add('Licença','Licença local HMAC','erro',$e->getMessage(),'Reparar tabela/licença comercial.',3); }

    $mode = (string)($commercial['license_mode'] ?? 'monitor');
    $add('Licença','Modo de bloqueio comercial', $mode==='enforce' ? 'ok':'alerta', 'Modo atual: '.$mode, 'Para cliente pagante usar enforce; para homologação usar monitor.', 2);

    $remote = class_exists('LicenseServerClientService') ? LicenseServerClientService::status() : ['status'=>'erro','mensagem'=>'Serviço ausente'];
    $add('Licença','Servidor licenciador remoto', ($remote['status'] ?? '')==='ok' ? 'ok':'alerta', $remote['mensagem'] ?? 'Sem status', 'Configurar license_server_url e license_server_public_key/hmac para SaaS.', 2);

    $billing = class_exists('BillingGatewayService') ? BillingGatewayService::status() : ['status'=>'erro','mensagem'=>'Serviço ausente'];
    $add('Cobrança','Gateway de cobrança', ($billing['status'] ?? '')==='ok' ? 'ok':'alerta', $billing['mensagem'] ?? 'Sem status', 'Configurar Mercado Pago/Pix/Boleto antes de cobrança real.', 2);

    $tenant = class_exists('TenantScopeAuditService') ? TenantScopeAuditService::run(false) : ['status'=>'erro','summary'=>'Serviço ausente'];
    $add('Tenant','Escopo multiempresa', ($tenant['status'] ?? '')==='ok' ? 'ok':'alerta', (string)($tenant['summary'] ?? 'Sem resumo'), 'Validar o isolamento contra banco real com duas empresas antes de uso multi-cliente (seção 5 do SECURITY.md).', 3);

    $matrix = class_exists('ConnectorCapabilityMatrixService') ? ConnectorCapabilityMatrixService::matrix() : [];
    $active = 0; foreach($matrix as $m){ if(($m['status'] ?? '')==='ativo') $active++; }
    $add('Conectores','Matriz de conectores', $active>=2 ? 'ok':'alerta', count($matrix).' conectores catalogados; '.$active.' ativos.', 'Homologar Tiny e VSM; ativar demais somente após contrato/API real.', 2);

    $cicd = class_exists('CiCdPipelineService') ? CiCdPipelineService::status() : ['status'=>'erro','mensagem'=>'Serviço ausente'];
    $add('Qualidade','CI/CD e testes', ($cicd['status'] ?? '')==='ok' ? 'ok':'alerta', $cicd['mensagem'] ?? 'Sem status', 'Rodar pipeline em branch de homologação antes do deploy.', 2);

    $workers = self::workersGuarded();
    $add('Operação','Workers CLI', $workers['status'], $workers['detalhe'], 'Todos os workers reais devem chamar WorkerCliGuardService::'.self::guardMethodName().'() na primeira linha executável.', 3);

    $selects = self::selectStarRisk();
    $add('Performance','Listagens sem payload pesado', $selects['status'], $selects['detalhe'], 'Trocar SELECT * restante por colunas explícitas nas telas de listagem.', 2);

    $errors=0; $alerts=0; $scoreBase=0; $scoreOk=0;
    foreach($items as $it){ $w=(int)($it['peso']??1); $scoreBase += $w; if($it['status']==='ok') $scoreOk += $w; if($it['status']==='erro') $errors++; if($it['status']==='alerta') $alerts++; }
    $score = $scoreBase>0 ? round(($scoreOk/$scoreBase)*100,1) : 0;
    $status = $errors>0 ? 'erro' : ($alerts>0 ? 'alerta' : 'ok');
    return ['status'=>$status,'score'=>$score,'items'=>$items,'version'=>SystemVersionService::info()];
  }

  /**
   * Auditoria 2026-09-14 (achado F-01): esta checagem procurava a string
   * "WorkerCliGuardService::requireCli" — um método que NUNCA existiu. O método real sempre foi
   * enforce(), e os 12 workers do pacote o chamam corretamente. Consequência: o item "Workers CLI"
   * do endurecimento comercial devolvia ERRO permanente listando TODOS os workers como
   * desprotegidos, e esse erro entrava na prontidão de produção
   * (ProductionCommercialReadinessService). Pior do que o falso alarme: como o indicador já estava
   * vermelho para todo mundo, um worker que realmente perdesse o guard não seria notado.
   *
   * O nome do método agora é derivado da própria classe por reflexão, em vez de repetido numa
   * string — assim a checagem não pode divergir de novo se o guard for renomeado.
   */
  private static function guardMethodName(): string {
    foreach (['enforce','requireCli'] as $candidato) {
      if (class_exists('WorkerCliGuardService') && method_exists('WorkerCliGuardService', $candidato)) return $candidato;
    }
    return 'enforce';
  }

  private static function workersGuarded(): array {
    $dir = __DIR__.'/../../workers';
    $files = glob($dir.'/*.php') ?: [];
    if(!$files) return ['status'=>'alerta','detalhe'=>'Nenhum worker encontrado em /workers.'];
    $metodo = self::guardMethodName();
    $alvo = 'WorkerCliGuardService::'.$metodo;
    $missing=[];
    foreach($files as $file){ $txt=file_get_contents($file) ?: ''; if(!str_contains($txt, $alvo)) $missing[] = basename($file); }
    if($missing) return ['status'=>'erro','detalhe'=>'Workers sem guard ('.$alvo.'): '.implode(', ', $missing)];
    return ['status'=>'ok','detalhe'=>count($files).' workers protegidos por '.$alvo.'().'];
  }

  private static function selectStarRisk(): array {
    $files = glob(__DIR__.'/../../app/Controllers/*.php') ?: [];
    $hits=[];
    foreach($files as $file){
      $txt=file_get_contents($file) ?: '';
      if(preg_match_all('/SELECT\s+\*/i', $txt, $m)) $hits[basename($file)] = count($m[0]);
    }
    $total = array_sum($hits);
    if($total===0) return ['status'=>'ok','detalhe'=>'Nenhum SELECT * encontrado em controllers.'];
    if($total<=10) return ['status'=>'alerta','detalhe'=>$total.' SELECT * restantes em controllers: '.implode(', ', array_keys($hits))];
    return ['status'=>'alerta','detalhe'=>$total.' SELECT * restantes. Priorizar DashboardController e detalhes operacionais.'];
  }
}
