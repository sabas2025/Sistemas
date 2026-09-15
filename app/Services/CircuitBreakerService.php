<?php
class CircuitBreakerService {
  private static function policy(string $sistema): array {
    $s = strtolower($sistema);
    $base = ['threshold'=>5, 'open_minutes'=>5, 'max_open_minutes'=>60];
    if (str_contains($s,'vsm')) return ['threshold'=>4, 'open_minutes'=>5, 'max_open_minutes'=>45];
    if (str_contains($s,'tiny_v3')) return ['threshold'=>5, 'open_minutes'=>5, 'max_open_minutes'=>60];
    if (str_contains($s,'tiny')) return ['threshold'=>5, 'open_minutes'=>5, 'max_open_minutes'=>60];
    return $base;
  }

  public static function permitir(string $sistema): bool {
    $pdo = Database::forTable('circuit_breakers');
    $st=$pdo->prepare('SELECT id, sistema, status, falhas_consecutivas, aberto_ate, atualizado_em FROM circuit_breakers WHERE sistema=? LIMIT 1'); $st->execute([$sistema]); $cb=$st->fetch();
    if(!$cb) { $pdo->prepare('INSERT IGNORE INTO circuit_breakers(sistema,status) VALUES(?,"fechado")')->execute([$sistema]); return true; }
    if($cb['status']==='aberto' && !empty($cb['aberto_ate']) && strtotime($cb['aberto_ate']) > time()) {
      if(class_exists('SecurityEventService')) SecurityEventService::log('circuit_breaker.bloqueio','alto','Circuit breaker aberto bloqueou chamada', ['sistema'=>$sistema,'aberto_ate'=>$cb['aberto_ate']]);
      return false;
    }
    if($cb['status']==='aberto') {
      $pdo->prepare('UPDATE circuit_breakers SET status="meio_aberto" WHERE sistema=?')->execute([$sistema]);
      if(class_exists('Audit')) Audit::event('circuit_breaker.meio_aberto','alerta',['mensagem'=>'Circuit breaker entrou em meio-aberto','contexto'=>['sistema'=>$sistema]]);
    }
    return true;
  }

  public static function sucesso(string $sistema): void {
    Database::forTable('circuit_breakers')->prepare('UPDATE circuit_breakers SET status="fechado", falhas_consecutivas=0, aberto_ate=NULL, ultimo_sucesso_em=NOW(), ultima_falha=NULL WHERE sistema=?')->execute([$sistema]);
  }

  public static function falha(string $sistema, string $mensagem): void {
    $pdo=Database::forTable('circuit_breakers');
    $pdo->prepare('INSERT IGNORE INTO circuit_breakers(sistema,status) VALUES(?,"fechado")')->execute([$sistema]);
    $transient = self::isTransientFailure($mensagem);
    if (!$transient) {
      $pdo->prepare('UPDATE circuit_breakers SET ultima_falha=? WHERE sistema=?')->execute([$mensagem,$sistema]);
      if(class_exists('Audit')) Audit::event('circuit_breaker.falha_nao_transiente','alerta',['mensagem'=>'Falha não transitória registrada sem abrir circuit breaker','contexto'=>['sistema'=>$sistema,'erro'=>$mensagem]]);
      return;
    }
    $policy = self::policy($sistema);
    $threshold = (int)$policy['threshold'];
    $pdo->prepare('UPDATE circuit_breakers SET falhas_consecutivas=falhas_consecutivas+1, ultima_falha=? WHERE sistema=?')->execute([$mensagem,$sistema]);
    $st=$pdo->prepare('SELECT id, sistema, status, falhas_consecutivas, aberto_ate, atualizado_em FROM circuit_breakers WHERE sistema=?'); $st->execute([$sistema]); $cb=$st->fetch();
    $falhas = (int)($cb['falhas_consecutivas'] ?? 0);
    if ($falhas >= $threshold) {
      $minutes = min((int)$policy['max_open_minutes'], max((int)$policy['open_minutes'], (int)$policy['open_minutes'] * (int)ceil($falhas / $threshold)));
      $pdo->prepare('UPDATE circuit_breakers SET status="aberto", aberto_ate=DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE sistema=?')->execute([$minutes,$sistema]);
      NotificationService::criar('erro_integracao','Circuit breaker aberto','O sistema '.$sistema.' teve '.$falhas.' falha(s) transitória(s) consecutiva(s) e ficará pausado por '.$minutes.' minuto(s).','erro',['trace_id'=>RequestContext::id(),'link'=>'index.php?page=security-circuit-breakers']);
      if(class_exists('SecurityEventService')) SecurityEventService::log('circuit_breaker.aberto','alto','Circuit breaker aberto por falhas transitórias', ['sistema'=>$sistema,'falhas'=>$falhas,'minutos'=>$minutes,'erro'=>$mensagem]);
    }
  }

  private static function isTransientFailure(string $mensagem): bool {
    $m = strtoupper($mensagem);
    foreach (['CURL','TIMEOUT','TIMED OUT','CONNECTION','ECONN','HTTP 429','HTTP 500','HTTP 502','HTTP 503','HTTP 504','VSM_HTTP_429','VSM_HTTP_500','VSM_HTTP_502','VSM_HTTP_503','VSM_HTTP_504','INVALID_JSON'] as $needle) {
      if (str_contains($m, $needle)) return true;
    }
    foreach (['BUSINESS_ERROR','VALIDATION','HTTP 400','HTTP 401','HTTP 403','TOKEN_MISSING','NOT_FOUND','SKU_NOT_FOUND'] as $permanent) {
      if (str_contains($m, $permanent)) return false;
    }
    return true;
  }
}
