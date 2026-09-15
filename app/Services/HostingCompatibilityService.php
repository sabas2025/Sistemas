<?php
class HostingCompatibilityService {
  public static function ambiente(): array {
    $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $isInfinity = stripos($docRoot, 'htdocs') !== false && (stripos($_SERVER['HTTP_HOST'] ?? '', 'infinityfree') !== false || stripos($_SERVER['HTTP_HOST'] ?? '', 'epizy') !== false || stripos($_SERVER['HTTP_HOST'] ?? '', 'rf.gd') !== false || stripos($_SERVER['HTTP_HOST'] ?? '', '42web') !== false);
    $checks = [
      ['item'=>'PHP >= 8.1','ok'=>version_compare(PHP_VERSION,'8.1.0','>='),'valor'=>PHP_VERSION,'acao'=>'Atualize o PHP no painel da hospedagem.'],
      ['item'=>'PDO MySQL','ok'=>extension_loaded('pdo_mysql'),'valor'=>extension_loaded('pdo_mysql')?'ativo':'inativo','acao'=>'Ative pdo_mysql.'],
      ['item'=>'cURL','ok'=>extension_loaded('curl'),'valor'=>extension_loaded('curl')?'ativo':'inativo','acao'=>'Ative cURL.'],
      ['item'=>'OpenSSL','ok'=>extension_loaded('openssl'),'valor'=>extension_loaded('openssl')?'ativo':'inativo','acao'=>'Ative OpenSSL para tokens e criptografia.'],
      ['item'=>'ZIP','ok'=>extension_loaded('zip'),'valor'=>extension_loaded('zip')?'ativo':'inativo','acao'=>'Ative ZIP para backup compactado.'],
      ['item'=>'JSON','ok'=>extension_loaded('json'),'valor'=>extension_loaded('json')?'ativo':'inativo','acao'=>'Ative JSON.'],
      ['item'=>'MBSTRING','ok'=>extension_loaded('mbstring'),'valor'=>extension_loaded('mbstring')?'ativo':'inativo','acao'=>'Ative mbstring.'],
      ['item'=>'file_put_contents','ok'=>!in_array('file_put_contents',$disabled,true),'valor'=>in_array('file_put_contents',$disabled,true)?'bloqueado':'liberado','acao'=>'Necessário para logs/snapshots.'],
      ['item'=>'exec/shell_exec não obrigatório','ok'=>true,'valor'=>(in_array('exec',$disabled,true)?'exec bloqueado':'exec liberado'),'acao'=>'No InfinityFree, use workers via URL protegida em vez de CLI.'],
    ];
    $ok = count(array_filter($checks, fn($c)=>$c['ok']));
    return ['is_infinityfree'=>$isInfinity,'score'=>round(($ok/count($checks))*100),'checks'=>$checks,'docroot'=>$docRoot,'host'=>$_SERVER['HTTP_HOST'] ?? 'cli'];
  }

  public static function recomendações(): array {
    return [
      'Envie somente o conteúdo do projeto para a pasta htdocs da InfinityFree.',
      'Use o MySQL fornecido pela InfinityFree: host, usuário, senha e nome do banco não são localhost/root.',
      'Não deixe /database, /storage e /config expostos publicamente se puder mover para fora do public_html; se não puder, mantenha bloqueios por .htaccess.',
      'Workers CLI não rodam continuamente em hospedagem gratuita; use URLs protegidas por token e cron externo gratuito, quando permitido.',
      'Desative display_errors em produção e acompanhe erros pela tela Logs/Auditoria.',
      'Use Tiny/VSM em homologação antes de ativar produção.',
    ];
  }
}
