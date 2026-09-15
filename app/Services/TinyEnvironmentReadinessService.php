<?php
class TinyEnvironmentReadinessService {
  public static function analisar(array $cfg, array $checklist=[]): array {
    $v2Token = trim((string)($cfg['tiny_v2_token'] ?? ''));
    $v3Client = trim((string)($cfg['tiny_v3_client_id'] ?? ''));
    $v3Secret = trim((string)($cfg['tiny_v3_client_secret'] ?? ''));
    $v3Redirect = trim((string)($cfg['tiny_v3_redirect_uri'] ?? ''));
    $v3Operacional = !empty($cfg['tiny_v3_operacional']);
    $v3Amb = (string)($cfg['tiny_v3_ambiente'] ?? 'homologacao');
    $checksOk = 0; $checksTotal = count($checklist);
    foreach($checklist as $i){ if(($i['status'] ?? '') === 'ok') $checksOk++; }
    $v2Pend=[]; $v3Pend=[];
    if ($v2Token === '') $v2Pend[]='Tiny V2 sem token configurado.';
    if (empty($cfg['tiny_v2_url'])) $v2Pend[]='Tiny V2 sem URL base.';
    if ($v3Client === '') $v3Pend[]='Tiny V3 sem Client ID.';
    if ($v3Secret === '') $v3Pend[]='Tiny V3 sem Client Secret.';
    if ($v3Redirect === '') $v3Pend[]='Tiny V3 sem Redirect URI.';
    if (!$v3Operacional) $v3Pend[]='Tiny V3 ainda não marcado como operacional.';
    if ($checksTotal > 0 && $checksOk < $checksTotal) $v3Pend[]='Checklist geral de homologação incompleto: '.$checksOk.'/'.$checksTotal.' itens OK.';
    return [
      'v2'=>[
        'titulo'=>'Tiny V2 Produção/Homologação',
        'status'=>empty($v2Pend)?'ok':'pendente',
        'pendencias'=>$v2Pend,
        'recomendacao'=>empty($v2Pend)?'Pode ser usado como operação principal ou fallback controlado.':'Configure token V2 e execute teste com SKU real.'
      ],
      'v3'=>[
        'titulo'=>'Tiny V3 Homologação OAuth',
        'ambiente'=>$v3Amb,
        'status'=>empty($v3Pend)?'ok':'pendente',
        'pendencias'=>$v3Pend,
        'recomendacao'=>empty($v3Pend)?'Pronto para piloto controlado V3.':'Manter V3 em homologação e Tiny V2 como produção/fallback.'
      ],
      'resumo'=>[
        'tiny_versao_ativa'=>$cfg['tiny_versao'] ?? 'v2',
        'checklist_ok'=>$checksOk,
        'checklist_total'=>$checksTotal,
      ]
    ];
  }
}
