<?php
/**
 * Fase 3 (decomposição do controller-deus, 2026-09-26) — etapa Segurança.
 *
 * Reúne as telas e ações de SEGURANÇA que viviam no DashboardController (achado A3-01). As rotas
 * são despachadas diretamente pelo FastRouteDispatcherService::$dispatchGroups; nenhuma URL muda,
 * só o controller que a atende. Handlers movidos verbatim — CSRF e PermissionService preservados.
 */
class SecurityController extends BaseModuleController {
  /** Rotas atendidas por este controller (referência; o mapa real é o $dispatchGroups). */
  public static function routes(): array {
    return [
      'security-center','security-soc','security-code-audit','security-inventory','security-backup-trust',
      'security-health','security-audit-signatures','security-events','security-ips','security-circuit-breakers',
      'security-hardening','security-ssl','security-user-audit','security-pentest',
      'security-audit-sign','security-fim','security-fim-gerar','security-score',
      'seguranca-auditoria','seguranca-extrema',
    ];
  }

  public function dispatch(string $page): void {
    switch ($page) {
      case 'security-audit-sign': $this->securityAuditSign(); break;
      case 'security-fim': $this->securityFim(); break;
      case 'security-fim-gerar': $this->securityFimGerar(); break;
      case 'security-score': $this->securityScore(); break;
      case 'seguranca-auditoria': $this->segurancaAuditoria(); break;
      case 'seguranca-extrema': $this->segurancaExtrema(); break;
      default: $this->securityCenter(); break; // security-center e os 13 aliases
    }
  }

  private function db(string $table): PDO { return Database::forTable($table); }

