<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks=[];

// Serviços puros reais.
require_once hub_root().'/app/Services/SensitiveDataService.php';
require_once hub_root().'/app/Services/IntegrationSourceOfTruthService.php';
require_once hub_root().'/app/Services/VsmOpenApiContractService.php';
// Melhoria 7: LoginRateLimitFallbackService passou a delegar o armazenamento ao contador atômico
// através da fachada RateLimitService, então estas três classes vêm junto.
require_once hub_root().'/app/Services/AtomicRateCounterService.php';
require_once hub_root().'/app/Services/SecurityHealthService.php';
require_once hub_root().'/app/Services/RateLimitService.php';
require_once hub_root().'/app/Services/LoginRateLimitFallbackService.php';

$masked=SensitiveDataService::sanitizeForStorage(['email'=>'cliente@example.com','cpf'=>'12345678901','telefone'=>'41999998888','token'=>'segredo','normal'=>'ok']);
hub_check($checks,'Redaction remove e-mail/CPF/telefone/token',!str_contains($masked,'cliente@example.com')&&!str_contains($masked,'12345678901')&&!str_contains($masked,'41999998888')&&!str_contains($masked,'segredo')&&str_contains($masked,'"normal":"ok"'));

$contract=VsmOpenApiContractService::validate(VsmOpenApiContractService::parse(hub_read('tests/fixtures/vsm/openapi-minimal.json')));
hub_check($checks,'Validador OpenAPI executa documento real',!empty($contract['ok'])&&count($contract['operations']??[])===2&&($contract['version']??'')==='3.0.3');
hub_check($checks,'Source of truth de estoque é VSM',IntegrationSourceOfTruthService::isAuthoritative('estoque','vsm'));
hub_check($checks,'Source of truth de pedido é Tiny',IntegrationSourceOfTruthService::isAuthoritative('pedidos','tiny'));

$unique='test-'.bin2hex(random_bytes(5)).'@example.invalid';$ip='198.51.100.'.random_int(1,200);
LoginRateLimitFallbackService::record($unique,$ip,false);
hub_check($checks,'Rate limit degradado ainda não bloqueia antes do limite',!LoginRateLimitFallbackService::isLimited($unique,$ip,['ip_minute'=>2,'ip_hour'=>20,'user_minute'=>2,'user_hour'=>10]));
LoginRateLimitFallbackService::record($unique,$ip,false);
hub_check($checks,'Rate limit degradado bloqueia no limite',LoginRateLimitFallbackService::isLimited($unique,$ip,['ip_minute'=>2,'ip_hour'=>20,'user_minute'=>2,'user_hour'=>10]));
LoginRateLimitFallbackService::record($unique,$ip,true);
hub_check($checks,'Login bem-sucedido limpa fallback',!LoginRateLimitFallbackService::isLimited($unique,$ip,['ip_minute'=>2,'ip_hour'=>20,'user_minute'=>2,'user_hour'=>10]));

// Ambiente isolado para validar posse/concorrência da fila sem driver PDO.
class App { public static array $cfg=['installation_id'=>'inst-a','security'=>['queue_processing_timeout_minutes'=>30,'queue_lease_minutes'=>5,'queue_lease_minutes_by_type'=>['pedido_tiny_para_vsm'=>7]],'enterprise'=>[]]; public static function config():array{return self::$cfg;} }
class IntegrationConfig { public static function get():array{return [];} }
class RequestContext { public static function id():string{return 'TRC-TEST';} }
class Auth { public static function user():array{return ['id'=>99];} }
class Audit { public static array $events=[]; public static function event(...$args):void{self::$events[]=$args;} }
class JsonLogger { public static function write(...$args):void{} }
class BestEffortLogService { public static function warning(...$args):void{} }
class PayloadSnapshotService { public static int $count=0; public static function registrar(...$args):void{self::$count++;} }
class IntegrationEventService { public static function onQueueFinished(...$args):void{} public static function onQueueClaimed(...$args):void{} }

