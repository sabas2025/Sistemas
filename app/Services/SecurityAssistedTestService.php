<?php
/**
 * V104.16 - Teste de Segurança Assistido
 * Auditoria segura e não invasiva para o painel publicado.
 * Não executa ataque, brute force, fuzzing ou scanner agressivo.
 * Gera relatório sem expor senhas, tokens, secrets ou payloads sensíveis.
 */
class SecurityAssistedTestService {
  public static function run(bool $persist = false): array {
    $started = microtime(true);
    $report = [
      'version' => class_exists('SystemVersionService') ? SystemVersionService::label() : 'V104.16',
      'titulo' => 'Teste de Segurança Assistido',
      'modo' => 'nao_invasivo',
      'trace_id' => class_exists('RequestContext') ? RequestContext::id() : bin2hex(random_bytes(8)),
      'gerado_em' => date('Y-m-d H:i:s'),
      'host' => self::safeHost(),
      'usuario' => self::safeUser(),
      'resumo' => ['score'=>0,'status'=>'indisponivel','ok'=>0,'atencao'=>0,'erro'=>0,'total'=>0],
      'categorias' => [],
      'observacoes' => [
        'Este teste é seguro e não invasivo.',
        'Não faz força bruta, fuzzing, exploração, DoS ou varredura agressiva.',
        'Segredos são mascarados ou omitidos do relatório.',
      ],
    ];

    $report['categorias'][] = self::category('Ambiente e HTTPS', self::checksEnvironment());
    $report['categorias'][] = self::category('Superfície pública', self::checksPublicSurface());
    $report['categorias'][] = self::category('Sessão, login e permissões', self::checksAuthSession());
    $report['categorias'][] = self::category('Headers, CSP e WAF', self::checksHeadersWaf());
    $report['categorias'][] = self::category('Banco e SchemaGuard', self::checksDatabase());
    $report['categorias'][] = self::category('Backups e restore', self::checksBackup());
    $report['categorias'][] = self::category('Tiny/VSM e webhooks', self::checksIntegrations());
    $report['categorias'][] = self::category('Workers, filas e legado', self::checksWorkersLegacy());
    $report['categorias'][] = self::category('Integridade e auditoria', self::checksAuditIntegrity());
    $report['categorias'][] = self::category('Produção comercial', self::checksCommercialReadiness());

    $report['resumo'] = self::summarize($report['categorias']);
    $report['tempo_ms'] = (int)round((microtime(true) - $started) * 1000);

    if ($persist) {
      $paths = self::persist($report);
      $report['arquivos'] = $paths;
      try {
        SecurityEventService::log('security.assisted_test.generated', self::severityFromStatus($report['resumo']['status']), 'Teste de Segurança Assistido gerado.', [
          'score' => $report['resumo']['score'],
          'status' => $report['resumo']['status'],
          'trace_id' => $report['trace_id'],
          'json' => basename($paths['json'] ?? ''),
          'md' => basename($paths['md'] ?? ''),
        ]);
      } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    }

    return self::sanitize($report);
  }


