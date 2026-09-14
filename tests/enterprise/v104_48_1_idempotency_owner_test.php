<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks=[];
class GuardStatement extends PDOStatement {
  private int $count=0;
  public function __construct(private GuardPDO $pdo,private string $sql){}
  public function execute(?array $params=null):bool{
    $params=$params??[];$this->count=0;
    if(str_starts_with($this->sql,'UPDATE fila_integracao SET')){
      $id=(int)$params[count($params)-($this->hasOwner()?2:1)];$owner=$this->hasOwner()?(string)$params[count($params)-1]:'';
      $row=$this->pdo->rows[$id]??null;
      if($row&&$row['status']==='processando'&&(!$this->hasOwner()||hash_equals((string)$row['locked_by'],$owner))){
        $this->pdo->rows[$id]['status']=str_contains($this->sql,"status='ignorado'")?'ignorado':(string)$params[0];
        $this->pdo->rows[$id]['locked_by']=null;$this->count=1;
      }
    }
    return true;
  }
  private function hasOwner():bool{return str_contains($this->sql,'AND locked_by=?');}
  public function rowCount():int{return $this->count;}
}
class GuardPDO extends PDO { public array $rows=[];public function __construct(){} public function prepare(string $query,array $options=[]):PDOStatement|false{return new GuardStatement($this,$query);} }
class Database { public static GuardPDO $pdo;public static function forTable(string $table):PDO{return self::$pdo;}public static function columnExists(string $table,string $column):bool{return true;} }
class BestEffortLogService { public static array $warnings=[];public static function warning(...$args):void{self::$warnings[]=$args;} }
class Audit { public static array $events=[];public static function event(...$args):void{self::$events[]=$args;}public static function exception(...$args):void{} }
class IntegrationEventService { public static int $ignored=0;public static function onQueueIgnored(...$args):void{self::$ignored++;} }
class DeadLetterQueueService { public static int $sent=0;public static function enviar(...$args):void{self::$sent++;} }
class RequestContext { public static function id():string{return 'TRC-GUARD';} }
Database::$pdo=new GuardPDO();
require_once hub_root().'/app/Services/EnterpriseIdempotencyGuardService.php';
Database::$pdo->rows[1]=['status'=>'processando','locked_by'=>'owner-current'];
EnterpriseIdempotencyGuardService::markIgnoredQueueItem(['id'=>1,'locked_by'=>'owner-stale'],['reason'=>'duplicate']);
hub_check($checks,'Worker obsoleto não consegue marcar item ignorado',Database::$pdo->rows[1]['status']==='processando'&&IntegrationEventService::$ignored===0&&count(Audit::$events)===0);
EnterpriseIdempotencyGuardService::markIgnoredQueueItem(['id'=>1,'locked_by'=>'owner-current'],['reason'=>'duplicate']);
hub_check($checks,'Proprietário atual consegue concluir item ignorado',Database::$pdo->rows[1]['status']==='ignorado'&&IntegrationEventService::$ignored===1&&count(Audit::$events)===1);
Database::$pdo->rows[2]=['status'=>'processando','locked_by'=>'owner-current'];
EnterpriseIdempotencyGuardService::markGuardFailureQueueItem(['id'=>2,'locked_by'=>'owner-stale'],['policy'=>'dlq']);
hub_check($checks,'Worker obsoleto não envia item para DLQ',Database::$pdo->rows[2]['status']==='processando'&&DeadLetterQueueService::$sent===0);
EnterpriseIdempotencyGuardService::markGuardFailureQueueItem(['id'=>2,'locked_by'=>'owner-current'],['policy'=>'dlq']);
hub_check($checks,'Somente proprietário envia falha idempotente para DLQ',Database::$pdo->rows[2]['status']==='falha_definitiva'&&DeadLetterQueueService::$sent===1);
hub_finish($checks);