class QueueFakeStatement extends PDOStatement {
  public array $result=[]; private int $count=0;
  public function __construct(private QueueFakePDO $db, private string $sql) {}
  public function execute(?array $params=null): bool {
    $params=$params??[];$this->result=[];$this->count=0;$sql=$this->sql;
    if(str_starts_with($sql,'SELECT * FROM fila_integracao WHERE id=?')){$row=$this->db->rows[(int)$params[0]]??null;if($row)$this->result=[$row];return true;}
    if(str_starts_with($sql,'UPDATE fila_integracao SET processando_desde=NOW()')){
      [$id,$owner]=$params;$row=$this->db->rows[(int)$id]??null;
      if($row&&$row['status']==='processando'&&$row['locked_by']===$owner){$this->db->rows[(int)$id]['heartbeat_at']=date('Y-m-d H:i:s');$this->count=1;}return true;
    }
    if(str_starts_with($sql,'UPDATE fila_integracao SET status=?')){
      $id=(int)$params[count($params)-2];$owner=(string)$params[count($params)-1];$row=$this->db->rows[$id]??null;
      if($row&&$row['status']==='processando'&&$row['locked_by']===$owner){$this->db->rows[$id]['status']=(string)$params[0];$this->db->rows[$id]['locked_by']=null;$this->count=1;}return true;
    }
    if(str_starts_with($sql,'INSERT INTO fila_reprocessamento_historico')){$this->db->history[]=$params;$this->count=1;return true;}
    if(str_starts_with($sql,"UPDATE fila_integracao SET status='pendente'")){
      [$id,$status]=$params;$row=$this->db->rows[(int)$id]??null;
      if($row&&$row['status']===$status){$this->db->rows[(int)$id]['status']='pendente';$this->db->rows[(int)$id]['tentativas']=0;$this->db->rows[(int)$id]['locked_by']=null;$this->count=1;}return true;
    }
    return true;
  }
  public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0): mixed {return array_shift($this->result)?:false;}
  public function rowCount(): int{return $this->count;}
}
class QueueFakePDO extends PDO {
  public array $rows=[];public array $history=[];private bool $tx=false;
  public function __construct(){}
  public function prepare(string $query,array $options=[]): PDOStatement|false{return new QueueFakeStatement($this,$query);}
  public function beginTransaction():bool{$this->tx=true;return true;}
  public function commit():bool{$this->tx=false;return true;}
  public function rollBack():bool{$this->tx=false;return true;}
  public function inTransaction():bool{return $this->tx;}
}
class Database {
  public static QueueFakePDO $pdo;
  public static function forTable(string $table):PDO{return self::$pdo;}
  public static function columnExists(string $table,string $column):bool{return true;}
  public static function tableExists(string $table):bool{return true;}
}
Database::$pdo=new QueueFakePDO();
class TenantContextService { public static function currentEmpresaId(): ?int { return 1; } }
require_once hub_root().'/app/Services/TenantScopeService.php';
require_once hub_root().'/app/Services/QueueService.php';
$future=date('Y-m-d H:i:s',time()+600);$now=date('Y-m-d H:i:s');$past=date('Y-m-d H:i:s',time()-3600);
Database::$pdo->rows[1]=['id'=>1,'status'=>'processando','locked_by'=>'owner-a','lease_expires_at'=>$future,'heartbeat_at'=>$now,'processando_desde'=>$now,'criado_em'=>$now,'tentativas'=>2,'tipo'=>'pedido_tiny_para_vsm','referencia'=>'P1','trace_id'=>'TRC-1'];
hub_check($checks,'Heartbeat aceita somente proprietário',QueueService::heartbeat(1,'owner-a','pedido_tiny_para_vsm')&&!QueueService::heartbeat(1,'owner-b','pedido_tiny_para_vsm'));
hub_check($checks,'Resultado de worker obsoleto é rejeitado',!QueueService::marcarResultado(1,true,['ok'=>1],null,'owner-b'));
hub_check($checks,'Resultado do proprietário é persistido',QueueService::marcarResultado(1,true,['ok'=>1],null,'owner-a')&&Database::$pdo->rows[1]['status']==='sucesso'&&PayloadSnapshotService::$count===1);
Database::$pdo->rows[2]=['id'=>2,'status'=>'processando','locked_by'=>'owner-active','lease_expires_at'=>$future,'heartbeat_at'=>$now,'processando_desde'=>$now,'criado_em'=>$now,'tentativas'=>4,'codigo_erro'=>'TIMEOUT','retorno'=>'x'];
hub_check($checks,'Reprocessamento bloqueia item ativo',!QueueService::reprocessar(2));
Database::$pdo->rows[2]['empresa_id']=1;
Database::$pdo->rows[2]['lease_expires_at']=$past;Database::$pdo->rows[2]['heartbeat_at']=$past;Database::$pdo->rows[2]['processando_desde']=$past;
hub_check($checks,'Reprocessamento libera item expirado e preserva histórico',QueueService::reprocessar(2)&&Database::$pdo->rows[2]['status']==='pendente'&&Database::$pdo->rows[2]['tentativas']===0&&count(Database::$pdo->history)===1);

require_once hub_root().'/app/Services/TinyV3TokenService.php';
$method=new ReflectionMethod(TinyV3TokenService::class,'refreshLockName');$method->setAccessible(true);
$nameA=$method->invoke(null,'homologacao');App::$cfg['installation_id']='inst-b';$nameB=$method->invoke(null,'homologacao');
hub_check($checks,'Lock OAuth muda por instalação e respeita 64 caracteres',$nameA!==$nameB&&strlen($nameA)<=64&&strlen($nameB)<=64);

hub_finish($checks);
