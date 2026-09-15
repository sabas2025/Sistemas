<?php
class VsmFichaTecnicaService {
  public static function endpoints(): array {
    $pdo = Database::forTable('vsm_endpoint_catalogo');
    if(!self::tableExists('vsm_endpoint_catalogo')) return [];
    return $pdo->query("SELECT * FROM vsm_endpoint_catalogo ORDER BY ambiente, nome")->fetchAll();
  }
  public static function metricas(): array {
    $pdo = Database::forTable('vsm_endpoint_catalogo');
    if(!self::tableExists('vsm_endpoint_metricas')) return [];
    return $pdo->query("SELECT endpoint, COUNT(*) total, ROUND(AVG(tempo_ms)) tempo_medio, SUM(status='erro') erros, MAX(criado_em) ultimo_teste FROM vsm_endpoint_metricas GROUP BY endpoint ORDER BY endpoint")->fetchAll();
  }
  public static function payloads(): array {
    $pdo = Database::forTable('vsm_endpoint_catalogo');
    if(!self::tableExists('vsm_payload_catalogo')) return [];
    return $pdo->query("SELECT * FROM vsm_payload_catalogo ORDER BY tipo_evento, versao DESC, id DESC LIMIT 100")->fetchAll();
  }
  public static function registrarEndpoint(string $nome,string $metodo,string $endpoint,string $tipo='estoque',string $ambiente='homologacao'): void {
    if(!self::tableExists('vsm_endpoint_catalogo')) return;
    $pdo=Database::forTable('vsm_endpoint_catalogo');
    $st=$pdo->prepare("INSERT INTO vsm_endpoint_catalogo(nome,tipo,metodo,endpoint,ambiente,status) VALUES(?,?,?,?,?,'pendente') ON DUPLICATE KEY UPDATE metodo=VALUES(metodo), endpoint=VALUES(endpoint), tipo=VALUES(tipo)");
    $st->execute([$nome,$tipo,$metodo,$endpoint,$ambiente]);
  }
  /* Achado I-10: `SHOW ... LIKE ?` é INVÁLIDO com prepares nativos (o Hub usa EMULATE_PREPARES=false): o servidor recusa com "near '?'". O catch rebaixava isso a "tabela ausente", e a tabela existia. Use sempre o helper central. */
  private static function tableExists(string $table): bool { return Database::tableExists($table); }
}
