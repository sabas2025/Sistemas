<?php
/**
 * Testes de regressão leves, sem dependência de PHPUnit.
 * Validam pré-condições críticas após atualização em hospedagem compartilhada.
 */
class EnterpriseRegressionTestService {
  public static function run(): array {
    $tests = [];
    $add = function(string $key, string $label, callable $fn) use (&$tests) {
      $start = microtime(true);
      try {
        $res = $fn();
        $skip = (bool)($res['skip'] ?? false);
        $ok = !$skip && (bool)($res['ok'] ?? false);
        $status = $skip ? 'skip' : ($ok ? 'ok' : 'erro');
        $tests[] = ['key'=>$key,'label'=>$label,'status'=>$status,'message'=>(string)($res['message'] ?? ($skip?'Não executado':($ok?'OK':'Falhou'))),'duration_ms'=>round((microtime(true)-$start)*1000,2)];
      }
      catch (Throwable $e) { $tests[] = ['key'=>$key,'label'=>$label,'status'=>'erro','message'=>$e->getMessage(),'duration_ms'=>round((microtime(true)-$start)*1000,2)]; }
    };
    $add('php_version','PHP compatível', fn()=>['ok'=>version_compare(PHP_VERSION,'8.0.0','>='),'message'=>'PHP '.PHP_VERSION]);
    $mysqlAvailable = in_array('mysql', PDO::getAvailableDrivers(), true);
    $dbSkip = ['skip'=>true,'message'=>'Driver pdo_mysql indisponível neste ambiente; execute o gate em homologação com MySQL.'];
    $add('database_connection','Conexão MySQL', function() use ($mysqlAvailable,$dbSkip){
      if (!$mysqlAvailable) return $dbSkip;
      $pdo=Database::getConnection(); $pdo->query('SELECT 1'); $db=Database::currentDatabaseName($pdo);
      return ['ok'=>true,'message'=>'Conexão OK — banco: '.($db ?: 'não identificado')];
    });
    $required = ['usuarios','configuracoes_integracao','fila_integracao','fila_morta','schema_migrations','integration_events','integration_idempotency','worker_heartbeats','observability_snapshots'];
    foreach ($required as $table) $add('table_'.$table,'Tabela '.$table, function() use ($table,$mysqlAvailable,$dbSkip) {
      if (!$mysqlAvailable) return $dbSkip;
      $pdo = Database::forTable($table);
      $db = Database::currentDatabaseName($pdo);
      $exists = Database::tableExistsOn($pdo, $table);
      return ['ok'=>$exists,'message'=>$exists ? 'Tabela disponível no banco '.($db ?: 'atual') : 'Tabela ausente no banco '.($db ?: 'não identificado').'; execute Aplicar Enterprise Core'];
    });
    $add('queue_status_enum','Fila suporta status ignorado', function() use ($mysqlAvailable,$dbSkip){
      if (!$mysqlAvailable) return $dbSkip;
      try { $col = TenantScopeService::run('fila_integracao', "SHOW COLUMNS FROM fila_integracao LIKE 'status'")->fetch(); $type = (string)($col['Type'] ?? ''); return ['ok'=>str_contains($type, 'ignorado'),'message'=>$type ?: 'Não identificado']; }
      catch(Throwable $e){ return ['ok'=>false,'message'=>$e->getMessage()]; }
    });
    $add('pwa_version','PWA responsivo V104.37+', function(){ $sw = @file_get_contents(__DIR__.'/../../public/sw.js') ?: ''; $version = preg_match("/const\s+HUB_VERSION\s*=\s*['\"]([0-9]+\.[0-9]+\.[0-9]+)['\"]/", $sw, $m) ? ($m[1] ?? '') : ''; $ok = $version !== '' && version_compare($version,'104.37.0','>='); return ['ok'=>$ok,'message'=>$ok?'Cache responsivo atualizado: '.$version:'Versão do service worker ausente ou inferior a 104.37.0']; });
    $ok = count(array_filter($tests, fn($t)=>$t['status']==='ok'));
    $erro = count(array_filter($tests, fn($t)=>$t['status']==='erro'));
    $skip = count(array_filter($tests, fn($t)=>$t['status']==='skip'));
    $executed = $ok + $erro;
    $result = ['trace_id'=>class_exists('RequestContext')?RequestContext::id():null,'total'=>count($tests),'executed'=>$executed,'ok'=>$ok,'erro'=>$erro,'skip'=>$skip,'score'=>$executed?round(($ok/$executed)*100):0,'tests'=>$tests];
    self::persist($result);
    return $result;
  }

  private static function persist(array $result): void {
    try {
      if (!Database::tableExists('enterprise_regression_runs')) return;
      Database::forTable('enterprise_regression_runs')->prepare('INSERT INTO enterprise_regression_runs(trace_id,score,total,ok_count,error_count,results_json) VALUES(?,?,?,?,?,?)')
        ->execute([$result['trace_id'] ?? null,(int)($result['score'] ?? 0),(int)($result['total'] ?? 0),(int)($result['ok'] ?? 0),(int)($result['erro'] ?? 0),json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
    } catch (Throwable $e) { try { Audit::exception($e, 'enterprise_regression.persist.error'); } catch(Throwable $ignored){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $ignored); } }
  }
}
