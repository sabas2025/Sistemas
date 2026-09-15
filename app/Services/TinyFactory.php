<?php
class TinyFactory {
  public static function make(): TinyClientInterface {
    $cfg = IntegrationConfig::get();
    $usarV3 = (($cfg['tiny_versao'] ?? 'v2') === 'v3') && !empty($cfg['tiny_v3_operacional']);
    if (($cfg['tiny_versao'] ?? 'v2') === 'v3' && empty($cfg['tiny_v3_operacional'])) {
      Audit::event('tiny.factory.v3_bloqueado','alerta',[
        'mensagem'=>'Tiny V3 foi selecionado, mas está marcado como não homologado/operacional. Fallback seguro para Tiny V2 aplicado.',
        'codigo_erro'=>'TINY_V3_NOT_OPERATIONAL',
        'acao_recomendada'=>'Marcar Tiny V3 como operacional somente após homologação real ou voltar configuração para Tiny V2.'
      ]);
    }
    return $usarV3 ? new TinyV3Service($cfg) : new TinyV2Service($cfg);
  }
}
