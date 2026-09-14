<?php
class SecurityScoreService {
  public static function evaluate(): array {
    $checks=[]; $score=0; $max=0;
    $add=function($key,$label,$ok,$weight,$risk='medio') use (&$checks,&$score,&$max){ $max+=$weight; if($ok)$score+=$weight; $checks[]=['key'=>$key,'label'=>$label,'ok'=>(bool)$ok,'peso'=>$weight,'risco'=>$risk]; };
    $cfg = App::config(); $sec = $cfg['security'] ?? [];
    $add('env_prod','Ambiente em produção', App::isProduction(), 10, 'alto');
    $add('https','HTTPS detectado', (class_exists('TrustedProxyService') ? TrustedProxyService::isHttps() : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off')) || !App::isPublicHost(), 10, 'alto');
    $add('csp','CSP sem unsafe-inline em scripts/estilos', !str_contains((string)($sec['content_security_policy']??''), 'unsafe-inline'), 10, 'alto');
    $add('crypto_key','Chave de criptografia forte', strlen((string)($sec['encryption_key'] ?? '')) >= 32, 10, 'critico');
    $add('storage_htaccess','storage protegido', is_file(__DIR__.'/../../storage/.htaccess'), 8, 'alto');
    $add('install_lock','Install lock presente ou ambiente local', is_file(__DIR__.'/../../storage/install.lock') || !App::isPublicHost(), 8, 'critico');
    $add('fim','Manifesto FIM presente', is_file(FileIntegrityService::manifestPath()), 8, 'alto');
    $add('waf','WAF limitado ao painel administrativo', class_exists('WafService') && !empty($sec['waf_panel_only']), 8, 'alto');
    $add('admin_2fa','2FA obrigatório para administradores', !empty($sec['require_admin_2fa']), 10, 'critico');
    $add('backup_hmac','Backups assinados com HMAC', strlen((string)($sec['backup_signature_key'] ?? '')) >= 32, 9, 'critico');
    $add('trusted_proxy','Trusted Proxy configurado ou ambiente local', !App::isPublicHost() || (string)($sec['trusted_proxies'] ?? '') !== '', 5, 'medio');
    $add('session','Sessão SameSite Strict', (ini_get('session.cookie_samesite') === 'Strict'), 6, 'medio');
    $add('errors','display_errors desativado em público', !App::isPublicHost() || ini_get('display_errors') === '0', 6, 'alto');
    $percent = $max ? (int)round(($score/$max)*100) : 0;
    return ['score'=>$percent, 'classificacao'=>$percent>=90?'forte':($percent>=75?'boa':($percent>=60?'atenção':'crítica')), 'checks'=>$checks];
  }
}