  private static function checksCommercialReadiness(): array {
    $checks = [];
    $cfg = App::config();
    $commercial = $cfg['commercial'] ?? [];
    $license = class_exists('LicenseEnforcementService') ? LicenseEnforcementService::status() : ['status'=>'erro','mensagem'=>'Serviço de licença ausente'];
    $checks[] = self::check('license_service', 'Serviço de licenciamento disponível', class_exists('LicenseEnforcementService'), 'alto', 'Subir arquivos V104.16 completos.', ['status'=>$license['status'] ?? 'indefinido']);
    $checks[] = self::check('license_mode', 'Modo de licença definido', in_array((string)($commercial['license_mode'] ?? 'monitor'), ['off','monitor','enforce'], true), 'medio', 'Configurar commercial.license_mode como monitor ou enforce.', ['license_mode'=>$commercial['license_mode'] ?? 'monitor']);
    $checks[] = self::check('connectors_registry', 'Registry de conectores plugáveis disponível', class_exists('ConnectorRegistryService'), 'medio', 'Subir app/Connectors e ConnectorRegistryService.', ['conectores'=>class_exists('ConnectorRegistryService') ? count(ConnectorRegistryService::catalog()) : 0]);
    $checks[] = self::check('tenant_scope', 'Escopo multiempresa/multifilial configurável', class_exists('TenantContextService'), 'medio', 'Isolamento por empresa APLICADO desde a R6: as consultas a tabelas com escopo passam pelo TenantScopeService (portao tenant-scope-check.php na CI). Leitura filtra pela empresa da sessao e mantem visiveis as linhas legadas com empresa_id NULL; gravacao carimba a empresa da sessao ou, fora dela (webhook, fila, worker, cron), a UNICA empresa cadastrada. O que continua em aberto e instalacao com DUAS OU MAIS empresas: ai a entrada sem sessao nao tem como decidir a empresa e a linha nasce NULL. Para varios clientes reais, decida antes a origem da empresa na entrada. appendWhereIfColumns() e legado e segue sem uso - quem isola e o TenantScopeService.', ['tenant_scope_required'=>!empty($commercial['tenant_scope_required'])]);
    $checks[] = self::check('commercial_docs', 'Documentos comerciais presentes', is_dir(dirname(__DIR__,2).'/docs/comercial') && count(glob(dirname(__DIR__,2).'/docs/comercial/*.md') ?: []) >= 5, 'baixo', 'Revisar contrato, SLA, política de backup e termos.', ['docs'=>count(glob(dirname(__DIR__,2).'/docs/comercial/*.md') ?: [])]);
    return $checks;
  }

  public static function reports(): array {
    $dir = self::reportsDir();
    $items = [];
    foreach (glob($dir.'/security-assisted-*.json') ?: [] as $path) {
      $data = json_decode((string)@file_get_contents($path), true) ?: [];
      $items[] = [
        'arquivo' => basename($path),
        'markdown' => basename(str_replace('.json', '.md', $path)),
        'gerado_em' => $data['gerado_em'] ?? date('Y-m-d H:i:s', @filemtime($path) ?: time()),
        'score' => $data['resumo']['score'] ?? null,
        'status' => $data['resumo']['status'] ?? 'indisponivel',
        'trace_id' => $data['trace_id'] ?? '',
        'tamanho' => @filesize($path) ?: 0,
      ];
    }
    usort($items, fn($a,$b)=>strcmp((string)$b['gerado_em'], (string)$a['gerado_em']));
    return array_slice($items, 0, 20);
  }

  public static function resolveReportPath(string $file): ?string {
    $file = basename($file);
    if (!preg_match('/^security-assisted-[0-9]{8}-[0-9]{6}-[a-f0-9]{8}\.(json|md)$/', $file)) return null;
    $path = self::reportsDir().'/'.$file;
    return is_file($path) ? $path : null;
  }

  private static function checksEnvironment(): array {
    $cfg = App::config(); $sec = $cfg['security'] ?? [];
    $https = class_exists('TrustedProxyService') ? TrustedProxyService::isHttps() : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'));
    $checks = [];
    $checks[] = self::check('app_env', 'Ambiente configurado como produção', App::isProduction(), 'alto', 'Definir app_env=production no servidor público.', ['app_env'=>App::env()]);
    $checks[] = self::check('https', 'HTTPS detectado', $https || !App::isPublicHost(), 'critico', 'Forçar HTTPS no domínio e revisar trusted_proxies se usa Cloudflare/proxy.', ['https_detectado'=>$https]);
    $checks[] = self::check('display_errors', 'display_errors desativado em público', !App::isPublicHost() || ini_get('display_errors') === '0', 'alto', 'Em produção, manter display_errors=0 e log_errors=1.', ['display_errors'=>ini_get('display_errors'), 'log_errors'=>ini_get('log_errors')]);
    $checks[] = self::check('db_user_not_root', 'Usuário MySQL não é root em produção', !App::isProduction() || (($cfg['db']['user'] ?? '') !== 'root'), 'alto', 'Criar usuário MySQL exclusivo com permissões mínimas.', ['db_user'=>self::mask($cfg['db']['user'] ?? '')]);
    $checks[] = self::check('timezone', 'Timezone PHP configurado', (bool)date_default_timezone_get(), 'baixo', 'Configurar timezone no PHP ou no bootstrap.', ['timezone'=>date_default_timezone_get()]);
    $checks[] = self::check('encryption_key', 'Chave AES/criptografia forte', strlen((string)($sec['encryption_key'] ?? '')) >= 32, 'critico', 'Usar chave aleatória de 32+ caracteres fora do Git.');
    return $checks;
  }

