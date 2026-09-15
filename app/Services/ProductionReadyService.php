<?php
class ProductionReadyService {
  public static function checks(): array {
    $cfg = App::config();
    $int = []; try { $int = IntegrationConfig::get(); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    $security = $cfg['security'] ?? [];
    $checks = [];
    $add = function(string $grupo, string $item, bool $ok, string $acao) use (&$checks) { $checks[] = compact('grupo','item','ok','acao'); };
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $add('Ambiente','app_env produção configurado', App::isProduction() || App::isLocal(), 'Em servidor real use app_env=production.');
    $add('Ambiente','HTTPS disponível', App::isLocal() || $https, 'Ative SSL antes de liberar webhooks/OAuth.');
    $add('Segurança','Chave de criptografia preenchida', !empty($security['encryption_key'] ?? ''), 'Gere uma encryption_key no config.php.');
    $add('Segurança','HMAC obrigatório em produção', !App::isProduction() || !empty($security['require_hmac_in_production']), 'Mantenha assinatura HMAC nos webhooks.');
    $db = $cfg['db'] ?? [];
    $add('Banco','Senha MySQL não vazia em produção', !App::isProduction() || (($db['pass'] ?? '') !== ''), 'Use usuário MySQL exclusivo e senha forte.');
    foreach(['core','pedidos','produtos','estoque','fiscal','fila','observabilidade','backups'] as $mod) {
      try { Database::connection($mod)->query('SELECT 1'); $ok = true; } catch(Throwable $e) { $ok = false; }
      $add('Banco modular', 'Conexão '.$mod, $ok, 'Reinstale/valide o banco modular '.$mod.'.');
    }
    foreach(['fila_integracao','fila_estoque','fila_fiscal','pedidos_hub','notas_fiscais','estoque_movimentos','notificacoes','auditoria_eventos'] as $table) {
      $add('Tabelas', $table, Database::tableExists($table), 'Executar install.php ou SQL de instalação consolidado.');
    }
    $add('Tiny','Tiny configurado', !empty($int['tiny_v2_token'] ?? '') || !empty($int['tiny_v3_operacional'] ?? ''), 'Configure Tiny V2 ou valide Tiny V3.');
    $add('VSM','URL VSM configurada', !empty($int['vsm_url'] ?? ''), 'Configure URL base e token da VSM.');
    $add('Estoque','VSM como fonte real', (EstoqueEnterpriseService::config()['estoque_mestre'] ?? 'vsm') === 'vsm', 'Mantenha VSM como estoque real conforme operação.');
    foreach(['storage','storage/logs','storage/cache','storage/backups'] as $dir) {
      $path = __DIR__.'/../../'.$dir; if(!is_dir($path)) @mkdir($path,0775,true);
      $add('Arquivos', $dir.' gravável', is_writable($path), 'Ajuste permissão de escrita em '.$dir.'.');
    }
    $ok = count(array_filter($checks, fn($c)=>$c['ok']));
    $score = (int)round(($ok / max(1,count($checks))) * 100);
    return ['score'=>$score,'status'=>$score>=90?'pronto':($score>=75?'atenção':'bloqueado'),'checks'=>$checks];
  }
}
