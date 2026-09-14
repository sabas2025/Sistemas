<?php
class TinyV2ObservabilityService {
  public static function metricas(): array {
    $pdo=Database::forTable('tiny_v2_endpoint_logs');
    if(!self::tableExists('tiny_v2_endpoint_logs')) return [];
    return $pdo->query("SELECT endpoint, COUNT(*) total, SUM(sucesso=0) erros, ROUND(AVG(tempo_ms)) tempo_medio, MAX(criado_em) ultimo FROM tiny_v2_endpoint_logs GROUP BY endpoint ORDER BY total DESC LIMIT 20")->fetchAll();
  }
  public static function erros(): array {
    $pdo=Database::forTable('tiny_v2_endpoint_logs');
    if(!self::tableExists('tiny_error_catalogo')) return [];
    $st=$pdo->prepare("SELECT * FROM tiny_error_catalogo WHERE versao='v2' ORDER BY codigo");
    $st->execute();
    return $st->fetchAll();
  }
  public static function retryPolicy(string $codigo): array {
    $codigo=strtoupper($codigo);
    if(str_contains($codigo,'TOKEN') || str_contains($codigo,'AUTH')) return ['retry'=>false,'acao'=>'Corrigir credencial manualmente.'];
    if(str_contains($codigo,'RATE') || str_contains($codigo,'429')) return ['retry'=>true,'atrasos_minutos'=>[5,15,30,60],'acao'=>'Reduzir frequência e tentar novamente.'];
    if(str_contains($codigo,'TIMEOUT') || str_contains($codigo,'500') || str_contains($codigo,'503')) return ['retry'=>true,'atrasos_minutos'=>[1,5,15,30],'acao'=>'Tentar novamente com backoff.'];
    return ['retry'=>true,'atrasos_minutos'=>[1,5,15],'acao'=>'Analisar retorno Tiny e reprocessar se seguro.'];
  }
  private static function tableExists(string $table): bool { try { $st=Database::forTable('tiny_v2_endpoint_logs')->prepare('SHOW TABLES LIKE ?'); $st->execute([$table]); return (bool)$st->fetch(); } catch(Throwable $e){ return false; } }
}
