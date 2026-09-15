<?php
class ErrorClassificationService {
  public static function classify(?string $codigoErro, array $retorno = [], ?string $motivo = null): array {
    $text = strtoupper((string)$codigoErro.' '.json_encode($retorno, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).' '.$motivo);
    $base = ['categoria'=>'operacional','severidade'=>'media','retryable'=>1,'owner_area'=>'operacao','acao'=>'Abrir auditoria pelo Trace ID, conferir payload e reprocessar após correção.'];
    if (preg_match('/TOKEN|OAUTH|AUTH|UNAUTHORIZED|FORBIDDEN|401|403|SECRET|CREDENCIAL/', $text)) {
      return ['categoria'=>'autenticacao','severidade'=>'alta','retryable'=>0,'owner_area'=>'seguranca_integracao','acao'=>'Atualizar token/segredo OAuth/HMAC, validar ambiente Tiny/VSM e somente depois reprocessar.'];
    }
    if (preg_match('/RATE|429|LIMITE|THROTTLE/', $text)) {
      return ['categoria'=>'rate_limit','severidade'=>'media','retryable'=>1,'owner_area'=>'operacao','acao'=>'Aguardar janela de limite, reduzir frequência do worker e reprocessar com backoff.'];
    }
    if (preg_match('/TIMEOUT|ECONN|CONNECTION|DNS|500|502|503|504|INDISPON/', $text)) {
      return ['categoria'=>'instabilidade_api','severidade'=>'media','retryable'=>1,'owner_area'=>'infra_integracao','acao'=>'Validar disponibilidade da API destino, circuit breaker e timeout antes de reprocessar.'];
    }
    if (preg_match('/SKU|PRODUTO|CATEGORIA|VALIDA|INVALID|XML|NFE|NOTA|PEDIDO|ESTOQUE|NEGATIVE/', $text)) {
      return ['categoria'=>'dados_negocio','severidade'=>'alta','retryable'=>0,'owner_area'=>'cadastro_integracao','acao'=>'Corrigir cadastro/mapeamento/regra de negócio antes de reprocessar para evitar duplicidade ou fiscal incorreto.'];
    }
    if (preg_match('/CONFIG|MISSING|AUSENTE|URL|ENDPOINT|PERMISSION|PERMISSAO|DATABASE|SQL/', $text)) {
      return ['categoria'=>'configuracao','severidade'=>'alta','retryable'=>0,'owner_area'=>'administracao_sistema','acao'=>'Corrigir configuração, permissão MySQL ou endpoint antes de reprocessar.'];
    }
    return $base;
  }
}
