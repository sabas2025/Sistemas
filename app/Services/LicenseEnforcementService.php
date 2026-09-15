<?php
/**
 * V104.17 - Licenciamento comercial com HMAC, monitor/enforce e limites por plano.
 */
class LicenseEnforcementService {
  private static array $publicRoutes = ['login','logout','produto-institucional','demo-online','api/csp-report'];
  private static array $technicalBypass = ['security-assisted-test','security-assisted-test-run','security-assisted-test-download','validar-banco','mapa-banco','diagnostico-config-real','health-modulos','central-tecnica','backups','backup','enterprise-core','enterprise-core-aplicar','migracoes-seguras','migracao-aplicar'];

  public static function enforceForRequest(string $page): void {
    if (in_array($page, self::$publicRoutes, true)) return;
    if (!Auth::check()) return;
    $cfg = class_exists('App') ? App::config() : [];
    $commercial = $cfg['commercial'] ?? [];
    $mode = (string)($commercial['license_mode'] ?? 'monitor'); // monitor|enforce|off
    if ($mode === 'off') return;
    // Rotas de recuperação não podem depender do próprio schema comercial que estão reparando.
    if (in_array($page, self::$technicalBypass, true)) return;

    $status = self::status();
    if ($mode === 'monitor') {
      if (($status['status'] ?? '') !== 'ok') {
        Audit::event('licenca.monitor_alerta','alerta',['mensagem'=>'Licença em modo monitoramento requer atenção.','contexto'=>self::safeStatus($status)]);
      }
      return;
    }

    if (($status['status'] ?? '') !== 'ok') {
      Audit::event('licenca.bloqueio','critico',['mensagem'=>'Acesso bloqueado por licença inválida/vencida.','contexto'=>self::safeStatus($status)]);
      http_response_code(402);
      $pageTitle = 'Licença comercial necessária';
      $licenseStatus = $status;
      require __DIR__.'/../../views/license_blocked.php';
      exit;
    }
  }

  public static function status(): array {
    try { CommercialProductService::ensureSchema(); } catch(Throwable $e) { return ['status'=>'erro','mensagem'=>'Tabela comercial indisponível: '.$e->getMessage()]; }
    $cfg = class_exists('App') ? App::config() : [];
    $commercial = $cfg['commercial'] ?? [];
    $pdo = Database::forTable('comercial_clientes_licencas');
    $row = $pdo->query("SELECT id, cliente_nome, documento, email_responsavel, plano, status, ambiente, limite_empresas, limite_filiais, limite_conectores, data_inicio, data_expiracao, license_key_hash, licenca_origem, ultimo_check_em, bloquear_ao_vencer, assinatura_hmac, atualizado_em FROM comercial_clientes_licencas WHERE status IN ('ativa','trial') ORDER BY FIELD(status,'ativa','trial'), id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
      return [
        'status' => !empty($commercial['allow_unlicensed_internal_use']) ? 'ok' : 'sem_licenca',
        'mensagem' => !empty($commercial['allow_unlicensed_internal_use']) ? 'Uso interno sem licença permitido pela configuração.' : 'Nenhuma licença ativa/trial encontrada.',
        'modo' => (string)($commercial['license_mode'] ?? 'monitor'),
      ];
    }

    $signature = self::verifySignature($row);
    if (($signature['status'] ?? '') !== 'ok') return $signature + ['licenca'=>self::safeRow($row)];

    if (!empty($row['data_expiracao']) && strtotime((string)$row['data_expiracao'].' 23:59:59') < time()) {
      return ['status'=>'vencida','mensagem'=>'Licença vencida em '.$row['data_expiracao'],'licenca'=>self::safeRow($row),'assinatura'=>$signature];
    }

    $limits = self::limitsOk($row);
    if (($limits['status'] ?? '') !== 'ok') return $limits + ['licenca'=>self::safeRow($row),'assinatura'=>$signature];

    try { $pdo->prepare('UPDATE comercial_clientes_licencas SET ultimo_check_em=NOW() WHERE id=?')->execute([(int)$row['id']]); } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    return ['status'=>'ok','mensagem'=>'Licença ativa/trial válida e assinada.','licenca'=>self::safeRow($row),'limites'=>$limits,'assinatura'=>$signature];
  }

