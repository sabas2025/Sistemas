<?php
/**
 * V104.35 - Retry enterprise por categoria de erro.
 * Protege APIs Tiny/VSM contra retry cego e evita reprocessar erro de cadastro/fiscal sem correção humana.
 */
class QueueRetryPolicyEnterpriseService {
  public static function decide(array $item, bool $success, array $retorno = [], ?string $codigoErro = null): array {
    if ($success) return ['retry'=>false,'status'=>'sucesso','max_attempts'=>0,'delay_minutes'=>null,'classification'=>null];
    $tentativas = (int)($item['tentativas'] ?? 0);
    $class = class_exists('ErrorClassificationService') ? ErrorClassificationService::classify($codigoErro, $retorno, $retorno['message'] ?? null) : ['categoria'=>'operacional','retryable'=>1,'severidade'=>'media'];
    $retryable = !empty($class['retryable']);
    $categoria = (string)($class['categoria'] ?? 'operacional');
    $max = match($categoria) {
      'rate_limit' => 8,
      'instabilidade_api' => 6,
      'operacional' => 4,
      'autenticacao','dados_negocio','configuracao' => 1,
      default => 3,
    };
    if (!$retryable || $tentativas >= $max) {
      return ['retry'=>false,'status'=>'falha_definitiva','max_attempts'=>$max,'delay_minutes'=>null,'classification'=>$class];
    }
    $delay = match($categoria) {
      'rate_limit' => min(180, 15 * max(1, $tentativas)),
      'instabilidade_api' => min(120, 5 * (2 ** max(0, min(5, $tentativas-1)))),
      default => min(60, max(2, $tentativas * 7)),
    };
    return ['retry'=>true,'status'=>'erro','max_attempts'=>$max,'delay_minutes'=>$delay,'classification'=>$class];
  }
}
