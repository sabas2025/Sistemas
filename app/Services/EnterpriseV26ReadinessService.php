<?php
class EnterpriseV26ReadinessService {
  public static function resumo(): array {
    $hosting = HostingCompatibilityService::ambiente();
    $recovery = InstallationRecoveryService::status();
    $audit = ['total'=>0,'valida'=>false,'erros'=>['Auditoria ainda não validada']];
    try { $audit = EnterpriseAuditHashChainService::validar(); } catch(Throwable $e){ $audit=['total'=>0,'valida'=>false,'erros'=>[$e->getMessage()]]; }
    $score = round(($hosting['score'] + $recovery['score'] + ($audit['valida']?100:70)) / 3);
    return ['score'=>$score,'hosting'=>$hosting,'recovery'=>$recovery,'audit'=>$audit,'recomendacoes'=>HostingCompatibilityService::recomendações()];
  }
}