  private function securityCenter(): void {
    PermissionService::require('seguranca','visualizar');
    SecurityEventService::ensureSchema();
    $pageTitle = 'Central Técnica · Segurança';
    $activeSecurityPage = $_GET['page'] ?? 'security-center';
    $eventos = [];
    $ips = [];
    $circuitBreakers = [];
    $loginTentativas = [];
    try { $eventos = $this->db('security_events')->query("SELECT id, tipo, severidade, ip, user_agent, mensagem, trace_id, created_at FROM security_events ORDER BY id DESC LIMIT 100")->fetchAll(); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    try { $ips = $this->db('ips_bloqueados')->query("SELECT id, ip, motivo, ativo, criado_em, expira_em FROM ips_bloqueados WHERE ativo=1 ORDER BY id DESC LIMIT 100")->fetchAll(); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    try { $circuitBreakers = Database::forTable('circuit_breakers')->query("SELECT id, sistema, status, falhas_consecutivas, aberto_ate, atualizado_em FROM circuit_breakers ORDER BY FIELD(status,'aberto','meio_aberto','fechado'), sistema ASC")->fetchAll(); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    try { $loginTentativas = Database::forTable('login_tentativas')->query("SELECT id, email, ip, sucesso, motivo, criado_em FROM login_tentativas ORDER BY id DESC LIMIT 80")->fetchAll(); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    $fim = class_exists('FileIntegrityService') ? FileIntegrityService::check() : ['status'=>'indisponivel','total'=>0,'alterados'=>[],'faltantes'=>[],'novos'=>[]];
    $score = class_exists('SecurityScoreService') ? SecurityScoreService::evaluate() : ['score'=>0,'classificacao'=>'indisponível','checks'=>[]];
    $hardening = class_exists('SecurityHardeningService') ? SecurityHardeningService::status() : ['score'=>0,'checks'=>[]];
    $codeAudit = class_exists('CodeExecutionAuditService') ? CodeExecutionAuditService::scan() : ['status'=>'indisponivel','counts'=>[],'hits'=>[]];
    $legacyInventory = class_exists('LegacyInventoryService') ? LegacyInventoryService::controllersServices() : ['items'=>[],'resumo'=>[]];
    $databaseInventory = class_exists('DatabaseInventoryService') ? DatabaseInventoryService::classify() : ['items'=>[],'resumo'=>[],'total'=>0];
    $backupTrust = class_exists('BackupTrustService') ? BackupTrustService::score() : ['score'=>0,'items'=>[]];
    $realtimeHealth = class_exists('RealtimeHealthService') ? RealtimeHealthService::snapshot() : ['score'=>0,'checks'=>[]];
    $auditDailySignatures = class_exists('AuditDailySignatureService') ? AuditDailySignatureService::latest(12) : [];
    $tokenVaultHealth = class_exists('TokenVaultService') ? TokenVaultService::health() : ['rows'=>[]];
    $ssl = [
      'https' => class_exists('TrustedProxyService') ? TrustedProxyService::isHttps() : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
      'hsts' => App::isProduction(),
      'host' => $_SERVER['HTTP_HOST'] ?? 'localhost',
      'acao' => 'Validar certificado no navegador/Cloudflare/hospedagem e manter renovação automática ativa.'
    ];
    $pentestChecklist = [
      ['item'=>'Login: brute force por IP e usuário', 'ok'=>!empty(App::config()['security']['login_user_rate_limit_per_hour'])],
      ['item'=>'Painel: WAF restrito às telas administrativas', 'ok'=>!empty(App::config()['security']['waf_panel_only'])],
      ['item'=>'Tiny/VSM: rotas sem filtro agressivo por palavras-chave', 'ok'=>true],
      ['item'=>'Tiny: webhook secret obrigatório', 'ok'=>!empty(App::config()['security']['tiny_webhook_exigir_secret'])],
      ['item'=>'VSM: HMAC disponível e allowlist opcional', 'ok'=>array_key_exists('vsm_hmac_enabled', App::config()['security'] ?? [])],
      ['item'=>'Backups: HMAC antes de restore', 'ok'=>strlen((string)((App::config()['security']['backup_signature_key'] ?? ''))) >= 32],
      ['item'=>'Anti-replay VSM com request_hash/request_time', 'ok'=>Database::tableExists('integration_replay_guard')],
      ['item'=>'Assinatura diária da auditoria', 'ok'=>Database::tableExists('audit_daily_signatures')],
      ['item'=>'Token Vault interno para rotação', 'ok'=>Database::tableExists('token_vault')],
      ['item'=>'Código sem chamada real ao sistema operacional', 'ok'=>(int)(($codeAudit['counts']['os_exec_real'] ?? 0)) === 0],
      ['item'=>'FIM: manifesto de integridade disponível', 'ok'=>is_file(FileIntegrityService::manifestPath())],
      ['item'=>'Sessão: versionamento para logout global', 'ok'=>Database::columnExists('usuarios','session_version')],
    ];
    require __DIR__.'/../../views/security_center.php';
  }

  private function securityFim(): void {
    PermissionService::require('seguranca','visualizar');
    $resultado = FileIntegrityService::check();
    require __DIR__.'/../../views/security_fim.php';
  }

  private function securityFimGerar(): void {
    PermissionService::require('seguranca','gerenciar');
    Csrf::validate();
    $ok = FileIntegrityService::saveManifest();
    SecurityEventService::log('fim.manifesto_gerado', $ok ? 'baixo' : 'alto', $ok ? 'Manifesto de integridade gerado' : 'Falha ao gerar manifesto');
    redirect('index.php?page=security-fim&gerado='.($ok?'1':'0'));
  }

  private function securityScore(): void {
    PermissionService::require('seguranca','visualizar');
    $score = SecurityScoreService::evaluate();
    require __DIR__.'/../../views/security_score.php';
  }

  private function securityAuditSign(): void {
    PermissionService::require('seguranca','gerenciar');
    Csrf::validate();
    $date = preg_replace('/[^0-9\-]/', '', (string)($_POST['audit_date'] ?? date('Y-m-d')));
    try { AuditDailySignatureService::sign($date ?: date('Y-m-d')); }
    catch(Throwable $e){ SecurityEventService::log('auditoria.assinatura_diaria.erro','alto','Falha ao assinar auditoria diária.', ['erro'=>$e->getMessage(),'date'=>$date]); }
    redirect('index.php?page=security-audit-signatures&signed=1');
  }

  private function segurancaAuditoria(): void {
    PermissionService::require('seguranca','visualizar');
    $eventos = SecurityAuditService::recentes(120);
    $timeline = $this->db('auditoria_timeline')->query("SELECT * FROM auditoria_timeline ORDER BY id DESC LIMIT 120")->fetchAll();
    $correlacoes = $this->db('evento_correlacao')->query("SELECT * FROM evento_correlacao ORDER BY id DESC LIMIT 80")->fetchAll();
    $pageTitle = 'Segurança e Auditoria Forense';
    require __DIR__.'/../../views/seguranca_auditoria.php';
  }

  private function segurancaExtrema(): void {
    PermissionService::require('seguranca','visualizar');
    $status = SecurityHardeningService::status();
    $pageTitle = 'Segurança Extrema';
    require __DIR__.'/../../views/seguranca_extrema.php';
  }
}