  public static function secret(): string {
    $cfg = class_exists('App') ? App::config() : [];
    return (string)($cfg['commercial']['license_hmac_key'] ?? $cfg['security']['backup_signature_key'] ?? $cfg['security']['fim_manifest_hmac_key'] ?? $cfg['app_key'] ?? 'hub-license-development-key');
  }

  public static function signaturePayload(array $row): string {
    return implode('|', [
      (string)($row['license_key_hash'] ?? ''),
      (string)($row['cliente_nome'] ?? ''),
      (string)($row['documento'] ?? ''),
      (string)($row['plano'] ?? ''),
      (string)($row['status'] ?? ''),
      (string)($row['ambiente'] ?? ''),
      (string)($row['limite_empresas'] ?? ''),
      (string)($row['limite_filiais'] ?? ''),
      (string)($row['limite_conectores'] ?? ''),
      (string)($row['data_expiracao'] ?? ''),
    ]);
  }

  public static function sign(array $row): string {
    return hash_hmac('sha256', self::signaturePayload($row), self::secret());
  }

  public static function verifySignature(array $row): array {
    $stored = (string)($row['assinatura_hmac'] ?? '');
    if ($stored === '') return ['status'=>'licenca_sem_assinatura','mensagem'=>'Licença sem assinatura HMAC. Gere/atualize a licença na versão comercial atual.'];
    $expected = self::sign($row);
    if (!hash_equals($expected, $stored)) return ['status'=>'licenca_assinatura_invalida','mensagem'=>'Assinatura HMAC da licença não confere.'];
    return ['status'=>'ok','mensagem'=>'Assinatura HMAC válida.'];
  }

  private static function limitsOk(array $license): array {
    $checks = [];
    try {
      $empresas = Database::tableExists('empresas') ? (int)Database::forTable('empresas')->query('SELECT COUNT(*) FROM empresas WHERE ativo=1')->fetchColumn() : 0;
      $filiais = Database::tableExists('filiais') ? (int)Database::forTable('filiais')->query('SELECT COUNT(*) FROM filiais WHERE ativo=1')->fetchColumn() : 0;
      $conectores = Database::tableExists('comercial_conectores_catalogo') ? (int)Database::forTable('comercial_conectores_catalogo')->query("SELECT COUNT(*) FROM comercial_conectores_catalogo WHERE status='ativo'")->fetchColumn() : 0;
      $checks = ['empresas'=>$empresas,'filiais'=>$filiais,'conectores_ativos'=>$conectores];
      if ($empresas > (int)$license['limite_empresas']) return ['status'=>'limite_empresas','mensagem'=>'Limite de empresas excedido.','uso'=>$checks];
      if ($filiais > (int)$license['limite_filiais']) return ['status'=>'limite_filiais','mensagem'=>'Limite de filiais excedido.','uso'=>$checks];
      if ($conectores > (int)$license['limite_conectores']) return ['status'=>'limite_conectores','mensagem'=>'Limite de conectores ativos excedido.','uso'=>$checks];
    } catch(Throwable $e) { return ['status'=>'erro','mensagem'=>'Falha ao validar limites da licença: '.$e->getMessage(),'uso'=>$checks]; }
    return ['status'=>'ok','uso'=>$checks];
  }

  private static function safeRow(array $row): array { unset($row['license_key_hash'], $row['assinatura_hmac']); return $row; }
  private static function safeStatus(array $status): array { if (isset($status['licenca'])) $status['licenca']=self::safeRow($status['licenca']); return $status; }
}
