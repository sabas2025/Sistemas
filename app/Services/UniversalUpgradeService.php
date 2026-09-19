<?php
class UniversalUpgradeService {
  public function __construct(private string $root) {}

  public function run(): array {
    $final = $this->root . '/database/install_final_v104_12.sql';
    $msg = is_file($final)
      ? 'Histórico update_vXX removido. Instalação nova deve usar database/install_final_v104_12.sql.'
      : 'Arquivo consolidado install_final_v104_12.sql não encontrado.';
    try {
      Audit::event('database.upgrade_universal.desativado','info',[
        'mensagem'=>$msg,
        'acao_recomendada'=>'Para banco antigo, use Validar Banco/Health de Módulos e aplique correções seguras por serviço. Para instalação nova, use install_final_v104_12.sql.'
      ]);
    } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    return [
      'arquivos'=>[],
      'mensagens'=>[$msg, 'Nenhum update legado foi executado.'],
      'erros'=>[],
      'finalizado_em'=>date('c'),
      // Achado I-11: a tela pedia um Trace ID que este retorno não trazia. Correlacionar a
      // execução com a Auditoria é exigência da fase 11 — e o evento acima já usa este id.
      'trace_id'=>class_exists('RequestContext') ? RequestContext::id() : null,
    ];
  }
}
