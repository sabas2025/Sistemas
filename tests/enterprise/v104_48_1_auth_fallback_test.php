<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks=[];
class FailingStatement extends PDOStatement { public function __construct(){} public function execute(?array $params=null):bool{throw new PDOException('db offline');} }
class FailingPDO extends PDO { public function __construct(){} public function prepare(string $query,array $options=[]):PDOStatement|false{return new FailingStatement();} }
class Database { public static function forTable(string $table):PDO{return new FailingPDO();} }
class LoginRateLimitFallbackService { public static array $calls=[]; public static function record(string $email,?string $ip,bool $success):void{self::$calls[]=[$email,$ip,$success];} }
class BestEffortLogService { public static array $calls=[]; public static function warning(string $where,Throwable $e,array $context=[]):void{self::$calls[]=[$where,get_class($e),$context];} }
require_once hub_root().'/app/Services/AuthRepository.php';
$thrown=false;try{AuthRepository::insertAttempt('User@Example.com','198.51.100.2',0,'falha');}catch(Throwable){$thrown=true;}
hub_check($checks,'Falha ao persistir tentativa de login não causa erro 500',!$thrown);
hub_check($checks,'Fallback de rate limit registra tentativa mesmo com banco indisponível',count(LoginRateLimitFallbackService::$calls)===1&&LoginRateLimitFallbackService::$calls[0][2]===false);
hub_check($checks,'Falha do banco é registrada sem expor e-mail em claro',count(BestEffortLogService::$calls)===1&&!str_contains(json_encode(BestEffortLogService::$calls),'User@Example.com')&&isset(BestEffortLogService::$calls[0][2]['email_hash']));
hub_finish($checks);
