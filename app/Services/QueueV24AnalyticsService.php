<?php
class QueueV24AnalyticsService {
  public static function resumoAvancado(): array {
    $pdo=Database::forTable('fila_integracao');
    $porStatus=$pdo->query("SELECT status, COUNT(*) total FROM fila_integracao GROUP BY status")->fetchAll();
    $porPrioridade=self::hasColumn('fila_integracao','prioridade') ? $pdo->query("SELECT prioridade, status, COUNT(*) total FROM fila_integracao GROUP BY prioridade,status ORDER BY FIELD(prioridade,'critica','alta','normal','baixa'),status")->fetchAll() : [];
    $topErros=self::tableExists('fila_morta') ? $pdo->query("SELECT COALESCE(codigo_erro,'SEM_CODIGO') codigo, COUNT(*) total FROM fila_morta GROUP BY COALESCE(codigo_erro,'SEM_CODIGO') ORDER BY total DESC LIMIT 10")->fetchAll() : [];
    $topTipos=$pdo->query("SELECT tipo, COUNT(*) total FROM fila_integracao GROUP BY tipo ORDER BY total DESC LIMIT 10")->fetchAll();
    $tempo=$pdo->query("SELECT ROUND(AVG(TIMESTAMPDIFF(SECOND, criado_em, COALESCE(processado_em,NOW())))) tempo_medio_seg FROM fila_integracao WHERE processado_em IS NOT NULL")->fetch();
    return compact('porStatus','porPrioridade','topErros','topTipos','tempo');
  }
  public static function snapshot(): void {
    if(!self::tableExists('fila_analytics_snapshots')) return;
    $pdo=Database::forTable('fila_integracao');
    $dados=self::resumoAvancado();
    $st=$pdo->prepare('INSERT INTO fila_analytics_snapshots(snapshot_json,trace_id) VALUES(?,?)');
    $st->execute([json_encode($dados,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),RequestContext::id()]);
  }
  /* Achado I-10: `SHOW ... LIKE ?` é INVÁLIDO com prepares nativos (o Hub usa EMULATE_PREPARES=false): o servidor recusa com "near '?'". O catch rebaixava isso a "tabela ausente", e a tabela existia. Use sempre o helper central. */
  private static function tableExists(string $table): bool { return Database::tableExists($table); }
  private static function hasColumn(string $table,string $column): bool { return Database::columnExists($table, $column); }
}
