<?php
class Audit {
  public static function event(string $acao, string $status='sucesso', array $data=[]): string {
    $trace = $data['trace_id'] ?? RequestContext::id();
    $nivel = $data['nivel'] ?? ($status === 'erro' ? 'erro' : ($status === 'alerta' ? 'alerta' : 'info'));
    $codigo = $data['codigo_erro'] ?? null;
    $exp = $codigo ? ErrorCatalog::explain($codigo, $data['mensagem'] ?? '') : null;
    $payload = $data['payload'] ?? null;
    $retorno = $data['retorno'] ?? null;
    $contexto = $data['contexto'] ?? [];
    $contexto = array_merge([
      'method'=>RequestContext::method(), 'route'=>RequestContext::route(), 'user_agent'=>RequestContext::userAgent()
    ], $contexto);
    try {
      $pdo = Database::forTable('auditoria_eventos');
      $st = $pdo->prepare("INSERT INTO auditoria_eventos(trace_id, usuario_id, acao, entidade, entidade_id, status, nivel, codigo_erro, mensagem, causa_provavel, acao_recomendada, payload, retorno, contexto, ip) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
      $st->execute([
        $trace, RequestContext::userId(), $acao, $data['entidade'] ?? null, $data['entidade_id'] ?? null, $status, $nivel, $codigo,
        $data['mensagem'] ?? $acao, $exp['causa'] ?? null, $exp['acao'] ?? null,
        self::json($payload), self::json($retorno), self::json($contexto), RequestContext::ip()
      ]);
      if (class_exists('AuditTimelineService')) { AuditTimelineService::registrar($acao, $status, ['entidade'=>$data['entidade'] ?? null, 'entidade_id'=>$data['entidade_id'] ?? null, 'mensagem'=>$data['mensagem'] ?? $acao]); }
      if (class_exists('AuditIntegrityService')) { AuditIntegrityService::assinarEvento((int)$pdo->lastInsertId()); }
      if (str_starts_with($acao, 'auth.') && class_exists('SecurityAuditService')) { SecurityAuditService::record($acao, $nivel, ['status'=>$status, 'mensagem'=>$data['mensagem'] ?? $acao]); }
    } catch(Throwable $e) {
      $dir = __DIR__.'/../../storage/logs';
      if (!is_dir($dir)) @mkdir($dir, 0770, true);
      @file_put_contents($dir.'/audit-fallback.log', '['.date('Y-m-d H:i:s')."] {$trace} {$acao} {$status} {$e->getMessage()}\n", FILE_APPEND|LOCK_EX);
    }
    return $trace;
  }
  public static function exception(Throwable $e, string $acao='exception', array $extra=[]): string {
    return self::event($acao, 'erro', array_merge($extra, [
      'codigo_erro'=>$extra['codigo_erro'] ?? 'UNHANDLED_EXCEPTION',
      'mensagem'=>$e->getMessage(),
      'contexto'=>array_merge($extra['contexto'] ?? [], ['arquivo'=>$e->getFile(), 'linha'=>$e->getLine(), 'trace'=>$e->getTraceAsString()])
    ]));
  }
  public static function json($v): ?string {
    if ($v === null || $v === '') return null;
    return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PARTIAL_OUTPUT_ON_ERROR);
  }
}
