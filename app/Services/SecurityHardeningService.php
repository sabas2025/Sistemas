<?php
class SecurityHardeningService {
  public static function status(): array {
    $cfg = App::config(); $sec = $cfg['security'] ?? [];
    $public = __DIR__.'/../../public'; $root = __DIR__.'/../..';
    $checks = [];
    $add = function(string $chave, string $titulo, bool $ok, string $risco, string $acao) use (&$checks) {
      $checks[] = ['chave'=>$chave,'titulo'=>$titulo,'ok'=>$ok,'status'=>$ok?'ok':'pendente','risco'=>$risco,'acao'=>$acao];
    };
    $add('robots_txt','robots.txt bloqueando buscadores', is_file($public.'/robots.txt') && str_contains((string)@file_get_contents($public.'/robots.txt'), 'Disallow: /'), 'Painel pode aparecer em mecanismos de busca.', 'Manter robots.txt com Disallow total e header X-Robots-Tag.');
    $add('htaccess_public','Proteção .htaccess no public', is_file($public.'/.htaccess'), 'Listagem e arquivos sensíveis podem ficar expostos em Apache.', 'Publicar com .htaccess ativo ou regras equivalentes no Nginx.');
    $add('htaccess_root','Proteção .htaccess na raiz', is_file($root.'/.htaccess'), 'Acesso direto a app/config/database/storage pode expor segredos.', 'Manter .htaccess raiz bloqueando pastas internas.');
    $add('install_lock','Instalador bloqueado', is_file($public.'/install.lock') || is_file($root.'/storage/install.lock'), 'Reinstalação ou alteração indevida do banco.', 'Criar install.lock após instalar e remover install.php da produção se possível.');
    $add('encryption_key','Chave de criptografia forte', self::strongKey((string)($sec['encryption_key'] ?? '')), 'Tokens Tiny/VSM podem ficar vulneráveis.', 'Usar chave aleatória com 32+ caracteres, fora do Git.');
    $add('app_prod','APP_ENV produção', App::isProduction(), 'Erros técnicos podem aparecer na tela.', 'Definir app_env como production no config.php.');
    $https = class_exists('TrustedProxyService') ? TrustedProxyService::isHttps() : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'));
    $add('https','HTTPS ativo', $https || App::isLocal(), 'Sessões e tokens podem trafegar sem criptografia.', 'Forçar HTTPS na hospedagem e redirecionar HTTP para HTTPS.');
    $add('session_timeout','Timeout de sessão configurado', (int)($sec['session_idle_timeout_seconds'] ?? 0) > 0, 'Sessões antigas podem ficar abertas.', 'Usar timeout de inatividade e expiração absoluta.');
    $add('db_root','Banco não usa root em produção', !App::isProduction() || (($cfg['db']['user'] ?? '') !== 'root'), 'Usuário root amplia impacto em caso de invasão.', 'Criar usuário MySQL exclusivo com permissões mínimas.');
    $add('install_php_removed','install.php removido ou bloqueado', !is_file($public.'/install.php') || is_file($root.'/storage/install.lock') || is_file($public.'/install.lock'), 'Instalador público pode permitir reinstalação indevida.', 'Após instalar, criar install.lock e remover/renomear public/install.php em produção.');
    $add('env_protegido','.env/config protegido', is_file($root.'/.htaccess') && is_file($root.'/config/.htaccess'), 'Arquivos de configuração podem expor credenciais.', 'Manter bloqueio de app/config/database/storage via servidor web.');
    $add('backups_protegidos','Backups protegidos por storage/.htaccess', is_file($root.'/storage/.htaccess'), 'Backups podem ser baixados diretamente.', 'Nunca deixar storage/backups público.');
    $add('hsts','HSTS ativo em produção', App::isProduction() && $https, 'Navegador pode aceitar downgrade HTTP.', 'Usar HTTPS válido e HSTS no domínio de produção.');
    $add('csp_nonce','CSP com nonce configurada', str_contains((string)($sec['content_security_policy'] ?? ''), 'nonce-__NONCE__'), 'Scripts/estilos inline podem facilitar XSS.', 'Manter CSP com nonce e remover inline sem nonce.');
    $add('trusted_proxy','Trusted Proxy configurado', !App::isPublicHost() || trim((string)($sec['trusted_proxies'] ?? '')) !== '', 'IP/fingerprint/rate limit podem usar IP do proxy.', 'Configurar IPs do proxy/CDN confiável em trusted_proxies.');
    $add('totp_admin','TOTP obrigatório para admin', !empty($sec['require_admin_2fa']), 'Conta admin sem segundo fator aumenta impacto de senha vazada.', 'Manter require_admin_2fa=true.');
    $add('waf_excecoes_integracao','WAF com exceções permanentes Tiny/VSM', str_contains((string)($sec['waf_never_inspect_routes'] ?? ''), 'api/webhook/vsm/*') && str_contains((string)($sec['waf_never_inspect_routes'] ?? ''), 'api/webhook/tiny/*'), 'Falso positivo pode travar webhooks ou payloads legítimos.', 'Manter exceções permanentes e proteger integrações por HMAC/secret/rate limit.');
    $add('anti_replay','Anti-replay de integração ativo', (int)($sec['integration_anti_replay_window_seconds'] ?? 0) >= 60, 'Reenvios duplicados podem gerar loop operacional.', 'Manter janela anti-replay de pelo menos 60 segundos.');
    $add('audit_daily_signature','Assinatura diária de auditoria ativa', !empty($sec['audit_daily_signature_enabled']), 'Auditoria pode ficar sem lacre diário.', 'Gerar assinatura diária HMAC pelo SOC ou rotina cron.');
    $add('shell_disabled','Funções de shell desativadas no PHP', class_exists('ShellCommandService') && ShellCommandService::disabled(), 'Execução de comandos OS aumenta impacto de RCE.', 'No php.ini disable_functions=exec,shell_exec,system,passthru,proc_open,popen,pcntl_exec.');
    $score = count($checks) ? (int)round(count(array_filter($checks, fn($c)=>$c['ok']))*100/count($checks)) : 0;
    return ['score'=>$score,'checks'=>$checks,'gerado_em'=>date('Y-m-d H:i:s')];
  }
  private static function strongKey(string $key): bool {
    $key = trim($key);
    if (strlen($key) < 32) return false;
    return !in_array($key, ['', 'troque-esta-chave-apos-instalar', 'hub-vsm-tiny-local-key'], true);
  }
  public static function maskSecretsInArray(array $data): array {
    $secretKeys = ['token','access_token','refresh_token','client_secret','senha','password','api_key','secret','authorization'];
    foreach($data as $k=>$v){
      $lk = strtolower((string)$k);
      if (is_array($v)) $data[$k] = self::maskSecretsInArray($v);
      elseif (in_array($lk,$secretKeys,true) || str_contains($lk,'token') || str_contains($lk,'secret') || str_contains($lk,'senha')) $data[$k] = Secrets::mask((string)$v);
    }
    return $data;
  }
}