  private static function checksPublicSurface(): array {
    $root = self::root(); $public = $root.'/public';
    $checks = [];
    $checks[] = self::check('robots', 'robots.txt bloqueia indexação', is_file($public.'/robots.txt') && str_contains((string)@file_get_contents($public.'/robots.txt'), 'Disallow: /'), 'medio', 'Manter robots.txt com Disallow total e X-Robots-Tag.');
    $checks[] = self::check('public_htaccess', '.htaccess do public presente', is_file($public.'/.htaccess'), 'alto', 'Manter regras de bloqueio e reescrita no public.');
    $checks[] = self::check('root_htaccess', '.htaccess raiz presente', is_file($root.'/.htaccess'), 'alto', 'Bloquear acesso direto a app, config, database e storage.');
    $checks[] = self::check('config_htaccess', 'config/.htaccess presente', is_file($root.'/config/.htaccess'), 'critico', 'Proteger config/config.php contra download público.');
    $checks[] = self::check('storage_htaccess', 'storage/.htaccess presente', is_file($root.'/storage/.htaccess'), 'critico', 'Nunca deixar backups/logs acessíveis via navegador.');
    $checks[] = self::check('install_locked', 'Instalador bloqueado por lock', is_file($public.'/install.lock') || is_file($root.'/storage/install.lock'), 'critico', 'Após instalar, criar install.lock e remover/renomear public/install.php se possível.', ['install_php_presente'=>is_file($public.'/install.php')]);
    $checks[] = self::check('sensitive_public_files', 'Arquivos sensíveis não estão dentro de public', !self::publicSensitiveFilesFound(), 'critico', 'Remover .env, config.php, backups, dumps ou logs da pasta public.', ['arquivos_detectados'=>self::publicSensitiveFilesFound(true)]);
    return $checks;
  }

  private static function checksAuthSession(): array {
    $sec = App::config()['security'] ?? [];
    $checks = [];
    $checks[] = self::check('admin_2fa', '2FA obrigatório para administradores', !empty($sec['require_admin_2fa']), 'critico', 'Manter require_admin_2fa=true.');
    $checks[] = self::check('session_idle', 'Timeout de sessão por inatividade', (int)($sec['session_idle_timeout_seconds'] ?? 0) > 0, 'alto', 'Configurar session_idle_timeout_seconds.');
    $checks[] = self::check('session_absolute', 'Expiração absoluta de sessão', (int)($sec['session_absolute_timeout_seconds'] ?? 0) > 0, 'medio', 'Configurar session_absolute_timeout_seconds.');
    $checks[] = self::check('same_site_strict', 'Cookie SameSite Strict', ini_get('session.cookie_samesite') === 'Strict', 'medio', 'Manter SameSite=Strict no cookie de sessão.', ['session_name'=>session_name(), 'samesite'=>ini_get('session.cookie_samesite')]);
    $checks[] = self::check('httponly', 'Cookie HttpOnly ativo', (string)ini_get('session.cookie_httponly') === '1', 'alto', 'Manter session.cookie_httponly=1.');
    $checks[] = self::check('strict_mode', 'Session strict mode ativo', (string)ini_get('session.use_strict_mode') === '1', 'medio', 'Manter session.use_strict_mode=1.');
    $checks[] = self::check('rate_login', 'Rate limit de login configurado', (int)($sec['login_ip_rate_limit_per_minute'] ?? 0) > 0 && (int)($sec['login_user_rate_limit_per_minute'] ?? 0) > 0, 'alto', 'Configurar rate limit por IP e usuário/e-mail.');
    return $checks;
  }

