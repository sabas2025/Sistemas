<?php
class Logger {
  public static function log(string $tipo, string $mensagem, $payload=null, string $nivel='info', ?string $codigoErro=null): void {
    $trace = class_exists('RequestContext') ? RequestContext::id() : null;
    $nivelPermitido = ['info','alerta','erro','critico'];
    if (!in_array($nivel, $nivelPermitido, true)) $nivel = 'info';
    try {
      $pdo=Database::forTable('logs_integracao');
      $cols = self::hasTraceColumn($pdo) ? 'tipo,nivel,mensagem,payload,ip,trace_id,codigo_erro' : 'tipo,nivel,mensagem,payload,ip';
      $marks = self::hasTraceColumn($pdo) ? '?,?,?,?,?,?,?' : '?,?,?,?,?';
      $params = [$tipo,$nivel,$mensagem,json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PARTIAL_OUTPUT_ON_ERROR),(class_exists('RequestContext') ? RequestContext::ip() : ($_SERVER['REMOTE_ADDR']??null))];
      if (self::hasTraceColumn($pdo)) { $params[]=$trace; $params[]=$codigoErro; }
      // Achado C-05: a linha nasce carimbada com a empresa ativa. Sem empresa no contexto (worker,
      // cron, instalação de cliente único) o carimbo não acontece e a linha fica global, visível
      // para todos — que é exatamente o comportamento de hoje e não muda.
      $sql = "INSERT INTO logs_integracao({$cols}) VALUES({$marks})";
      if (class_exists('TenantScopeService')) {
        [$sql, $params] = TenantScopeService::applyToInsert('logs_integracao', $sql, $params);
      }
      $st=$pdo->prepare($sql);
      $st->execute($params);
    } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }

    if (class_exists('Audit')) {
      $auditStatus = in_array($nivel, ['erro','critico'], true) ? 'erro' : ($nivel === 'alerta' ? 'alerta' : 'info');
      Audit::event($tipo, $auditStatus, ['mensagem'=>$mensagem,'payload'=>$payload,'codigo_erro'=>$codigoErro]);
    }

    self::writeFile('app', $trace, $nivel, $tipo, $mensagem, $payload);
    $map = [
      'webhook' => ['webhook','vsm.webhook','api.webhook'],
      'tiny' => ['tiny','pedido.tiny','produto.tiny'],
      'vsm' => ['vsm'],
      'worker' => ['worker','fila.processar','backup.worker'],
      'security' => ['seguranca','auth','permissao','csrf','webhook.bloqueado'],
    ];
    foreach ($map as $file=>$prefixes) {
      foreach ($prefixes as $prefix) {
        if (stripos($tipo, $prefix) !== false) { self::writeFile($file, $trace, $nivel, $tipo, $mensagem, $payload); break 2; }
      }
    }
  }

  public static function info(string $tipo, string $mensagem, $payload=null): void { self::log($tipo,$mensagem,$payload,'info'); }
  public static function alerta(string $tipo, string $mensagem, $payload=null, ?string $codigoErro=null): void { self::log($tipo,$mensagem,$payload,'alerta',$codigoErro); }
  public static function erro(string $tipo, string $mensagem, $payload=null, ?string $codigoErro=null): void { self::log($tipo,$mensagem,$payload,'erro',$codigoErro); }
  public static function critico(string $tipo, string $mensagem, $payload=null, ?string $codigoErro=null): void { self::log($tipo,$mensagem,$payload,'critico',$codigoErro); }

  private static function writeFile(string $name, ?string $trace, string $nivel, string $tipo, string $mensagem, $payload=null): void {
    $dir = __DIR__.'/../../storage/logs';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $line = '['.date('Y-m-d H:i:s')."] {$trace} {$nivel} {$tipo} - {$mensagem}";
    if ($payload !== null && in_array($nivel, ['erro','critico'], true)) {
      $line .= ' | '.substr(json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PARTIAL_OUTPUT_ON_ERROR), 0, 3000);
    }
    @file_put_contents($dir.'/'.$name.'.log', $line."
", FILE_APPEND);
  }

  private static function hasTraceColumn(PDO $pdo): bool {
    static $has=null; if($has!==null) return $has;
    // Sonda de ESTRUTURA, não leitura de dado: SHOW COLUMNS não tem empresa_id a filtrar.
    try { $r=$pdo->query("SHOW COLUMNS FROM logs_integracao LIKE 'trace_id'")->fetch(); $has=(bool)$r; } catch(Throwable $e){$has=false;}
    return $has;
  }
}
