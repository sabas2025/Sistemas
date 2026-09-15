<?php
class DiagnosticoApiService {
  public static function registrar(string $sistema, ?string $endpoint, string $status, ?int $httpCode, ?int $tempoMs, string $mensagem, $detalhes=null): void {
    try {
      if (!in_array($status, ['online','atencao','erro'], true)) $status = 'atencao';
      $pdo = Database::forTable('diagnostico_api');
      $st = $pdo->prepare('INSERT INTO diagnostico_api(sistema,endpoint,status,http_code,tempo_ms,mensagem,detalhes,trace_id) VALUES(?,?,?,?,?,?,?,?)');
      $st->execute([
        $sistema,
        $endpoint,
        $status,
        $httpCode,
        $tempoMs,
        $mensagem,
        json_encode($detalhes, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PARTIAL_OUTPUT_ON_ERROR),
        RequestContext::id()
      ]);
    } catch (Throwable $e) {
      Logger::erro('diagnostico.registrar.erro', 'Falha ao gravar histórico de diagnóstico: '.$e->getMessage(), ['sistema'=>$sistema,'endpoint'=>$endpoint], 'DIAGNOSTICO_SAVE_ERROR');
    }
  }

  public static function ultimos(int $limite=30): array {
    try {
      $pdo = Database::forTable('diagnostico_api');
      $st = $pdo->prepare('SELECT * FROM diagnostico_api ORDER BY id DESC LIMIT ?');
      $st->bindValue(1, max(1,min(200,$limite)), PDO::PARAM_INT);
      $st->execute();
      return $st->fetchAll();
    } catch (Throwable $e) { return []; }
  }

  public static function ultimoPorSistema(string $sistema): ?array {
    try {
      $pdo = Database::forTable('diagnostico_api');
      $st = $pdo->prepare('SELECT * FROM diagnostico_api WHERE sistema=? ORDER BY id DESC LIMIT 1');
      $st->execute([$sistema]);
      $r = $st->fetch();
      return $r ?: null;
    } catch (Throwable $e) { return null; }
  }
}