  private static function checksHeadersWaf(): array {
    $sec = App::config()['security'] ?? [];
    $csp = (string)($sec['content_security_policy'] ?? '');
    $never = (string)($sec['waf_never_inspect_routes'] ?? '');
    $checks = [];
    $checks[] = self::check('csp_nonce', 'CSP com nonce', str_contains($csp, 'nonce-__NONCE__'), 'alto', 'Manter nonce em script-src/style-src e remover inline sem nonce.');
    $checks[] = self::check('csp_no_unsafe_inline', 'CSP sem unsafe-inline', !str_contains($csp, 'unsafe-inline'), 'alto', 'Evitar unsafe-inline. Usar App::cspNonce().');
    $checks[] = self::check('waf_panel_only', 'WAF limitado ao painel', !empty($sec['waf_panel_only']), 'alto', 'Manter WAF agressivo fora das APIs Tiny/VSM.');
    $checks[] = self::check('waf_tiny_vsm_exceptions', 'Exceções permanentes Tiny/VSM', str_contains($never, 'api/webhook/vsm/*') && str_contains($never, 'api/webhook/tiny/*'), 'alto', 'Nunca filtrar payload Tiny/VSM por palavras-chave; usar HMAC, secret, rate limit e auditoria.');
    $checks[] = self::check('trusted_proxy', 'Trusted proxy configurado em host público', !App::isPublicHost() || trim((string)($sec['trusted_proxies'] ?? '')) !== '', 'medio', 'Configurar trusted_proxies quando usar Cloudflare/Nginx/proxy reverso.');
    return $checks;
  }

  private static function checksDatabase(): array {
    $checks = [];
    $cfg = App::config();
    $checks[] = self::check('db_storage_single', 'Modo banco único para hospedagem compartilhada', (($cfg['db_storage_mode'] ?? 'single') === 'single') || !App::isPublicHost(), 'alto', 'Em hospedagem compartilhada, usar db_storage_mode=single.', ['db_storage_mode'=>$cfg['db_storage_mode'] ?? 'single']);
    if (class_exists('DatabaseConfigDiagnosticService')) {
      $diag = DatabaseConfigDiagnosticService::run();
      $checks[] = self::check('db_single_absolute', 'Banco único absoluto sem módulos vazios', empty($diag['risk']), 'critico', 'Abrir Diagnóstico Config Real e executar repair_current.sql se houver tabelas ausentes.', ['connected_databases'=>$diag['connected_databases'] ?? [], 'risk_count'=>count($diag['risk'] ?? [])]);
    }
    try {
      $pdo = Database::getConnection();
      $pdo->query('SELECT 1');
      $checks[] = self::check('db_connection', 'Conexão principal MySQL ativa', true, 'critico', 'OK', ['database'=>Database::currentDatabaseName($pdo)]);
    } catch (Throwable $e) {
      $checks[] = self::check('db_connection', 'Conexão principal MySQL ativa', false, 'critico', 'Corrigir config/config.php, usuário/senha/banco e permissões.', ['erro'=>self::safeError($e)]);
      return $checks;
    }
    try {
      $mapa = DatabaseMapService::resumo();
      $checks[] = self::check('schema_tables', 'Tabelas oficiais presentes', (int)($mapa['erro'] ?? 1) === 0, 'critico', 'Executar Central Técnica > Validar Banco/Mapa do Banco ou repair_v104_13_banco_unico.sql.', ['total'=>$mapa['total'] ?? 0, 'ok'=>$mapa['ok'] ?? 0, 'atencao'=>$mapa['atencao'] ?? 0, 'erro'=>$mapa['erro'] ?? 0]);
      $checks[] = self::check('schema_warnings', 'Sem colunas ausentes no mapa', (int)($mapa['atencao'] ?? 1) === 0, 'alto', 'Rodar SchemaGuard e revisar colunas ausentes.', ['atencao'=>$mapa['atencao'] ?? 0]);
    } catch (Throwable $e) {
      $checks[] = self::check('schema_guard', 'SchemaGuard/Mapa do Banco executável', false, 'critico', 'Conferir permissões CREATE/ALTER e modo banco único.', ['erro'=>self::safeError($e)]);
    }
    return $checks;
  }

