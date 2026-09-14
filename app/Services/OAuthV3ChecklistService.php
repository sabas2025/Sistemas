<?php
class OAuthV3ChecklistService {
  public static function passos(): array {
    try { $cfg=IntegrationConfig::get(); } catch(Throwable $e){ $cfg=[]; }
    $amb=$cfg['tiny_v3_ambiente'] ?? ($cfg['ambiente'] ?? 'homologacao');
    $token=null;
    try { $token = TinyV3TokenService::getTokenRow($amb); } catch(Throwable $e) { $token=null; }
    $redirect = trim((string)($cfg['tiny_v3_redirect_uri'] ?? ''));
    $baseUrl = trim((string)($cfg['tiny_v3_url'] ?? 'https://api.tiny.com.br/public-api/v3'));
    $scopes = trim((string)($cfg['tiny_v3_scopes'] ?? ''));
    return [
      ['titulo'=>'Client ID preenchido','ok'=>trim((string)($cfg['tiny_v3_client_id'] ?? '')) !== '','acao'=>'Cole o Client ID do aplicativo Tiny V3.'],
      ['titulo'=>'Client Secret preenchido','ok'=>trim((string)($cfg['tiny_v3_client_secret'] ?? '')) !== '','acao'=>'Cole o Client Secret do aplicativo Tiny V3.'],
      ['titulo'=>'Redirect URI absoluta','ok'=>preg_match('#^https?://#i',$redirect)===1,'acao'=>'Use URL completa, exemplo: https://sabas.page.gd/public/index.php?page=tiny-v3-callback'],
      ['titulo'=>'Callback padronizado','ok'=>str_contains($redirect,'page=tiny-v3-callback'),'acao'=>'Evite tiny-v3/callbackv ou rotas diferentes.'],
      ['titulo'=>'Escopos OAuth válidos','ok'=>$scopes==='' || !preg_match('/\b(produtos|estoque|pedidos|notas-fiscais)\b/i',$scopes),'acao'=>'Deixe Escopos OAuth vazio, salvo se o Tiny informar escopo oficial aceito.'],
      ['titulo'=>'Base API V3 preenchida','ok'=>str_starts_with($baseUrl,'https://'),'acao'=>'Use https://api.tiny.com.br/public-api/v3'],
      ['titulo'=>'Token OAuth salvo','ok'=>!empty($token),'acao'=>'Clique em Conectar Tiny V3 via OAuth e conclua no Tiny.'],
      ['titulo'=>'Token não expirado','ok'=>!empty($token) && strtotime((string)($token['expires_at'] ?? '1970-01-01')) > time()+60,'acao'=>'Renove o token Tiny V3 no painel.'],
      ['titulo'=>'Tiny V3 operacional','ok'=>!empty($cfg['tiny_v3_operacional']),'acao'=>'Marque como operacional só depois dos testes de produto, estoque, pedido e logs.'],
    ];
  }
  public static function score(): int {
    $p=self::passos(); if(!$p) return 0; return (int)round(count(array_filter($p,fn($x)=>$x['ok']))*100/count($p));
  }
}
