<?php
declare(strict_types=1);
/** Teste destrutivo SOMENTE em bancos novos, aleatórios e locais; nunca reutiliza banco existente. */
if (PHP_SAPI!=='cli' || getenv('HUB_R7_TEST_CONFIRM')!=='1') { fwrite(STDERR,"Confirme ambiente descartável com HUB_R7_TEST_CONFIRM=1.\n"); exit(2); }
$host=getenv('HUB_TEST_DB_HOST') ?: '127.0.0.1';
if ($host!=='127.0.0.1') { fwrite(STDERR,"Este teste só permite banco local.\n"); exit(2); }
$root=dirname(__DIR__,2);
require_once $root.'/app/Core/Autoload.php';
require_once $root.'/app/Core/Helpers.php';
class App {
  public static array $cfg=[];
  public static function config(): array { return self::$cfg; }
  public static function isLocal(): bool { return true; }
  public static function isProduction(): bool { return false; }
}
$mode=$argv[1] ?? 'single';
if (!in_array($mode,['single','modular'],true)) exit(2);
$user=getenv('HUB_TEST_DB_USER') ?: 'root'; $pass=getenv('HUB_TEST_DB_PASS') ?: '';
$port=(int)(getenv('HUB_TEST_DB_PORT') ?: 3306);
$base='hub_ci_r7_upgrade_'.bin2hex(random_bytes(5));
$dbHost=$host.';port='.$port;
$admin=new PDO('mysql:host='.$host.';port='.$port,$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$modules=['core','pedidos','produtos','estoque','fiscal','fila','observabilidade','backups'];
$created=[]; $connections=[];
try {
  foreach ($modules as $module) {
    $name=$base.($mode==='single'?'':'_'.$module);
    if (!isset($connections[$name])) {
      $admin->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
      $created[]=$name;
      $connections[$name]=new PDO('mysql:host='.$host.';port='.$port.';dbname='.$name,$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    }
    $settings=['host'=>$dbHost,'name'=>$name,'user'=>$user,'pass'=>$pass,'charset'=>'utf8mb4'];
    App::$cfg['db_modules'][$module]=$settings;
    if ($module==='core') App::$cfg['db']=$settings;
    $sql=(string)file_get_contents($root.'/database/modules/'.$module.'.sql');
    $sql=str_replace('  integracao_empresa_id INT NULL,','',$sql);
    $sql=strtr($sql,['{ADMIN_HASH}'=>password_hash('test-only',PASSWORD_DEFAULT),'{ADMIN_NOME}'=>'Teste','{ADMIN_EMAIL}'=>'test@example.invalid','{AMBIENTE}'=>'homologacao','{TINY_VERSION}'=>'v2','{TINY_V2_TOKEN}'=>'','{TINY_V3_TOKEN}'=>'','{VSM_TOKEN}'=>'','{WEBHOOK_SECRET}'=>'']);
    foreach (UpgradeSqlService::split($sql) as $statement) {
      if (preg_match('/^CREATE TABLE IF NOT EXISTS myouro_conexoes\b/i',$statement)) continue;
      UpgradeSqlService::execute($connections[$name],$statement);
    }
  }
  App::$cfg['db_storage_mode']=$mode; App::$cfg['db_modular_strict']=$mode==='modular';
  App::$cfg['security']['encryption_key']=bin2hex(random_bytes(32));
  $fila=Database::forTable('fila_integracao');
  $fila->exec("INSERT INTO fila_integracao(tipo,payload,status,empresa_id) VALUES('teste','{}','pendente',NULL),('teste','{}','pendente',2),('teste','{}','pendente',1)");
  $fila->exec('ALTER TABLE fila_integracao MODIFY id INT NOT NULL AUTO_INCREMENT');
  $fila->exec('DROP INDEX idx_ie_fila ON integration_events');
  $fila->exec('CREATE INDEX idx_fila_ready ON fila_integracao(status,proxima_tentativa,prioridade,id)');
  $core=Database::connection('core');
  $core->exec("UPDATE configuracoes_integracao SET vsm_token='preservar-token-existente' WHERE id=1");
  $before=(int)$fila->query('SELECT COUNT(*) FROM fila_integracao')->fetchColumn();
  $plan=R7UpgradeService::plan(); R7UpgradeService::preflight();
  if ($before!==(int)$fila->query('SELECT COUNT(*) FROM fila_integracao')->fetchColumn()) throw new RuntimeException('Simulação alterou linhas.');
  R7UpgradeService::apply($plan);
  R7UpgradeService::apply($plan);
  if ((int)$fila->query('SELECT COUNT(*) FROM fila_integracao WHERE empresa_id IS NULL')->fetchColumn()!==1) throw new RuntimeException('Legado foi atribuído sem autorização.');
  if ($core->query('SELECT vsm_token FROM configuracoes_integracao WHERE id=1')->fetchColumn()!=='preservar-token-existente') throw new RuntimeException('Token legado foi alterado.');
  $bigint=R7UpgradeService::plan('bigint'); R7UpgradeService::apply($bigint); R7UpgradeService::apply($bigint);
  // Exclusão mútua em outra conexão, não apenas uma busca textual por GET_LOCK.
  $db=Database::moduleConfig(App::config(),'core');
  $lock='hub:r7:'.substr(hash('sha256',$db['host'].'|'.$db['name']),0,48);
  $locker=new PDO('mysql:host='.$host.';port='.$port.';dbname='.$db['name'],$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $st=$locker->prepare('SELECT GET_LOCK(?,0)');$st->execute([$lock]);$st->closeCursor();
  $blocked=false;try { R7UpgradeService::apply($plan); } catch (RuntimeException $e) { $blocked=str_contains($e->getMessage(),'Outro upgrade'); }
  $st=$locker->prepare('SELECT RELEASE_LOCK(?)');$st->execute([$lock]);$st->closeCursor();
  if (!$blocked) throw new RuntimeException('Upgrade concorrente não bloqueado.');
  // Falha parcial após DDL: registro fica em falha e a reaplicação idempotente pode concluir.
  $partial=array_values(array_filter($plan,static fn($s)=>$s['id']==='017'));
  $core->exec("UPDATE schema_migrations SET status='falha' WHERE migration='r7build20260917_017'");
  $partial[0]['statements'][]='SELECT * FROM tabela_que_nao_existe_no_teste_r7';
  $failed=false;try { R7UpgradeService::apply($partial); } catch (PDOException $e) { $failed=true; }
  if (!$failed || $core->query("SELECT status FROM schema_migrations WHERE migration='r7build20260917_017'")->fetchColumn()!=='falha') throw new RuntimeException('Falha parcial não registrada.');
  R7UpgradeService::apply($plan);
  $core->exec('UPDATE configuracoes_integracao SET integracao_empresa_id=1 WHERE id=1');
  if (IntegrationTenantService::boundEmpresaId()!==1) throw new RuntimeException('Vínculo não resolvido.');
  MyOuroConfigService::save(1,['codigo_loja'=>1,'token'=>'runtime-test-only','habilitado'=>1]);
  $saved=MyOuroConfigService::get(1);
  if (!CryptoService::isEncrypted($saved['token_encrypted']) || CryptoService::decrypt($saved['token_encrypted'],'myouro:1')!=='runtime-test-only') throw new RuntimeException('Criptografia não validada.');
  MyOuroConfigService::save(1,['codigo_loja'=>1,'token'=>'','habilitado'=>1]);
  if (MyOuroConfigService::get(1)['token_encrypted']!==$saved['token_encrypted']) throw new RuntimeException('Token não preservado.');
  [$sql,$params]=TenantScopeService::applyToSelect('fila_integracao',"SELECT * FROM fila_integracao WHERE tipo=? OR tipo=?",['teste','outro']);
  $st=$fila->prepare($sql);$st->execute($params);$rows=$st->fetchAll();$st->closeCursor();
  if (count($rows)!==1 || (int)$rows[0]['empresa_id']!==1) throw new RuntimeException('Escopo OR/NULL falhou em PDO real.');
  $core->exec("INSERT INTO empresas(id,nome) VALUES(2,'Outra empresa')");
  $blocked=false;try { IntegrationTenantService::boundEmpresaId(); } catch (RuntimeException $e) { $blocked=true; }
  if (!$blocked) throw new RuntimeException('Multicliente deveria estar bloqueado.');
  echo '[OK] R7 '.$mode.': simulação, migrations 008–017 repetidas, BIGINT, lock, falha parcial, NULL, tokens, criptografia, escopo OR e bloqueio multicliente em banco real.'.PHP_EOL;
} finally {
  // Somente nomes recém-criados nesta execução; nenhum banco preexistente é apagado.
  foreach (array_reverse($created) as $name) $admin->exec('DROP DATABASE `'.$name.'`');
}