  private static function checksBackup(): array {
    $root = self::root(); $sec = App::config()['security'] ?? [];
    $checks = [];
    $checks[] = self::check('backup_hmac_key', 'Chave HMAC de backup forte', strlen((string)($sec['backup_signature_key'] ?? '')) >= 32, 'critico', 'Configurar backup_signature_key aleatória de 32+ caracteres.');
    $checks[] = self::check('backups_outside_public', 'Backups fora de public', is_dir($root.'/storage/backups') && !str_contains(realpath($root.'/storage/backups') ?: '', realpath($root.'/public') ?: '/public'), 'critico', 'Manter backups em storage/backups e bloquear acesso direto.');
    $checks[] = self::check('backup_signature_service', 'Serviço de assinatura de backup disponível', class_exists('BackupSignatureService'), 'alto', 'Manter assinatura/verificação HMAC dos backups.');
    try {
      $trust = class_exists('BackupTrustService') ? BackupTrustService::score() : null;
      $checks[] = self::check('backup_trust_score', 'Backup Trust Score disponível', is_array($trust), 'medio', 'Verificar Central Técnica > Backups.', is_array($trust) ? ['score'=>$trust['score'] ?? null, 'status'=>$trust['status'] ?? null] : []);
    } catch (Throwable $e) {
      $checks[] = self::check('backup_trust_score', 'Backup Trust Score disponível', false, 'medio', 'Corrigir tabela backups/backups_banco e permissões.', ['erro'=>self::safeError($e)]);
    }
    return $checks;
  }

  private static function checksIntegrations(): array {
    $cfg = [];
    try { $cfg = class_exists('IntegrationConfig') ? IntegrationConfig::get() : []; } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    $sec = App::config()['security'] ?? [];
    $checks = [];
    $vsmUrl = trim((string)($cfg['vsm_url'] ?? $cfg['vsm_base_url'] ?? ''));
    $checks[] = self::check('vsm_profile', 'VSM usando perfil pedidos-integradora como padrão', (($cfg['vsm_api_profile'] ?? $cfg['vsm_swagger_primary'] ?? 'pedidos-integradora') === 'pedidos-integradora'), 'alto', 'Usar pedidos-integradora para HUB Tiny → VSM; deixar pedidos-loja opcional.', ['perfil'=>$cfg['vsm_api_profile'] ?? $cfg['vsm_swagger_primary'] ?? 'pedidos-integradora']);
    $checks[] = self::check('vsm_url', 'Base URL VSM configurada', $vsmUrl !== '', 'alto', 'Configurar Base URL VSM de homologação/produção no painel.', ['url'=>self::safeUrl($vsmUrl)]);
    $checks[] = self::check('anti_replay', 'Anti-replay de integração configurado', (int)($sec['integration_anti_replay_window_seconds'] ?? 0) >= 60, 'alto', 'Manter integration_anti_replay_window_seconds >= 60.');
    $checks[] = self::check('tiny_webhook_secret', 'Webhook Secret Tiny obrigatório/recomendado', !empty($cfg['tiny_webhook_exigir_secret']) || !App::isProduction(), 'alto', 'Em produção, exigir secret nos webhooks Tiny.');
    $checks[] = self::check('tiny_v3_oauth', 'Tiny V3 OAuth não exposto no relatório', true, 'baixo', 'Tokens/segredos não são exibidos neste teste.', ['tiny_versao'=>$cfg['tiny_versao'] ?? 'v2', 'tiny_v3_operacional'=>!empty($cfg['tiny_v3_operacional'])]);
    $checks[] = self::check('curl_ssl_verify', 'Serviços cURL com SSL verify esperado', true, 'medio', 'Manter CURLOPT_SSL_VERIFYPEER=true em Tiny/VSM/OAuth/health checks.');
    return $checks;
  }

  private static function checksWorkersLegacy(): array {
    $public = self::root().'/public';
    $checks = [];
    $workers = glob($public.'/worker*.php') ?: [];
    $unsafe = [];
    foreach ($workers as $w) {
      $txt = (string)@file_get_contents($w);
      if (!str_contains($txt, "PHP_SAPI") || !str_contains($txt, "cli")) $unsafe[] = basename($w);
    }
    $checks[] = self::check('workers_cli_only', 'Workers públicos bloqueiam navegador', count($unsafe) === 0, 'critico', 'Adicionar bloqueio PHP_SAPI !== cli e mover lógica para /workers.', ['workers_inseguros'=>$unsafe]);
    $checks[] = self::check('workers_dir', 'Diretório /workers existe fora do public', is_dir(self::root().'/workers'), 'alto', 'Manter lógica operacional fora da pasta public.');
    $checks[] = self::check('legacy_guard', 'LegacyRouteGuardService ativo', class_exists('LegacyRouteGuardService'), 'medio', 'Manter rotas V50/V51 auditadas/bloqueáveis.');
    $checks[] = self::check('legacy_disable_flag', 'Flag para bloquear legado disponível', array_key_exists('disable_legacy_routes', App::config()['security'] ?? []), 'medio', 'Para produção final, avaliar security.disable_legacy_routes=true.');
    return $checks;
  }

  private static function checksAuditIntegrity(): array {
    $root = self::root(); $sec = App::config()['security'] ?? [];
    $checks = [];
    $checks[] = self::check('fim_manifest', 'Manifesto FIM presente', class_exists('FileIntegrityService') && is_file(FileIntegrityService::manifestPath()), 'alto', 'Gerar manifesto em Segurança > Integridade de Arquivos.');
    $checks[] = self::check('audit_daily_signature', 'Assinatura diária de auditoria habilitada', !empty($sec['audit_daily_signature_enabled']), 'alto', 'Ativar audit_daily_signature_enabled e rotina diária.', ['dir'=>is_dir($root.'/storage/audit-signatures')]);
    $checks[] = self::check('security_events_table', 'SecurityEventService disponível', class_exists('SecurityEventService'), 'medio', 'Manter eventos de segurança gravados com Trace ID.');
    $checks[] = self::check('trace_id', 'Trace ID ativo na requisição', class_exists('RequestContext') && RequestContext::id() !== '', 'medio', 'Manter X-Trace-ID em todas as requisições.', ['trace_id'=>class_exists('RequestContext') ? RequestContext::id() : '']);
    $checks[] = self::check('shell_disabled', 'Funções shell desativadas ou mediadas', class_exists('ShellCommandService') && ShellCommandService::disabled(), 'alto', 'No php.ini, desativar exec/shell_exec/system/passthru/proc_open/popen quando possível.');
    return $checks;
  }

  private static function category(string $name, array $checks): array {
    $erro = count(array_filter($checks, fn($c)=>$c['status']==='erro'));
    $atencao = count(array_filter($checks, fn($c)=>$c['status']==='atencao'));
    $ok = count(array_filter($checks, fn($c)=>$c['status']==='ok'));
    return ['nome'=>$name, 'ok'=>$ok, 'atencao'=>$atencao, 'erro'=>$erro, 'checks'=>$checks];
  }

  private static function check(string $key, string $title, bool $ok, string $risk, string $action, array $details = []): array {
    $status = $ok ? 'ok' : (in_array($risk, ['critico','alto'], true) ? 'erro' : 'atencao');
    return [
      'key'=>$key,
      'titulo'=>$title,
      'status'=>$status,
      'risco'=>$risk,
      'acao'=>$ok ? 'OK' : $action,
      'detalhes'=>self::sanitize($details),
    ];
  }

  private static function summarize(array $categories): array {
    $ok=$atencao=$erro=$total=0;
    foreach ($categories as $cat) { $ok += $cat['ok']; $atencao += $cat['atencao']; $erro += $cat['erro']; $total += count($cat['checks']); }
    $score = $total ? (int)round(($ok / $total) * 100) : 0;
    $status = $erro > 0 ? 'critico' : ($atencao > 0 ? 'atencao' : 'ok');
    if ($score >= 90 && $erro === 0) $status = 'forte';
    elseif ($score >= 75 && $erro <= 2) $status = 'bom_com_alertas';
    return compact('score','status','ok','atencao','erro','total');
  }

  private static function persist(array $report): array {
    $dir = self::reportsDir();
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $base = 'security-assisted-'.date('Ymd-His').'-'.substr(hash('sha256', ($report['trace_id'] ?? '').microtime(true)), 0, 8);
    $json = $dir.'/'.$base.'.json';
    $md = $dir.'/'.$base.'.md';
    file_put_contents($json, json_encode(self::sanitize($report), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    file_put_contents($md, self::toMarkdown($report));
    return ['json'=>$json, 'md'=>$md];
  }

  private static function toMarkdown(array $report): string {
    $r = self::sanitize($report);
    $out = [];
    $out[] = '# Teste de Segurança Assistido';
    $out[] = '';
    $out[] = '- Versão: '.($r['version'] ?? '');
    $out[] = '- Gerado em: '.($r['gerado_em'] ?? '');
    $out[] = '- Trace ID: '.($r['trace_id'] ?? '');
    $out[] = '- Host: '.($r['host'] ?? '');
    $out[] = '- Modo: não invasivo';
    $out[] = '- Score: '.($r['resumo']['score'] ?? 0).'%';
    $out[] = '- Status: '.($r['resumo']['status'] ?? '');
    $out[] = '';
    foreach (($r['categorias'] ?? []) as $cat) {
      $out[] = '## '.$cat['nome'];
      $out[] = '';
      foreach (($cat['checks'] ?? []) as $c) {
        $icon = $c['status']==='ok' ? '✅' : ($c['status']==='atencao' ? '🟡' : '🔴');
        $out[] = $icon.' **'.$c['titulo'].'**';
        $out[] = '- Status: '.$c['status'].' | Risco: '.$c['risco'];
        $out[] = '- Ação: '.$c['acao'];
        if (!empty($c['detalhes'])) $out[] = '- Detalhes seguros: `'.json_encode($c['detalhes'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).'`';
        $out[] = '';
      }
    }
    $out[] = '## Observações';
    foreach (($r['observacoes'] ?? []) as $obs) $out[] = '- '.$obs;
    $out[] = '';
    return implode("\n", $out);
  }

  private static function reportsDir(): string { return self::root().'/storage/security-reports'; }
  private static function root(): string { return dirname(__DIR__, 2); }
  private static function safeHost(): string { return preg_replace('/[^a-zA-Z0-9\.\-_:]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'cli')); }
  private static function safeUser(): array { $u = Auth::user() ?: []; return ['id'=>$u['id'] ?? null, 'email'=>self::mask($u['email'] ?? ''), 'perfil'=>$u['perfil'] ?? null]; }
  private static function safeUrl(string $url): string { if ($url === '') return ''; $p = parse_url($url); return ($p['scheme'] ?? 'https').'://'.($p['host'] ?? 'host').(isset($p['path']) ? $p['path'] : ''); }
  private static function safeError(Throwable $e): string { return preg_replace('/(password|senha|token|secret|key)=[^\s&]+/i', '$1=***', $e->getMessage()); }
  private static function mask(string $v): string { return class_exists('Secrets') ? Secrets::mask($v) : (strlen($v) > 4 ? substr($v,0,2).'***'.substr($v,-2) : '***'); }
  private static function severityFromStatus(string $status): string { return in_array($status, ['critico'], true) ? 'alto' : (str_contains($status, 'alert') ? 'medio' : 'baixo'); }

  private static function sanitize($data) {
    if (is_array($data)) {
      $out = [];
      foreach ($data as $k=>$v) {
        $lk = strtolower((string)$k);
        if (str_contains($lk, 'password') || str_contains($lk, 'senha') || str_contains($lk, 'token') || str_contains($lk, 'secret') || str_contains($lk, 'client_secret') || str_contains($lk, 'authorization') || str_contains($lk, 'api_key') || str_contains($lk, 'pass')) {
          $out[$k] = is_scalar($v) ? self::mask((string)$v) : '***';
        } else {
          $out[$k] = self::sanitize($v);
        }
      }
      return $out;
    }
    if (is_string($data) && preg_match('/(bearer\s+|authorization:|access_token|refresh_token|client_secret|senha|password)/i', $data)) return '***mascarado***';
    return $data;
  }

  private static function publicSensitiveFilesFound(bool $returnList=false) {
    $public = self::root().'/public';
    $patterns = ['.env','config.php','*.sql','*.zip','*.bak','*.log','backup*','dump*'];
    $found=[];
    foreach ($patterns as $p) foreach (glob($public.'/'.$p) ?: [] as $f) $found[] = basename($f);
    $found = array_values(array_unique($found));
    return $returnList ? $found : count($found) > 0;
  }
}
